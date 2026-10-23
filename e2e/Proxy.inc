// A bad network between the terminal and the web app (included by e2e.cpp). The terminal talks to this proxy; the proxy talks to the web app and
// can spoil single requests: drop the request before the server sees it, let the server answer but drop the answer (the server has saved the data,
// the terminal does not know), answer 503 busy by itself, or hold a request back. The terminal's connection may carry several requests one after
// the other (the proxy decides again for each); the connection to the server is made for every request.
#include <QSet>
#include <QTcpServer>
#include <QTcpSocket>

class Proxy : public QObject
{
public:
    enum Fate { Pass, DropRequest, DropResponse, Busy, Delay };

    struct Rule {
        QString match;  // a part of "POST /api/push/sales"
        Fate fate;
        int times;
        int delayMs;
    };

    Proxy(const QString &host, int port) : host_(host), port_(port)
    {
        QObject::connect(&server_, &QTcpServer::newConnection, [this]() { accept(); });
    }

    bool listen() { return server_.listen(QHostAddress::LocalHost, 0); }
    int port() const { return server_.serverPort(); }
    void close() { server_.close(); }
    bool isListening() const { return server_.isListening(); }
    bool reopen(int port) { return server_.listen(QHostAddress::LocalHost, quint16(port)); }

    void add(const QString &match, Fate fate, int times = 1, int delayMs = 0)
    {
        Rule rule;
        rule.match = match;
        rule.fate = fate;
        rule.times = times;
        rule.delayMs = delayMs;
        rules_ << rule;
    }

    void clearRules() { rules_.clear(); }

    // how many requests that contain `match` were answered by the server with `status` (-1: any answer), and how many answers were dropped
    int answered(const QString &match, int status = -1) const
    {
        int n = 0;
        foreach (const QString &line, log) {
            const bool answer = line.contains(" -> ") && !line.contains("request dropped");
            if (answer && line.contains(match) && (status < 0 || line.contains(" -> " + QString::number(status)))) {
                ++n;
            }
        }

        return n;
    }

    int dropped(const QString &match) const
    {
        int n = 0;
        foreach (const QString &line, log) {
            if (line.contains(match) && line.contains("dropped")) {
                ++n;
            }
        }

        return n;
    }

    QStringList log;

private:
    struct Link {
        Link() : client(0), up(0), generation(0), decided(false), fate(Pass), delayMs(0), holding(false), delayHandled(false), statusSeen(false), upConnected(false), exchangeDone(false) {}

        QTcpSocket *client;
        QTcpSocket *up;
        int generation;      // changes with every request on this connection: a timer of an old request must do nothing
        QByteArray request;
        QString line;
        bool decided;
        Fate fate;
        int delayMs;         // for Delay: the answer is held back this long
        bool holding;
        bool delayHandled;
        QByteArray held;
        bool statusSeen;
        bool upConnected;
        bool exchangeDone;   // the server has answered and hung up; the terminal may send another request
    };

    Fate decide(const QString &line, int *delayMs)
    {
        for (int i = 0; i < rules_.size(); ++i) {
            if (rules_[i].times > 0 && line.contains(rules_[i].match)) {
                --rules_[i].times;
                *delayMs = rules_[i].delayMs;

                return rules_[i].fate;
            }
        }

        return Pass;
    }

    bool alive(Link *link, int generation) const { return live_.contains(link) && link->generation == generation; }

    void dropUp(Link *link)
    {
        if (link->up) {
            link->up->disconnect();
            link->up->close();
            link->up->deleteLater();
            link->up = 0;
        }
        link->upConnected = false;
    }

    void finish(Link *link)
    {
        if (!live_.contains(link)) {
            return;
        }
        live_.remove(link);
        dropUp(link);
        if (link->client) {
            link->client->disconnect();
            link->client->flush();
            link->client->close();
            link->client->deleteLater();
        }
        delete link;
    }

    void forward(Link *link)
    {
        const int generation = link->generation;
        link->up = new QTcpSocket;
        link->upConnected = false;
        QObject::connect(link->up, &QTcpSocket::connected, [link]() {
            link->upConnected = true;
            // a real server (Apache) keeps the connection open after its answer; the proxy takes the end of the answer from the closing of this connection
            QByteArray head = link->request;
            head.replace("Connection: keep-alive", "Connection: close");
            link->up->write(head);
            link->request.clear();
        });
        QObject::connect(link->up, &QTcpSocket::readyRead, [this, link, generation]() {
            if (!alive(link, generation)) {
                return;
            }
            const QByteArray data = link->up->readAll();
            if (!link->statusSeen) {
                link->statusSeen = true;
                const int status = QString::fromLatin1(data.left(20)).section(' ', 1, 1).toInt();
                const bool drop = (link->fate == DropResponse);
                log << link->line + " -> " + QString::number(status) + (drop ? " (answer dropped)" : "");
                if (drop) {
                    finish(link);

                    return;
                }
            }
            if (link->fate == Delay && link->holding) {
                link->held.append(data);
            } else if (link->fate == Delay && !link->delayHandled) {
                link->delayHandled = true;
                link->holding = true;
                link->held.append(data);
                QTimer::singleShot(link->delayMs, [this, link, generation]() {
                    if (alive(link, generation)) {
                        link->holding = false;
                        link->client->write(link->held);
                        link->held.clear();
                        if (link->exchangeDone) {
                            link->client->flush();
                        }
                    }
                });
            } else {
                link->client->write(data);
            }
        });
        QObject::connect(link->up, &QTcpSocket::disconnected, [this, link, generation]() {
            if (!alive(link, generation)) {
                return;
            }
            if (!link->holding) {
                link->client->flush();
            }
            link->exchangeDone = true;
            dropUp(link);
            // the terminal usually closes by itself after "Connection: close"; if it does not, the connection is closed after a while
            QTimer::singleShot(1500, [this, link, generation]() { if (alive(link, generation) && link->exchangeDone) { finish(link); } });
        });
        link->up->connectToHost(host_, quint16(port_));
    }

    // The head of a request is complete: decide what happens to it.
    void begin(Link *link)
    {
        link->decided = true;
        link->line = QString::fromLatin1(link->request.left(link->request.indexOf("\r\n"))).section(" HTTP", 0, 0);
        int delayMs = 0;
        link->fate = decide(link->line, &delayMs);
        const int generation = link->generation;
        if (link->fate == DropRequest) {
            log << link->line + " -> (request dropped)";
            finish(link);
        } else if (link->fate == Busy) {
            log << link->line + " -> 503 (by the proxy)";
            const QByteArray body = "{\"status\":\"busy\",\"message\":\"The server is busy (backup running)\"}";
            QTimer::singleShot(250, [this, link, generation, body]() { // the terminal may still be sending its body
                if (!alive(link, generation)) {
                    return;
                }
                link->client->write("HTTP/1.1 503 Service Unavailable\r\nContent-Type: application/json\r\nConnection: close\r\nContent-Length: "
                                    + QByteArray::number(body.size()) + "\r\n\r\n" + body);
                link->client->flush();
                link->exchangeDone = true;
                QTimer::singleShot(1500, [this, link, generation]() { if (alive(link, generation) && link->exchangeDone) { finish(link); } });
            });
        } else if (link->fate == Delay) {
            link->delayMs = delayMs;
            forward(link); // the server answers at once; the answer is held back
        } else {
            forward(link);
        }
    }

    // The first bytes of a new request on the same connection. Once the server has begun to answer, the terminal sends nothing more of the request it
    // has answered, so data that starts like a request line is the next request (the end of the exchange cannot be taken from the server closing the
    // connection: Apache closes a moment later than PHP's built-in server did).
    static bool startsLikeRequest(const QByteArray &data)
    {
        return data.startsWith("POST /") || data.startsWith("GET /") || data.startsWith("PUT /") || data.startsWith("DELETE /") || data.startsWith("HEAD /");
    }

    void clientData(Link *link, const QByteArray &data)
    {
        if (link->exchangeDone || (link->statusSeen && startsLikeRequest(data))) { // another request on the same connection
            dropUp(link);
            ++link->generation;
            link->exchangeDone = false;
            link->decided = false;
            link->statusSeen = false;
            link->holding = false;
            link->delayHandled = false;
            link->held.clear();
            link->request.clear();
        }
        if (link->decided) {
            if (link->up && link->upConnected) {
                link->up->write(data);
            } else {
                link->request.append(data);
            }

            return;
        }
        link->request.append(data);
        if (link->request.contains("\r\n\r\n")) {
            begin(link);
        }
    }

    void accept()
    {
        while (QTcpSocket *socket = server_.nextPendingConnection()) {
            Link *link = new Link;
            link->client = socket;
            live_.insert(link);
            QObject::connect(socket, &QTcpSocket::readyRead, [this, link]() { clientData(link, link->client->readAll()); });
            QObject::connect(socket, &QTcpSocket::disconnected, [this, link]() { finish(link); });
        }
    }

    QTcpServer server_;
    QString host_;
    int port_;
    QList<Rule> rules_;
    QSet<Link *> live_;
};
