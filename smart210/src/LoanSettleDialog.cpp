#include "LoanSettleDialog.h"

#include "Barcode.h"
#include "Decimal.h"
#include "LoanSettlement.h"
#include "Session.h"
#include "ui_LoanSettleDialog.h"
#include "Theme.h"

#include <QDateTime>
#include <QHeaderView>
#include <QLabel>
#include <QLineEdit>
#include <QPushButton>
#include <QTableWidget>

LoanSettleDialog::LoanSettleDialog(QSqlDatabase db, Session *session, const QString &loanId, const QString &customer, const QString &fuelType,
                                   qlonglong balanceX100, QWidget *parent)
    : QDialog(parent), ui_(new Ui::LoanSettleDialog), db_(db), session_(session), loanId_(loanId), fuelType_(fuelType), balanceX100_(balanceX100), kgX100_(0)
{
    ui_->setupUi(this);

    ui_->settleInfo->setText(tr("%1: %2 kg of %3 are owed. Scan the tickets the customer brings back.").arg(customer).arg(Decimal::format(balanceX100, 2)).arg(fuelType));
    scan_ = ui_->settleScanField;
    list_ = ui_->settleTable;
    list_->setColumnCount(2);
    list_->setHorizontalHeaderLabels(QStringList() << tr("Ticket") << tr("kg"));
    list_->horizontalHeader()->setStretchLastSection(true);
    list_->verticalHeader()->setVisible(false);
    list_->setEditTriggers(QAbstractItemView::NoEditTriggers);
    list_->setSelectionBehavior(QAbstractItemView::SelectRows);
    list_->setSelectionMode(QAbstractItemView::SingleSelection);
    totals_ = ui_->settleTotals;
    status_ = ui_->settleStatus;

    connect(scan_, SIGNAL(returnPressed()), this, SLOT(scanned()));
    connect(ui_->settleRemoveButton, SIGNAL(clicked()), this, SLOT(removeSelected()));
    connect(ui_->settleClearButton, SIGNAL(clicked()), this, SLOT(clearList()));
    connect(ui_->settleButton, SIGNAL(clicked()), this, SLOT(settle()));
    connect(ui_->settleCloseButton, SIGNAL(clicked()), this, SLOT(reject()));

    showTotals();
    scan_->setFocus();
}

LoanSettleDialog::~LoanSettleDialog()
{
    delete ui_;
}

void LoanSettleDialog::showStatus(const QString &message, bool ok)
{
    Theme::setState(status_, ok ? "ok" : "error");
    status_->setText(message);
}

void LoanSettleDialog::showTotals()
{
    totals_->setText(tr("%1 tickets, %2 kg, balance after: %3 kg").arg(list_->rowCount()).arg(Decimal::format(kgX100_, 2)).arg(Decimal::format(balanceX100_ - kgX100_, 2)));
    ui_->settleButton->setEnabled(list_->rowCount() > 0);
}

void LoanSettleDialog::scanned()
{
    const QString text = scan_->text().trimmed();
    scan_->clear();
    scan_->setFocus();
    if (text.isEmpty()) {
        return;
    }

    for (int row = 0; row < list_->rowCount(); ++row) {
        if (list_->item(row, 0)->text() == text) {
            showStatus(tr("This ticket is already in the list."), false);

            return;
        }
    }

    const LoanSettlement::Scan found = LoanSettlement::scan(db_, text, fuelType_);
    if (!found.ok) {
        showStatus(found.error, false);

        return;
    }

    const int row = list_->rowCount();
    list_->insertRow(row);
    list_->setItem(row, 0, new QTableWidgetItem(text));
    QTableWidgetItem *kg = new QTableWidgetItem(Decimal::format(found.kgX100, 2));
    kg->setTextAlignment(Qt::AlignRight | Qt::AlignVCenter);
    list_->setItem(row, 1, kg);
    list_->scrollToBottom();
    kgX100_ += found.kgX100;

    status_->clear();
    showTotals();
}

void LoanSettleDialog::removeSelected()
{
    const int row = list_->currentRow();
    if (row < 0) {
        return;
    }

    qlonglong kg = 0;
    Decimal::parse(list_->item(row, 1)->text(), 2, &kg);
    kgX100_ -= kg;
    list_->removeRow(row);
    showTotals();
    scan_->setFocus();
}

void LoanSettleDialog::clearList()
{
    list_->setRowCount(0);
    kgX100_ = 0;
    showTotals();
    scan_->setFocus();
}

void LoanSettleDialog::settle()
{
    QList<qlonglong> barcodes;
    for (int row = 0; row < list_->rowCount(); ++row) {
        barcodes << Barcode::toKey(list_->item(row, 0)->text());
    }

    const LoanSettlement::Result result = LoanSettlement::create(db_, loanId_, barcodes, session_->userId(), QDateTime::currentDateTime());
    if (!result.ok) {
        showStatus(result.error, false);
        scan_->setFocus();

        return;
    }

    message_ = result.balanceX100 > 1
        ? tr("Settled %1 tickets, %2 kg. Still owed: %3 kg").arg(result.tickets).arg(Decimal::format(result.kgX100, 2)).arg(Decimal::format(result.balanceX100, 2))
        : tr("Settled %1 tickets, %2 kg. The loan is paid off.").arg(result.tickets).arg(Decimal::format(result.kgX100, 2));
    accept();
}
