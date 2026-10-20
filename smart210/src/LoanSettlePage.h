#ifndef LOANSETTLEPAGE_H
#define LOANSETTLEPAGE_H

#include <QSqlDatabase>
#include <QWidget>

class QLabel;
class QPushButton;
class QTableWidget;
class Session;

namespace Ui {
class LoanSettlePage;
}

// The ticket debt screen: the open loans (who owes how much). Choose one and press Settle: a dialog (LoanSettleDialog) takes the tickets the
// customer brings back and surrenders them against that debt. See LoanSettlement.
class LoanSettlePage : public QWidget
{
    Q_OBJECT

public:
    LoanSettlePage(QSqlDatabase db, Session *session, QWidget *parent = 0);
    ~LoanSettlePage();

    // Called whenever the page is opened (and after a settlement): the open loans as they stand now.
    void reload();

signals:
    void settled(); // tickets were surrendered: what waits for the upload has changed

private slots:
    void loanSelected();
    void settle();

private:
    Ui::LoanSettlePage *ui_;
    QString selectedLoanId() const;

    QSqlDatabase db_;
    Session *session_;
    QTableWidget *loans_;
    QPushButton *settle_;
    QLabel *status_;
};

#endif
