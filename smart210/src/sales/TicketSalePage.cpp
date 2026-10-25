#include "TicketSalePage.h"

#include "Barcode.h"
#include "Decimal.h"
#include "EnterChain.h"
#include "Session.h"
#include "SpotSale.h"
#include "ui_TicketSalePage.h"
#include "Theme.h"

#include <QButtonGroup>
#include <QCheckBox>
#include <QComboBox>
#include <QDateTime>
#include <QEvent>
#include <QHeaderView>
#include <QIntValidator>
#include <QLabel>
#include <QLineEdit>
#include <QMessageBox>
#include <QPushButton>
#include <QRegExpValidator>
#include <QSqlQuery>
#include <QTableWidget>

namespace {

QTableWidgetItem *cell(const QString &text, bool right)
{
    QTableWidgetItem *item = new QTableWidgetItem(text);
    if (right) {
        item->setTextAlignment(Qt::AlignRight | Qt::AlignVCenter);
    }

    return item;
}

}

TicketSalePage::TicketSalePage(QSqlDatabase db, Session *session, QWidget *parent)
    : QWidget(parent), ui_(new Ui::TicketSalePage), db_(db), session_(session), kgX100_(0), tickets_(0)
{
    ui_->setupUi(this);

    // the customer, the fuel type and the price
    customer_ = ui_->tsCustomerCombo;
    fuel_ = ui_->tsFuelCombo;
    price_ = ui_->tsPriceField;
    price_->setValidator(new QRegExpValidator(QRegExp("[0-9]{0,9}([.][0-9]{0,2})?"), this));

    // the items: the add area at the left, the table at the right
    spec_ = ui_->tsSpecCombo;
    start_ = ui_->tsStartField;
    start_->setValidator(new QRegExpValidator(QRegExp("[0-9]{0,9}"), this));
    count_ = ui_->tsCountField;
    count_->setValidator(new QIntValidator(1, TicketSale::MaxTickets, this));
    end_ = ui_->tsEndField;
    result_ = ui_->tsCheckResult;

    items_ = ui_->tsItemsTable;
    items_->setColumnCount(5);
    items_->setHorizontalHeaderLabels(QStringList() << tr("Spec") << tr("Count") << tr("Start serial") << tr("End serial") << tr("kg"));
    items_->horizontalHeader()->setStretchLastSection(false);
    items_->viewport()->installEventFilter(this); // the width of the viewport is what the columns share
    items_->verticalHeader()->setVisible(false);
    items_->setEditTriggers(QAbstractItemView::NoEditTriggers);
    items_->setSelectionBehavior(QAbstractItemView::SelectRows);
    items_->setSelectionMode(QAbstractItemView::SingleSelection);

    // the total, the receipt, what the fuel is for and the comment
    totals_ = ui_->tsTotals;
    receipt_ = ui_->tsReceiptCheck;
    usage_ = ui_->tsUsageCombo; // the index of the choice is its number (0 sale, 1 self use, 2 other, 3 ticket loan)
    usage_->addItem(tr("Sale"));
    usage_->addItem(tr("Self use"));
    usage_->addItem(tr("Other"));
    usage_->addItem(tr("Ticket loan"));
    comment_ = ui_->tsCommentField;
    status_ = ui_->tsStatus;

    // Enter goes through the fields in the order of the form; the count keeps its own Enter (it adds the item)
    chain_ = new EnterChain(this);
    chain_->add(customer_);
    chain_->add(fuel_);
    chain_->add(price_);
    chain_->add(usage_);
    chain_->add(spec_);
    chain_->add(start_);
    chain_->add(count_, true);
    chain_->add(receipt_);
    chain_->add(comment_);
    chain_->add(ui_->saveTicketSaleButton);

    connect(fuel_, SIGNAL(activated(int)), this, SLOT(fuelChanged()));
    connect(spec_, SIGNAL(activated(int)), this, SLOT(specChanged()));
    connect(start_, SIGNAL(textEdited(QString)), this, SLOT(showEnd()));
    connect(count_, SIGNAL(textEdited(QString)), this, SLOT(showEnd()));
    connect(ui_->tsCheckButton, SIGNAL(clicked()), this, SLOT(check()));
    connect(ui_->tsAddButton, SIGNAL(clicked()), this, SLOT(addItem()));
    connect(count_, SIGNAL(returnPressed()), this, SLOT(addItem()));
    connect(ui_->tsClearButton, SIGNAL(clicked()), this, SLOT(clearItems()));
    connect(items_, SIGNAL(cellDoubleClicked(int,int)), this, SLOT(itemDoubleClicked(int)));
    connect(usage_, SIGNAL(currentIndexChanged(int)), this, SLOT(usageChanged()));
    connect(price_, SIGNAL(textEdited(QString)), this, SLOT(priceEdited()));
    connect(ui_->saveTicketSaleButton, SIGNAL(clicked()), this, SLOT(save()));
}

TicketSalePage::~TicketSalePage()
{
    delete ui_;
}

QString TicketSalePage::usage() const
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

QString TicketSalePage::fuelType() const
{
    return fuel_->itemData(fuel_->currentIndex()).toString();
}

void TicketSalePage::reload()
{
    customer_->clear();
    customer_->addItem(tr("Choose the customer"), 0);
    QSqlQuery customers(db_);
    customers.exec("SELECT id, name, is_self FROM customers WHERE deleted = 0 ORDER BY is_self DESC, name COLLATE NOCASE");
    while (customers.next()) {
        customer_->addItem(customers.value(1).toString(), customers.value(0).toInt());
        customer_->setItemData(customer_->count() - 1, customers.value(2).toInt(), Qt::UserRole + 1);
    }

    // the fuel types that have tickets in stock
    fuel_->clear();
    QSqlQuery fuels(db_);
    // (asked of each fuel type, one look at the index each: a DISTINCT over the tickets would read every ticket in stock)
    fuels.exec("SELECT f.fuel_type, f.name FROM fuel_types f WHERE EXISTS (SELECT 1 FROM tickets t WHERE t.fuel_type = f.fuel_type AND t.status = 0 "
               "AND t.spec IS NOT NULL) ORDER BY 2");
    while (fuels.next()) {
        fuel_->addItem(fuels.value(1).toString(), fuels.value(0).toString());
    }

    usage_->setCurrentIndex(0);
    receipt_->setChecked(false);
    comment_->clear();
    status_->clear();
    itemList_.clear();
    count_->setText("1");
    fuelChanged();
    usageChanged();
    chain_->focusWidget(customer_);
}

// A new fuel type: its price, and the items start again.
void TicketSalePage::fuelChanged()
{
    itemList_.clear();
    QSqlQuery fuel(db_);
    fuel.prepare("SELECT price FROM fuel_types WHERE fuel_type = ?");
    fuel.addBindValue(fuelType());
    price_->setText(!fuelType().isEmpty() && fuel.exec() && fuel.next() ? Decimal::format(fuel.value(0).toLongLong(), 2) : QString());
    status_->clear();
    fillSpecs();
    showItems();
}

// The specs of the fuel type that have tickets, each with how many are in stock.
void TicketSalePage::fillSpecs()
{
    spec_->clear();
    foreach (const TicketSale::SpecStock &one, TicketSale::specs(db_, fuelType())) {
        spec_->addItem(tr("%1 kg (%2)").arg(Decimal::format(one.kgX100, 2)).arg(one.count), one.spec);
    }
}

void TicketSalePage::specChanged()
{
    fillStart();
}

TicketSale::Item TicketSalePage::formItem() const
{
    TicketSale::Item item;
    item.spec = spec_->itemData(spec_->currentIndex()).toInt();
    item.quantity = count_->text().toInt();
    item.start = start_->text().toLongLong();

    return item;
}

// The start serial of the add area: the first one in stock of the chosen spec, after the items in the table.
void TicketSalePage::fillStart()
{
    start_->clear();
    if (spec_->count() > 0) {
        QMap<int, QList<TicketSale::Stock> > left;
        TicketSale::resolve(db_, fuelType(), itemList_, &left);
        const int spec = spec_->itemData(spec_->currentIndex()).toInt();
        // without items of this spec above, only the first ticket in stock is needed, not the whole stock
        const QList<TicketSale::Stock> inStock = left.contains(spec) ? left.value(spec) : TicketSale::stock(db_, fuelType(), spec, 1);
        if (!inStock.isEmpty()) {
            start_->setText(QString::number(inStock.first().serial()));
        }
    }
    showEnd();
}

// The end serial follows from the start serial and the count (without a start it is the end of the lowest ones in stock).
void TicketSalePage::showEnd()
{
    const TicketSale::Item item = formItem();
    if (item.start > 0 && item.quantity > 0) {
        end_->setText(QString::number(item.start + item.quantity - 1));
    } else {
        end_->clear();
        if (item.quantity > 0 && spec_->count() > 0) {
            QMap<int, QList<TicketSale::Stock> > left;
            TicketSale::resolve(db_, fuelType(), itemList_, &left);
            // the lowest ones in stock: only as many as the count says
            QList<TicketSale::Stock> inStock = left.contains(item.spec) ? left.value(item.spec) : TicketSale::stock(db_, fuelType(), item.spec, item.quantity);
            const TicketSale::Taken taken = TicketSale::take(&inStock, item.quantity, 0);
            if (taken.ok) {
                end_->setText(QString::number(taken.end));
            }
        }
    }
    result_->clear();
}

void TicketSalePage::showResult(const QString &message, bool ok)
{
    Theme::setState(result_, ok ? "ok" : "error");
    result_->setText(message);
}

// Can these serials be sold now? They must all be in stock and not taken by an item above.
void TicketSalePage::check()
{
    const TicketSale::Item item = formItem();
    if (spec_->count() == 0 || item.quantity < 1) {
        showResult(tr("There are not enough tickets of this spec in stock to sell."), false);

        return;
    }

    QMap<int, QList<TicketSale::Stock> > left;
    TicketSale::resolve(db_, fuelType(), itemList_, &left);
    QList<TicketSale::Stock> inStock = left.contains(item.spec) ? left.value(item.spec) : TicketSale::stock(db_, fuelType(), item.spec);
    const TicketSale::Taken taken = TicketSale::take(&inStock, item.quantity, item.start);
    if (taken.ok) {
        showResult(tr("Valid: serials %1 to %2 can be sold.").arg(taken.start).arg(taken.end), true);
    } else if (item.start > 0) {
        showResult(tr("Not valid: some tickets from %1 to %2 are not available to sell.").arg(item.start).arg(item.start + item.quantity - 1), false);
    } else {
        showResult(tr("There are not enough tickets of this spec in stock to sell."), false);
    }
}

// An item is added only when the check says all its serials are available.
void TicketSalePage::addItem()
{
    check();
    const TicketSale::Item item = formItem();
    if (item.quantity < 1 || spec_->count() == 0) {
        return;
    }
    if (tickets_ + item.quantity > TicketSale::MaxTickets) {
        showResult(tr("Too many tickets for one sale."), false);

        return;
    }

    QMap<int, QList<TicketSale::Stock> > left;
    TicketSale::resolve(db_, fuelType(), itemList_, &left);
    QList<TicketSale::Stock> inStock = left.contains(item.spec) ? left.value(item.spec) : TicketSale::stock(db_, fuelType(), item.spec);
    if (!TicketSale::take(&inStock, item.quantity, item.start).ok) {
        return;
    }

    itemList_ << item;
    count_->setText("1");
    status_->clear();
    showItems();
    chain_->focusWidget(spec_);            // the next item; one more Enter here, with nothing typed, goes on to the receipt
    chain_->jumpFrom(spec_, receipt_);
}

void TicketSalePage::showItems()
{
    if (itemList_.isEmpty()) {
        chain_->jumpFrom(spec_, 0);
    }
    items_->setRowCount(0);
    kgX100_ = 0;
    tickets_ = 0;

    const QList<TicketSale::Taken> taken = TicketSale::resolve(db_, fuelType(), itemList_);
    QMap<int, qlonglong> kgOf; // the weight of one ticket of each spec
    foreach (const TicketSale::SpecStock &one, TicketSale::specs(db_, fuelType())) {
        kgOf.insert(one.spec, one.kgX100);
    }
    for (int i = 0; i < itemList_.size(); ++i) {
        const int row = items_->rowCount();
        items_->insertRow(row);
        items_->setItem(row, 0, cell(Decimal::format(kgOf.value(itemList_.at(i).spec), 2) + " " + tr("kg"), false));
        items_->setItem(row, 1, cell(QString::number(itemList_.at(i).quantity), true));
        // the start and the end as full barcodes (the first and the last ticket of the item)
        items_->setItem(row, 2, cell(taken.at(i).ok ? Barcode::fromKey(taken.at(i).barcodes.first()) : QString("-"), true));
        items_->setItem(row, 3, cell(taken.at(i).ok ? Barcode::fromKey(taken.at(i).barcodes.last()) : QString("-"), true));
        items_->setItem(row, 4, cell(Decimal::format(taken.at(i).kgX100, 2), true));
        kgX100_ += taken.at(i).kgX100;
        tickets_ += itemList_.at(i).quantity;
    }
    items_->scrollToBottom();
    fillStart();
    showTotals();
}

bool TicketSalePage::eventFilter(QObject *watched, QEvent *event)
{
    if (watched == items_->viewport() && event->type() == QEvent::Resize) {
        static const int ratio[] = {1, 1, 2, 2, 1};
        const int width = items_->viewport()->width();
        int used = 0;
        for (int column = 0; column < 5; ++column) {
            const int w = column < 4 ? width * ratio[column] / 7 : width - used; // the last takes what is left
            items_->setColumnWidth(column, w);
            used += w;
        }
    }

    return QWidget::eventFilter(watched, event);
}

void TicketSalePage::removeItem(int row)
{
    if (row < 0 || row >= itemList_.size()) {
        return;
    }

    itemList_.removeAt(row);
    showItems();
}

// A double click on an item of the list: after a question that item is taken out.
void TicketSalePage::itemDoubleClicked(int row)
{
    if (row < 0 || row >= itemList_.size()) {
        return;
    }
    if (QMessageBox::question(this, tr("Remove the item"), tr("Remove this item from the list?"), QMessageBox::Yes | QMessageBox::No, QMessageBox::No) == QMessageBox::Yes) {
        removeItem(row);
    }
}

void TicketSalePage::clearItems()
{
    itemList_.clear();
    showItems();
}

// Own use and other use are always the customer "Individual" (the one the download marks), which cannot be changed.
void TicketSalePage::usageChanged()
{
    if (usage() == "self" || usage() == "other") {
        const int individual = customer_->findData(1, Qt::UserRole + 1);
        if (individual >= 0) {
            customer_->setCurrentIndex(individual);
        }
        customer_->setEnabled(false);
    } else {
        customer_->setEnabled(true);
        if (customer_->itemData(customer_->currentIndex(), Qt::UserRole + 1).toInt() == 1) {
            customer_->setCurrentIndex(0);
        }
    }
    showTotals();
}

void TicketSalePage::priceEdited()
{
    showTotals();
}

void TicketSalePage::showStatus(const QString &message, bool ok)
{
    Theme::setState(status_, ok ? "ok" : "error");
    status_->setText(message);
}

void TicketSalePage::showTotals()
{
    qlonglong price = 0;
    const bool priced = Decimal::parse(price_->text(), 2, &price);
    QString text = tr("%1 tickets, %2 kg").arg(tickets_).arg(Decimal::format(kgX100_, 2));
    if (usage() == "sale" && priced) {
        text += tr(", amount %1").arg(Decimal::format(SpotSale::amountFor(kgX100_, price), 2));
    }
    totals_->setText(text);
}

void TicketSalePage::save()
{
    TicketSale::Input input;
    input.usage = usage();
    input.fuelType = fuelType();
    input.customerId = customer_->itemData(customer_->currentIndex()).toInt();
    input.comment = comment_->text();
    input.receipt = receipt_->isChecked();

    qlonglong value = 0;
    input.priceX100 = Decimal::parse(price_->text(), 2, &value) ? value : -1;
    const QList<TicketSale::Taken> taken = TicketSale::resolve(db_, input.fuelType, itemList_);
    for (int i = 0; i < taken.size(); ++i) {
        if (!taken.at(i).ok) {
            showStatus(tr("Some tickets are not available to sell any more: remove the item and add it again."), false);

            return;
        }
        input.barcodes << taken.at(i).barcodes;

        TicketSale::Line line; // the item as entered, kept with the sale
        line.spec = itemList_.at(i).spec;
        line.quantity = itemList_.at(i).quantity;
        line.start = taken.at(i).start;
        line.end = taken.at(i).end;
        line.kgX100 = taken.at(i).kgX100;
        input.lines << line;
    }

    const TicketSale::Result result = TicketSale::create(db_, input, session_->userId(), QDateTime::currentDateTime());
    if (!result.ok) {
        showStatus(result.error, false);

        return;
    }

    const QString message = input.usage == "sale"
        ? tr("Saved: %1 tickets, %2 kg, amount %3").arg(result.tickets).arg(Decimal::format(result.kgX100, 2)).arg(Decimal::format(result.totalX100, 2))
        : tr("Saved: %1 tickets, %2 kg").arg(result.tickets).arg(Decimal::format(result.kgX100, 2));
    itemList_.clear(); // ready for the next customer; the use and customer stay
    comment_->clear();
    receipt_->setChecked(false);
    fillSpecs();       // the stock has changed
    showItems();
    showStatus(message, true);
    chain_->focusWidget(customer_);
    emit saved();
}
