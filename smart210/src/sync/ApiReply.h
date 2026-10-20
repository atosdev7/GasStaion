#ifndef APIREPLY_H
#define APIREPLY_H

#include <QJsonObject>
#include <QObject>
#include <QString>

class QNetworkReply;
class QTimer;

// The answer to one call to the web app. Wait for finished(), then read the result; call deleteLater() when done.
//
// ok()      the server answered 2xx with {"status": "ok", ...}
// data()    the whole JSON object the server sent (empty when there was none)
// message() what to show the user when !ok(): the server's own message, or a plain description of what went wrong
// isBusy()  the server is running a backup (503 with {"status": "busy"}): keep the data and ask again in a minute
class ApiReply : public QObject
{
    Q_OBJECT

public:
    ApiReply(QNetworkReply *reply, int timeoutMs, QObject *parent = 0);

    bool ok() const { return ok_; }
    bool isBusy() const { return busy_; }
    bool isNetworkError() const { return networkError_; } // no usable answer at all (refused, timed out, not JSON)
    int httpStatus() const { return httpStatus_; }
    QString message() const { return message_; }
    QJsonObject data() const { return data_; }

signals:
    void finished();

private slots:
    void replyFinished();
    void timedOut();

private:
    QNetworkReply *reply_;
    QTimer *timer_;
    bool ok_;
    bool busy_;
    bool networkError_;
    bool timedOut_;
    int httpStatus_;
    QString message_;
    QJsonObject data_;
};

#endif
