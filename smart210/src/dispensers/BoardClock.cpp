#include "BoardClock.h"

#include "MachineConfig.h"

#include <QDateTime>
#include <QTimer>

using namespace BoardProtocol;

namespace {

const int OneHourMs = 3600 * 1000;
const int RetryMs = 5000; // after a failed attempt a board is tried again after this long

}

BoardClock::BoardClock(BoardBus *bus, QObject *parent)
    : QObject(parent), bus_(bus), timer_(new QTimer(this)), intervalMs_(OneHourMs)
{
    connect(bus_, SIGNAL(boardOnlineChanged(int,bool)), this, SLOT(boardOnline(int,bool)));
    connect(bus_, SIGNAL(commandDone(int,int,QByteArray)), this, SLOT(commandDone(int,int,QByteArray)));
    connect(bus_, SIGNAL(commandFailed(int,int,int)), this, SLOT(commandFailed(int,int,int)));
    connect(timer_, SIGNAL(timeout()), this, SLOT(tick()));
    timer_->setInterval(2000);
    timer_->start();
}

void BoardClock::setTickMs(int ms)
{
    timer_->setInterval(ms);
}

void BoardClock::syncAll()
{
    lastSet_.clear();
}

void BoardClock::boardOnline(int address, bool online)
{
    // a board that went away and came back may have lost its clock (or been replaced by one that knows the command)
    lastSet_.remove(address);
    pending_.remove(address);
    unsupported_.remove(address);
    Q_UNUSED(online);
}

void BoardClock::tick()
{
    const qint64 now = QDateTime::currentMSecsSinceEpoch();

    // (a nozzle in manual mode is not polled, so its board is never online here and its clock is not set: nothing of it is used)
    for (int address = 1; address <= MachineConfig::NozzleCount; ++address) {
        if (!bus_->hasBoard(address) || !bus_->isOnline(address) || pending_.value(address, false) || unsupported_.value(address, false)) {
            continue;
        }
        const bool due = !lastSet_.contains(address) || now - lastSet_.value(address) >= intervalMs_;
        if (!due || now - lastTry_.value(address, 0) < RetryMs / 10) {
            continue;
        }
        lastTry_[address] = now;
        if (bus_->send(address, CmdSetTime, timeData(QDateTime::currentDateTime()))) {
            pending_[address] = true;
        }
    }
}

void BoardClock::commandDone(int address, int command, const QByteArray &data)
{
    Q_UNUSED(data);
    if (command == CmdSetTime && pending_.value(address, false)) {
        pending_.remove(address);
        lastSet_[address] = QDateTime::currentMSecsSinceEpoch();
        emit timeSet(address);
    }
}

void BoardClock::commandFailed(int address, int command, int error)
{
    if (command != CmdSetTime || !pending_.value(address, false)) {
        return;
    }
    pending_.remove(address);
    if (error == ErrBadCommand) {
        unsupported_[address] = true;
    }
    lastTry_[address] = QDateTime::currentMSecsSinceEpoch() + RetryMs - RetryMs / 10; // not again at once
}
