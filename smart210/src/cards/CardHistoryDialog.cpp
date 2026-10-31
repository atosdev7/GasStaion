#include "CardHistoryDialog.h"

#include "CardLedger.h"
#include "Decimal.h"
#include "ui_CardHistoryDialog.h"

#include <QHeaderView>
#include <QLabel>
#include <QPushButton>
#include <QTableWidget>

const int CardHistoryDialog::Latest;

namespace {

// A number cell: x100 with two decimals, right aligned; `sign` puts a + before a positive one; an x100 below zero with `blankNegative` is no value.
QTableWidgetItem *numberItem(qlonglong x100, bool sign, bool blankNegative = false)
{
    QString text = (blankNegative && x100 < 0) ? QString() : Decimal::format(x100, 2);
    if (sign && x100 > 0) {
        text = "+" + text;
    }
    QTableWidgetItem *item = new QTableWidgetItem(text);
    item->setTextAlignment(Qt::AlignRight | Qt::AlignVCenter);

    return item;
}

}

CardHistoryDialog::CardHistoryDialog(QSqlDatabase db, const QString &cardId, const QString &holder, QWidget *parent)
    : QDialog(parent), ui_(new Ui::CardHistoryDialog)
{
    ui_->setupUi(this);
    ui_->historyTitle->setText(tr("History of the card of %1").arg(holder));

    QTableWidget *table = ui_->historyTable;
    QStringList headers;
    headers << tr("Time") << tr("Entry") << tr("kg in / out") << tr("Balance after (kg)") << tr("Price per kg") << tr("Paid");
    table->setColumnCount(headers.size());
    table->setHorizontalHeaderLabels(headers);
    table->horizontalHeader()->setStretchLastSection(true);
    table->verticalHeader()->setVisible(false);
    table->setEditTriggers(QAbstractItemView::NoEditTriggers);
    table->setSelectionMode(QAbstractItemView::NoSelection);
    table->setFocusPolicy(Qt::NoFocus);
    table->setColumnWidth(0, 190);
    table->setColumnWidth(1, 200);

    const QList<CardLedger::Entry> entries = CardLedger::entries(db, cardId, Latest);
    for (int i = 0; i < entries.size(); ++i) {
        const CardLedger::Entry &entry = entries.at(i);
        QString kind = entry.type == "fill" ? tr("Fill") : (entry.type == "reversal" ? tr("Reversal (deleted)") : (entry.type == "refund" ? tr("Refund (unused part)") : tr("Top-up")));
        if (entry.provisional) {
            kind += " " + tr("(not confirmed yet)");
        }

        table->insertRow(i);
        table->setItem(i, 0, new QTableWidgetItem(entry.when));
        table->setItem(i, 1, new QTableWidgetItem(kind));
        table->setItem(i, 2, numberItem(entry.kgX100, true));
        table->setItem(i, 3, numberItem(entry.balanceX100, false));
        table->setItem(i, 4, numberItem(entry.priceX100, false, true));     // none for a fill
        table->setItem(i, 5, numberItem(entry.totalX100, false, true));
    }
    ui_->historyBalance->setText(entries.isEmpty() ? tr("No entries yet.") : tr("Balance: %1 kg").arg(Decimal::format(entries.first().balanceX100, 2)));

    connect(ui_->historyCloseButton, SIGNAL(clicked()), this, SLOT(accept()));
}

CardHistoryDialog::~CardHistoryDialog()
{
    delete ui_;
}
