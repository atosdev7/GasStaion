#ifndef APICLIENT_H
#define APICLIENT_H

#include <QJsonObject>
#include <QNetworkAccessManager>
#include <QObject>
#include <QString>
#include <QUrl>

class ApiReply;

// Talks to the web app's JSON API (see the README of the project) over plain HTTP on the station's network. The web app
// knows this terminal by its IP address, so there is nothing to sign in with. The caller connects to the returned reply's
// finished() signal; the reply belongs to the client until deleteLater().
class ApiClient : public QObject
{
    Q_OBJECT

public:
    explicit ApiClient(QObject *parent = 0);

    void setServer(const QString &host, int port);
    void setTimeoutMs(int timeoutMs) { timeoutMs_ = timeoutMs; }

    // The calls of all clients of this program leave at least this many milliseconds apart (default 1000: one call per second), so
    // that a long download or upload does not run into the server's limit of calls per minute. 0 sends at once (tests).
    static void setMinIntervalMs(int intervalMs);

    QUrl url(const QString &path) const;

    ApiReply *get(const QString &path);
    ApiReply *post(const QString &path, const QJsonObject &body);

    // The connection test: server time, and whether the web app has registered this terminal's address.
    ApiReply *ping();

private:
    QNetworkRequest request(const QString &path) const;
    ApiReply *send(const QNetworkRequest &request, const QByteArray &body, bool post);

    QNetworkAccessManager manager_;
    QString host_;
    int port_;
    int timeoutMs_;
};

#endif
