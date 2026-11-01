#ifndef CLOCK_H
#define CLOCK_H

#include <QDateTime>
#include <QSqlDatabase>
#include <QString>

class Settings;

// Sales and supplies are stamped with this terminal's clock, so it must agree with the server's, and the business day (9 AM to 9 AM, see Checkout)
// must be the station's day. Every download brings the server's time in UTC, the name of its time zone and the zone's offset from UTC at that moment
// (it changes with daylight saving time). From them the terminal
//   - keeps the offset and applies it as its own time zone (the TZ of the program: a fixed offset, so that the board needs no zone files),
//   - keeps how far its clock is from the server's, and shows a warning when it is more than a minute,
//   - when config.ini says setSystemClock=true (the default for a program that runs as root on Linux), sets the clock of the board in UTC and writes
//     it to the real-time clock chip (battery backed, see the manual of the Smart210: `date -s`, then `hwclock -w`), so the time survives a power-off.
// An older server sends only its local time: then the old way is used, the local time as it is.
class Clock
{
public:
    static const int WarnSeconds = 60; // the footer warns when the clock is off by more than this
    static const int SetSeconds = 5;   // the clock is set when it is off by more than this (and setting it is allowed)

    // server minus local, in seconds; 0 when the server time cannot be read.
    static qint64 offsetSeconds(const QString &serverTime, const QDateTime &local);

    // Keeps the offset for the screens (setting clock_offset_seconds). Returns it.
    static qint64 record(Settings &settings, const QString &serverTime, const QDateTime &local);

    static bool isOff(qint64 offsetSeconds);

    // A clock that is earlier than the newest record this terminal stamped itself (a sale or card top-up, a started fill, a remainder) is wrong (a power cut
    // with a flat RTC battery, or a clock set back): a record made now would be dated before the ones it follows. Empty when the clock is fine, else the text for
    // the cashier, which says what to do. TolerateSeconds: a clock that was corrected by a download may be a little behind.
    static const int TolerateSeconds = 120;
    static QString behindRecords(QSqlDatabase db, const QDateTime &now);
    // The end-to-end program writes records of whole simulated days with times ahead of the clock of the PC: it switches the check off.
    static void setBehindCheck(bool on);

    // Sets the system clock to the server's local time. False when it is not possible (not Linux, or no permission). The way of an older server.
    static bool setSystemClock(const QDateTime &serverTime);

    // ---- the time in UTC and the time zone

    // "yyyy-MM-dd HH:mm:ss" as a UTC time; invalid when it cannot be read.
    static QDateTime utcOf(const QString &serverUtc);

    // server minus this terminal, in seconds, both read as UTC: independent of the time zone the terminal is set to. 0 when unreadable.
    static qint64 offsetFromUtc(const QString &serverUtc, const QDateTime &nowUtc);

    // The same, kept as the setting clock_offset_seconds. Returns it.
    static qint64 recordUtc(Settings &settings, const QString &serverUtc, const QDateTime &nowUtc);

    static bool needsSetting(qint64 offsetSeconds);

    // Sets the system clock to this UTC time (`date -u -s`) and writes it to the RTC (`hwclock -w -u`; the RTC chip keeps UTC). True when the system
    // clock was set (the RTC write is tried and only logged when it fails). False when it is not possible (not Linux, or no permission).
    static bool setSystemClockUtc(const QDateTime &utc);

    // The time zone as a POSIX TZ string of a fixed offset: +32400 -> "UTC-9", -18000 -> "UTC5", +19800 -> "UTC-5:30", 0 -> "UTC0" (the sign of
    // POSIX is the other way round). It needs no zone files, which a small board may not have.
    static QString posixTimeZone(int offsetSeconds);

    // Makes the local time of this program the time of that offset (TZ and tzset). Qt's local time follows it on Linux.
    static void applyTimeZone(int offsetSeconds);

    // What a download brought: keeps the offset and the name of the zone in the settings and applies the offset.
    static void keepTimeZone(Settings &settings, int offsetSeconds, const QString &name);

    // At the start of the program: the zone kept by the last download (nothing is done before the first download).
    static void applyStoredTimeZone(Settings &settings);

    // "UTC+09:00 (Asia/Seoul)", "UTC+09:00" without a name; empty before the first download.
    static QString zoneText(Settings &settings);

    // The default of config.ini setSystemClock: only a Linux program running as root may set the clock.
    static bool mayDefaultToSettingTheClock();
};

#endif
