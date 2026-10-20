#ifndef BOARDBUS_H
#define BOARDBUS_H

#include <QByteArray>
#include <QList>
#include <QObject>
#include <QString>

#include "BoardProtocol.h"

class QTcpServer;
class QTcpSocket;
class QTimer;

// The master side of protocol v0.2 for the boards behind one RS485-to-Ethernet converter. The Smart210 is the TCP SERVER: the converter
// is a TCP client that connects to the listening port by itself, and again after every drop. One converter link is used; a new
// connection replaces the old one (the converter has restarted and the old link is dead). It works in polling mode:
// one request to one board at a time, going round the boards; a board gets the command waiting for it or else a POLL. There are no quick
// retries: an answer that is missing or has a wrong CRC is dropped and the next round asks again, with the same SEQ and the same
// command until one good answer comes (the board then repeats its last answer instead of running the command twice). A board without a
// good answer for `missLimit` rounds in a row is offline and is only tried every `offlineRetryMs`; a command still waiting for it fails.
// While no converter is connected nothing is sent and every board is offline.
class BoardBus : public QObject
{
    Q_OBJECT

public:
    explicit BoardBus(QObject *parent = 0);
    ~BoardBus();

    void setListenPort(int port) { port_ = port; }
    int listenPort() const { return port_; }
    void addBoard(int address);
    bool hasBoard(int address) const;

    void setCycleMs(int ms) { cycleMs_ = ms; }                // least time for one round over the boards
    void setAnswerTimeoutMs(int ms) { answerTimeoutMs_ = ms; } // how long to wait for one answer
    void setMissLimit(int rounds) { missLimit_ = rounds; }
    void setOfflineRetryMs(int ms) { offlineRetryMs_ = ms; }

    bool start(); // starts listening; false (see errorText) when the port cannot be used
    void stop();
    QString errorText() const { return error_; }
    bool isConnected() const { return connected_; }
    bool isOnline(int address) const;
    BoardProtocol::Status lastStatus(int address) const;

    // Queue one command for a board. False (and nothing queued) when the board is unknown or offline, the link is down, or the board
    // already has a command waiting. The outcome comes as commandDone() or commandFailed().
    bool send(int address, int command, const QByteArray &data = QByteArray());

    bool select(int address) { return send(address, BoardProtocol::CmdSelect); }
    bool setPreset(int address, quint32 presetG) { return send(address, BoardProtocol::CmdSetPreset, BoardProtocol::presetData(presetG)); }
    bool startFill(int address) { return send(address, BoardProtocol::CmdStart); }
    bool getResult(int address) { return send(address, BoardProtocol::CmdGetResult); }
    bool ackResult(int address, quint32 resultId) { return send(address, BoardProtocol::CmdAckResult, BoardProtocol::resultIdData(resultId)); }
    bool getConfig(int address) { return send(address, BoardProtocol::CmdGetConfig); }
    bool setConfig(int address, const BoardProtocol::BoardConfig &config) { return send(address, BoardProtocol::CmdSetConfig, BoardProtocol::configData(config)); }
    bool info(int address) { return send(address, BoardProtocol::CmdInfo); }

signals:
    void connectionChanged(bool connected);
    void boardOnlineChanged(int address, bool online);
    void polled(int address, const BoardProtocol::Status &status); // every good POLL answer
    void commandDone(int address, int command, const QByteArray &data);
    void commandFailed(int address, int command, int error); // a NAK (error 1-8) or BoardProtocol::ErrNoAnswer / ErrNoConnection

private slots:
    void newConnection();
    void disconnected();
    void readyRead();
    void answerTimedOut();
    void nextExchange();

private:
    struct Board {
        Board() : address(0), seq(0), missed(0), online(false), hasPending(false), pendingCommand(0), lastTry(0) {}
        int address;
        int seq;
        int missed;
        bool online;
        bool hasPending;
        int pendingCommand;
        QByteArray pendingData;
        qint64 lastTry;
        BoardProtocol::Status status;
    };

    int indexOf(int address) const;
    bool due(const Board &board, qint64 now) const;
    void beginRound();
    void exchange(int index);
    void gotAnswer(int index, const BoardProtocol::Frame &frame);
    void missed(int index);
    void failPending(int index, int error);
    void linkUp();
    void linkDown();
    void dropSocket();
    void scheduleNext(int ms);

    QString error_;
    int port_;
    int cycleMs_;
    int answerTimeoutMs_;
    int missLimit_;
    int offlineRetryMs_;

    QTcpServer *server_;
    QTcpSocket *socket_; // the converter's link, 0 when none
    QTimer *answerTimer_;
    QTimer *stepTimer_;
    QList<Board> boards_;
    BoardProtocol::FrameParser parser_;
    bool started_;
    bool connected_;
    int current_;      // the board in the round now (-1 before the first)
    int waitingFor_;   // index of the board whose answer is awaited, -1 when none
    int waitingCommand_;
    qint64 roundStart_;
};

#endif
