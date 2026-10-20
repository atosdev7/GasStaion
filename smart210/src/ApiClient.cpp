#include "ApiClient.h"

#include "ApiReply.h"

#include <QJsonDocument>
#include <QNetworkReply>
#include <QNetworkRequest>

ApiClient::ApiClient(QObject *parent)
    : QObject(parent), port_(80), timeoutMs_(10000)
{
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

ApiReply *ApiClient::get(const QString &path)
{
    return new ApiReply(manager_.get(request(path)), timeoutMs_, this);
}

ApiReply *ApiClient::post(const QString &path, const QJsonObject &body)
{
    QNetworkRequest req = request(path);
    req.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");

    return new ApiReply(manager_.post(req, QJsonDocument(body).toJson(QJsonDocument::Compact)), timeoutMs_, this);
}

ApiReply *ApiClient::ping()
{
    return get("ping");
}
