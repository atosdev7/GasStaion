#include "DispensersPage.h"

#include "BoardBus.h"
#include "Decimal.h"
#include "MachineConfig.h"
#include "SupplyQueue.h"
#include "ui_DispensersPage.h"
#include "Theme.h"

#include <QBrush>
#include <QHeaderView>
#include <QLabel>
#include <QTableWidget>
#include <QTimer>

using namespace BoardProtocol;

namespace {

enum { ColNozzle, ColMode, ColFuel, ColDensity, ColPreset, ColKg, ColStatus, Columns };

const int RefreshMs = 500;

QColor colorOf(int state, bool online)
{
    if (!online) {
        return QColor("#6b7280");
    }
    switch (state) {
    case StateSelected:
    case StateArmed:
        return QColor("#1d4ed8");
    case StateDispensing:
        return QColor("#15803d");
    case StateResult:
        return QColor("#7e22ce");
    case StateFault:
        return QColor("#b91c1c");
    }

    return QColor("#111827");
}

}

DispensersPage::DispensersPage(QSqlDatabase db, BoardBus *bus, MachineConfig *config, QWidget *parent)
    : QWidget(parent), ui_(new Ui::DispensersPage), db_(db), bus_(bus), config_(config)
{
    ui_->setupUi(this);

    link_ = ui_->dispensersLink;

    table_ = ui_->dispensersTable;
    table_->setRowCount(0); // a row for each nozzle that was added: refresh() makes them
    table_->setColumnCount(Columns);
    table_->setHorizontalHeaderLabels(QStringList() << tr("Nozzle") << tr("Mode") << tr("Fuel type") << tr("Density") << tr("Preset (kg)")
                                                    << tr("Dispensed (kg)") << tr("Status"));
    table_->horizontalHeader()->setStretchLastSection(true);
    table_->setColumnWidth(ColNozzle, 90);
    table_->setColumnWidth(ColMode, 100);
    table_->setColumnWidth(ColFuel, 110);
    table_->setColumnWidth(ColDensity, 110);
    table_->setColumnWidth(ColPreset, 130);
    table_->setColumnWidth(ColKg, 175);
    table_->setFocusPolicy(Qt::NoFocus);
    table_->verticalHeader()->setVisible(false);
    table_->verticalHeader()->setDefaultSectionSize(48);
    table_->setEditTriggers(QAbstractItemView::NoEditTriggers);
    table_->setSelectionMode(QAbstractItemView::NoSelection);
    table_->setVerticalScrollBarPolicy(Qt::ScrollBarAlwaysOff);

    timer_ = new QTimer(this);
    timer_->setInterval(RefreshMs);
    connect(timer_, SIGNAL(timeout()), this, SLOT(refresh()));
    timer_->start();

    refresh();
}

DispensersPage::~DispensersPage()
{
    delete ui_;
}

void DispensersPage::reload()
{
    refresh();
}

void DispensersPage::refresh()
{
    link_->setText(bus_->isConnected() ? tr("The converter is connected.") : tr("The converter is not connected: the boards cannot be reached."));
    Theme::setState(link_, bus_->isConnected() ? "" : "warning");

    const QList<MachineConfig::Nozzle> nozzles = config_->nozzles();
    table_->setRowCount(nozzles.size());
    for (int i = 0; i < nozzles.size(); ++i) {
        const MachineConfig::Nozzle &nozzle = nozzles.at(i);
        const bool online = bus_->isOnline(nozzle.number);
        const Status status = bus_->lastStatus(nozzle.number);

        QString state;
        if (!bus_->isConnected()) {
            state = tr("No converter");
        } else if (nozzle.mode == ModeManual && !bus_->isPolled(nozzle.number)) {
            state = tr("Not polled"); // a nozzle in manual mode: nothing is asked of its board, the cashier types its supplies
        } else if (!online) {
            state = tr("No answer");
        } else if (!nozzle.seen()) {
            state = tr("Config not set");
        } else {
            switch (status.state) {
            case StateSelected:
                state = tr("Selected");
                break;
            case StateArmed:
                state = tr("Ready to start");
                break;
            case StateDispensing:
                state = tr("Dispensing");
                break;
            case StateResult:
                state = tr("Finished");
                break;
            case StateFault:
                state = tr("Fault");
                break;
            default:
                state = tr("Free");
                break;
            }
        }

        // what is set on the board: the weight of the session that runs, else of the tickets that wait (a manual nozzle's amount is typed at the
        // dispenser and not known here)
        qlonglong preset = online ? qlonglong(status.presetG / 10) : 0;
        if (!online && nozzle.mode != ModeManual) {
            const SupplyQueue::Session session = SupplyQueue::session(db_, nozzle.number);
            preset = session.exists ? session.presetX100 : SupplyQueue::totalX100(db_, nozzle.number);
        }

        const bool running = online && (status.state == StateDispensing || status.state == StateResult);
        QStringList cells;
        cells << QString::number(nozzle.number);
        if (nozzle.seen()) {
            cells << (nozzle.mode == ModeManual ? tr("Manual") : tr("Auto")) << nozzle.fuelType
                  << (nozzle.density < 0 ? QString() : Decimal::format(nozzle.density, 4));
        } else {
            cells << QString() << QString() << QString();
        }
        cells << (preset > 0 ? Decimal::format(preset, 2) : QString()) << (running ? Decimal::format(qlonglong(status.dispensedG / 10), 2) : QString()) << state;

        for (int column = 0; column < Columns; ++column) {
            QTableWidgetItem *item = table_->item(i, column);
            if (!item) {
                item = new QTableWidgetItem;
                table_->setItem(i, column, item);
            }
            item->setText(cells.at(column));
            item->setForeground(QBrush(colorOf(status.state, online)));
            if (column == ColKg || column == ColPreset || column == ColDensity) {
                item->setTextAlignment(Qt::AlignRight | Qt::AlignVCenter);
            }
        }
    }
}
