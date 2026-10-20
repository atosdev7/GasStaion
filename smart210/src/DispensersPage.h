#ifndef DISPENSERSPAGE_H
#define DISPENSERSPAGE_H

#include <QSqlDatabase>
#include <QWidget>

class BoardBus;
class MachineConfig;
class QLabel;
class QTableWidget;
class QTimer;

namespace Ui {
class DispensersPage;
}

// Dispensers: the nozzles and their boards at a glance, read only: for each nozzle the mode, the fuel type and the density its board reported,
// the preset (what is set on the board: the weight of the started session, or of the tickets queued), the weight dispensed so far and the status
// (online or not, free, selected, ready to start, dispensing, finished, fault). The pulses per litre are not shown. Changing a board's config is
// on Machine Config (Settings), behind the PIN.
class DispensersPage : public QWidget
{
    Q_OBJECT

public:
    DispensersPage(QSqlDatabase db, BoardBus *bus, MachineConfig *config, QWidget *parent = 0);
    ~DispensersPage();

    void reload(); // called when the page is opened

private slots:
    void refresh();

private:
    Ui::DispensersPage *ui_;
    QSqlDatabase db_;
    BoardBus *bus_;
    MachineConfig *config_;
    QTableWidget *table_;
    QLabel *link_;
    QTimer *timer_;
};

#endif
