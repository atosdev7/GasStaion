#ifndef DIRECTSET_H
#define DIRECTSET_H

#include <QCoreApplication>
#include <QSqlDatabase>
#include <QString>

class BoardBus;
class MachineConfig;
class QWidget;
class SupplySelection;
class SupplySession;

// "Set directly": the weight of a ticket goes straight to a nozzle and no ticket is printed. A question asks for the nozzle (of the fuel type; see NozzleDialog),
// the nozzle becomes the chosen one on its board and the ticket goes to its queue, so the weight is the preset of the board (the dispenser shows it) and the supplier
// starts the fill by taking the gun up, exactly as when the supplier scans the ticket (SupplySession::add). Used by a spot sale and by a card fill, as their
// settings say (Settings page).
class DirectSet
{
    Q_DECLARE_TR_FUNCTIONS(DirectSet)

public:
    // True when the weight was set. `message` says what happened, either way (it is for the screen; a screen that can print adds "Print again prints the ticket").
    static bool run(QWidget *parent, QSqlDatabase db, BoardBus *bus, MachineConfig *config, SupplySelection *selection, SupplySession *supply,
                    const QString &fuelType, qlonglong kgX100, const QString &barcode, QString *message);
};

#endif