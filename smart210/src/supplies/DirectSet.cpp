#include "DirectSet.h"

#include "Decimal.h"
#include "NozzleDialog.h"
#include "SupplySelection.h"
#include "SupplySession.h"

bool DirectSet::run(QWidget *parent, QSqlDatabase db, BoardBus *bus, MachineConfig *config, SupplySelection *selection, SupplySession *supply,
                    const QString &fuelType, qlonglong kgX100, const QString &barcode, QString *message)
{
    NozzleDialog dialog(db, bus, config, fuelType, kgX100, parent);
    if (dialog.exec() != QDialog::Accepted || dialog.nozzle() < 1) {
        *message = tr("No nozzle was chosen, so nothing was sent.");

        return false;
    }

    // The nozzle becomes the chosen one (the choice is kept by the Smart210; the board beeps), the ticket goes to its queue as when the supplier
    // scans it, and the session sends the weight as the preset (the dispenser shows it). The supplier takes the gun up and the fill starts.
    const int nozzle = dialog.nozzle();
    QString why;
    if (!selection->select(nozzle, &why) || !supply->add(nozzle, barcode, &why)) {
        *message = why;

        return false;
    }
    *message = tr("Nozzle %1: %2 kg are set. Take the gun up to start the fill.").arg(nozzle).arg(Decimal::format(kgX100, 2));

    return true;
}