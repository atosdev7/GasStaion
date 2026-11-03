#include "SupplySession.h"
#include "Clock.h"

#include "Barcode.h"
#include "Decimal.h"
#include "MachineConfig.h"
#include "Session.h"
#include "Settings.h"
#include "SupplyQueue.h"
#include "SupplySelection.h"
#include "TicketNumbers.h"

#include <QDateTime>

using namespace BoardProtocol;

namespace {

const int ResultRetryMs = 5000; // after a result could not be saved, it is read again after this long

QString kg(qlonglong x100)
{
    return Decimal::format(x100, 2);
}

}

SupplySession::SupplySession(QSqlDatabase db, BoardBus *bus, MachineConfig *config, SupplySelection *selection, Session *session, QObject *parent)
    : QObject(parent), db_(db), bus_(bus), config_(config), selection_(selection), session_(session), chosen_(0), startAgainMs_(2000), idleCancelMs_(5000)
{
    connect(selection_, SIGNAL(currentChanged(int)), this, SLOT(choiceChanged(int)));
    connect(bus_, SIGNAL(polled(int,BoardProtocol::Status)), this, SLOT(polled(int,BoardProtocol::Status)));
    connect(bus_, SIGNAL(commandDone(int,int,QByteArray)), this, SLOT(commandDone(int,int,QByteArray)));
    connect(bus_, SIGNAL(commandFailed(int,int,int)), this, SLOT(commandFailed(int,int,int)));
    connect(bus_, SIGNAL(boardOnlineChanged(int,bool)), this, SLOT(boardOnline(int,bool)));
}

qint64 SupplySession::nowMs() const
{
    return QDateTime::currentMSecsSinceEpoch();
}

bool SupplySession::isLocked(int nozzle) const
{
    return SupplyQueue::session(db_, nozzle).exists;
}

bool SupplySession::add(int nozzle, const QString &barcode, QString *why)
{
    QString reason;
    const MachineConfig::Nozzle info = config_->nozzle(nozzle);

    if (nozzle < 1 || nozzle > MachineConfig::NozzleCount) {
        reason = tr("Choose a nozzle first: tap it, or press its select key at the dispenser.");
    } else if (!info.seen()) {
        reason = tr("The config of nozzle %1 has not been read yet.").arg(nozzle);
    } else if (info.mode == ModeManual) {
        reason = tr("Nozzle %1 is in manual mode: the supplier types the amount at the dispenser, the cashier enters the tickets afterwards.").arg(nozzle);
    } else if (isLocked(nozzle)) {
        reason = tr("Nozzle %1 has started: its tickets cannot be changed.").arg(nozzle);
    } else if (!Clock::behindRecords(db_, QDateTime::currentDateTime()).isEmpty()) {
        reason = Clock::behindRecords(db_, QDateTime::currentDateTime());
    } else {
        const SupplyQueue::Scan scan = SupplyQueue::add(db_, nozzle, barcode, info.fuelType, QDateTime::currentDateTime());
        if (scan.ok) {
            emit queueChanged(nozzle);
            emit message(tr("Nozzle %1: ticket added, %2 kg. Together %3 kg.").arg(nozzle).arg(kg(scan.kgX100)).arg(kg(SupplyQueue::totalX100(db_, nozzle))), true);

            return true;
        }
        reason = scan.error;
    }

    if (why) {
        *why = reason;
    }

    return false;
}

bool SupplySession::typed(const QString &text)
{
    QString digits;
    for (int i = 0; i < text.size(); ++i) {
        const QChar c = text.at(i);
        if (c.isDigit() && c.unicode() < 128) {
            digits.append(c);
        } else if (!c.isSpace() && c != '-') {
            digits = "x";                       // anything else is not part of a ticket number
            break;
        }
    }

    QString barcode;
    if (digits.size() == Barcode::Length && Barcode::isValid(digits)) {
        barcode = digits;
    } else if (digits.size() == Barcode::Length - SignLength && Barcode::isValid(QString(SignLength, '0') + digits)) {
        const QString sign = Settings(db_).value(SettingKey::TicketSign);
        if (sign.size() != SignLength) {
            emit message(tr("The sign of the station is not known yet: type all 16 digits of the ticket."), false);

            return false;
        }
        barcode = sign + digits;
    } else {
        emit message(tr("Type the 11 digits after the station sign (the code and the serial), or all 16 digits of the ticket."), false);

        return false;
    }

    QString why;
    if (!add(selection_->current(), barcode, &why)) {
        emit message(why, false);

        return false;
    }

    return true;
}

void SupplySession::scanned(const QString &barcode)
{
    QString why;
    if (!add(selection_->current(), barcode, &why)) {
        emit message(why, false);
    }
}

bool SupplySession::remove(int nozzle, qlonglong barcode, QString *why)
{
    if (isLocked(nozzle)) {
        if (why) {
            *why = tr("Nozzle %1 has started: its tickets cannot be changed.").arg(nozzle);
        }

        return false;
    }
    if (SupplyQueue::remove(db_, nozzle, barcode)) {
        emit queueChanged(nozzle);
    }

    return true;
}

bool SupplySession::clear(int nozzle, QString *why)
{
    if (isLocked(nozzle)) {
        if (why) {
            *why = tr("Nozzle %1 has started: its tickets cannot be changed.").arg(nozzle);
        }

        return false;
    }
    SupplyQueue::clear(db_, nozzle);
    // a board that has the preset of the queue (SET) forgets it: SET_PRESET with zero, as after the clear key of the dispenser
    presetSent_.remove(nozzle);
    if (bus_->isOnline(nozzle) && bus_->lastStatus(nozzle).state == StateArmed && !forgetPreset_.contains(nozzle)) {
        forgetPreset_.append(nozzle);
    }
    emit queueChanged(nozzle);

    return true;
}

bool SupplySession::showCardBalance(int nozzle, qlonglong balanceX100)
{
    const MachineConfig::Nozzle info = config_->nozzle(nozzle);
    if (balanceX100 < 0 || !info.seen() || info.mode == ModeManual || isLocked(nozzle) || !bus_->isOnline(nozzle)
        || bus_->lastStatus(nozzle).state != StateIdle) {
        return false;
    }

    return bus_->showBalance(nozzle, quint32(balanceX100 * 10));
}

bool SupplySession::recordManual(int nozzle, qlonglong dispensedX100, qlonglong litersX100, const QList<qlonglong> &tickets, const QDateTime &when, QString *why, const QString &comment)
{
    QString reason;
    const MachineConfig::Nozzle info = config_->nozzle(nozzle);
    const int state = bus_->isOnline(nozzle) ? bus_->lastStatus(nozzle).state : int(StateIdle);

    const QDateTime now = QDateTime::currentDateTime();
    const int days = Settings(db_).intValue(SettingKey::ManualSupplyDays, 3);

    if (nozzle < 1 || nozzle > MachineConfig::NozzleCount || !info.seen()) {
        reason = tr("Choose a nozzle first.");
    } else if (dispensedX100 <= 0 || litersX100 <= 0) {
        reason = tr("Enter the weight and the litres that were dispensed.");
    } else if (!Clock::behindRecords(db_, now).isEmpty()) {
        reason = Clock::behindRecords(db_, now);
    } else if (when > now.addSecs(60)) {
        reason = tr("The time is in the future.");
    } else if (when < QDateTime(now.date().addDays(-days), QTime(0, 0, 0))) {
        reason = days > 0 ? tr("The time cannot be more than %1 days back (Machine Config, Options).").arg(days) : tr("The time must be today (Machine Config, Options).");
    } else if (state == StateDispensing || state == StateResult) {
        reason = tr("Nozzle %1 has a fill running or a result waiting: the app reads it by itself.").arg(nozzle);
    } else if (isLocked(nozzle) && SupplyQueue::session(db_, nozzle).byCard() && dispensedX100 > SupplyQueue::session(db_, nozzle).presetX100) {
        reason = tr("More than was asked for (%1 kg). Check the reading.").arg(kg(SupplyQueue::session(db_, nozzle).presetX100));
    } else if (isLocked(nozzle) && !SupplyQueue::session(db_, nozzle).byCard() && dispensedX100 > SupplyQueue::totalX100(db_, nozzle)) {
        reason = tr("More than the tickets cover (%1 kg). Check the reading.").arg(kg(SupplyQueue::totalX100(db_, nozzle)));
    } else if (!isLocked(nozzle) && tickets.isEmpty()) {
        reason = tr("Enter the tickets first.");
    }
    if (reason.isEmpty()) {
        SupplyRecord::Reading reading;
        reading.dispensedX100 = dispensedX100;
        reading.litersX100 = litersX100;
        if (!isLocked(nozzle)) {
            reading.tickets = tickets; // a fill that was started has its own tickets (or its card); the reading only says what came out
        }
        reading.byHand = true;
        reading.comment = comment;
        const SupplyRecord::Outcome outcome = SupplyRecord::record(db_, nozzle, reading, when);
        if (outcome.ok) {
            saved(nozzle, outcome);

            return true;
        }
        reason = outcome.error;
    }

    if (why) {
        *why = reason;
    }

    return false;
}

void SupplySession::saved(int nozzle, const SupplyRecord::Outcome &outcome)
{
    emit queueChanged(nozzle);
    emit recorded(nozzle, outcome.supplementId);
    if (!outcome.remainderBarcode.isEmpty()) {
        emit remainderMade(outcome.remainderBarcode);
    }
}

void SupplySession::polled(int address, const Status &status)
{
    const MachineConfig::Nozzle info = config_->nozzle(address);

    if (status.state == StateIdle) {
        presetSent_.remove(address); // an idle board has no preset
    }
    if (info.seen() && info.mode == ModeAuto && !startStep_.contains(address)) {
        followTheSupplier(address, status);
    }

    // a manual-mode board is only polled (its status, gun, preset and dispensed amount): its fills are not read, acknowledged or recorded
    if (status.state != StateResult || info.mode == ModeManual || resultStep_.contains(address) || startStep_.contains(address)) {
        return;
    }
    if (nowMs() < retryAfter_.value(address, 0)) {
        return;
    }
    if (bus_->getResult(address)) {
        resultStep_[address] = GettingResult;
    }
}

// The supplier works at the dispenser; the app follows: the preset goes to the board when the queue changes, the gun lifted (the fill running)
// locks the queue as a session, the clear key drops the queue.
void SupplySession::followTheSupplier(int address, const Status &status)
{
    if (isLocked(address)) {
        recoverStart(address, status);

        return;
    }

    if (status.flags & FlagClearKey) {
        // the clear key of the dispenser: the board only told. The queue goes, and a preset of zero makes a board that has the preset (SET) forget it (IDLE).
        presetSent_.remove(address);
        if (!SupplyQueue::entries(db_, address).isEmpty()) {
            SupplyQueue::clear(db_, address);
            emit queueChanged(address);
            emit message(tr("Nozzle %1: the queue was cleared at the dispenser.").arg(address), true);
        }
        if (status.state == StateArmed && !forgetPreset_.contains(address)) {
            forgetPreset_.append(address);
        }
    }
    if (forgetPreset_.contains(address)) {
        // SET_PRESET with zero, until the board is not SET any more (its command slot may be busy for a moment)
        if (status.state != StateArmed || bus_->setPreset(address, 0)) {
            forgetPreset_.removeAll(address);
        }

        return;
    }

    const qlonglong total = SupplyQueue::totalX100(db_, address);
    if (total <= 0 && status.state == StateArmed && !presetPending_.contains(address) && !(status.flags & FlagGunUp)) {
        // no tickets are queued but the board still holds a preset (the queue was cleared while the preset was on its way): it forgets it
        forgetPreset_.append(address);
        presetSent_.remove(address);

        return;
    }
    if ((status.flags & FlagGunUp) && status.state != StateDispensing && status.state != StateResult && status.state != StateFault) {
        // The gun is up (the board reports it, it never starts by itself). With the tickets of the queue and their preset on the board the Smart210
        // sends START; when the queue was cleared it does not, and sets the preset to zero (the board is IDLE).
        if (total <= 0) {
            if (status.state == StateArmed) {
                bus_->setPreset(address, 0);
            }

            return;
        }
        if (status.state == StateArmed && presetSent_.value(address, -1) == total && !presetPending_.contains(address)) {
            // saved before START, with its id: after a crash the session is still known, and the board keeps the id with the result
            const quint32 sessionId = SupplyQueue::begin(db_, address, total, QDateTime::currentDateTime());
            if (sessionId != 0 && bus_->startFill(address, sessionId)) {
                startStep_[address] = Starting;
                lastStartMs_[address] = nowMs();
                emit queueChanged(address);
            } else {
                SupplyQueue::release(db_, address); // the board is busy: the next poll tries again
            }

            return;
        }
    }
    if (status.state == StateDispensing) {
        if (total > 0) {
            // the gun was taken up: the fill runs with the preset the board has, so the queue is the session from now on
            SupplyQueue::begin(db_, address, total, QDateTime::currentDateTime(), QString(), status.sessionId); // the id the board runs the fill with (a new one when it has none)
            presetSent_.remove(address);
            emit queueChanged(address);
            emit message(tr("Nozzle %1 started: %2 kg.").arg(address).arg(kg(total)), true);
        }

        return;
    }

    // the preset goes to the nozzle the supplier chose (the choice is the Smart210's), or to one that has it already
    if (total > 0 && selection_->current() == address && (status.state == StateArmed || status.state == StateIdle) && presetSent_.value(address, -1) != total
        && !presetPending_.contains(address) && session_->userId() > 0 && bus_->setPreset(address, quint32(total * 10))) {
        presetPending_[address] = total;
    }
}

// A session is saved before START is sent. When START never ran on the board (a lost command, a restart in between) nothing would end the session: the board stays SET and
// no result comes. So, for a started session whose START is not on its way:
//   - the board is SET and the gun is up: START is sent again with the same session id (after startAgainMs; the first answer may only have been lost, then the board
//     is DISPENSING and refuses the repeat, which is not a failure);
//   - the board is IDLE for idleCancelMs: the preset is gone and no fill runs, so no result will come: the session is cancelled and its tickets are free;
//   - anything else (the fill runs, a result waits, the board is SET with the gun down): the session goes on.
void SupplySession::recoverStart(int address, const Status &status)
{
    const SupplyQueue::Session started = SupplyQueue::session(db_, address);
    if (!started.exists || started.sessionId == 0 || startStep_.contains(address) || resultStep_.contains(address)) {
        idleSince_.remove(address);

        return;
    }

    if (status.state != StateIdle) {
        idleSince_.remove(address);
    }
    if (status.state == StateArmed && (status.flags & FlagGunUp)) {
        if (nowMs() - lastStartMs_.value(address, 0) >= startAgainMs_ && bus_->startFill(address, started.sessionId)) {
            startStep_[address] = Starting;
            startAgain_.append(address);
            lastStartMs_[address] = nowMs();
            emit message(tr("Nozzle %1: the start is sent again.").arg(address), true);
        }

        return;
    }
    if (status.state == StateIdle) {
        if (!idleSince_.contains(address)) {
            idleSince_[address] = nowMs();
        } else if (nowMs() - idleSince_.value(address) >= idleCancelMs_ && nowMs() - lastStartMs_.value(address, 0) >= idleCancelMs_) {
            idleSince_.remove(address);
            SupplyQueue::release(db_, address);
            emit queueChanged(address);
            emit message(tr("Nozzle %1: the fill did not start. Its tickets are free again.").arg(address), false);
        }
    }
}

// A nozzle that lost the choice before its fill started (the queue and the preset are there, nobody took the gun up) gives its preset back: a
// preset of zero makes the board IDLE again. Its tickets stay in the queue; the preset is set again when the nozzle is chosen again.
void SupplySession::choiceChanged(int number)
{
    const int lost = chosen_;

    chosen_ = number;
    if (lost > 0 && lost != number && !isLocked(lost)) {
        presetSent_.remove(lost);
        if (!forgetPreset_.contains(lost)) {
            forgetPreset_.append(lost);
        }
    }
}

void SupplySession::boardOnline(int address, bool online)
{
    if (!online) {
        presetSent_.remove(address);
        presetPending_.remove(address);
        resultStep_.remove(address);
        if (startStep_.value(address) == Starting) {
            // no answer to the start: the fill may be running. The nozzle stays locked and its result is recorded with the tickets when it comes.
            startStep_.remove(address);
            emit queueChanged(address);
            emit message(tr("Nozzle %1: no answer to the start. If the fill runs, its result is recorded by itself.").arg(address), false);
        }
    }
}

void SupplySession::commandDone(int address, int command, const QByteArray &data)
{
    if (command == CmdSetPreset && presetPending_.contains(address)) {
        presetSent_[address] = presetPending_.take(address);
    } else if (command == CmdStart && startStep_.value(address) == Starting) {
        startStep_.remove(address);
        startAgain_.removeAll(address);
        quint32 echoed = 0;
        if (parseSessionId(data, &echoed) && echoed != SupplyQueue::session(db_, address).sessionId) {
            emit message(tr("Nozzle %1: the board started a fill with another session id.").arg(address), false);
        }
        emit queueChanged(address);
        emit message(tr("Nozzle %1 started: %2 kg.").arg(address).arg(kg(SupplyQueue::session(db_, address).presetX100)), true);
    } else if (command == CmdGetResult && resultStep_.value(address) == GettingResult) {
        Result result;
        if (!parseResult(data, &result)) {
            resultStep_.remove(address);
            retryAfter_[address] = nowMs() + ResultRetryMs;

            return;
        }

        SupplyRecord::Reading reading;
        reading.sessionId = result.sessionId;
        reading.dispensedX100 = (qlonglong(result.dispensedG) + 5) / 10;
        reading.litersX100 = (qlonglong(result.dispensedMl) + 5) / 10;
        reading.presetX100 = (qlonglong(result.presetG) + 5) / 10;
        reading.reason = result.reason;
        const SupplyRecord::Outcome outcome = SupplyRecord::record(db_, address, reading, QDateTime::currentDateTime());
        if (!outcome.ok) {
            // the result stays on the board; it is read again a little later
            resultStep_.remove(address);
            retryAfter_[address] = nowMs() + ResultRetryMs;
            emit message(tr("Nozzle %1: the result could not be saved: %2").arg(address).arg(outcome.error), false);

            return;
        }

        if (!outcome.duplicate) {
            if (outcome.tickets == 0 && outcome.kgX100 == 0) {
                emit message(tr("Nozzle %1: %2 kg dispensed, but its tickets could not be matched: the supply is saved without them.").arg(address).arg(kg(reading.dispensedX100)), false);
            } else if (outcome.refundKgX100 > 0) {
                emit message(tr("Nozzle %1: %2 kg dispensed. %3 kg of the card's ticket went back to the card.").arg(address).arg(kg(reading.dispensedX100)).arg(kg(outcome.refundKgX100)), true);
            } else if (outcome.remainderKgX100 > 0) {
                emit message(tr("Nozzle %1: %2 kg dispensed. A remainder ticket of %3 kg is made.").arg(address).arg(kg(reading.dispensedX100)).arg(kg(outcome.remainderKgX100)), true);
            } else {
                emit message(tr("Nozzle %1: %2 kg dispensed.").arg(address).arg(kg(reading.dispensedX100)), true);
            }
            saved(address, outcome); // after the message: the remainder ticket's print has its own to say
        }

        // saved: the board may forget the result now (a lost answer is repeated by the next poll: the result is a duplicate then)
        if (bus_->ackResult(address, result.sessionId)) {
            resultStep_[address] = Acknowledging;
        } else {
            resultStep_.remove(address);
        }
    } else if (command == CmdAckResult && resultStep_.value(address) == Acknowledging) {
        resultStep_.remove(address);
    }
}

void SupplySession::commandFailed(int address, int command, int error)
{
    if (command == CmdSetPreset && presetPending_.contains(address)) {
        const qlonglong total = presetPending_.take(address);
        if (error < ErrNoAnswer) {
            presetSent_[address] = total; // refused: not asked again until the queue changes
            emit message(tr("Nozzle %1: %2").arg(address).arg(errorText(error)), false);
        }
    } else if (command == CmdStart && startStep_.value(address) == Starting) {
        startStep_.remove(address);
        const bool repeat = startAgain_.removeAll(address) > 0;
        if (repeat && error == ErrWrongState) {
            return; // the first START had run: the fill is running already (the next poll shows it), the session goes on
        }
        if (error < ErrNoAnswer) {
            SupplyQueue::release(db_, address); // the board refused: nothing runs, the tickets stay
            emit queueChanged(address);
            emit message(tr("Nozzle %1: %2").arg(address).arg(errorText(error)), false);
        } else {
            // no answer: the fill may have started. The nozzle stays locked and its result is recorded with the tickets when it comes.
            emit queueChanged(address);
            emit message(tr("Nozzle %1: no answer to the start. If the fill runs, its result is recorded by itself.").arg(address), false);
        }
    } else if ((command == CmdGetResult || command == CmdAckResult) && resultStep_.contains(address)) {
        resultStep_.remove(address);
        retryAfter_[address] = nowMs() + 1000;
    }
}
