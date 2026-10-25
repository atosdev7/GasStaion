#ifndef FAKESERVER_H
#define FAKESERVER_H

#include <QHash>
#include <QList>
#include <QObject>
#include <QStringList>
#include <QTcpServer>
#include <QTcpSocket>

// A minimal HTTP server on localhost for the tests: answers each path (with its query) with a canned status and body, or
// never answers, and keeps every request it received. A path can have a sequence of answers: each request takes the next
// one, the last one repeats.
class FakeServer : public QObject
{
    Q_OBJECT

public:
    struct Request {
        QString method;
        QString path;
        QByteArray body;
        QHash<QString, QString> headers; // names in lower case
    };

    typedef QPair<int, QByteArray> Answer;

    FakeServer() { connect(&server_, SIGNAL(newConnection()), this, SLOT(accept())); }

    bool listen() { return server_.listen(QHostAddress::LocalHost, 0); }
    quint16 port() const { return server_.serverPort(); }
    void close() { server_.close(); }

    void reply(const QString &path, int status, const QByteArray &body)
    {
        replies_[path] = QList<Answer>() << qMakePair(status, body);
    }
    void replyNext(const QString &path, int status, const QByteArray &body) { replies_[path] << qMakePair(status, body); }
    void setRetryAfter(const QByteArray &seconds) { retryAfter_ = seconds; } // sent with every 429
    void hang(const QString &path) { replies_[path] = QList<Answer>() << qMakePair(-1, QByteArray()); }

    QList<Request> requests() const { return requests_; }

    // "GET /api/ping" style lines, in the order they arrived.
    QStringList log() const
    {
        QStringList lines;
        foreach (const Request &request, requests_) {
            lines << request.method + " " + request.path;
        }

        return lines;
    }

private slots:
    void accept()
    {
        while (QTcpSocket *socket = server_.nextPendingConnection()) {
            connect(socket, SIGNAL(readyRead()), this, SLOT(read()));
            connect(socket, SIGNAL(disconnected()), socket, SLOT(deleteLater()));
        }
    }

    void read()
    {
        QTcpSocket *socket = qobject_cast<QTcpSocket *>(sender());
        buffers_[socket] += socket->readAll();
        const QByteArray &data = buffers_[socket];

        const int headEnd = data.indexOf("\r\n\r\n");
        if (headEnd < 0) {
            return;
        }

        Request request;
        const QList<QByteArray> lines = data.left(headEnd).split('\n');
        const QList<QByteArray> start = lines.at(0).trimmed().split(' ');
        request.method = QString::fromLatin1(start.value(0));
        request.path = QString::fromLatin1(start.value(1));
        for (int i = 1; i < lines.size(); ++i) {
            const int colon = lines.at(i).indexOf(':');
            if (colon > 0) {
                request.headers[QString::fromLatin1(lines.at(i).left(colon).trimmed().toLower())]
                    = QString::fromLatin1(lines.at(i).mid(colon + 1).trimmed());
            }
        }

        const int length = request.headers.value("content-length", "0").toInt();
        if (data.size() < headEnd + 4 + length) {
            return; // the body is still arriving
        }
        request.body = data.mid(headEnd + 4, length);
        buffers_.remove(socket);
        requests_.append(request);

        Answer answer = qMakePair(404, QByteArray("{\"status\":\"error\",\"message\":\"not found\"}"));
        if (replies_.contains(request.path)) {
            QList<Answer> &queue = replies_[request.path];
            answer = queue.first();
            if (queue.size() > 1) {
                queue.removeFirst();
            }
        }
        if (answer.first < 0) {
            return; // never answers
        }

        socket->write("HTTP/1.1 " + QByteArray::number(answer.first) + " X\r\nContent-Type: application/json\r\n"
                      + (answer.first == 429 && !retryAfter_.isEmpty() ? "Retry-After: " + retryAfter_ + "\r\n" : QByteArray()) + "Content-Length: "
                      + QByteArray::number(answer.second.size()) + "\r\nConnection: close\r\n\r\n" + answer.second);
        socket->disconnectFromHost();
    }

private:
    QTcpServer server_;
    QByteArray retryAfter_;
    QHash<QString, QList<Answer> > replies_;
    QHash<QTcpSocket *, QByteArray> buffers_;
    QList<Request> requests_;
};

#endif
