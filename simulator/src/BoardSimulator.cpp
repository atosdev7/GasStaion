#include "BoardSimulator.h"

#include <QSettings>

using namespace BoardProtocol;

const quint32 BoardSimulator::MaxShownG;
const int BoardSimulator::ShownMs;

namespace {

QByteArray u32(quint32 v)
{
    QByteArray out;
    for (int i = 0; i < 4; ++i) {
        out.append(char((v >> (8 * i)) & 0xFF));
    }

    return out;
}

quint32 get32(const QByteArray &d, int at)
{
    quint32 v = 0;
    for (int i = 3; i >= 0; --i) {
        v = (v << 8) | quint8(d.at(at + i));
    }

    return v;
}

}

BoardSimulator::BoardSimulator(QObject *parent)
    : QObject(parent), requests_(0)
{
}

void BoardSimulator::add(int address)
{
    boards_[address] = Board();
    Board &b = boards_[address];
    b.config.mode = ModeAuto;
    b.config.fuelType = "D";
    b.config.density = 8300;
    b.config.pulsesX100 = 100000;
    emit changed(address);
}

void BoardSimulator::addBlank(int address)
{
    boards_[address] = Board();
    emit changed(address);
}

QString BoardSimulator::stateName(int state)
{
    switch (state) {
    case StateIdle:
        return "IDLE";
    case StateSelected:
        return "SELECTED";
    case StateArmed:
        return "ARMED";
    case StateDispensing:
        return "DISPENSING";
    case StateResult:
        return "RESULT";
    case StateFault:
        return "FAULT";
    }

    return QString::number(state);
}

QString BoardSimulator::commandName(int command)
{
    switch (command) {
    case CmdInfo:
        return "INFO";
    case CmdPoll:
        return "POLL";
    case CmdSelect:
        return "SELECT";
    case CmdSetPreset:
        return "SET_PRESET";
    case CmdStart:
        return "START";
    case CmdGetResult:
        return "GET_RESULT";
    case CmdAckResult:
        return "ACK_RESULT";
    case CmdGetConfig:
        return "GET_CONFIG";
    case CmdSetConfig:
        return "SET_CONFIG";
    case CmdSetTime:
        return "SET_TIME";
    case CmdGetTime:
        return "GET_TIME";
    case CmdShowBalance:
        return "SHOW_BALANCE";
    }

    return QString("0x%1").arg(command, 2, 16, QChar('0'));
}

quint32 BoardSimulator::mlOf(const Board &b, quint32 grams) const
{
    return b.config.density > 0 ? quint32(quint64(grams) * 10000 / b.config.density) : 0;
}

QByteArray BoardSimulator::nak(const Frame &f, int error) const
{
    QByteArray d;
    d.append(char(error));
    d.append(char(f.command));

    return build(f.address, f.seq, NakCommand, d);
}

QByteArray BoardSimulator::ok(const Frame &f, const QByteArray &data) const
{
    return build(f.address, f.seq, f.command | AnswerFlag, data);
}

QByteArray BoardSimulator::answer(const Frame &request)
{
    ++requests_;
    if (!boards_.contains(request.address)) {
        return QByteArray();
    }
    Board &b = boards_[request.address];
    b.requestSeqs.append(request.seq);
    if (b.silent) {
        return QByteArray();
    }

    QByteArray reply;
    bool repeat = false;
    if (b.haveSeq && b.lastSeq == request.seq) {
        reply = b.lastAnswer; // a repeat: the same answer, nothing runs again
        repeat = true;
    } else {
        reply = execute(b, request);
        b.haveSeq = true;
        b.lastSeq = request.seq;
        b.lastAnswer = reply;
    }

    QString fate;
    QByteArray result = reply;
    if (b.dropReplies > 0 || (b.dropCommandOnce != 0 && b.dropCommandOnce == request.command)) {
        if (b.dropReplies > 0) {
            --b.dropReplies;
        } else {
            b.dropCommandOnce = 0;
        }
        result = QByteArray();
        fate = " (answer lost)";
    } else {
        bool spoil = false;
        if (b.corruptReplies > 0) {
            --b.corruptReplies;
            spoil = true;
        } else if (b.corruptCommandOnce != 0 && b.corruptCommandOnce == request.command) {
            b.corruptCommandOnce = 0;
            spoil = true;
        }
        if (spoil) {
            result[result.size() - 1] = char(result.at(result.size() - 1) ^ 0x55);
            fate = " (answer spoiled)";
        }
    }

    // the same exchange in words, for the log
    Frame answered;
    FrameParser parser;
    parser.feed(reply);
    QString said = "no answer";
    if (parser.next(&answered)) {
        if (answered.command == NakCommand) {
            said = QString("NAK %1").arg(answered.data.isEmpty() ? 0 : quint8(answered.data.at(0)));
        } else if (request.command == CmdPoll) {
            Status status;
            said = parseStatus(answered.data, &status) ? QString("%1, %2 g").arg(stateName(status.state)).arg(status.dispensedG) : QString("OK");
        } else {
            said = "OK";
        }
    }
    emit exchanged(request.address, commandName(request.command) + (repeat ? " (again)" : ""), said + fate);

    return result;
}

void BoardSimulator::startFill(Board &b)
{
    b.resultId = ++b.nextId;
    b.dispensedG = 0;
    b.dispensedMl = 0;
    b.accumulated = 0;
    b.reason = ReasonPresetReached;
    b.state = StateDispensing;
}

void BoardSimulator::endFill(Board &b, int reason)
{
    b.dispensedMl = mlOf(b, b.dispensedG);
    b.reason = reason;
    b.gunUp = false; // the fill is over: the gun goes back
    if (b.config.mode == ModeManual) {
        b.state = StateIdle; // a manual board keeps no result: nothing is reported about the end of a fill
        b.presetG = 0;
    } else {
        b.state = StateResult;
    }
}

QByteArray BoardSimulator::execute(Board &b, const Frame &f)
{
    const int cmd = f.command;

    if (cmd == CmdPoll) {
        ++polls_[f.address];
        // the key events are delivered once; the result and fault flags follow the state
        const int levels = (b.state == StateResult ? FlagResult : 0) | ((b.state == StateFault || b.fault != 0) ? FlagFault : 0) | (b.gunUp ? FlagGunUp : 0);
        QByteArray d;
        d.append(char(b.state));
        d.append(char((b.flags & (FlagSelectKey | FlagDeselectKey | FlagClearKey)) | levels));
        d.append(char(b.fault));
        d.append(u32((b.state == StateDispensing || b.state == StateResult) ? b.resultId : 0));
        d.append(u32(b.dispensedG));
        d.append(u32(b.dispensedMl));
        d.append(u32((b.state == StateArmed || b.state == StateDispensing || b.state == StateResult) ? b.presetG : 0));
        b.flags &= ~(FlagSelectKey | FlagDeselectKey | FlagClearKey);

        return ok(f, d);
    }

    ++b.executed;
    b.commands.append(cmd);
    const bool manual = b.config.mode == ModeManual;
    const int address = f.address;
    QByteArray reply;

    switch (cmd) {
    case CmdInfo: {
        QByteArray d;
        d.append(char(4));
        d.append(char(1));
        d.append(char(4));
        d.append(u32(0xCAFE0000 + quint32(address)));
        reply = ok(f, d);
        break;
    }
    case CmdSelect:
        if (b.state == StateFault) {
            reply = nak(f, ErrFault);
        } else if (manual || (b.state != StateIdle && b.state != StateArmed)) {
            reply = nak(f, ErrWrongState);
        } else {
            // the board beeps; nothing else changes: the choice of the nozzle is kept by the Smart210
            ++b.beeps;
            reply = ok(f);
        }
        break;
    case CmdSetPreset:
        if (f.data.size() != 4) {
            reply = nak(f, ErrBadLength);
        } else if (b.state == StateFault) {
            reply = nak(f, ErrFault);
        } else if (manual || (b.state != StateIdle && b.state != StateArmed)) {
            reply = nak(f, ErrWrongState);
        } else {
            b.presetG = get32(f.data, 0);
            b.state = b.presetG == 0 ? StateIdle : StateArmed; // a preset of zero is the cancel: the nozzle is IDLE again
            b.shownG = 0;                                      // the dispenser shows the preset now, not a balance
            b.shownMsLeft = 0;
            reply = ok(f, f.data);
        }
        break;
    case CmdShowBalance:
        // the dispenser shows the balance of a card; it arms nothing (no preset, still IDLE) and a gun taken up changes nothing
        if (f.data.size() != 4) {
            reply = nak(f, ErrBadLength);
        } else if (b.state == StateFault) {
            reply = nak(f, ErrFault);
        } else if (manual || b.state != StateIdle) {
            reply = nak(f, ErrWrongState);
        } else if (get32(f.data, 0) > MaxShownG) {
            reply = nak(f, ErrBadValue);
        } else {
            b.shownG = get32(f.data, 0);
            b.shownMsLeft = b.shownG > 0 ? ShownMs : 0;
            reply = ok(f, f.data);
        }
        break;
    case CmdStart:
        if (b.config.pulsesX100 == 0 || b.config.density == 0) {
            reply = nak(f, ErrNotConfigured);
        } else if (b.state == StateFault) {
            reply = nak(f, ErrFault);
        } else if (manual || b.state != StateArmed) {
            reply = nak(f, ErrWrongState);
        } else {
            startFill(b);
            reply = ok(f, u32(b.resultId));
        }
        break;
    case CmdGetResult:
        if (b.state != StateResult) {
            reply = nak(f, ErrNoResult);
        } else {
            QByteArray d = u32(b.resultId) + u32(b.presetG) + u32(b.dispensedG) + u32(b.dispensedMl);
            d.append(char(b.reason));
            reply = ok(f, d);
        }
        break;
    case CmdAckResult:
        if (f.data.size() != 4) {
            reply = nak(f, ErrBadLength);
        } else {
            const quint32 id = get32(f.data, 0);
            if (b.state == StateResult && id == b.resultId) {
                b.lastAckedId = id;
                b.state = b.fault != 0 ? StateFault : StateIdle;
                b.dispensedG = 0;
                b.dispensedMl = 0;
                reply = ok(f, f.data);
            } else if (id == b.lastAckedId && id != 0) {
                reply = ok(f, f.data);
            } else {
                reply = nak(f, b.state == StateResult ? ErrIdMismatch : ErrNoResult);
            }
        }
        break;
    case CmdGetConfig:
        reply = f.data.isEmpty() ? ok(f, configData(b.config)) : nak(f, ErrBadLength);
        break;
    case CmdSetConfig: {
        BoardConfig wanted;
        if (!parseBoardConfig(f.data, &wanted)) {
            reply = nak(f, ErrBadLength);
        } else if (b.state != StateIdle) {
            reply = nak(f, ErrWrongState);
        } else if (!wanted.isValid()) {
            reply = nak(f, ErrBadValue);
        } else {
            if (!b.keepsNoConfig) {
                b.config = wanted;
            }
            reply = ok(f, configData(b.config));
        }
        break;
    }
    case CmdSetTime:
    case CmdGetTime: {
        QDateTime wanted;
        if (!b.supportsTime) {
            reply = nak(f, ErrBadCommand);
        } else if (cmd == CmdGetTime) {
            reply = f.data.isEmpty() ? ok(f, timeData(QDateTime::currentDateTime().addSecs(b.clockOffsetSecs))) : nak(f, ErrBadLength);
        } else if (f.data.size() != TimeSize) {
            reply = nak(f, ErrBadLength);
        } else if (!parseTime(f.data, &wanted)) {
            reply = nak(f, ErrBadValue);
        } else {
            b.clockOffsetSecs = QDateTime::currentDateTime().secsTo(wanted);
            reply = ok(f, f.data);
        }
        break;
    }
    default:
        reply = nak(f, ErrBadCommand);
        break;
    }

    emit changed(address);

    return reply;
}

void BoardSimulator::pressSelect(int address)
{
    if (!boards_.contains(address)) {
        return;
    }
    Board &b = boards_[address];
    if (b.config.mode == ModeManual) {
        return; // the select key is not used in manual mode
    }
    // The key does not choose the nozzle: the board only tells the Smart210 in its next POLL answer (SELECT_KEY). The Smart210 answers with SELECT.
    b.flags |= FlagSelectKey;
    emit changed(address);
}

void BoardSimulator::pressClearKey(int address)
{
    if (!boards_.contains(address)) {
        return;
    }
    Board &b = boards_[address];
    if (b.config.mode == ModeManual) {
        return;
    }
    // Only told, like the select key (CLEAR_KEY): the Smart210 drops the queue and sends SELECT again, which makes the board forget its preset.
    b.flags |= FlagClearKey;
    emit changed(address);
}

bool BoardSimulator::liftGun(int address)
{
    if (!boards_.contains(address)) {
        return false;
    }
    Board &b = boards_[address];
    b.hookDownMsLeft = 0; // the gun is up again: the fill goes on
    if (b.config.mode == ModeManual) {
        // a manual board is not worked by the Smart210: with the preset typed at its keyboard it starts by itself when the gun is taken up
        b.gunUp = true;
        if (b.state == StateArmed && b.config.pulsesX100 != 0 && b.config.density != 0) {
            startFill(b);
        }
        emit changed(address);

        return true;
    }
    b.gunUp = true; // the board only reports it; the Smart210 decides (START when it has tickets queued, a zero preset when it has none)
    emit changed(address);

    return true;
}

void BoardSimulator::putGunDown(int address)
{
    if (boards_.contains(address)) {
        Board &b = boards_[address];
        b.gunUp = false;
        if (b.state == StateDispensing) {
            b.hookDownMsLeft = HookDownMs; // the nozzle on its hook ends the fill after a few seconds
        }
        emit changed(address);
    }
}

bool BoardSimulator::typeAmount(int address, quint32 grams)
{
    if (!boards_.contains(address)) {
        return false;
    }
    Board &b = boards_[address];
    if (b.config.mode != ModeManual || b.state != StateIdle || grams == 0 || b.config.pulsesX100 == 0 || b.config.density == 0) {
        return false;
    }
    b.presetG = grams;       // the supplier types the preset on the keyboard of the dispenser: the board is SET...
    b.state = StateArmed;    // ...and starts when the gun is taken up (liftGun)
    emit changed(address);

    return true;
}

void BoardSimulator::finish(int address, quint32 dispensedG, quint32 dispensedMl, int reason)
{
    Board &b = boards_[address];
    b.dispensedG = dispensedG;
    b.reason = reason;
    b.gunUp = false;
    b.dispensedMl = dispensedMl;
    if (b.config.mode == ModeManual) {
        b.state = StateIdle;
        b.presetG = 0;
    } else {
        b.state = StateResult;
    }
    emit changed(address);
}

void BoardSimulator::stopFill(int address)
{
    if (!boards_.contains(address) || boards_[address].state != StateDispensing) {
        return;
    }
    endFill(boards_[address], ReasonTankFullOrNoFlow);
    emit changed(address);
}

void BoardSimulator::setFault(int address, int code)
{
    if (!boards_.contains(address)) {
        return;
    }
    Board &b = boards_[address];
    b.fault = code;
    if (code != 0) {
        if (b.state == StateDispensing) {
            endFill(b, ReasonFault); // the result is held; the board is in fault after it is acknowledged
        } else if (b.state != StateResult) {
            b.state = StateFault;
        }
    } else if (b.state == StateFault) {
        b.state = StateIdle;
    }
    emit changed(address);
}

void BoardSimulator::powerCut(int address, int milliseconds)
{
    if (!boards_.contains(address)) {
        return;
    }
    Board &b = boards_[address];
    if (b.state == StateDispensing) {
        endFill(b, ReasonInterrupted); // comes back as a result with what was stored
    }
    b.silent = true;
    b.silentMsLeft = milliseconds;
    emit changed(address);
}

void BoardSimulator::tick(int milliseconds)
{
    const QList<int> all = boards_.keys();
    for (int i = 0; i < all.size(); ++i) {
        Board &b = boards_[all.at(i)];
        bool any = false;

        if (b.shownMsLeft > 0) {
            b.shownMsLeft -= milliseconds;
            if (b.shownMsLeft <= 0) {
                b.shownMsLeft = 0;
                b.shownG = 0;           // the balance goes from the display by itself
                any = true;
            }
        }
        if (b.silentMsLeft > 0) {
            b.silentMsLeft -= milliseconds;
            if (b.silentMsLeft <= 0) {
                b.silentMsLeft = 0;
                b.silent = false;
                any = true;
            }
        }
        if (b.hookDownMsLeft > 0 && b.state == StateDispensing && !b.silent) {
            b.hookDownMsLeft -= milliseconds;
            if (b.hookDownMsLeft <= 0) {
                b.hookDownMsLeft = 0;
                endFill(b, ReasonTankFullOrNoFlow); // the gun stayed on its hook: the fill ends with what came out
                any = true;
            }
        }
        if (b.state == StateDispensing && !b.silent) {
            b.accumulated += double(b.flowGramsPerSecond) * milliseconds / 1000.0;
            const quint32 grams = quint32(b.accumulated);
            if (grams >= b.presetG) {
                b.dispensedG = b.presetG;
                endFill(b, ReasonPresetReached);
            } else {
                b.dispensedG = grams;
                b.dispensedMl = mlOf(b, grams);
            }
            any = true;
        }
        if (any) {
            emit changed(all.at(i));
        }
    }
}

void BoardSimulator::save(QSettings &settings) const
{
    for (QMap<int, Board>::const_iterator it = boards_.begin(); it != boards_.end(); ++it) {
        const QString key = QString("board%1/").arg(it.key());
        settings.setValue(key + "mode", it.value().config.mode);
        settings.setValue(key + "fuel", it.value().config.fuelType);
        settings.setValue(key + "density", int(it.value().config.density));
        settings.setValue(key + "pulses", it.value().config.pulsesX100);
        settings.setValue(key + "nextId", it.value().nextId);
        settings.setValue(key + "flow", it.value().flowGramsPerSecond);
    }
}

void BoardSimulator::load(QSettings &settings)
{
    for (QMap<int, Board>::iterator it = boards_.begin(); it != boards_.end(); ++it) {
        const QString key = QString("board%1/").arg(it.key());
        if (!settings.contains(key + "mode")) {
            continue;
        }
        it.value().config.mode = settings.value(key + "mode").toInt();
        it.value().config.fuelType = settings.value(key + "fuel").toString();
        it.value().config.density = quint16(settings.value(key + "density").toInt());
        it.value().config.pulsesX100 = settings.value(key + "pulses").toUInt();
        it.value().nextId = settings.value(key + "nextId", 100).toUInt();
        it.value().flowGramsPerSecond = settings.value(key + "flow", 500).toInt();
        emit changed(it.key());
    }
}
