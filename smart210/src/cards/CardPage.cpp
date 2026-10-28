#include "CardPage.h"

#include "Barcode.h"
#include "BoardBus.h"
#include "CardHistoryDialog.h"
#include "CardReader.h"
#include "ConfirmDialog.h"
#include "Decimal.h"
#include "DirectSet.h"
#include "EnterChain.h"
#include "MachineConfig.h"
#include "PrintService.h"
#include "Session.h"
#include "Settings.h"
#include "SpotSale.h"
#include "SupplySelection.h"
#include "SupplySession.h"
#include "ui_CardPage.h"
#include "Theme.h"

#include <QComboBox>
#include <QDateTime>
#include <QGroupBox>
#include <QLabel>
#include <QLineEdit>
#include <QPushButton>
#include <QRegExpValidator>
#include <QSqlQuery>
#include <QStackedWidget>

namespace {

enum { PanelNone, PanelRegister, PanelCard };
enum { ActionFill, ActionTopUp };

QString kg(qlonglong x100)
{
    return Decimal::format(x100, 2);
}

QString statusText(const QString &status)
{
    if (status == "locked") {
        return QObject::tr("Locked (wrong PINs)");
    }
    if (status == "blocked") {
        return QObject::tr("Blocked (lost)");
    }

    return QObject::tr("Active");
}

}

CardPage::CardPage(QSqlDatabase db, Session *session, PrintService *printer, CardReader *reader, BoardBus *bus, MachineConfig *config, SupplySelection *selection,
                   SupplySession *supply, Settings *settings, QWidget *parent)
    : QWidget(parent), ui_(new Ui::CardPage), db_(db), session_(session), printer_(printer), reader_(reader), bus_(bus), config_(config), selection_(selection),
      supply_(supply), settings_(settings), printing_(false), priceTouched_(false), totalTyped_(false)
{
    ui_->setupUi(this);

    // the card: read by the reader
    idField_ = ui_->cardIdField;
    // Without a reader on this system (a PC) the id is typed, and Enter reads it as if the card were put on the reader.
    if (!CardReader::supported()) {
        idField_->setReadOnly(false);
        idField_->setFocusPolicy(Qt::StrongFocus);
        idField_->setPlaceholderText(tr("Type the card id and press Enter"));
        connect(idField_, SIGNAL(returnPressed()), this, SLOT(idEntered()));
    }
    readerStatus_ = ui_->cardReaderStatus;
    holder_ = ui_->cardHolder;
    fuel_ = ui_->cardFuel;
    balance_ = ui_->cardBalance;
    state_ = ui_->cardState;

    // the panels: nothing yet, register, or the card's fill and top-up
    panels_ = ui_->cardPanels;
    registerName_ = ui_->registerNameField;
    registerPhone_ = ui_->registerPhoneField;
    registerFuel_ = ui_->registerFuelCombo;
    registerPin_ = ui_->registerPinField;
    registerPinAgain_ = ui_->registerPinAgainField;

    QRegExpValidator *decimal = new QRegExpValidator(QRegExp("[0-9]{0,9}([.][0-9]{0,2})?"), this);
    topUpKg_ = ui_->topUpKgField;
    topUpKg_->setValidator(decimal);
    topUpPrice_ = ui_->topUpPriceField;
    topUpPrice_->setValidator(decimal);
    topUpTotal_ = ui_->topUpTotal;
    topUpTotal_->setValidator(decimal);

    // the two actions of a registered card: the Fill page is the main one, the button above History shows the Top Up page
    actionPages_ = ui_->cardActionPages;
    switchButton_ = ui_->cardTopUpButton;
    fillKg_ = ui_->fillKgField;
    fillKg_->setValidator(decimal);
    fillPin_ = ui_->fillPinField;
    fillOk_ = ui_->fillOkButton;
    ticketBox_ = ui_->ticketBox;
    ticketLabel_ = ui_->ticketLabel;
    printAgain_ = ui_->ticketPrintButton;
    cancelTicket_ = ui_->ticketCancelButton;
    message_ = ui_->cardMessage;

    connect(ui_->cardHistoryButton, SIGNAL(clicked()), this, SLOT(historyClicked()));
    connect(ui_->registerButton, SIGNAL(clicked()), this, SLOT(registerClicked()));
    connect(ui_->topUpButton, SIGNAL(clicked()), this, SLOT(topUpClicked()));
    connect(topUpKg_, SIGNAL(textEdited(QString)), this, SLOT(topUpEdited()));
    connect(topUpPrice_, SIGNAL(textEdited(QString)), this, SLOT(topUpEdited()));
    connect(topUpTotal_, SIGNAL(textEdited(QString)), this, SLOT(topUpTotalEdited()));
    connect(fillOk_, SIGNAL(clicked()), this, SLOT(fillClicked()));
    connect(switchButton_, SIGNAL(clicked()), this, SLOT(switchClicked()));
    connect(printAgain_, SIGNAL(clicked()), this, SLOT(printAgainClicked()));
    connect(cancelTicket_, SIGNAL(clicked()), this, SLOT(cancelTicketClicked()));

    // keyboard entry: Enter in a field moves on to the next one of the page (the PIN is the last), Enter on the button presses it
    fillChain_ = new EnterChain(this);
    fillChain_->add(fillKg_);
    fillChain_->add(fillPin_);
    fillChain_->add(fillOk_);
    topUpChain_ = new EnterChain(this);
    topUpChain_->add(topUpKg_);
    topUpChain_->add(topUpPrice_);
    topUpChain_->add(topUpTotal_);
    topUpChain_->add(ui_->topUpButton);
    connect(reader_, SIGNAL(cardRead(QString)), this, SLOT(showCard(QString)));
    connect(reader_, SIGNAL(openChanged(bool)), this, SLOT(readerChanged()));
    connect(printer_, SIGNAL(finished(bool,QString)), this, SLOT(printFinished(bool,QString)));

    reload();
}

CardPage::~CardPage()
{
    delete ui_;
}

void CardPage::reload()
{
    cardId_.clear();
    uid_.clear();
    ticket_.clear();
    filled_.clear();
    idField_->clear();
    message_->clear();
    holder_->clear();
    ui_->cardPhone->clear();
    fuel_->clear();
    balance_->clear();
    state_->clear();
    registerName_->clear();
    registerPhone_->clear();
    registerPin_->clear();
    registerPinAgain_->clear();
    topUpKg_->clear();
    topUpPrice_->clear();
    topUpTotal_->clear();
    fillPin_->clear();
    fillKg_->clear();
    priceTouched_ = false;
    totalTyped_ = false;

    registerFuel_->clear();
    QSqlQuery fuels(db_);
    fuels.exec("SELECT fuel_type, name FROM fuel_types ORDER BY name");
    while (fuels.next()) {
        registerFuel_->addItem(fuels.value(1).toString(), fuels.value(0).toString());
    }

    ui_->cardHistoryButton->setEnabled(false);
    switchButton_->setEnabled(false);
    showPanel(PanelNone);
    readerChanged();
    showAction(ActionFill);
    showTicket();
}

void CardPage::readerChanged()
{
    if (!CardReader::supported()) {
        readerStatus_->setText(tr("No card reader on this system: type the card id above and press Enter."));
    } else if (reader_->isOpen()) {
        readerStatus_->setText(tr("Card reader: ready (%1).").arg(reader_->config().port));
    } else {
        readerStatus_->setText(tr("Card reader: not connected (%1).").arg(reader_->config().port.isEmpty() ? CardReader::defaultPort() : reader_->config().port));
    }
}

void CardPage::showPanel(int index)
{
    panels_->setCurrentIndex(index);
}

// Enter in the id box (only where there is no reader): the typed id is read like one from the reader.
void CardPage::idEntered()
{
    showCard(idField_->text());
}

// The latest top-ups and fills of the card that is shown.
void CardPage::historyClicked()
{
    if (cardId_.isEmpty()) {
        return;
    }

    CardHistoryDialog dialog(db_, cardId_, holder_->text(), this);
    dialog.exec();
}

// A card id (from the reader or typed): known cards are shown, others can be registered.
void CardPage::showCard(const QString &id)
{
    const QString uid = Cards::normalizeUid(id);
    if (uid.isEmpty()) {
        showStatus(tr("Read the card first."), false);

        return;
    }

    const Cards::Card card = Cards::find(db_, uid);

    // A reader that repeats the id while the card lies on it (the Arduino one does) must not wipe what the cashier is typing: the same
    // card read again only refreshes what is shown.
    if (card.exists && !card.deleted && card.id == cardId_) {
        showInfo(card);

        return;
    }
    if ((!card.exists || card.deleted) && cardId_.isEmpty() && uid == uid_ && panels_->currentIndex() == PanelRegister) {
        return;
    }

    uid_ = uid;
    idField_->setText(uid);
    if (!card.exists || card.deleted) {
        cardId_.clear();
        ui_->cardHistoryButton->setEnabled(false);
        switchButton_->setEnabled(false);
        holder_->clear();
        ui_->cardPhone->clear();
        fuel_->clear();
        balance_->clear();
        state_->setText(tr("Not registered"));
        registerName_->clear();
        registerPhone_->clear();
        registerPin_->clear();
        registerPinAgain_->clear();
        showPanel(PanelRegister);
        showStatus(tr("This card is not registered: enter the customer's name, the fuel type and the PIN."), true);

        return;
    }

    cardId_ = card.id;
    ui_->cardHistoryButton->setEnabled(true);
    switchButton_->setEnabled(true);
    showInfo(card);
    fillPin_->clear();
    fillKg_->clear();
    topUpKg_->clear();
    topUpTotal_->clear();
    priceTouched_ = false;
    totalTyped_ = false;
    filled_.clear();

    // the price of the card's fuel is the default of a top-up
    QSqlQuery price(db_);
    price.prepare("SELECT price FROM fuel_types WHERE fuel_type = ?");
    price.addBindValue(card.fuelType);
    topUpPrice_->setText(price.exec() && price.next() ? kg(price.value(0).toLongLong()) : QString());

    showPanel(PanelCard);
    showAction(ActionFill);         // a card that is shown starts with the Fill page, the cursor in its first field
    showTicket();
    if (card.status == "blocked") {
        showStatus(tr("This card is blocked (lost): it cannot be used or topped up."), false);
    } else if (card.status == "locked") {
        showStatus(tr("This card is locked after wrong PINs: it can be topped up, but only the manager or the accountant unlocks it on the web app."), false);
    } else {
        message_->clear();
    }
}

void CardPage::showInfo(const Cards::Card &card)
{
    holder_->setText(card.holderName);
    ui_->cardPhone->setText(card.holderPhone);
    QString fuelText = card.fuelType;
    QSqlQuery fuel(db_);
    fuel.prepare("SELECT name FROM fuel_types WHERE fuel_type = ?");
    fuel.addBindValue(card.fuelType);
    if (fuel.exec() && fuel.next()) {
        fuelText += "  " + fuel.value(0).toString();
    }
    fuel_->setText(fuelText);
    balance_->setText(kg(card.balanceX100));
    state_->setText(statusText(card.status) + (card.failedAttempts > 0 && card.status == "active" ? "  " + tr("(%1 wrong PINs)").arg(card.failedAttempts) : QString()));
}

void CardPage::registerClicked()
{
    if (session_->userId() <= 0) {
        showStatus(tr("Log in first."), false);

        return;
    }
    if (registerPin_->text() != registerPinAgain_->text()) {
        showStatus(tr("The two PINs are not the same."), false);

        return;
    }

    const Cards::Registration result = Cards::registerCard(db_, uid_, registerName_->text(), registerFuel_->itemData(registerFuel_->currentIndex()).toString(),
                                                           registerPin_->text(), registerPhone_->text());
    if (!result.ok) {
        showStatus(result.error, false);

        return;
    }

    registerPin_->clear();
    registerPinAgain_->clear();
    emit saved();
    showCard(uid_);
    showStatus(tr("The card is registered."), true);
}

// The kg or the price changed: the total follows (kg x price), unless the cashier typed the total last; then the kg follows the total and the price.
void CardPage::topUpEdited()
{
    if (sender() == topUpPrice_) {
        priceTouched_ = true;
    } else {
        totalTyped_ = false; // the kg typed again: the total is kg x price
    }
    qlonglong weight = 0;
    qlonglong price = 0;
    qlonglong total = 0;
    if (!Decimal::parse(topUpPrice_->text(), 2, &price) || price <= 0) {
        if (!totalTyped_) {
            topUpTotal_->clear();
        }
    } else if (totalTyped_ && Decimal::parse(topUpTotal_->text(), 2, &total)) {
        topUpKg_->setText(kg(SpotSale::kgFor(total, price)));
    } else if (Decimal::parse(topUpKg_->text(), 2, &weight) && weight > 0) {
        topUpTotal_->setText(kg(SpotSale::amountFor(weight, price)));
    } else {
        topUpTotal_->clear();
    }
}

// The total payment was typed: the kg follows from it and the price, and the typed total is what is paid.
void CardPage::topUpTotalEdited()
{
    totalTyped_ = true;
    qlonglong price = 0;
    qlonglong total = 0;
    if (Decimal::parse(topUpPrice_->text(), 2, &price) && price > 0 && Decimal::parse(topUpTotal_->text(), 2, &total)) {
        topUpKg_->setText(kg(SpotSale::kgFor(total, price)));
    } else {
        topUpKg_->clear();
    }
}

void CardPage::topUpClicked()
{
    qlonglong weight = 0;
    qlonglong price = 0;
    if (cardId_.isEmpty()) {
        showStatus(tr("Read a card first."), false);

        return;
    }
    if (!Decimal::parse(topUpKg_->text(), 2, &weight) || weight <= 0) {
        showStatus(tr("Enter the weight in kg, above 0 and up to 999999."), false);

        return;
    }
    if (!Decimal::parse(topUpPrice_->text(), 2, &price) || price <= 0) {
        showStatus(tr("Enter the price per kg."), false);

        return;
    }

    qlonglong typedTotal = 0;
    const bool useTotal = totalTyped_ && Decimal::parse(topUpTotal_->text(), 2, &typedTotal);
    const qlonglong total = useTotal ? typedTotal : (weight * price + 50) / 100;
    if (!ConfirmDialog::ask(this, tr("Confirm top up"), tr("Top up %1 kg on the card of %2?\nPrice per kg: %3\nTotal payment: %4 (cash)").arg(kg(weight)).arg(Cards::findById(db_, cardId_).holderName).arg(kg(price)).arg(kg(total)))) {
        message_->clear();

        return;
    }
    const Cards::TopUpResult result = Cards::topUp(db_, cardId_, weight, price, session_->userId(), QDateTime::currentDateTime(), useTotal ? typedTotal : -1);
    if (!result.ok) {
        showStatus(result.error, false);

        return;
    }

    topUpKg_->clear();
    topUpTotal_->clear();
    totalTyped_ = false;
    showInfo(Cards::findById(db_, cardId_));
    emit saved();
    showStatus(tr("Topped up %1 kg for %2 (cash). The card holds %3 kg.").arg(kg(weight)).arg(kg(result.totalX100)).arg(kg(result.balanceX100)), true);
}

// The page that shows on the right (Fill or Top Up); the button above History offers the other one. The cursor goes to the first field of the page.
void CardPage::showAction(int page)
{
    actionPages_->setCurrentIndex(page);
    switchButton_->setText(page == ActionFill ? tr("Top Up") : tr("Fill"));

    if (page == ActionFill) {
        fillChain_->focusFirst();
    } else {
        topUpChain_->focusFirst();
    }
}

void CardPage::switchClicked()
{
    showAction(actionPages_->currentIndex() == ActionFill ? ActionTopUp : ActionFill);
}

// OK on the Fill page: the card pays the kg and a ticket for them is made and printed. The same checks first, then "OK / Cancel", then the PIN (a wrong one
// counts, the 5th locks the card).
void CardPage::fillClicked()
{
    const Cards::Card card = Cards::findById(db_, cardId_);
    qlonglong weight = 0;

    if (!card.exists) {
        showStatus(tr("Read a card first."), false);

        return;
    }
    if (session_->userId() <= 0) {
        showStatus(tr("Log in first."), false);

        return;
    }
    if (!card.isActive()) {
        showStatus(card.status == "blocked" ? tr("This card is blocked (lost).") : tr("This card is locked after wrong PINs. The manager or the accountant unlocks it on the web app."), false);

        return;
    }
    if (!Decimal::parse(fillKg_->text(), 2, &weight) || weight <= 0) {
        showStatus(tr("Enter the weight to supply."), false);

        return;
    }
    if (weight > card.balanceX100) {
        showStatus(tr("The card holds only %1 kg.").arg(kg(card.balanceX100)), false);

        return;
    }
    if (!ConfirmDialog::ask(this, tr("Confirm fill"), tr("Fill %1 kg for the card of %2?\nThe card holds %3 kg.").arg(kg(weight)).arg(card.holderName).arg(kg(card.balanceX100)))) {
        message_->clear();

        return;
    }

    const Cards::PinCheck pin = Cards::checkPin(db_, card.id, fillPin_->text());
    if (pin.result != Cards::PinRight) {
        fillPin_->clear();
        showInfo(Cards::findById(db_, card.id));
        showStatus(pin.error, false);
        emit saved(); // the wrong tries wait for the upload

        return;
    }
    fillPin_->clear();

    const Cards::TicketResult made = Cards::issueTicket(db_, card.id, weight, session_->userId(), QDateTime::currentDateTime());
    if (!made.ok) {
        showStatus(made.error, false);
        emit saved();

        return;
    }

    fillKg_->clear();
    showInfo(Cards::findById(db_, card.id));
    showTicket();
    emit saved();
    filled_ = tr("The ticket %1 for %2 kg is made. The card holds %3 kg.").arg(made.barcode).arg(kg(made.kgX100)).arg(kg(made.balanceX100));

    // what the fill gives is the setting of the Settings page: the ticket on paper, or the weight straight to a nozzle (no paper)
    if (settings_->resultIsDirect(SettingKey::CardFillResult)) {
        QString message;
        const bool done = DirectSet::run(this, db_, bus_, config_, selection_, supply_, card.fuelType, made.kgX100, made.barcode, &message);
        showStatus(filled_ + "\n" + message + (done ? QString() : " " + tr("Print again prints the ticket.")), done);

        return;
    }
    startPrint(made.barcode);
}

// The last ticket of the card that is not used yet, with the two things the cashier can do with it.
void CardPage::showTicket()
{
    ticket_ = cardId_.isEmpty() ? QString() : Cards::lastTicket(db_, cardId_);
    ticketBox_->setVisible(!ticket_.isEmpty());
    if (ticket_.isEmpty()) {
        ticketLabel_->clear();

        return;
    }

    QSqlQuery query(db_);
    query.prepare("SELECT amount_kg, COALESCE(sale_date, ''), print_count FROM tickets WHERE barcode = ?");
    query.addBindValue(Barcode::toKey(ticket_));
    if (query.exec() && query.next()) {
        ticketLabel_->setText(tr("%1\n%2 kg, %3\nPrinted: %4").arg(ticket_).arg(kg(query.value(0).toLongLong())).arg(query.value(1).toString()).arg(query.value(2).toInt()));
    }
    printAgain_->setEnabled(!printing_);
}

// The ticket goes to the printer; it is saved already, so a printer that fails only gives a message and Print again tries once more.
void CardPage::startPrint(const QString &barcode)
{
    if (printer_->isBusy()) {
        showStatus(filled_ + "\n" + tr("The printer is busy. Print again prints the ticket."), false);

        return;
    }

    printing_ = true;
    printAgain_->setEnabled(false);
    showStatus(filled_ + "\n" + tr("Printing..."), true);
    printer_->printCardTicket(barcode);
}

void CardPage::printAgainClicked()
{
    if (ticket_.isEmpty()) {
        return;
    }

    filled_ = tr("The ticket %1.").arg(ticket_);
    startPrint(ticket_);
}

void CardPage::printFinished(bool ok, const QString &message)
{
    if (!printing_) {
        return; // another screen's print
    }

    printing_ = false;
    showTicket();                                                           // (the count of prints is one more)
    printAgain_->setEnabled(!ticket_.isEmpty());
    showStatus(filled_ + "\n" + (ok ? tr("The ticket was sent to the printer.") : tr("It was not printed: %1 Print again prints it.").arg(message)), ok);
}

// Cancel ticket: the ticket was not used and has not been uploaded; it goes away and its kg go back to the card.
void CardPage::cancelTicketClicked()
{
    if (ticket_.isEmpty()) {
        return;
    }

    QSqlQuery query(db_);
    query.prepare("SELECT amount_kg FROM tickets WHERE barcode = ?");
    query.addBindValue(Barcode::toKey(ticket_));
    const qlonglong weight = query.exec() && query.next() ? query.value(0).toLongLong() : 0;
    if (!ConfirmDialog::ask(this, tr("Cancel ticket"), tr("Cancel the ticket %1 (%2 kg)?\nThe kg go back to the card.").arg(ticket_).arg(kg(weight)))) {
        return;
    }

    QString why;
    if (!Cards::cancelTicket(db_, ticket_, &why)) {
        showStatus(why, false);

        return;
    }

    const QString cancelled = ticket_;
    const Cards::Card card = Cards::findById(db_, cardId_);
    showInfo(card);
    showTicket();
    emit saved();
    showStatus(tr("The ticket %1 is cancelled: %2 kg are back on the card. The card holds %3 kg.").arg(cancelled).arg(kg(weight)).arg(kg(card.balanceX100)), true);
}

void CardPage::showStatus(const QString &message, bool ok)
{
    Theme::setState(message_, ok ? "ok" : "error");
    message_->setText(message);
}
