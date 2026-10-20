#ifndef PRINTERCONNECTION_H
#define PRINTERCONNECTION_H

#include <QByteArray>
#include <QObject>
#include <QString>

class QTcpSocket;
class QTimer;

// One job to the network printer (raw TCP, port 9100 as a rule): connect, ask for the printer's status, send the bytes, close. The
// status check stops a job when the printer says it is out of paper, offline or has its cover open. A printer that does not answer the
// status question (some do not) is sent the job anyway. finished() is emitted once.
class PrinterConnection : public QObject
{
    Q_OBJECT

public:
    explicit PrinterConnection(QObject *parent = 0);

    void setTimeoutMs(int timeoutMs) { timeoutMs_ = timeoutMs; }
    void setStatusWaitMs(int waitMs) { statusWaitMs_ = waitMs; }
    bool isBusy() const { return socket_ != 0; }

    void send(const QString &host, int port, const QByteArray &job);
    void check(const QString &host, int port); // only the status question: is the printer there and fine?

signals:
    void finished(bool ok, const QString &message);

private slots:
    void connected();
    void readyRead();
    void statusWaitOver();
    void socketError();
    void disconnected();
    void timedOut();

private:
    void start(const QString &host, int port, const QByteArray &job, bool checkOnly);
    void sendJob();
    void finish(bool ok, const QString &message);

    int timeoutMs_;
    int statusWaitMs_;
    QTcpSocket *socket_;
    QTimer *timer_;
    QTimer *statusTimer_;
    QByteArray job_;
    QByteArray answer_;
    bool checkOnly_;
    bool jobSent_;
    bool done_;
};

#endif
