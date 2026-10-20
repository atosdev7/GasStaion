#ifndef BOARDCLOCK_H
#define BOARDCLOCK_H

#include "BoardBus.h"

#include <QMap>
#include <QObject>

class QTimer;

// Keeps the clocks of the nozzle boards right. The Smart210 sends its date and time (SET_TIME) to every board when the board comes online, then
// again every hour, and at once for all boards when the terminal's own clock was just corrected from the server (syncAll). A board that does not
// know the command (an older firmware answers BAD_COMMAND) is left alone until it goes offline and comes back. The boards use their clock to
// stamp what they hold; the Smart210 stays the one source of time at the station.
class BoardClock : public QObject
{
    Q_OBJECT

public:
    explicit BoardClock(BoardBus *bus, QObject *parent = 0);

    void setIntervalMs(int ms) { intervalMs_ = ms; }   // how often a board's clock is set again (default: one hour)
    void setTickMs(int ms);                             // how often the boards are looked at (default: 2 s)

    // Every board gets the time again as soon as possible.
    void syncAll();

    bool isSet(int address) const { return lastSet_.value(address, 0) > 0; }
    bool isUnsupported(int address) const { return unsupported_.value(address, false); }

signals:
    void timeSet(int address);

private slots:
    void tick();
    void boardOnline(int address, bool online);
    void commandDone(int address, int command, const QByteArray &data);
    void commandFailed(int address, int command, int error);

private:
    BoardBus *bus_;
    QTimer *timer_;
    int intervalMs_;
    QMap<int, qint64> lastSet_;     // address -> when (ms since epoch) its clock was last set; absent: not yet
    QMap<int, qint64> lastTry_;
    QMap<int, bool> pending_;
    QMap<int, bool> unsupported_;
};

#endif
