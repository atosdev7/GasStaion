#include "BoardProtocol.h"

#include <QCoreApplication>

namespace BoardProtocol {

namespace {

void putU16(QByteArray *out, quint16 value)
{
    out->append(char(value & 0xFF));
    out->append(char((value >> 8) & 0xFF));
}

quint16 getU16(const QByteArray &data, int at)
{
    return quint16(quint8(data.at(at))) | quint16(quint16(quint8(data.at(at + 1))) << 8);
}

void putU32(QByteArray *out, quint32 value)
{
    for (int i = 0; i < 4; ++i) {
        out->append(char((value >> (8 * i)) & 0xFF));
    }
}

quint32 getU32(const QByteArray &data, int at)
{
    quint32 value = 0;
    for (int i = 3; i >= 0; --i) {
        value = (value << 8) | quint8(data.at(at + i));
    }

    return value;
}

}

quint16 crc16(const QByteArray &bytes)
{
    quint16 crc = 0xFFFF;
    for (int n = 0; n < bytes.size(); ++n) {
        crc ^= quint8(bytes.at(n));
        for (int bit = 0; bit < 8; ++bit) {
            crc = (crc & 1) ? quint16((crc >> 1) ^ 0xA001) : quint16(crc >> 1);
        }
    }

    return crc;
}

QByteArray build(int address, int seq, int command, const QByteArray &data)
{
    QByteArray body;
    body.append(char(address));
    body.append(char(seq));
    body.append(char(command));
    body.append(char(data.size()));
    body.append(data);

    const quint16 crc = crc16(body);
    QByteArray frame;
    frame.append(char(Stx));
    frame.append(body);
    frame.append(char(crc & 0xFF));
    frame.append(char(crc >> 8));

    return frame;
}

bool BoardConfig::isValid() const
{
    return (mode == ModeAuto || mode == ModeManual) && !fuelType.isEmpty() && fuelType.size() <= 4
        && density >= MinDensity && density <= MaxDensity && pulsesX100 >= 1 && pulsesX100 <= quint32(MaxPulsesX100);
}

QByteArray presetData(quint32 presetG)
{
    QByteArray data;
    putU32(&data, presetG);

    return data;
}

QByteArray sessionIdData(quint32 sessionId)
{
    QByteArray data;
    putU32(&data, sessionId);

    return data;
}

QByteArray configData(const BoardConfig &config)
{
    QByteArray data;
    data.append(char(config.mode));
    QByteArray fuel = config.fuelType.left(4).toLatin1();
    fuel.append(QByteArray(4 - fuel.size(), '\0'));
    data.append(fuel);
    data.append(char(config.density & 0xFF));
    data.append(char(config.density >> 8));
    putU32(&data, config.pulsesX100);

    return data;
}

bool parseStatus(const QByteArray &data, Status *status)
{
    if (data.size() != 19) {
        return false;
    }
    status->state = quint8(data.at(0));
    status->flags = quint8(data.at(1));
    status->fault = quint8(data.at(2));
    status->sessionId = getU32(data, 3);
    status->dispensedG = getU32(data, 7);
    status->dispensedMl = getU32(data, 11);
    status->presetG = getU32(data, 15);

    return true;
}

bool parseResult(const QByteArray &data, Result *result)
{
    if (data.size() != 17) {
        return false;
    }
    result->sessionId = getU32(data, 0);
    result->presetG = getU32(data, 4);
    result->dispensedG = getU32(data, 8);
    result->dispensedMl = getU32(data, 12);
    result->reason = quint8(data.at(16));

    return true;
}

bool parseInfo(const QByteArray &data, Info *info)
{
    if (data.size() != 7) {
        return false;
    }
    info->protocol = quint8(data.at(0));
    info->versionMajor = quint8(data.at(1));
    info->versionMinor = quint8(data.at(2));
    info->serial = getU32(data, 3);

    return true;
}

bool parseBoardConfig(const QByteArray &data, BoardConfig *config)
{
    if (data.size() != ConfigSize) {
        return false;
    }
    config->mode = quint8(data.at(0));
    int length = 0;
    while (length < 4 && data.at(1 + length) != '\0') {
        ++length;
    }
    config->fuelType = QString::fromLatin1(data.constData() + 1, length);
    config->density = quint16(quint8(data.at(5))) | quint16(quint16(quint8(data.at(6))) << 8);
    config->pulsesX100 = getU32(data, 7);

    return true;
}

QByteArray timeData(const QDateTime &time)
{
    QByteArray data;
    putU16(&data, quint16(time.date().year()));
    data.append(char(time.date().month()));
    data.append(char(time.date().day()));
    data.append(char(time.time().hour()));
    data.append(char(time.time().minute()));
    data.append(char(time.time().second()));

    return data;
}

bool parseTime(const QByteArray &data, QDateTime *time)
{
    if (data.size() != TimeSize) {
        return false;
    }
    const QDate date(getU16(data, 0), quint8(data.at(2)), quint8(data.at(3)));
    const QTime clock(quint8(data.at(4)), quint8(data.at(5)), quint8(data.at(6)));
    if (!date.isValid() || !clock.isValid()) {
        return false;
    }
    *time = QDateTime(date, clock);

    return true;
}

bool parseTotal(const QByteArray &data, quint64 *totalG)
{
    if (data.size() != 8) {
        return false;
    }
    quint64 v = 0;
    for (int i = 7; i >= 0; --i) {
        v = (v << 8) | quint8(data.at(i));
    }
    *totalG = v;

    return true;
}

QByteArray totalData(quint64 totalG)
{
    QByteArray out;
    for (int i = 0; i < 8; ++i) {
        out.append(char((totalG >> (8 * i)) & 0xFF));
    }

    return out;
}

bool parseSessionId(const QByteArray &data, quint32 *sessionId)
{
    if (data.size() != 4) {
        return false;
    }
    *sessionId = getU32(data, 0);

    return true;
}

QString errorText(int error)
{
    switch (error) {
    case ErrBadCommand:
        return QCoreApplication::translate("BoardProtocol", "The board does not know this command.");
    case ErrBadLength:
    case ErrBadValue:
        return QCoreApplication::translate("BoardProtocol", "The board refused the value.");
    case ErrWrongState:
        return QCoreApplication::translate("BoardProtocol", "The nozzle is not ready for this.");
    case ErrNotConfigured:
        return QCoreApplication::translate("BoardProtocol", "The board has no pulses per litre set.");
    case ErrNoResult:
    case ErrIdMismatch:
        return QCoreApplication::translate("BoardProtocol", "The board has no such result.");
    case ErrFault:
        return QCoreApplication::translate("BoardProtocol", "The board reports a fault.");
    case ErrNoAnswer:
        return QCoreApplication::translate("BoardProtocol", "The board does not answer.");
    case ErrNoConnection:
        return QCoreApplication::translate("BoardProtocol", "There is no connection to the boards.");
    }

    return QCoreApplication::translate("BoardProtocol", "The board reported error %1.").arg(error);
}

bool FrameParser::next(Frame *frame)
{
    for (;;) {
        const int start = buffer_.indexOf(char(Stx));
        if (start < 0) {
            buffer_.clear();

            return false;
        }
        if (start > 0) {
            buffer_.remove(0, start);
        }
        if (buffer_.size() < HeaderSize) {
            return false;
        }

        const int length = quint8(buffer_.at(4));
        if (length > MaxData) {
            buffer_.remove(0, 1); // not a real header: look for the next STX
            continue;
        }

        const int total = HeaderSize + length + CrcSize;
        if (buffer_.size() < total) {
            return false;
        }

        const QByteArray body = buffer_.mid(1, HeaderSize - 1 + length);
        const quint16 crc = quint16(quint8(buffer_.at(total - 2))) | quint16(quint16(quint8(buffer_.at(total - 1))) << 8);
        if (crc16(body) != crc) {
            buffer_.remove(0, 1);
            continue;
        }

        frame->address = quint8(buffer_.at(1));
        frame->seq = quint8(buffer_.at(2));
        frame->command = quint8(buffer_.at(3));
        frame->data = buffer_.mid(HeaderSize, length);
        buffer_.remove(0, total);

        return true;
    }
}

} // namespace BoardProtocol
