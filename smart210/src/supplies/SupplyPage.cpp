#include "SupplyPage.h"

#include "Barcode.h"
#include "BoardBus.h"
#include "Cards.h"
#include "Decimal.h"
#include "MachineConfig.h"
#include "SupplySelection.h"
#include "SupplySession.h"
#include "ui_SupplyPage.h"
#include "Theme.h"

#include <QBrush>
#include <QHeaderView>
#include <QLabel>
#include <QSqlQuery>
#include <QTableWidget>
#include <QTimer>

using namespace BoardProtocol;

namespace {

enum { ColNozzle, ColFuel, ColMode, ColStatus, ColTickets, ColKg, Columns };
enum { QueueColBarcode, QueueColFuel, QueueColKg, QueueColumns };

const int RefreshMs = 250;
const int RowHeight = 32;
const int DoneMs = 10000; // a finished fill stays as Done this long

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

struct Row {
    QString code;
    qlonglong kgX100;
};

QString kg(qlonglong x100)
{
    return Decimal::format(x100, 2);
}

}

SupplyPage::SupplyPage(QSqlDatabase db, BoardBus *bus, MachineConfig *config, SupplySelection *selection, SupplySession *session, QWidget *parent)
    : QWidget(parent), ui_(new Ui::SupplyPage), db_(db), bus_(bus), config_(config), selection_(selection), session_(session), viewed_(0), followed_(0), doneMs_(DoneMs)
{
    ui_->setupUi(this);

    table_ = ui_->supplyTable;
    table_->setRowCount(0); // a row for each nozzle that was added: refresh() makes them
    table_->setColumnCount(Columns);
    table_->setHorizontalHeaderLabels(QStringList() << tr("Nozzle") << tr("Fuel type") << tr("Mode") << tr("Status") << tr("Tickets (kg - count)") << tr("Dispensed (kg)"));
    table_->horizontalHeader()->setStretchLastSection(true);
    table_->setColumnWidth(ColNozzle, 90);
    table_->setColumnWidth(ColFuel, 200);
    table_->setColumnWidth(ColMode, 100);
    table_->setColumnWidth(ColStatus, 160);
    table_->setColumnWidth(ColTickets, 200);
    table_->setFocusPolicy(Qt::NoFocus);
    table_->verticalHeader()->setVisible(false);
    table_->verticalHeader()->setDefaultSectionSize(RowHeight);
    table_->setEditTriggers(QAbstractItemView::NoEditTriggers);
    table_->setSelectionMode(QAbstractItemView::NoSelection);
    table_->setFixedHeight(RowHeight * MachineConfig::NozzleCount + 54); // all six nozzles show, without a scroll bars
    table_->setHorizontalScrollBarPolicy(Qt::ScrollBarAlwaysOff);
    table_->setSizePolicy(QSizePolicy::Expanding, QSizePolicy::Fixed);
    table_->setVerticalScrollBarPolicy(Qt::ScrollBarAlwaysOff);

    // the tickets of the nozzle that is looked at
    queue_ = ui_->supplyQueueTable;
    queue_->setColumnCount(QueueColumns);
    queue_->setHorizontalHeaderLabels(QStringList() << tr("Barcode") << tr("Fuel type") << tr("Amount (kg)"));
    queue_->horizontalHeader()->setStretchLastSection(true);
    queue_->setColumnWidth(QueueColBarcode, 300);
    queue_->setColumnWidth(QueueColFuel, 200);
    queue_->verticalHeader()->setVisible(false);
    queue_->verticalHeader()->setDefaultSectionSize(RowHeight);
    queue_->setEditTriggers(QAbstractItemView::NoEditTriggers);
    queue_->setSelectionMode(QAbstractItemView::NoSelection);
    queue_->setFocusPolicy(Qt::NoFocus);
    queueTotal_ = ui_->supplyQueueTotal;
    current_ = ui_->supplyCurrent;
    message_ = ui_->supplyMessage;

    timer_ = new QTimer(this);
    timer_->setInterval(RefreshMs);

    connect(table_, SIGNAL(cellClicked(int,int)), this, SLOT(rowClicked(int)));
    connect(session_, SIGNAL(message(QString,bool)), this, SLOT(sessionMessage(QString,bool)));
    connect(session_, SIGNAL(recorded(int,QString)), this, SLOT(supplyRecorded(int,QString)));
    connect(timer_, SIGNAL(timeout()), this, SLOT(refresh()));
    timer_->start();

    refresh();
}

SupplyPage::~SupplyPage()
{
    delete ui_;
}

void SupplyPage::reload()
{
    message_->clear();
    refresh();
}

// The nozzle whose tickets are shown: the one that was clicked, else the one the supplier chose last at the dispenser.
int SupplyPage::viewedNozzle() const
{
    return viewed_ > 0 ? viewed_ : followed_;
}

const SupplyPage::DoneFill *SupplyPage::doneOf(int nozzle) const
{
    for (int i = done_.size() - 1; i >= 0; --i) {
        if (done_.at(i).nozzle == nozzle) {
            return &done_.at(i);
        }
    }

    return 0;
}

// The list is drawn from the boards' last answers and the saved sessions a few times a second while the page is on screen.
void SupplyPage::refresh()
{
    const qint64 now = QDateTime::currentMSecsSinceEpoch();
    for (int i = done_.size() - 1; i >= 0; --i) {
        if (done_.at(i).until <= now) {
            done_.removeAt(i);
        }
    }

    if (selection_->current() > 0) {
        followed_ = selection_->current();
    }

    const QList<MachineConfig::Nozzle> nozzles = config_->nozzles();
    const QList<MachineConfig::Fuel> fuels = config_->fuels();
    table_->setRowCount(nozzles.size());

    for (int i = 0; i < nozzles.size(); ++i) {
        const MachineConfig::Nozzle &nozzle = nozzles.at(i);
        const bool online = bus_->isOnline(nozzle.number);
        const Status status = bus_->lastStatus(nozzle.number);

        QString fuel;
        if (nozzle.seen()) {
            fuel = nozzle.fuelType;
            foreach (const MachineConfig::Fuel &f, fuels) {
                if (f.code == nozzle.fuelType) {
                    fuel += "  " + f.name;
                }
            }
        }

        // what the board says, and over it what the session says
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
                state = selection_->current() == nozzle.number ? tr("Selected") : tr("Free"); // the choice is the Smart210's, not the board's
                break;
            }
        }

        const SupplyQueue::Session session = SupplyQueue::session(db_, nozzle.number);
        const QList<SupplyQueue::Entry> entries = SupplyQueue::entries(db_, nozzle.number);
        const DoneFill *done = doneOf(nozzle.number);
        const bool running = online && (status.state == StateDispensing || status.state == StateResult);
        qlonglong ticketsKg = 0;
        int ticketCount = 0;
        foreach (const SupplyQueue::Entry &entry, entries) {
            ticketsKg += entry.kgX100;
            ++ticketCount;
        }
        QString dispensed = running ? kg(qlonglong(status.dispensedG / 10)) : QString();

        if (session.exists) {
            state = running ? tr("Working") : tr("Set");
            if (session.byCard()) {
                ticketsKg = session.presetX100;
                ticketCount = 0;
            }
        } else if (ticketCount > 0) {
            state = (online && status.state == StateArmed) ? tr("Set") : tr("Queued");
        } else if (done) {
            state = tr("Done");
            dispensed = kg(done->dispensedX100);
            foreach (const SupplyQueue::Entry &entry, done->tickets) {
                ticketsKg += entry.kgX100;
                ++ticketCount;
            }
        }

        QStringList cells;
        cells << (nozzle.name.isEmpty() ? QString::number(nozzle.number) : nozzle.name) << fuel
              << (nozzle.seen() ? (nozzle.mode == ModeManual ? tr("Manual") : tr("Auto")) : QString()) << state
              << (ticketsKg > 0 ? (ticketCount > 0 ? tr("%1 - %2").arg(kg(ticketsKg)).arg(ticketCount) : kg(ticketsKg)) : QString())
              << dispensed;

        const bool viewed = viewedNozzle() == nozzle.number;
        for (int column = 0; column < Columns; ++column) {
            QTableWidgetItem *item = table_->item(i, column);
            if (!item) {
                item = new QTableWidgetItem;
                table_->setItem(i, column, item);
            }
            item->setText(cells.at(column));
            item->setForeground(QBrush(colorOf(status.state, online)));
            item->setBackground(QBrush(viewed ? QColor("#dbeafe") : QColor("#ffffff")));
            if (column == ColTickets || column == ColKg) {
                item->setTextAlignment(Qt::AlignRight | Qt::AlignVCenter);
            }
        }
    }

    showQueue();
}

// The tickets of the nozzle that is looked at: the queue, or for a few seconds after the fill what it was.
void SupplyPage::showQueue()
{
    const int number = viewedNozzle();
    QList<Row> rows;
    QString total;
    QString heading = tr("Click a nozzle to see its tickets.");

    if (number > 0) {
        const MachineConfig::Nozzle nozzle = config_->nozzle(number);
        const SupplyQueue::Session session = SupplyQueue::session(db_, number);
        const DoneFill *done = doneOf(number);
        heading = tr("Tickets of nozzle %1").arg(number);

        if (session.byCard()) {
            Row row = {tr("Card: %1").arg(Cards::findById(db_, session.cardId).holderName), session.presetX100};
            rows.append(row);
            total = tr("A fill with a card: %1 kg.").arg(kg(session.presetX100));
        } else {
            const QList<SupplyQueue::Entry> entries = SupplyQueue::entries(db_, number);
            foreach (const SupplyQueue::Entry &entry, entries) {
                Row row = {Barcode::fromKey(entry.barcode), entry.kgX100};
                rows.append(row);
            }
            if (session.exists) {
                total = tr("Started: %1 kg. The tickets are locked until the fill ends.").arg(kg(SupplyQueue::totalX100(db_, number)));
            } else if (!entries.isEmpty()) {
                total = tr("%1 tickets, together %2 kg.").arg(entries.size()).arg(kg(SupplyQueue::totalX100(db_, number)));
            } else if (done) {
                foreach (const SupplyQueue::Entry &entry, done->tickets) {
                    Row row = {Barcode::fromKey(entry.barcode), entry.kgX100};
                    rows.append(row);
                }
                if (!done->card.isEmpty()) {
                    Row row = {tr("Card: %1").arg(done->card), done->dispensedX100};
                    rows.append(row);
                }
                total = tr("Done: %1 kg dispensed.").arg(kg(done->dispensedX100));
            } else {
                total = tr("No tickets are queued on this nozzle.");
            }
        }

        QString signature = QString::number(number) + "|" + total;
        foreach (const Row &row, rows) {
            signature += QString("|%1|%2").arg(row.code).arg(row.kgX100);
        }
        if (signature != shown_) {
            shown_ = signature;
            queue_->setRowCount(rows.size());
            for (int i = 0; i < rows.size(); ++i) {
                QTableWidgetItem *weight = new QTableWidgetItem(kg(rows.at(i).kgX100));
                weight->setTextAlignment(Qt::AlignRight | Qt::AlignVCenter);
                queue_->setItem(i, QueueColBarcode, new QTableWidgetItem(rows.at(i).code));
                queue_->setItem(i, QueueColFuel, new QTableWidgetItem(nozzle.fuelType));
                queue_->setItem(i, QueueColKg, weight);
            }
        }
    } else if (!shown_.isEmpty()) {
        shown_.clear();
        queue_->setRowCount(0);
    }

    current_->setText(heading);
    queueTotal_->setText(total);
}

void SupplyPage::showMessage(const QString &text, bool good)
{
    Theme::setState(message_, good ? "ok" : "error");
    message_->setText(text);
}

void SupplyPage::sessionMessage(const QString &text, bool good)
{
    showMessage(text, good);
}

// A click only looks at the nozzle; nothing is sent to the board.
void SupplyPage::rowClicked(int row)
{
    const QList<MachineConfig::Nozzle> nozzles = config_->nozzles();
    viewed_ = row >= 0 && row < nozzles.size() ? nozzles.at(row).number : 0; // the rows are the nozzles that were added, by number
    refresh();
}

// A fill ended and was recorded: it stays as Done for a while.
void SupplyPage::supplyRecorded(int nozzle, const QString &supplementId)
{
    DoneFill fill;
    fill.nozzle = nozzle;
    fill.until = QDateTime::currentMSecsSinceEpoch() + doneMs_;

    QSqlQuery supplement(db_);
    supplement.prepare("SELECT s.out_amount_kg, s.card_id, COALESCE(c.holder_name, '') FROM supplements s LEFT JOIN cards c ON c.id = s.card_id WHERE s.id = ?");
    supplement.addBindValue(supplementId);
    if (supplement.exec() && supplement.next()) {
        fill.dispensedX100 = supplement.value(0).toLongLong();
        if (!supplement.value(1).isNull()) {
            fill.card = supplement.value(2).toString();
        }
    }

    QSqlQuery tickets(db_);
    tickets.prepare("SELECT barcode, amount_kg FROM tickets WHERE supplement_id = ? ORDER BY barcode");
    tickets.addBindValue(supplementId);
    if (tickets.exec()) {
        while (tickets.next()) {
            SupplyQueue::Entry entry;
            entry.barcode = tickets.value(0).toLongLong();
            entry.kgX100 = tickets.value(1).toLongLong();
            fill.tickets.append(entry);
        }
    }

    done_.append(fill);
    refresh();
}
