#include "RemainderTicketsPage.h"

#include "Decimal.h"
#include "SupplyRecord.h"
#include "TicketStatus.h"
#include "ui_RemainderTicketsPage.h"
#include "Theme.h"

#include <QBrush>
#include <QHeaderView>
#include <QLabel>
#include <QLineEdit>
#include <QPushButton>
#include <QTableWidget>

namespace {

enum { ColTime, ColNozzle, ColFuel, ColKg, ColTicket, ColState, ColComment, Columns };

}

RemainderTicketsPage::RemainderTicketsPage(QSqlDatabase db, QWidget *parent)
    : QWidget(parent), ui_(new Ui::RemainderTicketsPage), db_(db)
{
    ui_->setupUi(this);

    table_ = ui_->remainderTable;
    table_->setColumnCount(Columns);
    table_->setHorizontalHeaderLabels(QStringList() << tr("Time") << tr("Nozzle") << tr("Fuel") << tr("kg") << tr("Ticket") << tr("State") << tr("Comment"));
    table_->setColumnWidth(ColTime, 170);
    table_->setColumnWidth(ColNozzle, 100);
    table_->setColumnWidth(ColFuel, 80);
    table_->setColumnWidth(ColKg, 110);
    table_->setColumnWidth(ColTicket, 250);
    table_->horizontalHeader()->setStretchLastSection(true);
    table_->verticalHeader()->setVisible(false);
    table_->setEditTriggers(QAbstractItemView::NoEditTriggers);
    table_->setSelectionBehavior(QAbstractItemView::SelectRows);
    table_->setSelectionMode(QAbstractItemView::SingleSelection);
    table_->setFocusPolicy(Qt::NoFocus);

    print_ = ui_->remainderPrintButton;
    comment_ = ui_->remainderCommentField;
    saveComment_ = ui_->remainderSaveCommentButton;
    status_ = ui_->remainderStatus;

    connect(table_, SIGNAL(itemSelectionChanged()), this, SLOT(selectionChanged()));
    connect(print_, SIGNAL(clicked()), this, SLOT(printClicked()));
    connect(saveComment_, SIGNAL(clicked()), this, SLOT(saveCommentClicked()));

    selectionChanged();
}

RemainderTicketsPage::~RemainderTicketsPage()
{
    delete ui_;
}

void RemainderTicketsPage::reload()
{
    QString keep;
    if (table_->currentRow() >= 0 && !table_->selectedItems().isEmpty()) {
        keep = table_->item(table_->currentRow(), ColTicket)->text();
    }

    table_->setRowCount(0);
    ids_.clear();
    const QList<SupplyRecord::RemainderRow> rows = SupplyRecord::remainders(db_);
    table_->setRowCount(rows.size());
    for (int i = 0; i < rows.size(); ++i) {
        const SupplyRecord::RemainderRow &r = rows.at(i);
        QString state;
        QColor color("#15803d");
        if (r.ticketStatus != TicketStatus::Sale) {
            state = tr("Used");
            color = QColor("#6b7280");
        } else if (r.printedAt.isEmpty()) {
            state = tr("Not printed");
            color = QColor("#b91c1c");
        } else {
            state = tr("Printed");
        }

        QStringList cells;
        ids_ << r.id;
        cells << r.time.mid(0, 16) << r.nozzle << r.fuelType << Decimal::format(r.kgX100, 2) << r.barcode << state << r.comment;
        for (int column = 0; column < Columns; ++column) {
            QTableWidgetItem *item = new QTableWidgetItem(cells.at(column));
            if (column == ColKg) {
                item->setTextAlignment(Qt::AlignRight | Qt::AlignVCenter);
            }
            if (column == ColState) {
                item->setForeground(QBrush(color));
            }
            table_->setItem(i, column, item);
        }
        if (r.barcode == keep) {
            table_->selectRow(i);
        }
    }
    status_->clear();
    selectionChanged();
}

void RemainderTicketsPage::showResult(const QString &text, bool good)
{
    Theme::setState(status_, good ? "ok" : "error");
    status_->setText(text);
    reload();
    Theme::setState(status_, good ? "ok" : "error");
    status_->setText(text);
}

void RemainderTicketsPage::selectionChanged()
{
    const bool chosen = table_->currentRow() >= 0 && !table_->selectedItems().isEmpty();
    print_->setEnabled(chosen);
    saveComment_->setEnabled(chosen);
    comment_->setEnabled(chosen);
    const QTableWidgetItem *item = chosen ? table_->item(table_->currentRow(), ColComment) : 0; // the row may be half built while the list is refilled
    comment_->setText(item ? item->text() : QString());
}

void RemainderTicketsPage::saveCommentClicked()
{
    const int row = table_->currentRow();
    if (row < 0 || table_->selectedItems().isEmpty() || row >= ids_.size()) {
        return;
    }
    if (SupplyRecord::setRemainderComment(db_, ids_.at(row), comment_->text())) {
        reload();
        Theme::setState(status_, "ok");
        status_->setText(tr("The comment was saved. It will be uploaded."));
        emit saved();
    } else {
        Theme::setState(status_, "ok");
        status_->setText(tr("The comment is the same: nothing changed."));
    }
}

void RemainderTicketsPage::printClicked()
{
    if (table_->currentRow() < 0 || table_->selectedItems().isEmpty()) {
        return;
    }
    status_->clear();
    emit printRequested(table_->item(table_->currentRow(), ColTicket)->text());
}
