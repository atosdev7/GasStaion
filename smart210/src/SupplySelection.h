#ifndef SUPPLYSELECTION_H
#define SUPPLYSELECTION_H

#include "BoardBus.h"

#include <QList>
#include <QObject>
#include <QString>

class MachineConfig;

// Which nozzle the supply works on. **The choice is kept here, in the Smart210, not on the boards**: a nozzle board has the states IDLE, SET (the
// preset is on the board), DISPENSING and DONE, and no "selected" state. When the supplier presses the select key at the dispenser the board only
// reports the key in its next POLL answer (flag SELECT_KEY); the Smart210 makes that nozzle the current one and sends it the SELECT command, which
// makes the board beep (and changes nothing else). Pressing the key of the current nozzle again lets it go (no command). One nozzle is chosen at a
// time: the one chosen last. The scanner's tickets go to the queue of the current nozzle. The Cards screen chooses a nozzle the same way (select()).
// Only auto nozzles can be chosen: in manual mode the board does not report the key and the supplier types the amount at the dispenser.
class SupplySelection : public QObject
{
    Q_OBJECT

public:
    SupplySelection(BoardBus *bus, MachineConfig *config, QObject *parent = 0);

    int current() const { return current_; } // 0 when no nozzle is chosen

    // Makes the nozzle the current one (the board gets SELECT and beeps). False with the reason in `why` when it cannot be chosen.
    bool select(int number, QString *why = 0);
    bool deselect(int number, QString *why = 0);

    bool isSelected(int number) const { return current_ == number; }

signals:
    void currentChanged(int number); // 0: none

private slots:
    void polled(int address, const BoardProtocol::Status &status);
    void boardOnline(int address, bool online);

private:
    void setCurrent(int number);

    BoardBus *bus_;
    MachineConfig *config_;
    int current_;
    QList<int> beep_;     // nozzles whose SELECT (the beep) was not sent yet because the board was busy with another command
};

#endif
