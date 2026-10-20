#include "LoanSettlePage.h"

#include "Decimal.h"
#include "LoanSettleDialog.h"
#include "LoanSettlement.h"
#include "Session.h"
#include "ui_LoanSettlePage.h"
#include "Theme.h"

#include <QHeaderView>
#include <QLabel>
#include <QPushButton>
#include <QTableWidget>

LoanSettlePage::LoanSettlePage(QSqlDatabase db, Session *session, QWidget *parent)
    : QWidget(parent), ui_(new Ui::LoanSettlePage), db_(db), session_(session)
{
    ui_->setupUi(this);

    loans_ = ui_->loansTable;
    loans_->setColumnCount(6);
    loans_->setHorizontalHeaderLabels(QStringList() << tr("Customer") << tr("Fuel") << tr("Loaned at") << tr("Loaned kg") << tr("Settled kg") << tr("Balance kg"));
    loans_->horizontalHeader()->setStretchLastSection(true);
    loans_->verticalHeader()->setVisible(false);
    loans_->setEditTriggers(QAbstractItemView::NoEditTriggers);
    loans_->setSelectionBehavior(QAbstractItemView::SelectRows);
    loans_->setSelectionMode(QAbstractItemView::SingleSelection);
    settle_ = ui_->loanSettleButton;
    status_ = ui_->loanStatus;

    connect(loans_, SIGNAL(itemSelectionChanged()), this, SLOT(loanSelected()));
    connect(settle_, SIGNAL(clicked()), this, SLOT(settle()));
}

LoanSettlePage::~LoanSettlePage()
{
    delete ui_;
}

QString LoanSettlePage::selectedLoanId() const
{
    if (loans_->currentRow() < 0 || loans_->selectedItems().isEmpty()) {
        return QString();
    }

    return loans_->item(loans_->currentRow(), 0)->data(Qt::UserRole).toString();
}

void LoanSettlePage::reload()
{
    loans_->blockSignals(true);
    loans_->setRowCount(0);
    int row = 0;
    foreach (const LoanSettlement::Loan &loan, LoanSettlement::openLoans(db_)) {
        loans_->insertRow(row);

        QStringList cells;
        cells << loan.customer << loan.fuelType << loan.loanedAt << Decimal::format(loan.amountX100, 2)
              << Decimal::format(loan.settledX100, 2) << Decimal::format(loan.balanceX100(), 2);
        for (int column = 0; column < cells.size(); ++column) {
            QTableWidgetItem *item = new QTableWidgetItem(cells.at(column));
            if (column == 0) {
                item->setData(Qt::UserRole, loan.id);
            }
            if (column >= 3) {
                item->setTextAlignment(Qt::AlignRight | Qt::AlignVCenter);
            }
            loans_->setItem(row, column, item);
        }
        ++row;
    }
    loans_->resizeColumnsToContents();
    loans_->blockSignals(false);

    settle_->setEnabled(false);
    status_->setText(loans_->rowCount() == 0 ? tr("There are no open ticket loans.") : QString());
    Theme::setState(status_, "");
}

void LoanSettlePage::loanSelected()
{
    settle_->setEnabled(!selectedLoanId().isEmpty());
}

// The dialog of the chosen debt; after a settlement the list shows the new balance (or the debt is gone).
void LoanSettlePage::settle()
{
    const QString id = selectedLoanId();
    if (id.isEmpty()) {
        return;
    }

    const int row = loans_->currentRow();
    qlonglong balance = 0;
    Decimal::parse(loans_->item(row, 5)->text(), 2, &balance);
    LoanSettleDialog dialog(db_, session_, id, loans_->item(row, 0)->text(), loans_->item(row, 1)->text(), balance, this);
    if (dialog.exec() != QDialog::Accepted) {
        return;
    }

    reload();
    Theme::setState(status_, "ok");
    status_->setText(dialog.message());
    emit settled();
}
