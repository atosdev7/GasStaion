#include "ApiReply.h"

#include <QJsonDocument>
#include <QNetworkReply>
#include <QTimer>
#include <QVariant>

ApiReply::ApiReply(int timeoutMs, QObject *parent)
    : QObject(parent), manager_(0), post_(false), timeoutMs_(timeoutMs), reply_(0), ok_(false), busy_(false), networkError_(false),
      timedOut_(false), httpStatus_(0), retries_(0), waitedMs_(0)
{
    timer_ = new QTimer(this);
    timer_->setSingleShot(true);
    connect(timer_, SIGNAL(timeout()), this, SLOT(timedOut()));
}

void ApiReply::send(QNetworkAccessManager *manager, const QNetworkRequest &request, const QByteArray &body, bool post, int delayMs)
{
    manager_ = manager;
    request_ = request;
    body_ = body;
    post_ = post;

    if (delayMs <= 0) {
        sendNow();
    } else {
        QTimer::singleShot(delayMs, this, SLOT(sendNow()));
    }
}

void ApiReply::sendNow()
{
    reply_ = post_ ? manager_->post(request_, body_) : manager_->get(request_);
    reply_->setParent(this);
    connect(reply_, SIGNAL(finished()), this, SLOT(replyFinished()));
    timer_->start(timeoutMs_);
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

    if (httpStatus_ == 429) {
        // too many requests: wait as long as the server asks, then ask again (the same request, on a new connection)
        bool numeric = false;
        const int seconds = QString::fromLatin1(reply_->rawHeader("Retry-After")).trimmed().toInt(&numeric);
        const int waitMs = qMin(numeric && seconds >= 0 ? seconds * 1000 : int(DefaultWaitMs), int(LongestWaitMs));
        if (retries_ < MaxRetries && waitedMs_ + waitMs <= MaxWaitMs) {
            ++retries_;
            waitedMs_ += waitMs;
            reply_->deleteLater();
            reply_ = 0;
            emit throttled(waitMs);
            QTimer::singleShot(waitMs, this, SLOT(sendNow()));

            return;
        }
        busy_ = true;
        message_ = tr("The server is busy (too many requests), try again in a minute.");
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
