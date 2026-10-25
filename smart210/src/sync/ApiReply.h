#ifndef APIREPLY_H
#define APIREPLY_H

#include <QByteArray>
#include <QJsonObject>
#include <QNetworkRequest>
#include <QObject>
#include <QString>

class QNetworkAccessManager;
class QNetworkReply;
class QTimer;

// The answer to one call to the web app. Wait for finished(), then read the result; call deleteLater() when done.
//
// ok()      the server answered 2xx with {"status": "ok", ...}
// data()    the whole JSON object the server sent (empty when there was none)
// message() what to show the user when !ok(): the server's own message, or a plain description of what went wrong
// isBusy()  the server is running a backup (503 with {"status": "busy"}), or still refuses with 429 (too many requests) after the waits below: keep
//           the data and ask again in a minute
//
// A 429 (the server's limit of calls a minute for this address) is not an answer: the reply waits as long as the server asks (the Retry-After header, in
// seconds; 5 seconds when it gives none; at most a minute each time) and sends the same request again, up to MaxRetries times and MaxWaitMs of waiting in
// all. The limiter refuses a call before anything is done with it, so asking again is safe. throttled() tells the client, which holds back its next calls.
class ApiReply : public QObject
{
    Q_OBJECT

public:
    ApiReply(int timeoutMs, QObject *parent = 0);

    // Sends the request after delayMs (at once when 0): the client spaces its calls (see ApiClient::setMinIntervalMs). The time to answer
    // counts from the moment of sending; finished() is always emitted later, so the caller may connect to it after this returns.
    void send(QNetworkAccessManager *manager, const QNetworkRequest &request, const QByteArray &body, bool post, int delayMs);

    bool ok() const { return ok_; }
    bool isBusy() const { return busy_; }
    bool isNetworkError() const { return networkError_; } // no usable answer at all (refused, timed out, not JSON)
    int httpStatus() const { return httpStatus_; }
    QString message() const { return message_; }
    QJsonObject data() const { return data_; }

    static const int MaxRetries = 5;
    static const int MaxWaitMs = 120000;
    static const int DefaultWaitMs = 5000;
    static const int LongestWaitMs = 60000;

    int retries() const { return retries_; } // how many times a 429 made this reply wait

signals:
    void finished();
    void throttled(int waitMs); // a 429: the reply waits this long and asks again

private slots:
    void sendNow();
    void replyFinished();
    void timedOut();

private:
    QNetworkAccessManager *manager_;
    QNetworkRequest request_;
    QByteArray body_;
    bool post_;
    int timeoutMs_;
    QNetworkReply *reply_;
    QTimer *timer_;
    bool ok_;
    bool busy_;
    bool networkError_;
    bool timedOut_;
    int httpStatus_;
    int retries_;
    int waitedMs_;
    QString message_;
    QJsonObject data_;
};

#endif
