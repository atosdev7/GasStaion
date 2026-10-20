#include "ApiReply.h"

#include <QJsonDocument>
#include <QNetworkReply>
#include <QTimer>
#include <QVariant>

ApiReply::ApiReply(QNetworkReply *reply, int timeoutMs, QObject *parent)
    : QObject(parent), reply_(reply), ok_(false), busy_(false), networkError_(false), timedOut_(false), httpStatus_(0)
{
    reply_->setParent(this);
    connect(reply_, SIGNAL(finished()), this, SLOT(replyFinished()));

    timer_ = new QTimer(this);
    timer_->setSingleShot(true);
    connect(timer_, SIGNAL(timeout()), this, SLOT(timedOut()));
    timer_->start(timeoutMs);
}

void ApiReply::timedOut()
{
    timedOut_ = true;
    reply_->abort(); // ends in replyFinished()
}

void ApiReply::replyFinished()
{
    timer_->stop();
    httpStatus_ = reply_->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();

    if (timedOut_) {
        networkError_ = true;
        message_ = tr("The server did not answer in time.");
        emit finished();

        return;
    }
    if (httpStatus_ == 0) {
        networkError_ = true;
        message_ = tr("Cannot reach the server: %1").arg(reply_->errorString());
        emit finished();

        return;
    }

    const QByteArray body = reply_->readAll();
    QJsonParseError parseError;
    const QJsonDocument document = QJsonDocument::fromJson(body, &parseError);
    if (parseError.error != QJsonParseError::NoError || !document.isObject()) {
        networkError_ = true;
        message_ = tr("The server sent an unexpected answer (HTTP %1).").arg(httpStatus_);
        emit finished();

        return;
    }

    data_ = document.object();
    const QString status = data_.value("status").toString();
    busy_ = (status == "busy");
    ok_ = (httpStatus_ >= 200 && httpStatus_ < 300 && status == "ok");

    if (!ok_) {
        if (busy_) {
            message_ = tr("The server is busy, try again in a minute.");
        } else if (!data_.value("message").toString().isEmpty()) {
            message_ = data_.value("message").toString();
        } else {
            message_ = tr("The server refused the request (HTTP %1).").arg(httpStatus_);
        }
    }

    emit finished();
}
