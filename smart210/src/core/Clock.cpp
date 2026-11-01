#include "Clock.h"

#include "Settings.h"

#include <QByteArray>
#include <QDebug>
#include <QProcess>
#include <QSqlQuery>
#include <QStringList>

#include <stdlib.h>
#include <time.h>
#ifdef Q_OS_LINUX
#include <unistd.h>
#endif

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

static bool behindCheckOn = true;

void Clock::setBehindCheck(bool on)
{
    behindCheckOn = on;
}

QString Clock::behindRecords(QSqlDatabase db, const QDateTime &now)
{
    if (!behindCheckOn) {
        return QString();
    }
    QSqlQuery query(db);
    query.exec("SELECT MAX(t) FROM (SELECT MAX(created_at) AS t FROM sales UNION ALL SELECT MAX(created_at) FROM remainders UNION ALL SELECT MAX(started_at) FROM supply_sessions)");
    if (!query.next() || query.value(0).toString().isEmpty()) {
        return QString();
    }
    const QDateTime newest = QDateTime::fromString(query.value(0).toString(), "yyyy-MM-dd HH:mm:ss");
    if (!newest.isValid() || now.secsTo(newest) <= TolerateSeconds) {
        return QString();
    }

    return QObject::tr("The clock of this terminal is behind: the last record was made at %1. Set the clock right (a download from the server sets it) before you go on.").arg(newest.toString("yyyy-MM-dd HH:mm"));
}

bool Clock::setSystemClock(const QDateTime &serverTime)
{
#ifdef Q_OS_LINUX
    return QProcess::execute("date", QStringList() << "-s" << serverTime.toString("yyyy-MM-dd HH:mm:ss")) == 0;
#else
    Q_UNUSED(serverTime);

    return false;
#endif
}

QDateTime Clock::utcOf(const QString &serverUtc)
{
    QDateTime time = QDateTime::fromString(serverUtc, "yyyy-MM-dd HH:mm:ss");
    if (time.isValid()) {
        time.setTimeSpec(Qt::UTC); // the same digits, read as UTC
    }

    return time;
}

qint64 Clock::offsetFromUtc(const QString &serverUtc, const QDateTime &nowUtc)
{
    const QDateTime server = utcOf(serverUtc);
    if (!server.isValid()) {
        return 0;
    }

    return nowUtc.toUTC().secsTo(server);
}

qint64 Clock::recordUtc(Settings &settings, const QString &serverUtc, const QDateTime &nowUtc)
{
    const qint64 offset = offsetFromUtc(serverUtc, nowUtc);
    settings.setValue(SettingKey::ClockOffsetSeconds, QString::number(offset));

    return offset;
}

bool Clock::needsSetting(qint64 offsetSeconds)
{
    return offsetSeconds > SetSeconds || offsetSeconds < -SetSeconds;
}

bool Clock::setSystemClockUtc(const QDateTime &utc)
{
#ifdef Q_OS_LINUX
    const bool set = QProcess::execute("date", QStringList() << "-u" << "-s" << utc.toUTC().toString("yyyy-MM-dd HH:mm:ss")) == 0;
    if (!set) {
        return false;
    }

    // The board has a real-time clock chip with a battery (/dev/rtc): without this the time is lost at the next power-off, and the board starts with
    // the date of the factory. The chip keeps UTC. Some images name the device rtc0.
    if (QProcess::execute("hwclock", QStringList() << "-w" << "-u") != 0
            && QProcess::execute("hwclock", QStringList() << "-w" << "-u" << "-f" << "/dev/rtc0") != 0) {
        qWarning() << "The system clock was set but the RTC chip could not be written (hwclock).";
    }

    return true;
#else
    Q_UNUSED(utc);

    return false;
#endif
}

QString Clock::posixTimeZone(int offsetSeconds)
{
    // POSIX counts the zone's offset the other way: UTC+9 is "UTC-9"
    const int west = -offsetSeconds;
    const int minutes = (west < 0 ? -west : west) / 60;
    QString text = QString("UTC") + (west < 0 ? "-" : "") + QString::number(minutes / 60);
    if (minutes % 60 != 0) {
        text += QString(":%1").arg(minutes % 60, 2, 10, QChar('0'));
    }

    return text;
}

void Clock::applyTimeZone(int offsetSeconds)
{
    qputenv("TZ", posixTimeZone(offsetSeconds).toLatin1());
#ifdef Q_OS_WIN
    _tzset();
#else
    tzset();
#endif
}

void Clock::keepTimeZone(Settings &settings, int offsetSeconds, const QString &name)
{
    settings.setValue(SettingKey::UtcOffsetSeconds, QString::number(offsetSeconds));
    if (name.isEmpty()) {
        settings.remove(SettingKey::TimeZoneName);
    } else {
        settings.setValue(SettingKey::TimeZoneName, name);
    }
    applyTimeZone(offsetSeconds);
}

void Clock::applyStoredTimeZone(Settings &settings)
{
    if (settings.contains(SettingKey::UtcOffsetSeconds)) {
        applyTimeZone(settings.intValue(SettingKey::UtcOffsetSeconds));
    }
}

QString Clock::zoneText(Settings &settings)
{
    if (!settings.contains(SettingKey::UtcOffsetSeconds)) {
        return QString();
    }

    const int offset = settings.intValue(SettingKey::UtcOffsetSeconds);
    const int minutes = (offset < 0 ? -offset : offset) / 60;
    QString text = QString("UTC%1%2:%3").arg(offset < 0 ? "-" : "+").arg(minutes / 60, 2, 10, QChar('0')).arg(minutes % 60, 2, 10, QChar('0'));
    const QString name = settings.value(SettingKey::TimeZoneName);

    return name.isEmpty() ? text : text + " (" + name + ")";
}

bool Clock::mayDefaultToSettingTheClock()
{
#ifdef Q_OS_LINUX
    return geteuid() == 0;
#else
    return false;
#endif
}
