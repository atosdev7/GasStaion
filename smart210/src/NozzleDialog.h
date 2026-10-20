#ifndef NOZZLEDIALOG_H
#define NOZZLEDIALOG_H

#include <QDialog>
#include <QSqlDatabase>

class BoardBus;
class MachineConfig;
class QTimer;

namespace Ui {
class NozzleDialog;
}

// The question after a spot sale that is set directly: which nozzle gets the weight as its preset. It lists the nozzles of the sale's fuel type
// that work in auto mode, with what each one is doing now (the list follows the boards); the ones that are busy cannot be chosen. OK is the
// chosen nozzle (nozzle()), Cancel leaves the sale saved without a nozzle.
class NozzleDialog : public QDialog
{
    Q_OBJECT

public:
    NozzleDialog(QSqlDatabase db, BoardBus *bus, MachineConfig *config, const QString &fuelType, qlonglong kgX100, QWidget *parent = 0);
    ~NozzleDialog();

    int nozzle() const; // 0 when none is chosen

    // What a nozzle is doing, for the lists of the screens that work with nozzles, and whether a fill can be set on it now.
    static QString stateText(QSqlDatabase db, BoardBus *bus, int nozzle);
    static bool isFree(QSqlDatabase db, BoardBus *bus, int nozzle);

private slots:
    void refresh();
    void selectionChanged();

private:
    Ui::NozzleDialog *ui_;
    QSqlDatabase db_;
    BoardBus *bus_;
    MachineConfig *config_;
    QString fuelType_;
    QTimer *timer_;
};

#endif
