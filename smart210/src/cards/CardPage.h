#ifndef CARDPAGE_H
#define CARDPAGE_H

#include "Cards.h"

#include <QSqlDatabase>
#include <QWidget>

class CardReader;
class EnterChain;
class BoardBus;
class MachineConfig;
class PrintService;
class QComboBox;
class QGroupBox;
class QLabel;
class QLineEdit;
class QPushButton;
class QStackedWidget;
class Session;
class Settings;
class SupplySelection;
class SupplySession;

namespace Ui {
class CardPage;
}

// The Cards screen: the RFID cards of the customers.
//
//   The cashier puts a card on the reader (on a PC the id is typed) and the screen looks it up; History shows the latest 10 top-ups and the latest 10 fills
//   of a known card:
//     - a card that is not known is registered here: the customer's name (only a name), the fuel type and the PIN the customer gives (twice);
//     - a known card shows its holder, its fuel type, its balance and its status, and the Fill page, the main work of a card: the cursor is in its first field.
//       The Top Up button above History switches to the Top Up page (and its text becomes Fill, which switches back).
//         Fill        the weight to supply and the PIN the customer said (the PIN is the last field); OK asks "OK / Cancel" (ConfirmDialog), checks the PIN (5 wrong
//                     ones lock the card), takes the kg from the card and makes a ticket for them (Cards::issueTicket). The cashier's setting (Settings page,
//                     SettingKey::CardFillResult) says what the fill gives: the ticket is printed, and the supplier scans it at the dispenser like the ticket of a spot
//                     sale, at an auto or a manual nozzle; or nothing is printed and the weight is set straight on a nozzle (DirectSet, as a spot sale does). Under the form the last ticket of the card that is not used yet can be
//                     printed again or cancelled (the kg go back to the card) while it waits for its upload.
//         Top up      kg at a price per kg (the fuel's price is filled in, it can be changed), paid in cash: a sale of type "card"; Top Up asks "OK / Cancel" too
//     Enter moves on to the next field of a page and Enter on the button presses it.
//   A locked card is unlocked, and a lost card blocked or unblocked, on the web app only.
class CardPage : public QWidget
{
    Q_OBJECT

public:
    CardPage(QSqlDatabase db, Session *session, PrintService *printer, CardReader *reader, BoardBus *bus, MachineConfig *config, SupplySelection *selection,
             SupplySession *supply, Settings *settings, QWidget *parent = 0);
    ~CardPage();

    // Called whenever the page is opened: nothing is shown until a card is read.
    void reload();

signals:
    void saved(); // a card, a top-up or a fill was written: what waits for the upload has changed

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
    void fillClicked();
    void switchClicked();
    void printAgainClicked();
    void cancelTicketClicked();
    void printFinished(bool ok, const QString &message);
    void readerChanged();

private:
    Ui::CardPage *ui_;
    void showPanel(int index);
    void showInfo(const Cards::Card &card);
    void showAction(int page);
    void showTicket();
    void startPrint(const QString &barcode);
    void showStatus(const QString &message, bool ok);

    QSqlDatabase db_;
    Session *session_;
    PrintService *printer_;
    CardReader *reader_;
    BoardBus *bus_;
    MachineConfig *config_;
    SupplySelection *selection_;
    SupplySession *supply_;
    Settings *settings_;

    QLineEdit *idField_;
    QLabel *readerStatus_;
    QLabel *holder_;
    QLabel *fuel_;
    QLabel *balance_;
    QLabel *state_;
    QStackedWidget *panels_;
    QLabel *message_;

    QLineEdit *registerName_;
    QLineEdit *registerPhone_;
    QComboBox *registerFuel_;
    QLineEdit *registerPin_;
    QLineEdit *registerPinAgain_;

    QLineEdit *topUpKg_;
    QLineEdit *topUpPrice_;
    QLineEdit *topUpTotal_;

    QStackedWidget *actionPages_;    // Fill / Top Up
    QPushButton *switchButton_;      // the Top Up button above History: it shows the Top Up page, and then the Fill page
    QLineEdit *fillKg_;
    QLineEdit *fillPin_;
    QPushButton *fillOk_;
    QGroupBox *ticketBox_;
    QLabel *ticketLabel_;
    QPushButton *printAgain_;
    QPushButton *cancelTicket_;

    // keyboard entry of each action: Enter moves on to the next field (the PIN is the last one) and Enter on the button presses it
    EnterChain *topUpChain_;
    EnterChain *fillChain_;

    QString cardId_;      // the card on screen, empty when none
    QString uid_;         // the id that was read (also for a card not registered yet)
    QString ticket_;      // the ticket shown under the Fill form (16 digits), empty when none
    QString filled_;      // what the last fill said, shown above the result of its print
    bool printing_;
    bool priceTouched_;
    bool totalTyped_;   // the cashier typed the total payment last: it counts as typed, even when it is not kg x price
};

#endif
