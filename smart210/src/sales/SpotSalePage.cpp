#include "SpotSalePage.h"

#include "BoardBus.h"
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
#include "ui_SpotSalePage.h"
#include "Theme.h"

#include <QButtonGroup>
#include <QCheckBox>
#include <QComboBox>
#include <QDateTime>
#include <QLabel>
#include <QLineEdit>
#include <QPushButton>
#include <QRegExpValidator>
#include <QSqlQuery>
#include <QTimer>

SpotSalePage::SpotSalePage(QSqlDatabase db, Session *session, PrintService *printer, BoardBus *bus, MachineConfig *config, SupplySelection *selection,
                           SupplySession *supply, Settings *settings, QWidget *parent)
    : QWidget(parent), ui_(new Ui::SpotSalePage), db_(db), session_(session), printer_(printer), bus_(bus), config_(config), selection_(selection),
      supply_(supply), settings_(settings), printing_(false), amountTypedLast_(false)
{
    ui_->setupUi(this);

    // what the fuel is for: the index of the choice is its number (0 sale, 1 self use, 2 other, 3 ticket loan)
    usage_ = ui_->usageCombo;
    usage_->addItem(tr("Sale"));
    usage_->addItem(tr("Self use"));
    usage_->addItem(tr("Other"));
    usage_->addItem(tr("Ticket loan"));

    // the same order as the web form: the customer, the fuel type and the price; the weight, the amount and the payment (always cash);
    // what the fuel is for; the receipt and the comment
    fuel_ = ui_->fuelCombo;
    customer_ = ui_->customerCombo;

    QRegExpValidator *decimal = new QRegExpValidator(QRegExp("[0-9]{0,9}([.][0-9]{0,2})?"), this);
    price_ = ui_->priceField;
    price_->setValidator(decimal);
    kg_ = ui_->kgField;
    kg_->setValidator(decimal);
    amount_ = ui_->amountField;
    amount_->setValidator(decimal);
    comment_ = ui_->commentField;
    receipt_ = ui_->receiptCheck;
    status_ = ui_->saleStatus;
    printAgain_ = ui_->printAgainButton;

    // Enter goes through the fields in the order of the form
    chain_ = new EnterChain(this);
    chain_->add(customer_);
    chain_->add(fuel_);
    chain_->add(price_);
    chain_->add(usage_);
    chain_->add(kg_);
    chain_->add(amount_);
    chain_->add(receipt_);
    chain_->add(comment_);
    chain_->add(ui_->saveSaleButton);

    connect(usage_, SIGNAL(currentIndexChanged(int)), this, SLOT(usageChanged()));
    connect(fuel_, SIGNAL(currentIndexChanged(int)), this, SLOT(fuelChanged()));
    connect(price_, SIGNAL(textEdited(QString)), this, SLOT(priceEdited()));
    connect(kg_, SIGNAL(textEdited(QString)), this, SLOT(kgEdited()));
    connect(amount_, SIGNAL(textEdited(QString)), this, SLOT(amountEdited()));
    connect(ui_->saveSaleButton, SIGNAL(clicked()), this, SLOT(save()));
    connect(printAgain_, SIGNAL(clicked()), this, SLOT(printAgain()));
    connect(printer_, SIGNAL(finished(bool,QString)), this, SLOT(printFinished(bool,QString)));

}

SpotSalePage::~SpotSalePage()
{
    delete ui_;
}

QString SpotSalePage::usage() const
{
    switch (usage_->currentIndex()) {
    case 0:
        return "sale";
    case 1:
        return "self";
    case 2:
        return "other";
    case 3:
        return "ticket_loan";
    }

    return QString();
}

void SpotSalePage::reload()
{
    fuel_->blockSignals(true);
    fuel_->clear();
    QSqlQuery fuels(db_);
    fuels.exec("SELECT fuel_type, name, price FROM fuel_types ORDER BY name");
    while (fuels.next()) {
        fuel_->addItem(fuels.value(1).toString() + " (" + Decimal::format(fuels.value(2).toLongLong(), 2) + ")", fuels.value(0).toString());
        fuel_->setItemData(fuel_->count() - 1, fuels.value(2).toLongLong(), Qt::UserRole + 1);
    }
    fuel_->blockSignals(false);

    customer_->clear();
    customer_->addItem(tr("Choose the customer"), 0);
    QSqlQuery customers(db_);
    customers.exec("SELECT id, name, is_self FROM customers WHERE deleted = 0 ORDER BY is_self DESC, name COLLATE NOCASE");
    while (customers.next()) {
        customer_->addItem(customers.value(1).toString(), customers.value(0).toInt());
        customer_->setItemData(customer_->count() - 1, customers.value(2).toInt(), Qt::UserRole + 1);
    }

    usage_->setCurrentIndex(0);
    clearEntry();
    status_->clear();
    lastSaleId_.clear();
    printAgain_->setEnabled(false);
    usageChanged();
    fuelChanged();
    chain_->focusWidget(kg_);
}

void SpotSalePage::clearEntry()
{
    kg_->clear();
    amount_->clear();
    comment_->clear();
    receipt_->setChecked(false);
    amountTypedLast_ = false;
}

// Only a sale has an amount. Own use and other use are always the customer "Individual" (the one the download marks), which cannot be changed; a
// sale starts with it too (the buyer who pays cash and needs no name: what is known goes in the comment), and a ticket loan needs a customer to be chosen.
void SpotSalePage::usageChanged()
{
    const bool counts = (usage() == "sale");
    amount_->setEnabled(counts);
    if (!counts) {
        amount_->clear();
        amountTypedLast_ = false;
    }

    const int individual = customer_->findData(1, Qt::UserRole + 1);
    if (usage() == "self" || usage() == "other") {
        if (individual >= 0) {
            customer_->setCurrentIndex(individual);
        }
        customer_->setEnabled(false);
    } else {
        const bool wasFixed = !customer_->isEnabled();
        customer_->setEnabled(true);
        if (usage() == "ticket_loan") {
            if (customer_->currentIndex() == individual) {
                customer_->setCurrentIndex(0); // a loan is to a customer who is chosen
            }
        } else if ((wasFixed || customer_->currentIndex() == 0) && individual >= 0) {
            customer_->setCurrentIndex(individual);
        }
    }
}

// The price of the fuel as downloaded; the cashier may change it for this sale.
void SpotSalePage::fuelChanged()
{
    const QVariant price = fuel_->itemData(fuel_->currentIndex(), Qt::UserRole + 1);
    price_->setText(price.isValid() ? Decimal::format(price.toLongLong(), 2) : QString());
    recalculate();
}

void SpotSalePage::priceEdited()
{
    recalculate();
}

void SpotSalePage::kgEdited()
{
    amountTypedLast_ = false;
    recalculate();
}

void SpotSalePage::amountEdited()
{
    amountTypedLast_ = true;
    recalculate();
}

// The field that was not typed follows from the one that was and the price.
void SpotSalePage::recalculate()
{
    qlonglong price = 0;
    if (!Decimal::parse(price_->text(), 2, &price) || price <= 0 || usage() != "sale") {
        return;
    }

    qlonglong value = 0;
    if (amountTypedLast_) {
        if (Decimal::parse(amount_->text(), 2, &value)) {
            kg_->setText(Decimal::format(SpotSale::kgFor(value, price), 2));
        }
    } else if (Decimal::parse(kg_->text(), 2, &value)) {
        amount_->setText(Decimal::format(SpotSale::amountFor(value, price), 2));
    } else {
        amount_->clear();
    }
}

void SpotSalePage::showStatus(const QString &message, bool ok)
{
    Theme::setState(status_, ok ? "ok" : "error");
    status_->setText(message);
}

void SpotSalePage::save()
{
    SpotSale::Input input;
    input.usage = usage();
    input.fuelType = fuel_->itemData(fuel_->currentIndex()).toString();
    input.customerId = customer_->itemData(customer_->currentIndex()).toInt();
    input.comment = comment_->text();
    input.receipt = receipt_->isChecked();

    qlonglong value = 0;
    input.priceX100 = Decimal::parse(price_->text(), 2, &value) ? value : -1;
    input.kgX100 = Decimal::parse(kg_->text(), 2, &value) ? value : 0;
    if (input.usage == "sale" && amountTypedLast_ && Decimal::parse(amount_->text(), 2, &value)) {
        input.amountX100 = value;
    }

    const SpotSale::Result result = SpotSale::create(db_, input, session_->userId(), QDateTime::currentDateTime());
    if (!result.ok) {
        showStatus(result.error, false);

        return;
    }

    savedMessage_ = input.usage == "sale"
        ? tr("Saved: %1 kg, amount %2. Ticket %3").arg(Decimal::format(result.kgX100, 2)).arg(Decimal::format(result.totalX100, 2)).arg(result.barcode)
        : tr("Saved: %1 kg. Ticket %2").arg(Decimal::format(result.kgX100, 2)).arg(result.barcode);
    lastSaleId_ = result.saleId;
    printAgain_->setEnabled(true);
    showStatus(savedMessage_, true);
    clearEntry(); // the next sale of the same kind starts with an empty weight; the last one stays on the screen
    chain_->focusWidget(kg_);
    emit saved();
    // what the sale gives is the setting of the Settings page: the ticket on paper, or the weight straight to a nozzle
    if (settings_->resultIsDirect(SettingKey::SpotSaleResult)) {
        setDirectly(input.fuelType, result.kgX100, result.barcode);
    } else {
        startPrint(); // the ticket is printed at once
    }
}

// No ticket on paper: the weight of the sale goes to a nozzle of its fuel type, like the tickets the supplier scans (see DirectSet).
void SpotSalePage::setDirectly(const QString &fuelType, qlonglong kgX100, const QString &barcode)
{
    QString message;
    const bool done = DirectSet::run(this, db_, bus_, config_, selection_, supply_, fuelType, kgX100, barcode, &message);
    showStatus(savedMessage_ + "\n" + message + (done ? QString() : " " + tr("Print again prints the ticket.")), done);
}

// The ticket of the last sale goes to the printer; the sale is already saved, so a printer that fails only gives a message.
void SpotSalePage::startPrint()
{
    if (lastSaleId_.isEmpty() || printer_->isBusy()) {
        return;
    }

    printing_ = true;
    printAgain_->setEnabled(false);
    showStatus(savedMessage_ + "\n" + tr("Printing..."), true);
    printer_->printSaleTicket(lastSaleId_);
}

void SpotSalePage::printAgain()
{
    startPrint();
}

void SpotSalePage::printFinished(bool ok, const QString &message)
{
    if (!printing_) {
        return; // another screen's print
    }

    printing_ = false;
    printAgain_->setEnabled(!lastSaleId_.isEmpty());
    showStatus(savedMessage_ + "\n" + (ok ? tr("The ticket was sent to the printer.") : tr("It was not printed: %1").arg(message)), ok);
}
