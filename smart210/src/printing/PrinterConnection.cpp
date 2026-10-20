#include "PrinterConnection.h"

#include "EscPos.h"

#include <QTcpSocket>
#include <QTimer>

PrinterConnection::PrinterConnection(QObject *parent)
    : QObject(parent), timeoutMs_(10000), statusWaitMs_(1500), socket_(0), checkOnly_(false), jobSent_(false), done_(false)
{
    timer_ = new QTimer(this);
    timer_->setSingleShot(true);
    connect(timer_, SIGNAL(timeout()), this, SLOT(timedOut()));

    statusTimer_ = new QTimer(this);
    statusTimer_->setSingleShot(true);
    connect(statusTimer_, SIGNAL(timeout()), this, SLOT(statusWaitOver()));
}

void PrinterConnection::send(const QString &host, int port, const QByteArray &job)
{
    start(host, port, job, false);
}

void PrinterConnection::check(const QString &host, int port)
{
    start(host, port, QByteArray(), true);
}

void PrinterConnection::start(const QString &host, int port, const QByteArray &job, bool checkOnly)
{
    if (socket_) {
        finish(false, tr("The printer is busy."));

        return;
    }

    job_ = job;
    answer_.clear();
    checkOnly_ = checkOnly;
    jobSent_ = false;
    done_ = false;

    socket_ = new QTcpSocket(this);
    connect(socket_, SIGNAL(connected()), this, SLOT(connected()));
    connect(socket_, SIGNAL(readyRead()), this, SLOT(readyRead()));
    connect(socket_, SIGNAL(error(QAbstractSocket::SocketError)), this, SLOT(socketError()));
    connect(socket_, SIGNAL(disconnected()), this, SLOT(disconnected()));

    timer_->start(timeoutMs_);
    socket_->connectToHost(host, port);
}

void PrinterConnection::connected()
{
    socket_->write(EscPos::statusRequest());
    statusTimer_->start(statusWaitMs_);
}

void PrinterConnection::readyRead()
{
    answer_.append(socket_->readAll());
    if (jobSent_ || answer_.size() < 3) {
        return;
    }

    statusTimer_->stop();
    const QString problem = EscPos::statusProblem(answer_);
    if (!problem.isEmpty()) {
        finish(false, problem);

        return;
    }
    if (checkOnly_) {
        finish(true, tr("The printer is ready."));

        return;
    }
    sendJob();
}

// No status answer in time: this printer may not answer, so the job is sent anyway (a check alone reports it).
void PrinterConnection::statusWaitOver()
{
    if (checkOnly_) {
        finish(true, tr("The printer is connected (it did not report its status)."));

        return;
    }

    sendJob();
}

void PrinterConnection::sendJob()
{
    if (jobSent_) {
        return;
    }
    jobSent_ = true;
    socket_->write(job_);
    socket_->disconnectFromHost(); // closes once everything is written
}

void PrinterConnection::disconnected()
{
    if (jobSent_) {
        finish(true, tr("The ticket was sent to the printer."));
    } else {
        finish(false, tr("The printer closed the connection."));
    }
}

void PrinterConnection::socketError()
{
    if (done_ || !socket_) {
        return;
    }
    // after the job was written the printer may drop the line: that is the end, not an error
    if (jobSent_ && socket_->error() == QAbstractSocket::RemoteHostClosedError) {
        return;
    }

    finish(false, tr("Cannot reach the printer: %1").arg(socket_->errorString()));
}

void PrinterConnection::timedOut()
{
    finish(false, tr("The printer did not answer in time."));
}

void PrinterConnection::finish(bool ok, const QString &message)
{
    if (done_) {
        return;
    }
    done_ = true;

    timer_->stop();
    statusTimer_->stop();
    if (socket_) {
        socket_->disconnect(this);
        socket_->abort();
        socket_->deleteLater();
        socket_ = 0;
    }

    emit finished(ok, message);
}
