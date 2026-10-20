#include "SupplySelection.h"

#include "MachineConfig.h"

using namespace BoardProtocol;

SupplySelection::SupplySelection(BoardBus *bus, MachineConfig *config, QObject *parent)
    : QObject(parent), bus_(bus), config_(config), current_(0)
{
    connect(bus_, SIGNAL(polled(int,BoardProtocol::Status)), this, SLOT(polled(int,BoardProtocol::Status)));
    connect(bus_, SIGNAL(boardOnlineChanged(int,bool)), this, SLOT(boardOnline(int,bool)));
}

void SupplySelection::setCurrent(int number)
{
    if (number != current_) {
        current_ = number;
        emit currentChanged(number);
    }
}

bool SupplySelection::select(int number, QString *why)
{
    QString reason;
    const MachineConfig::Nozzle nozzle = config_->nozzle(number);
    const int state = bus_->lastStatus(number).state;

    if (number < 1 || number > MachineConfig::NozzleCount) {
        reason = tr("There is no such nozzle.");
    } else if (!bus_->isConnected()) {
        reason = tr("There is no connection to the boards.");
    } else if (!bus_->isOnline(number)) {
        reason = tr("The board of nozzle %1 does not answer.").arg(number);
    } else if (!nozzle.seen()) {
        reason = tr("The config of nozzle %1 has not been read yet.").arg(number);
    } else if (nozzle.mode == ModeManual) {
        reason = tr("Nozzle %1 is in manual mode: the supplier types the amount at the dispenser.").arg(number);
    } else if (state == StateFault) {
        reason = tr("Nozzle %1 has a fault.").arg(number);
    } else if (state != StateIdle && state != StateArmed) {
        reason = tr("Nozzle %1 is busy.").arg(number);
    }
    if (!reason.isEmpty()) {
        if (why) {
            *why = reason;
        }

        return false;
    }

    if (current_ != number) {
        setCurrent(number);
        if (!bus_->select(number)) {
            beep_.append(number); // the board is busy with another command: its SELECT (the beep) follows with the next poll
        }
    }

    return true;
}

bool SupplySelection::deselect(int number, QString *why)
{
    Q_UNUSED(why);
    if (current_ == number) {
        setCurrent(0); // only the Smart210 knows the choice: nothing is sent
    }
    beep_.removeAll(number);

    return true;
}

void SupplySelection::polled(int address, const Status &status)
{
    if (beep_.contains(address) && bus_->select(address)) {
        beep_.removeAll(address);
    }
    if (current_ == address && status.state == StateFault) {
        setCurrent(0);
    }
    if (status.flags & FlagSelectKey) {
        // the select key at the dispenser: the board only told. The nozzle is chosen, or let go when it was the chosen one.
        if (current_ == address) {
            deselect(address);
        } else if (status.state == StateIdle || status.state == StateArmed) {
            select(address);
        }
    }
}

void SupplySelection::boardOnline(int address, bool online)
{
    if (!online) {
        beep_.removeAll(address);
        if (current_ == address) {
            setCurrent(0);
        }
    }
}
