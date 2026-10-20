#include "ConverterClient.h"

ConverterClient::ConverterClient(QObject *parent)
    : QObject(parent), port_(0), autoReconnect_(true), reconnectMs_(1000), cutReplies_(false)
{
    timer_.setSingleShot(true);
    connect(&socket_, SIGNAL(connected()), this, SLOT(linked()));
    connect(&socket_, SIGNAL(readyRead()), this, SLOT(read()));
    connect(&socket_, SIGNAL(disconnected()), this, SLOT(gone()));
    connect(&socket_, SIGNAL(error(QAbstractSocket::SocketError)), this, SLOT(failed()));
    connect(&timer_, SIGNAL(timeout()), this, SLOT(reconnect()));
}

ConverterClient::~ConverterClient()
{
    autoReconnect_ = false; // the socket reports its closing while this object is half gone
    timer_.stop();
    socket_.disconnect(this);
    socket_.abort();
}

void ConverterClient::connectTo(const QString &host, quint16 port)
{
    host_ = host;
    port_ = port;
    reconnect();
}

void ConverterClient::disconnectNow()
{
    autoReconnect_ = false;
    timer_.stop();
    const bool was = isConnected();
    socket_.abort();
    if (was) {
        emit connectionChanged(false);
    }
}

void ConverterClient::reconnect()
{
    if (port_ != 0 && socket_.state() == QAbstractSocket::UnconnectedState) {
        socket_.connectToHost(host_, port_);
    }
}

void ConverterClient::linked()
{
    socket_.setSocketOption(QAbstractSocket::LowDelayOption, 1);
    parser_.clear();
    emit connectionChanged(true);
}

void ConverterClient::read()
{
    parser_.feed(socket_.readAll());
    BoardProtocol::Frame frame;
    while (parser_.next(&frame)) {
        emit requestReceived(frame);
    }
}

void ConverterClient::gone()
{
    emit connectionChanged(false);
    again();
}

void ConverterClient::failed()
{
    if (socket_.state() == QAbstractSocket::UnconnectedState) {
        again();
    }
}

void ConverterClient::again()
{
    if (autoReconnect_) {
        timer_.start(reconnectMs_);
    }
}

void ConverterClient::send(const QByteArray &bytes)
{
    if (bytes.isEmpty() || !isConnected()) {
        return;
    }
    if (cutReplies_) {
        for (int i = 0; i < bytes.size(); ++i) {
            socket_.write(bytes.mid(i, 1));
            socket_.flush();
        }
    } else {
        socket_.write(bytes);
        socket_.flush();
    }
}
