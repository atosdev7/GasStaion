#ifndef CARDPAGE_H
#define CARDPAGE_H

#include "Cards.h"

#include <QSqlDatabase>
#include <QWidget>

class BoardBus;
class CardReader;
class MachineConfig;
class QComboBox;
class QLabel;
class QLineEdit;
class QPushButton;
class QStackedWidget;
class QTimer;
class Session;
class SupplySelection;
class SupplySession;
class EnterChain;

namespace Ui {
class CardPage;
}

// The Cards screen: the RFID cards of the customers.
//
//   The cashier puts a card on the reader (the id is only read, never typed) and the screen looks it up; History shows the latest 10 top-ups and
//   the latest 10 fills of a known card:
//     - a card that is not known is registered here: the customer's name (only a name), the fuel type and the PIN the customer gives (twice);
//     - a known card shows its holder, its fuel type, its balance and its status, and offers
//     Choosing an action puts the cursor in its first field; Enter moves on to the next field (PIN last), Enter on the button presses it, and Top Up, Start fill
//     and Save fill ask "OK / Cancel" (ConfirmDialog) before they do it.
//     The three actions are three buttons on the left (Top Up, Fill Auto, Fill Manual); the one pressed shows its page on the right:
//         Top up      kg at a price per kg (the fuel's price is filled in, it can be changed), paid in cash: a sale of type "card"
//         Fill        the cashier types the PIN the customer said, the kg to supply and chooses the nozzle (of the card's fuel type); Start
//                     checks the PIN (5 wrong ones lock the card), then the preset goes to the dispenser like the tickets of a supply do
//                     (SupplySession::startCard). When the fill ends the card's balance falls by what really came out.   (Fill Auto: the nozzles in auto mode)
//         Fill Manual the supplier filled at a manual-mode nozzle: the cashier types the PIN, chooses the nozzle, types the kg that came out (the litres
//                     follow from the density of the nozzle) and saves (SupplySession::recordManualCard): the card's balance falls by the kg.
//   A locked card is unlocked, and a lost card blocked or unblocked, on the web app only.
class CardPage : public QWidget
{
    Q_OBJECT

public:
    CardPage(QSqlDatabase db, Session *session, BoardBus *bus, MachineConfig *config, SupplySelection *selection, SupplySession *supply,
             CardReader *reader, QWidget *parent = 0);
    ~CardPage();

    // Called whenever the page is opened: nothing is shown until a card is read.
    void reload();

signals:
    void saved(); // a card, a top-up or a supply was written: what waits for the upload has changed

public slots:
    // A card id as the reader (or the cashier) gave it: the card is looked up and shown.
    void showCard(const QString &id);

private slots:
    void idEntered();
    void historyClicked();
    void registerClicked();
    void topUpClicked();
    void topUpEdited();
    void topUpTotalEdited();
    void startClicked();
    void actionClicked();
    void manualKgEdited();
    void manualSaveClicked();
    void nozzleChosen();
    void refreshNozzles();
    void readerChanged();
    void supplyRecorded(int nozzle, const QString &supplementId);
    void supplyMessage(const QString &text, bool good);

private:
    Ui::CardPage *ui_;
    void showPanel(int index);
    void showInfo(const Cards::Card &card);
    void fillNozzles();
    void showAction(int page);
    qlonglong manualDensity() const;
    void showStatus(const QString &message, bool ok);
    QString stateText(int nozzle) const;

    QSqlDatabase db_;
    Session *session_;
    BoardBus *bus_;
    MachineConfig *config_;
    SupplySelection *selection_;
    SupplySession *supply_;
    CardReader *reader_;

    QLineEdit *idField_;
    QLabel *readerStatus_;
    QLabel *holder_;
    QLabel *fuel_;
    QLabel *balance_;
    QLabel *state_;
    QStackedWidget *panels_;
    QLabel *message_;
    QTimer *timer_;

    QLineEdit *registerName_;
    QLineEdit *registerPhone_;
    QComboBox *registerFuel_;
    QLineEdit *registerPin_;
    QLineEdit *registerPinAgain_;

    QLineEdit *topUpKg_;
    QLineEdit *topUpPrice_;
    QLineEdit *topUpTotal_;

    QLineEdit *fillPin_;
    QLineEdit *fillKg_;
    QComboBox *fillNozzle_;
    QLabel *fillNozzleState_;
    QPushButton *start_;

    QStackedWidget *actionPages_;    // none / Top Up / Fill Auto / Fill Manual: the page of the button the cashier pressed
    QPushButton *topUpAction_;
    QPushButton *fillAutoAction_;
    QPushButton *fillManualAction_;
    QLineEdit *manualPin_;
    QComboBox *manualNozzle_;
    QLineEdit *manualKg_;
    QLineEdit *manualLiters_;

    // keyboard entry of each action: Enter moves on to the next field (the PIN is the last one) and Enter on the button presses it
    EnterChain *topUpChain_;
    EnterChain *fillAutoChain_;
    EnterChain *manualChain_;

    QString cardId_;      // the card on screen, empty when none
    QString uid_;         // the id that was read (also for a card not registered yet)
    int fillNozzleNumber_; // the nozzle a fill of this screen runs on, 0 when none
    bool priceTouched_;
    bool totalTyped_;   // the cashier typed the total payment last: it counts as typed, even when it is not kg x price
};

#endif
