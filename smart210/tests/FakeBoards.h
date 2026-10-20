#ifndef FAKEBOARDS_H
#define FAKEBOARDS_H

#include "BoardSimulator.h"
#include "ConverterClient.h"

#include <QObject>

// Nozzle boards behind an RS485-to-Ethernet converter, for the tests: the converter (a TCP client that connects to the Smart210, which
// listens, and connects again by itself) and the boards behind it, which are the BoardSimulator of the simulator program. It can lose,
// spoil or cut up answers, and it keeps what it was asked so the tests can see what really ran.
class FakeBoards : public QObject
{
    Q_OBJECT

public:
    typedef BoardSimulator::Board Board;

    FakeBoards()
    {
        client_.setReconnectMs(100);
        connect(&client_, SIGNAL(requestReceived(BoardProtocol::Frame)), this, SLOT(request(BoardProtocol::Frame)));
    }

    // the converter connects to the Smart210's port (and again after a drop, unless autoReconnect is off)
    void connectTo(quint16 port) { client_.connectTo("127.0.0.1", port); }
    bool isConnected() const { return client_.isConnected(); }
    void setAutoReconnect(bool on) { client_.setAutoReconnect(on); }
    void setReconnectMs(int ms) { client_.setReconnectMs(ms); }
    void dropConnection() { client_.dropConnection(); }
    void powerOff() { client_.disconnectNow(); } // the converter goes dark and stays so
    void cutReplies(bool on) { client_.setCutReplies(on); }

    // a board that is already configured: auto, fuel D, density 0.8300, 1000.00 pulses per litre
    void add(int address) { sim_.add(address); }
    // a board straight from the factory
    void addBlank(int address) { sim_.addBlank(address); }
    Board &board(int address) { return sim_.board(address); }
    BoardSimulator &simulator() { return sim_; }
    int requests() const { return sim_.requests(); }
    int pollsOf(int address) const { return sim_.pollsOf(address); }

    void pressSelect(int address) { sim_.pressSelect(address); }
    void finish(int address, quint32 dispensedG, quint32 dispensedMl, int reason) { sim_.finish(address, dispensedG, dispensedMl, reason); }

private slots:
    void request(const BoardProtocol::Frame &frame) { client_.send(sim_.answer(frame)); }

private:
    BoardSimulator sim_;
    ConverterClient client_;
};

#endif
