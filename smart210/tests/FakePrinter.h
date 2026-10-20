#ifndef FAKEPRINTER_H
#define FAKEPRINTER_H

#include <QByteArray>
#include <QHash>
#include <QList>
#include <QObject>
#include <QTcpServer>
#include <QTcpSocket>

// A network printer for the tests: listens on localhost, answers the status question with the bytes it is given (or not at all) and keeps
// what it was sent. Every connection is one job: `jobs()` has the bytes that followed the status question.
class FakePrinter : public QObject
{
    Q_OBJECT

public:
    FakePrinter() : answers_(true), connections_(0) { connect(&server_, SIGNAL(newConnection()), this, SLOT(accept())); }

    bool listen() { return server_.listen(QHostAddress::LocalHost, 0); }
    quint16 port() const { return server_.serverPort(); }
    void close() { server_.close(); }

    // The three status bytes (printer, offline, paper) it replies with; an empty answer is a printer that does not answer.
    void setStatus(const QByteArray &bytes) { status_ = bytes; }
    void goodStatus() { status_ = QByteArray("\x16\x12\x12", 3); }

    QList<QByteArray> jobs() const { return jobs_; }
    int connections() const { return connections_; }

private slots:
    void accept()
    {
        while (QTcpSocket *socket = server_.nextPendingConnection()) {
            ++connections_;
            buffers_[socket] = QByteArray();
            connect(socket, SIGNAL(readyRead()), this, SLOT(read()));
            connect(socket, SIGNAL(disconnected()), this, SLOT(gone()));
        }
    }

    void read()
    {
        QTcpSocket *socket = qobject_cast<QTcpSocket *>(sender());
        const bool first = buffers_[socket].isEmpty();
        buffers_[socket] += socket->readAll();

        // the status question comes first; answer it once
        const QByteArray question("\x10\x04\x01\x10\x04\x02\x10\x04\x04", 9);
        if (first && buffers_[socket].startsWith(question) && !status_.isEmpty()) {
            socket->write(status_);
        }
    }

    void gone()
    {
        QTcpSocket *socket = qobject_cast<QTcpSocket *>(sender());
        QByteArray all = buffers_.take(socket);
        const QByteArray question("\x10\x04\x01\x10\x04\x02\x10\x04\x04", 9);
        if (all.startsWith(question)) {
            all = all.mid(question.size());
        }
        if (!all.isEmpty()) {
            jobs_.append(all);
        }
        socket->deleteLater();
    }

private:
    QTcpServer server_;
    QHash<QTcpSocket *, QByteArray> buffers_;
    QList<QByteArray> jobs_;
    QByteArray status_;
    bool answers_;
    int connections_;
};

#endif
