#include "CardPage.h"

#include "BoardBus.h"
#include "CardHistoryDialog.h"
#include "CardReader.h"
#include "Decimal.h"
#include "MachineConfig.h"
#include "NozzleDialog.h"
#include "Session.h"
#include "SpotSale.h"
#include "SupplyQueue.h"
#include "SupplySelection.h"
#include "SupplySession.h"
#include "ui_CardPage.h"
#include "Theme.h"

#include <QComboBox>
#include <QDateTime>
#include <QLabel>
#include <QLineEdit>
#include <QPushButton>
#include <QRegExpValidator>
#include <QSqlQuery>
#include <QStackedWidget>
#include <QTimer>

using namespace BoardProtocol;

namespace {

enum { PanelNone, PanelRegister, PanelCard };

const int RefreshMs = 500;

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

CardPage::CardPage(QSqlDatabase db, Session *session, BoardBus *bus, MachineConfig *config, SupplySelection *selection, SupplySession *supply,
                   CardReader *reader, QWidget *parent)
    : QWidget(parent), ui_(new Ui::CardPage), db_(db), session_(session), bus_(bus), config_(config), selection_(selection), supply_(supply), reader_(reader),
      fillNozzleNumber_(0), priceTouched_(false), totalTyped_(false)
{
    ui_->setupUi(this);

    // the card: read by the reader
    idField_ = ui_->cardIdField;
    readerStatus_ = ui_->cardReaderStatus;
    holder_ = ui_->cardHolder;
    fuel_ = ui_->cardFuel;
    balance_ = ui_->cardBalance;
    state_ = ui_->cardState;

    // the panels: nothing yet, register, or the card's top-up and fill
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

    fillPin_ = ui_->fillPinField;
    fillKg_ = ui_->fillKgField;
    fillKg_->setValidator(decimal);
    fillNozzle_ = ui_->fillNozzleCombo;
    fillNozzleState_ = ui_->fillNozzleState;
    start_ = ui_->fillStartButton;
    message_ = ui_->cardMessage;

    timer_ = new QTimer(this);
    timer_->setInterval(RefreshMs);

    connect(ui_->cardHistoryButton, SIGNAL(clicked()), this, SLOT(historyClicked()));
    connect(ui_->registerButton, SIGNAL(clicked()), this, SLOT(registerClicked()));
    connect(ui_->topUpButton, SIGNAL(clicked()), this, SLOT(topUpClicked()));
    connect(topUpKg_, SIGNAL(textEdited(QString)), this, SLOT(topUpEdited()));
    connect(topUpPrice_, SIGNAL(textEdited(QString)), this, SLOT(topUpEdited()));
    connect(topUpTotal_, SIGNAL(textEdited(QString)), this, SLOT(topUpTotalEdited()));
    connect(start_, SIGNAL(clicked()), this, SLOT(startClicked()));
    connect(fillNozzle_, SIGNAL(activated(int)), this, SLOT(nozzleChosen()));
    connect(timer_, SIGNAL(timeout()), this, SLOT(refreshNozzles()));
    connect(reader_, SIGNAL(cardRead(QString)), this, SLOT(showCard(QString)));
    connect(reader_, SIGNAL(openChanged(bool)), this, SLOT(readerChanged()));
    connect(supply_, SIGNAL(recorded(int,QString)), this, SLOT(supplyRecorded(int,QString)));
    connect(supply_, SIGNAL(message(QString,bool)), this, SLOT(supplyMessage(QString,bool)));
    timer_->start();

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
    idField_->clear();
    message_->clear();
    holder_->clear();
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

    registerFuel_->clear();
    QSqlQuery fuels(db_);
    fuels.exec("SELECT fuel_type, name FROM fuel_types ORDER BY name");
    while (fuels.next()) {
        registerFuel_->addItem(fuels.value(1).toString(), fuels.value(0).toString());
    }

    ui_->cardHistoryButton->setEnabled(false);
    showPanel(PanelNone);
    readerChanged();
    fillNozzles();
}

void CardPage::readerChanged()
{
    if (!CardReader::supported()) {
        readerStatus_->setText(tr("No card reader on this system."));
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
    showInfo(card);
    fillPin_->clear();
    fillKg_->clear();
    topUpKg_->clear();
    topUpTotal_->clear();
    priceTouched_ = false;
    totalTyped_ = false;

    // the price of the card's fuel is the default of a top-up
    QSqlQuery price(db_);
    price.prepare("SELECT price FROM fuel_types WHERE fuel_type = ?");
    price.addBindValue(card.fuelType);
    topUpPrice_->setText(price.exec() && price.next() ? kg(price.value(0).toLongLong()) : QString());

    showPanel(PanelCard);
    fillNozzles();
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

// The nozzles of the card's fuel type that can be filled from the screen (auto mode).
void CardPage::fillNozzles()
{
    const int chosen = fillNozzle_->itemData(fillNozzle_->currentIndex()).toInt();
    const Cards::Card card = cardId_.isEmpty() ? Cards::Card() : Cards::findById(db_, cardId_);

    fillNozzle_->blockSignals(true);
    fillNozzle_->clear();
    if (card.exists) {
        const QList<MachineConfig::Nozzle> nozzles = config_->nozzles();
        for (int i = 0; i < nozzles.size(); ++i) {
            const MachineConfig::Nozzle &nozzle = nozzles.at(i);
            if (nozzle.seen() && nozzle.fuelType == card.fuelType && nozzle.mode == ModeAuto) {
                fillNozzle_->addItem(tr("Nozzle %1").arg(nozzle.number), nozzle.number);
            }
        }
        const int at = fillNozzle_->findData(chosen);
        fillNozzle_->setCurrentIndex(at >= 0 ? at : (fillNozzle_->count() > 0 ? 0 : -1));
    }
    fillNozzle_->blockSignals(false);
    refreshNozzles();
}

QString CardPage::stateText(int nozzle) const
{
    return NozzleDialog::stateText(db_, bus_, nozzle);
}

void CardPage::refreshNozzles()
{
    const int nozzle = fillNozzle_->itemData(fillNozzle_->currentIndex()).toInt();
    fillNozzleState_->setText(nozzle > 0 ? stateText(nozzle) : QString());
}

// Choosing a nozzle selects it on its board (its select light), like tapping it on the Supplies screen.
void CardPage::nozzleChosen()
{
    const int nozzle = fillNozzle_->itemData(fillNozzle_->currentIndex()).toInt();
    if (nozzle < 1) {
        return;
    }
    QString why;
    if (!selection_->select(nozzle, &why)) {
        showStatus(why, false);
    }
    refreshNozzles();
}

void CardPage::startClicked()
{
    const Cards::Card card = Cards::findById(db_, cardId_);
    const int nozzle = fillNozzle_->itemData(fillNozzle_->currentIndex()).toInt();
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
    if (nozzle < 1) {
        showStatus(tr("Choose a nozzle of the card's fuel type."), false);

        return;
    }

    // the PIN: a wrong one counts, the 5th locks the card
    const Cards::PinCheck pin = Cards::checkPin(db_, card.id, fillPin_->text());
    if (pin.result != Cards::PinRight) {
        fillPin_->clear();
        showInfo(Cards::findById(db_, card.id));
        showStatus(pin.error, false);
        emit saved(); // the wrong tries wait for the upload

        return;
    }
    fillPin_->clear();
    emit saved();

    QString why;
    if (!supply_->startCard(nozzle, card.id, weight, &why)) {
        showStatus(why, false);

        return;
    }

    fillNozzleNumber_ = nozzle;
    showStatus(tr("Nozzle %1: %2 kg are set. Start the fill at the nozzle.").arg(nozzle).arg(kg(weight)), true);
}

// A fill of this screen ended: the card shows its new balance.
void CardPage::supplyRecorded(int nozzle, const QString &supplementId)
{

    QSqlQuery query(db_);
    query.prepare("SELECT card_id, out_amount_kg FROM supplements WHERE id = ?");
    query.addBindValue(supplementId);
    if (!query.exec() || !query.next() || query.value(0).toString().isEmpty()) {
        return; // a supply of tickets
    }

    const QString id = query.value(0).toString();
    const qlonglong dispensed = query.value(1).toLongLong();
    const Cards::Card card = Cards::findById(db_, id);
    if (id == cardId_) {
        showInfo(card);
    }
    if (nozzle == fillNozzleNumber_) {
        fillNozzleNumber_ = 0;
        showStatus(tr("Nozzle %1: %2 kg were supplied. The card holds %3 kg.").arg(nozzle).arg(kg(dispensed)).arg(kg(card.balanceX100)), true);
    }
    emit saved();
}

void CardPage::supplyMessage(const QString &text, bool good)
{
    if (fillNozzleNumber_ > 0 && text.startsWith(tr("Nozzle %1").arg(fillNozzleNumber_))) {
        showStatus(text, good);
    }
}

void CardPage::showStatus(const QString &message, bool ok)
{
    Theme::setState(message_, ok ? "ok" : "error");
    message_->setText(message);
}
