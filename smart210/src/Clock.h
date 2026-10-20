#ifndef CLOCK_H
#define CLOCK_H

#include <QDateTime>
#include <QString>

class Settings;

// Sales and supplies are stamped with this terminal's clock, so it must agree with the server's. Every download brings the
// server's time; the difference is kept and shown as a warning when it is more than a minute. If config.ini says
// setSystemClock=true the clock of the board is also set (a Linux `date -s`, which needs root).
class Clock
{
public:
    static const int WarnSeconds = 60;

    // server minus local, in seconds; 0 when the server time cannot be read.
    static qint64 offsetSeconds(const QString &serverTime, const QDateTime &local);

    // Keeps the offset for the screens (setting clock_offset_seconds). Returns it.
    static qint64 record(Settings &settings, const QString &serverTime, const QDateTime &local);

    static bool isOff(qint64 offsetSeconds);

    // Sets the system clock to server time. False when it is not possible (not Linux, or no permission).
    static bool setSystemClock(const QDateTime &serverTime);
};

#endif
