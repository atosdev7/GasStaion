#include "SpotSalePage.h"

#include "BoardBus.h"
#include "Decimal.h"
#include "MachineConfig.h"
#include "NozzleDialog.h"
#include "PrintService.h"
#include "Session.h"
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

namespace {

enum { ResultPrint = 0, ResultDirect = 1 };

}

SpotSalePage::SpotSalePage(QSqlDatabase db, Session *session, PrintService *printer, BoardBus *bus, MachineConfig *config, SupplySelection *selection,
                           SupplySession *supply, QWidget *parent)
    : QWidget(parent), ui_(new Ui::SpotSalePage), db_(db), session_(session), printer_(printer), bus_(bus), config_(config), selection_(selection),
      supply_(supply), printing_(false), amountTypedLast_(false)
{
    ui_->setupUi(this);

    // what the fuel is for: the index of the choice is its number (0 sale, 1 self use, 2 other, 3 ticket loan)
    usage_ = ui_->usageCombo;
    usage_->addItem(tr("Sale"));
    usage_->addItem(tr("Self use"));
    usage_->addItem(tr("Other"));
    usage_->addItem(tr("Ticket loan"));

    // what the sale gives: the ticket on paper, or the weight straight to a nozzle (the index of the choice is the mode)
    result_ = ui_->resultCombo;
    result_->addItem(tr("Print ticket"));
    result_->addItem(tr("Set directly"));

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
}

void SpotSalePage::clearEntry()
{
    kg_->clear();
    amount_->clear();
    comment_->clear();
    receipt_->setChecked(false);
    amountTypedLast_ = false;
}

// Only a sale has an amount; own use goes to the "self" customer.
void SpotSalePage::usageChanged()
{
    const bool counts = (usage() == "sale");
    amount_->setEnabled(counts);
    if (!counts) {
        amount_->clear();
        amountTypedLast_ = false;
    }

    if (usage() == "self") {
        for (int i = 0; i < customer_->count(); ++i) {
            if (customer_->itemData(i, Qt::UserRole + 1).toInt() == 1) {
                customer_->setCurrentIndex(i);
            }
        }
        customer_->setEnabled(false);
    } else {
        customer_->setEnabled(true);
        if (customer_->itemData(customer_->currentIndex(), Qt::UserRole + 1).toInt() == 1) {
            customer_->setCurrentIndex(0); // coming back from "self use": choose again
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
    emit saved();
    if (result_->currentIndex() == ResultDirect) {
        setDirectly(input.fuelType, result.kgX100, result.barcode);
    } else {
        startPrint(); // the ticket is printed at once
    }
}

// No ticket on paper: the weight of the sale goes to a nozzle of its fuel type, like the tickets the supplier scans. The nozzle is chosen on
// its board first (the board's answer comes as currentNozzleChanged), then the ticket is queued and the fill started.
void SpotSalePage::setDirectly(const QString &fuelType, qlonglong kgX100, const QString &barcode)
{
    NozzleDialog dialog(db_, bus_, config_, fuelType, kgX100, this);
    if (dialog.exec() != QDialog::Accepted || dialog.nozzle() < 1) {
        showStatus(savedMessage_ + "\n" + tr("No nozzle was chosen, so nothing was sent. Print again prints the ticket."), false);

        return;
    }

    // The nozzle becomes the chosen one (the choice is kept by the Smart210; the board beeps), the ticket goes to its queue as when the supplier
    // scans it, and the session sends the weight as the preset (the dispenser shows it). The supplier takes the gun up and the fill starts.
    const int nozzle = dialog.nozzle();
    QString why;
    if (!selection_->select(nozzle, &why) || !supply_->add(nozzle, barcode, &why)) {
        showStatus(savedMessage_ + "\n" + why + " " + tr("Print again prints the ticket."), false);

        return;
    }
    showStatus(savedMessage_ + "\n" + tr("Nozzle %1: %2 kg are set. Take the gun up to start the fill.").arg(nozzle).arg(Decimal::format(kgX100, 2)), true);
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
