#ifndef CARDSUPPLYDIALOG_H
#define CARDSUPPLYDIALOG_H

#include <QDialog>
#include <QSqlDatabase>

class MachineConfig;
class QComboBox;
class QLabel;
class QLineEdit;
class QPushButton;
class Session;
class SupplySession;

// A fill paid with a card, without a ticket: the customer's card is read on the Cards screen, this dialog does the rest.
//   1. the cashier chooses the nozzle (an automatic one of the card's fuel type) and types the PIN the customer gave;
//   2. Show balance: the PIN is checked (a wrong one counts, the 5th locks the card), the balance that may be used is shown here and on the display of the dispenser
//      (SHOW_BALANCE), and the customer confirms it;
//   3. the cashier types the kg and presses Set amount: the kg are held on the card and go to the dispenser as its preset (SupplySession::startCardFill);
//   4. the supplier takes the gun up and fills; the card is charged what really came out when the result is recorded.
// A card that has a fill open (set, not started) shows it with a button to cancel it instead.
class CardSupplyDialog : public QDialog
{
    Q_OBJECT

public:
    CardSupplyDialog(QSqlDatabase db, Session *session, MachineConfig *config, SupplySession *supply, const QString &cardId, QWidget *parent = 0);

    // What happened, for the screen that opened the dialog: set when a fill was set or cancelled.
    QString message() const { return result_; }

private slots:
    void showBalance();
    void setAmount();
    void cancelOpenFill();
    void nozzleChanged();
    void reject();

private:
    int nozzle() const;
    void refresh();
    void say(const QString &text, bool good);

    QSqlDatabase db_;
    Session *session_;
    MachineConfig *config_;
    SupplySession *supply_;
    QString cardId_;
    bool pinOk_;
    int shownOn_;     // the nozzle whose dispenser was told to show the balance; 0 when none
    QString result_;

    QLabel *info_;
    QComboBox *nozzles_;
    QLineEdit *pin_;
    QPushButton *show_;
    QLineEdit *kg_;
    QPushButton *set_;
    QPushButton *cancelOpen_;
    QLabel *status_;
};

#endif
