#include "NozzleDialog.h"

#include "BoardBus.h"
#include "BoardProtocol.h"
#include "Decimal.h"
#include "MachineConfig.h"
#include "SupplyQueue.h"
#include "ui_NozzleDialog.h"

#include <QLabel>
#include <QListWidget>
#include <QPushButton>
#include <QSqlQuery>
#include <QTimer>

using namespace BoardProtocol;

NozzleDialog::NozzleDialog(QSqlDatabase db, BoardBus *bus, MachineConfig *config, const QString &fuelType, qlonglong kgX100, QWidget *parent)
    : QDialog(parent), ui_(new Ui::NozzleDialog), db_(db), bus_(bus), config_(config), fuelType_(fuelType)
{
    ui_->setupUi(this);

    QString fuel = fuelType;
    QSqlQuery name(db_);
    name.prepare("SELECT name FROM fuel_types WHERE fuel_type = ?");
    name.addBindValue(fuelType);
    if (name.exec() && name.next()) {
        fuel += "  " + name.value(0).toString();
    }
    ui_->nzIntro->setText(tr("%1 kg of %2 are to be supplied. Choose the nozzle and press OK.").arg(Decimal::format(kgX100, 2)).arg(fuel));

    connect(ui_->nzList, SIGNAL(itemSelectionChanged()), this, SLOT(selectionChanged()));
    connect(ui_->nzOkButton, SIGNAL(clicked()), this, SLOT(accept()));
    connect(ui_->nzCancelButton, SIGNAL(clicked()), this, SLOT(reject()));

    // the boards change while the question is open: the list follows them
    timer_ = new QTimer(this);
    timer_->setInterval(1000);
    connect(timer_, SIGNAL(timeout()), this, SLOT(refresh()));
    timer_->start();
    refresh();
}

NozzleDialog::~NozzleDialog()
{
    delete ui_;
}

int NozzleDialog::nozzle() const
{
    const QListWidgetItem *item = ui_->nzList->currentItem();

    return item && (item->flags() & Qt::ItemIsEnabled) ? item->data(Qt::UserRole).toInt() : 0;
}

bool NozzleDialog::isFree(QSqlDatabase db, BoardBus *bus, int nozzle)
{
    if (!bus->isConnected() || !bus->isOnline(nozzle) || SupplyQueue::session(db, nozzle).exists) {
        return false;
    }
    const int state = bus->lastStatus(nozzle).state;

    return state == StateIdle; // a nozzle that has a preset (SET) or a fill is not free
}

QString NozzleDialog::stateText(QSqlDatabase db, BoardBus *bus, int nozzle)
{
    if (!bus->isConnected()) {
        return tr("No converter");
    }
    if (!bus->isOnline(nozzle)) {
        return tr("No answer");
    }
    if (SupplyQueue::session(db, nozzle).exists) {
        return tr("A fill is running");
    }
    switch (bus->lastStatus(nozzle).state) {
    case StateSelected:
        return tr("Selected");
    case StateArmed:
        return tr("Ready to start");
    case StateDispensing:
        return tr("Dispensing");
    case StateResult:
        return tr("Result waiting");
    case StateFault:
        return tr("Fault");
    }

    return tr("Free: choose it");
}

void NozzleDialog::refresh()
{
    const int chosen = nozzle();
    QListWidget *list = ui_->nzList;

    list->blockSignals(true);
    list->clear();
    const QList<MachineConfig::Nozzle> nozzles = config_->nozzles();
    for (int i = 0; i < nozzles.size(); ++i) {
        const MachineConfig::Nozzle &info = nozzles.at(i);
        if (!info.seen() || info.fuelType != fuelType_ || info.mode != ModeAuto) {
            continue;
        }
        QListWidgetItem *item = new QListWidgetItem(tr("Nozzle %1:  %2").arg(info.number).arg(stateText(db_, bus_, info.number)), list);
        item->setData(Qt::UserRole, info.number);
        if (!isFree(db_, bus_, info.number)) {
            item->setFlags(item->flags() & ~(Qt::ItemIsEnabled | Qt::ItemIsSelectable));
        } else if (info.number == chosen) {
            list->setCurrentItem(item);
        }
    }
    list->blockSignals(false);

    ui_->nzStatus->setText(list->count() == 0 ? tr("No nozzle of this fuel type works in auto mode.") : QString());
    selectionChanged();
}

void NozzleDialog::selectionChanged()
{
    ui_->nzOkButton->setEnabled(nozzle() > 0);
}
