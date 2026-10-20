#include "Clock.h"

#include "Settings.h"

#include <QProcess>

qint64 Clock::offsetSeconds(const QString &serverTime, const QDateTime &local)
{
    const QDateTime server = QDateTime::fromString(serverTime, "yyyy-MM-dd HH:mm:ss");
    if (!server.isValid()) {
        return 0;
    }

    return local.secsTo(server);
}

qint64 Clock::record(Settings &settings, const QString &serverTime, const QDateTime &local)
{
    const qint64 offset = offsetSeconds(serverTime, local);
    settings.setValue(SettingKey::ClockOffsetSeconds, QString::number(offset));

    return offset;
}

bool Clock::isOff(qint64 offsetSeconds)
{
    return offsetSeconds > WarnSeconds || offsetSeconds < -WarnSeconds;
}

bool Clock::setSystemClock(const QDateTime &serverTime)
{
#ifdef Q_OS_LINUX
    const bool set = QProcess::execute("date", QStringList() << "-s" << serverTime.toString("yyyy-MM-dd HH:mm:ss")) == 0;

    // To keep the time over a power-off, write the system time to the RTC chip too. Uncomment when the board has one:
    // QProcess::execute("hwclock", QStringList() << "-w"); // same as "hwclock --systohc"; add "-f" "/dev/rtc0" if it cannot find the chip

    return set;
#else
    Q_UNUSED(serverTime);

    return false;
#endif
}
