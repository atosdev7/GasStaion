#ifndef LOANSETTLEDIALOG_H
#define LOANSETTLEDIALOG_H

#include <QDialog>
#include <QSqlDatabase>

class QLabel;
class QLineEdit;
class QTableWidget;
class Session;

namespace Ui {
class LoanSettleDialog;
}

// The dialog of the Ticket Debt screen for one chosen debt: the tickets the customer brings back are scanned (or typed) one by one into a list,
// each checked at once (see LoanSettlement::scan: sold, of the loan's fuel type, not used); Settle surrenders them against the debt and closes the
// dialog. Close leaves without doing anything.
class LoanSettleDialog : public QDialog
{
    Q_OBJECT

public:
    LoanSettleDialog(QSqlDatabase db, Session *session, const QString &loanId, const QString &customer, const QString &fuelType, qlonglong balanceX100,
                     QWidget *parent = 0);
    ~LoanSettleDialog();

    // After a successful Settle: what to tell the cashier.
    QString message() const { return message_; }

private slots:
    void scanned();
    void removeSelected();
    void clearList();
    void settle();

private:
    Ui::LoanSettleDialog *ui_;
    void showTotals();
    void showStatus(const QString &message, bool ok);

    QSqlDatabase db_;
    Session *session_;
    QString loanId_;
    QString fuelType_;
    qlonglong balanceX100_;
    QLineEdit *scan_;
    QTableWidget *list_;
    QLabel *totals_;
    QLabel *status_;
    QString message_;
    qlonglong kgX100_;
};

#endif
