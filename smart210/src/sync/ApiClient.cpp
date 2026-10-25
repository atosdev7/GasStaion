#include "ApiClient.h"

#include "ApiReply.h"

#include <QElapsedTimer>
#include <QJsonDocument>
#include <QNetworkReply>
#include <QNetworkRequest>

namespace {

int minIntervalMs = 1000;
QElapsedTimer callClock; // the program's own time, started with the first call
qint64 nextFreeMs = 0; // when the next call may leave

}

ApiClient::ApiClient(QObject *parent)
    : QObject(parent), port_(80), timeoutMs_(10000)
{
}

void ApiClient::setMinIntervalMs(int intervalMs)
{
    minIntervalMs = intervalMs;
}

void ApiClient::setServer(const QString &host, int port)
{
    host_ = host.trimmed();
    port_ = port;
}

QUrl ApiClient::url(const QString &path) const
{
    QUrl url;
    url.setScheme("http");
    url.setHost(host_);
    url.setPort(port_);

    // "pull/users?limit=200": the part after ? is the query, not part of the path (setPath would encode the ?).
    const int question = path.indexOf('?');
    url.setPath("/api/" + (question < 0 ? path : path.left(question)));
    if (question >= 0) {
        url.setQuery(path.mid(question + 1));
    }

    return url;
}

QNetworkRequest ApiClient::request(const QString &path) const
{
    QNetworkRequest request(url(path));
    request.setRawHeader("Accept", "application/json");

    return request;
}

// Every call takes the next free moment of the program: the first at once, the next ones one interval after the one before.
ApiReply *ApiClient::send(const QNetworkRequest &request, const QByteArray &body, bool post)
{
    if (!callClock.isValid()) {
        callClock.start();
    }
    const qint64 now = callClock.elapsed();
    const qint64 at = nextFreeMs > now ? nextFreeMs : now;
    nextFreeMs = at + minIntervalMs;

    ApiReply *reply = new ApiReply(timeoutMs_, this);
    connect(reply, SIGNAL(throttled(int)), this, SLOT(pause(int)));
    reply->send(&manager_, request, body, post, int(at - now));

    return reply;
}

// The server's limit is for the address, so every call of the program waits, not only the one that was refused.
void ApiClient::pause(int ms)
{
    const qint64 until = callClock.elapsed() + ms;
    if (until > nextFreeMs) {
        nextFreeMs = until;
    }
}

ApiReply *ApiClient::get(const QString &path)
{
    return send(request(path), QByteArray(), false);
}

ApiReply *ApiClient::post(const QString &path, const QJsonObject &body)
{
    QNetworkRequest req = request(path);
    req.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");

    return send(req, QJsonDocument(body).toJson(QJsonDocument::Compact), true);
}

ApiReply *ApiClient::ping()
{
    return get("ping");
}
