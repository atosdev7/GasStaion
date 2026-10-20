#ifndef CONVERTERCLIENT_H
#define CONVERTERCLIENT_H

#include "BoardProtocol.h"

#include <QObject>
#include <QString>
#include <QTcpSocket>
#include <QTimer>

// The RS485-to-Ethernet converter as the nozzle boards see it: a TCP client that connects to the Smart210 (which listens), and connects
// again by itself after every drop. Bytes that arrive are cut into protocol frames; answers go back as they are given. The simulator
// program and the tests share it.
class ConverterClient : public QObject
{
    Q_OBJECT

public:
    explicit ConverterClient(QObject *parent = 0);
    ~ConverterClient();

    void connectTo(const QString &host, quint16 port); // and again after a drop, while auto reconnect is on
    void disconnectNow();                               // closes, and stays closed
    bool isConnected() const { return socket_.state() == QAbstractSocket::ConnectedState; }
    QString errorText() const { return socket_.errorString(); }

    void setAutoReconnect(bool on) { autoReconnect_ = on; }
    void setReconnectMs(int ms) { reconnectMs_ = ms; }
    void setCutReplies(bool on) { cutReplies_ = on; } // send every answer one byte at a time
    void dropConnection() { socket_.disconnectFromHost(); } // the link breaks (it is made again when auto reconnect is on)

    void send(const QByteArray &bytes);

signals:
    void connectionChanged(bool connected);
    void requestReceived(const BoardProtocol::Frame &request);

private slots:
    void linked();
    void read();
    void gone();
    void failed();
    void reconnect();

private:
    void again();

    QTcpSocket socket_;
    QTimer timer_;
    BoardProtocol::FrameParser parser_;
    QString host_;
    quint16 port_;
    bool autoReconnect_;
    int reconnectMs_;
    bool cutReplies_;
};

#endif
