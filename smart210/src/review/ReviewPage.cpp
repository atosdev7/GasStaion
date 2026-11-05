#include "ReviewPage.h"

#include "Checkout.h"
#include "Decimal.h"
#include "PrintService.h"
#include "SaleDeletion.h"
#include "Session.h"
#include "Settings.h"
#include "Uploader.h"
#include "ReviewUpdateDialog.h"
#include "ui_ReviewPage.h"
#include "Theme.h"

#include <QBrush>
#include <QDateEdit>
#include <QDateTime>
#include <QHeaderView>
#include <QLabel>
#include <QLineEdit>
#include <QKeySequence>
#include <QPushButton>
#include <QShortcut>
#include <QSet>
#include <QTabWidget>
#include <QTimer>
#include <QTableWidget>

namespace {

const int IdRole = Qt::UserRole;
const int KindRole = Qt::UserRole + 1;
const int PrintRole = Qt::UserRole + 2; // what the Print ticket button prints for the record: "sale" (its own ticket) or "remainder:<16 digits>"; empty: no ticket

QString money(qlonglong value)
{
    return Decimal::format(value, 2);
}

QString shortTime(const QString &time)
{
    return time.mid(5, 11); // "MM-dd HH:mm"
}

QString useText(const QString &use)
{
    return use == "sale" ? QObject::tr("Sale") : use == "self" ? QObject::tr("Self use") : use == "other" ? QObject::tr("Other") : QObject::tr("Ticket loan");
}

}

ReviewPage::ReviewPage(QSqlDatabase db, Uploader *uploader, Session *session, PrintService *printer, QWidget *parent)
    : QWidget(parent), ui_(new Ui::ReviewPage), db_(db), uploader_(uploader), session_(session), printer_(printer), loading_(false), printing_(false)
{
    ui_->setupUi(this);

    date_ = ui_->reviewDate;
    date_->setDate(QDate::fromString(Checkout::businessDay(QDateTime::currentDateTime()), "yyyy-MM-dd"));
    dayInfo_ = ui_->reviewDayInfo;
    uploadButton_ = ui_->uploadButton;
    uploadStatus_ = ui_->uploadStatus; // shown after an upload

    sales_ = ui_->reviewSalesTable;
    prepareTable(sales_, QStringList() << tr("Time") << tr("Type") << tr("Customer") << tr("Fuel") << tr("kg") << tr("Amount")
                                       << tr("Comment") << tr("Date") << tr("State"));
    supplies_ = ui_->reviewSupplyTable;
    prepareTable(supplies_, QStringList() << tr("Time") << tr("Nozzle") << tr("Fuel") << tr("Litres") << tr("kg") << tr("Preset kg")
                                          << tr("Manual") << tr("Comment") << tr("Date") << tr("State"));
    debts_ = ui_->reviewDebtTable;
    prepareTable(debts_, QStringList() << tr("Time") << tr("Record") << tr("Customer") << tr("Fuel") << tr("kg") << tr("Ticket")
                                       << tr("Comment") << tr("Date") << tr("State"));

    salesTotals_ = ui_->reviewSalesTotals;
    supplyTotals_ = ui_->reviewSupplyTotals;
    debtTotals_ = ui_->reviewDebtTotals;
    tabs_ = ui_->reviewTabs;

    // what the cashier may change on the selected record: the Update button opens the window with its comment and date
    selectedLabel_ = ui_->reviewSelected;
    updateButton_ = ui_->updateButton;
    printTicket_ = ui_->printTicketButton;
    deleteSale_ = ui_->deleteSaleButton;
    deleteTimer_ = new QTimer(this);
    deleteTimer_->setSingleShot(true);
    deleteTimer_->setInterval(6000);
    status_ = ui_->reviewStatus;

    connect(date_, SIGNAL(dateChanged(QDate)), this, SLOT(dateChanged()));
    connect(tabs_, SIGNAL(currentChanged(int)), this, SLOT(tabChanged()));
    connect(sales_, SIGNAL(itemSelectionChanged()), this, SLOT(selectionChanged()));
    connect(supplies_, SIGNAL(itemSelectionChanged()), this, SLOT(selectionChanged()));
    connect(debts_, SIGNAL(itemSelectionChanged()), this, SLOT(selectionChanged()));
    connect(updateButton_, SIGNAL(clicked()), this, SLOT(updateRecord()));
    connect(printTicket_, SIGNAL(clicked()), this, SLOT(printTicket()));
    connect(printer_, SIGNAL(finished(bool,QString)), this, SLOT(printFinished(bool,QString)));
    connect(deleteSale_, SIGNAL(clicked()), this, SLOT(deleteSale()));
    connect(deleteTimer_, SIGNAL(timeout()), this, SLOT(resetDelete()));
    connect(uploadButton_, SIGNAL(clicked()), this, SLOT(upload()));
    connect(uploader_, SIGNAL(uploadFinished(bool,QString)), this, SLOT(uploadFinished(bool,QString)));

    // F8 turns the listing of the sales without a receipt on and off, as the box on Machine Config does. A hidden shortcut: nothing on the screen names it. It
    // works while this page is shown (the setting is only used here).
    QShortcut *toggle = new QShortcut(QKeySequence(Qt::Key_F8), this);
    connect(toggle, SIGNAL(activated()), this, SLOT(toggleNoReceipt()));

    selectionChanged();
}

ReviewPage::~ReviewPage()
{
    delete ui_;
}

void ReviewPage::toggleNoReceipt()
{
    Settings settings(db_);
    const bool show = !settings.boolValue(SettingKey::ShowSalesWithoutReceipt, true);

    if (!settings.setBool(SettingKey::ShowSalesWithoutReceipt, show)) {
        showStatus(tr("The setting could not be saved."), false);

        return;
    }
    reload();   // the list and the line that says how many are left out follow the setting
    emit showNoReceiptChanged(show);
}

void ReviewPage::prepareTable(QTableWidget *table, const QStringList &headers)
{
    table->setColumnCount(headers.size());
    table->setHorizontalHeaderLabels(headers);
    table->verticalHeader()->setVisible(false);
    table->setEditTriggers(QAbstractItemView::NoEditTriggers);
    table->setSelectionBehavior(QAbstractItemView::SelectRows);
    table->setSelectionMode(QAbstractItemView::SingleSelection);
    table->setFocusPolicy(Qt::NoFocus);
}

QDate ReviewPage::viewDate() const
{
    return date_->date();
}

void ReviewPage::setViewDate(const QDate &date)
{
    date_->setDate(date); // reloads
}

QTableWidget *ReviewPage::currentTable() const
{
    switch (tabs_->currentIndex()) {
    case 1:
        return supplies_;
    case 2:
        return debts_;
    }

    return sales_;
}

QList<ReviewData::Ref> ReviewPage::selected() const
{
    QList<ReviewData::Ref> refs;
    QTableWidget *table = currentTable();
    QSet<int> rows;
    foreach (const QModelIndex &index, table->selectionModel()->selectedRows()) {
        rows << index.row();
    }
    foreach (int row, rows) {
        QTableWidgetItem *item = table->item(row, 0);
        if (item) {
            refs << ReviewData::Ref(ReviewData::Kind(item->data(KindRole).toInt()), item->data(IdRole).toString());
        }
    }

    return refs;
}

// The records in a table; the columns of each table are given by the kind of record.
void ReviewPage::fillRecords(QTableWidget *table, const QList<ReviewData::Record> &records)
{
    table->setRowCount(0);
    table->setRowCount(records.size());

    for (int i = 0; i < records.size(); ++i) {
        const ReviewData::Record &r = records.at(i);
        QStringList cells;
        QList<int> right;

        if (r.kind == ReviewData::Sale) {
            cells << shortTime(r.time) << (r.saleType == "spot" ? tr("Spot") : (r.saleType == "card" ? tr("Card") : tr("Ticket"))) + " / " + useText(r.use) << r.customer << r.fuel << money(r.kgX100)
                  << money(r.amountX100);
            right << 4 << 5;
        } else if (r.kind == ReviewData::Supplement) {
            cells << shortTime(r.time) << r.nozzle << r.fuel << money(r.litersX100) << money(r.kgX100) << money(r.presetKgX100) << (r.manual ? tr("Yes") : tr("No"));
            right << 3 << 4 << 5;
        } else {
            cells << shortTime(r.time) << (r.kind == ReviewData::Loan ? tr("Loan") : tr("Settlement")) << r.customer << r.fuel << money(r.kgX100) << r.ticket;
            right << 4;
        }
        cells << r.comment << r.date;
        cells << (r.state == ReviewData::Refused ? tr("Refused") : r.state == ReviewData::Waiting ? tr("Waiting") : tr("Uploaded"));

        const int dateColumn = cells.size() - 2;
        const int stateColumn = cells.size() - 1;
        for (int column = 0; column < cells.size(); ++column) {
            QTableWidgetItem *item = new QTableWidgetItem(cells.at(column));
            if (right.contains(column)) {
                item->setTextAlignment(Qt::AlignRight | Qt::AlignVCenter);
            }
            if (column == 0) {
                item->setData(IdRole, r.id);
                item->setData(KindRole, int(r.kind));
                if (r.kind == ReviewData::Sale && r.saleType == "spot") {
                    item->setData(PrintRole, QString("sale"));
                } else if (r.kind == ReviewData::Supplement && !r.remainderTicket.isEmpty()) {
                    item->setData(PrintRole, "remainder:" + r.remainderTicket);
                }
            }
            if (column == dateColumn && r.nearShiftLine) {
                item->setBackground(QBrush(QColor("#fef3c7")));
                item->setToolTip(tr("Made near 9:00: check that the date is right."));
            }
            if (column == stateColumn) {
                item->setForeground(QBrush(r.state == ReviewData::Refused ? QColor("#b91c1c") : r.state == ReviewData::Waiting ? QColor("#b45309") : QColor("#15803d")));
                if (r.state == ReviewData::Refused) {
                    item->setToolTip(r.refusal);
                }
            }
            table->setItem(i, column, item);
        }
    }
    // every column as wide as its text, the comment takes what is left, so that the state at the right is always in view
    QHeaderView *header = table->horizontalHeader();
    header->setStretchLastSection(false);
    header->setSectionResizeMode(QHeaderView::ResizeToContents);
    header->setSectionResizeMode(table->columnCount() - 3, QHeaderView::Stretch);
}

void ReviewPage::reselect(const QStringList &ids)
{
    QTableWidget *table = currentTable();
    table->clearSelection();
    for (int row = 0; row < table->rowCount(); ++row) {
        if (ids.contains(table->item(row, 0)->data(IdRole).toString())) {
            table->selectRow(row);
        }
    }
}

void ReviewPage::reload()
{
    QStringList keep;
    foreach (const ReviewData::Ref &ref, selected()) {
        keep << ref.id;
    }

    loading_ = true;
    const QString date = date_->date().toString("yyyy-MM-dd");

    // Machine Config can hide the sales without a receipt; the totals below still count them, and a line says how many are left out
    const bool showNoReceipt = Settings(db_).boolValue(SettingKey::ShowSalesWithoutReceipt, true);
    fillRecords(sales_, ReviewData::sales(db_, date, showNoReceipt));
    fillRecords(supplies_, ReviewData::supplements(db_, date));
    fillRecords(debts_, ReviewData::debts(db_, date));

    const ReviewData::Totals t = ReviewData::totals(db_, date);
    salesTotals_->setText(tr("Spot sales: %1 records, %2 kg, amount %3\nTicket sales: %4 records, %5 kg, amount %6\nCard sales: %7 records, %8 kg, amount %9\nTotal paid: %10, unpaid: %11")
                              .arg(t.spotCount).arg(money(t.spotKgX100)).arg(money(t.spotAmountX100)).arg(t.ticketCount).arg(money(t.ticketKgX100))
                              .arg(money(t.ticketAmountX100)).arg(t.cardCount).arg(money(t.cardKgX100)).arg(money(t.cardAmountX100))
                              .arg(money(t.paidX100)).arg(money(t.unpaidX100)));
    const int hidden = showNoReceipt ? 0 : ReviewData::salesWithoutReceipt(db_, date);
    if (hidden > 0) {
        salesTotals_->setText(salesTotals_->text() + "\n" + tr("%1 sales without a receipt are not listed (Machine Config).").arg(hidden));
    }
    // the total of the supplies, then the total of each fuel type (gasoline and diesel), then the remainders
    QString supplyText = tr("Supplies: %1 records, %2 litres, %3 kg").arg(t.supplements).arg(money(t.litersX100)).arg(money(t.supplementKgX100));
    foreach (const ReviewData::FuelTotal &f, t.supplementsByFuel) {
        supplyText += "\n" + tr("%1: %2 records, %3 litres, %4 kg").arg(f.name).arg(f.count).arg(money(f.litersX100)).arg(money(f.kgX100));
    }
    supplyText += "\n" + tr("Remainders: %1 records, %2 kg").arg(t.remainders).arg(money(t.remainderKgX100));
    supplyTotals_->setText(supplyText);
    debtTotals_->setText(tr("Ticket loans: %1 records, %2 kg\nSettlements: %3 records, %4 kg")
                             .arg(t.loans).arg(money(t.loanKgX100)).arg(t.settlements).arg(money(t.settlementKgX100)));

    QString info;
    if (t.refused > 0) {
        info = tr("%1 waiting, %2 refused").arg(t.waiting).arg(t.refused);
    } else if (t.waiting > 0) {
        info = tr("%1 waiting for upload").arg(t.waiting);
    } else {
        info = tr("Nothing waits on this date");
    }
    dayInfo_->setText(info);
    Theme::setState(dayInfo_, t.refused > 0 ? "error" : (t.waiting > 0 ? "warning" : "ok"));

    // Upload data sends what waits on the chosen date
    const int waiting = Uploader::pendingTotal(db_, date);
    uploadButton_->setText(waiting > 0 ? tr("Upload data (%1 waiting)").arg(waiting) : tr("Upload data"));
    uploadButton_->setEnabled(waiting > 0 && !uploader_->isRunning());
    loading_ = false;

    reselect(keep);
    selectionChanged();
}

void ReviewPage::dateChanged()
{
    if (!loading_) {
        status_->clear();
        reload();
    }
}

void ReviewPage::tabChanged()
{
    status_->clear();
    selectionChanged();
}

// What is selected: one record at a time. Update opens the window with its comment and date.
void ReviewPage::selectionChanged()
{
    if (loading_) {
        return;
    }
    const QList<ReviewData::Ref> refs = selected();
    selectedLabel_->setText(refs.isEmpty() ? tr("Select a record") : tr("1 selected"));
    updateButton_->setEnabled(!refs.isEmpty());

    // a mistaken spot sale can be deleted: the reason is said at once when it cannot
    resetDelete();
    bool deletable = false;
    if (refs.size() == 1 && refs.first().kind == ReviewData::Sale) {
        const QString why = SaleDeletion::blocker(db_, refs.first().id, session_->userId());
        deletable = why.isEmpty();
        if (!deletable) {
            showStatus(why, false);
        }
    }
    deleteSale_->setEnabled(deletable);

    QTableWidget *table = currentTable();
    QString printable;
    if (refs.size() == 1) {
        printable = table->item(table->selectionModel()->selectedRows().first().row(), 0)->data(PrintRole).toString();
    }
    printTicket_->setEnabled(!printable.isEmpty() && !printing_);
    printTicket_->setToolTip(refs.size() == 1 && printable.isEmpty()
                                 ? tr("This record has no ticket to print: a spot sale and a supply with a remainder ticket have one.") : QString());
}

void ReviewPage::showStatus(const QString &message, bool ok)
{
    Theme::setState(status_, ok ? "ok" : "error");
    status_->setText(message);
}

// The ticket of the selected record goes to the printer again: the ticket of a spot sale, or the remainder ticket of a supply (the program prints that one and
// marks it printed, as on the Remainders screen).
void ReviewPage::printTicket()
{
    QTableWidget *table = currentTable();
    const QModelIndexList rows = table->selectionModel()->selectedRows();
    if (rows.size() != 1 || printing_) {
        return;
    }
    QTableWidgetItem *item = table->item(rows.first().row(), 0);
    const QString what = item->data(PrintRole).toString();
    if (what.isEmpty()) {
        return;
    }
    if (printer_->isBusy()) {
        showStatus(tr("The printer is busy. Try again in a moment."), false);

        return;
    }

    if (what.startsWith("remainder:")) {
        emit printRemainderRequested(what.mid(10));
        showStatus(tr("The remainder ticket %1 is being printed.").arg(what.mid(10)), true);

        return;
    }

    printing_ = true;
    printTicket_->setEnabled(false);
    showStatus(tr("Printing..."), true);
    printer_->printSaleTicket(item->data(IdRole).toString());
}

void ReviewPage::printFinished(bool ok, const QString &message)
{
    if (!printing_) {
        return; // another screen's print
    }

    printing_ = false;
    showStatus(ok ? tr("The ticket was sent to the printer.") : tr("It was not printed: %1").arg(message), ok);
    selectionChanged();
}

// The first press only asks ("Confirm delete" for six seconds); the second deletes the sale (SaleDeletion).
void ReviewPage::deleteSale()
{
    const QList<ReviewData::Ref> refs = selected();
    if (refs.size() != 1 || refs.first().kind != ReviewData::Sale) {
        return;
    }
    if (!deleteTimer_->isActive()) {
        deleteSale_->setText(tr("Confirm delete"));
        deleteTimer_->start();

        return;
    }

    deleteTimer_->stop();
    QString error;
    if (!SaleDeletion::remove(db_, refs.first().id, session_->userId(), QDateTime::currentDateTime(), &error)) {
        resetDelete();
        showStatus(error, false);

        return;
    }

    reload();
    showStatus(tr("The sale was deleted. It will never be uploaded."), true);
    emit edited();
}

void ReviewPage::resetDelete()
{
    deleteTimer_->stop();
    deleteSale_->setText(tr("Delete sale"));
}

// The Update button: a window with the comment and the date of the selected record, to change.
void ReviewPage::updateRecord()
{
    const QList<ReviewData::Ref> refs = selected();
    if (refs.size() != 1 || (!updateDialog_.isNull() && updateDialog_->isVisible())) {
        return;                  // (a window that was closed is deleted a moment later: only an open one counts)
    }
    QTableWidget *table = currentTable();
    const int row = table->selectionModel()->selectedRows().first().row();
    const int columns = table->columnCount();
    QStringList about;
    for (int column = 0; column < columns - 3; ++column) {
        if (!table->item(row, column)->text().isEmpty()) {
            about << table->item(row, column)->text();
        }
    }

    ReviewUpdateDialog *dialog = new ReviewUpdateDialog(about.join("  "), table->item(row, columns - 3)->text(),
                                                         QDate::fromString(table->item(row, columns - 2)->text(), "yyyy-MM-dd"), ReviewData::MaxComment, this);
    dialog->setAttribute(Qt::WA_DeleteOnClose);
    dialog->setWindowModality(Qt::ApplicationModal);
    connect(dialog, SIGNAL(applyRequested(QString,QDate)), this, SLOT(applyUpdate(QString,QDate)));
    updateDialog_ = dialog;
    dialog->show();
}

// The Update button of the window: the comment and the date go to the record. A date that is not allowed is said in the window, which stays open; otherwise the window closes.
void ReviewPage::applyUpdate(const QString &comment, const QDate &newDate)
{
    const QList<ReviewData::Ref> refs = selected();
    if (refs.isEmpty()) {
        if (!updateDialog_.isNull()) {
            updateDialog_->reject();
        }

        return;
    }
    QTableWidget *table = currentTable();
    const int row = table->selectionModel()->selectedRows().first().row();
    const QString oldDate = table->item(row, table->columnCount() - 2)->text();
    const QString date = newDate.toString("yyyy-MM-dd");
    const bool dateChanges = date != oldDate;
    if (dateChanges) {
        const QString problem = ReviewData::dateProblem(db_, date, QDateTime::currentDateTime());
        if (!problem.isEmpty()) {
            if (!updateDialog_.isNull()) {
                updateDialog_->showProblem(problem);
            }

            return;
        }
    }
    const int commentChanged = ReviewData::setComment(db_, refs, comment);
    const int dateChanged = dateChanges ? ReviewData::setDate(db_, refs, date) : 0;

    reload();
    if (commentChanged > 0 && dateChanged > 0) {
        showStatus(date == date_->date().toString("yyyy-MM-dd")
                       ? tr("The record was updated. It will be uploaded again.")
                       : tr("The record was updated and moved to %1. It will be uploaded again.").arg(date),
                   true);
    } else if (commentChanged > 0) {
        showStatus(tr("The comment was saved. The record will be uploaded again."), true);
    } else if (dateChanged > 0) {
        showStatus(date == date_->date().toString("yyyy-MM-dd")
                       ? tr("The date was set. The record will be uploaded again.")
                       : tr("The record was moved to %1. It will be uploaded again.").arg(date),
                   true);
    } else {
        showStatus(tr("Nothing changed."), true);
    }
    if (commentChanged > 0 || dateChanged > 0) {
        emit edited();
    }
    if (!updateDialog_.isNull()) {
        updateDialog_->accept();
    }
}

void ReviewPage::upload()
{
    uploadButton_->setEnabled(false);
    // Everything that leaves the terminal has its date: what is still open is checked out first.
    Checkout::run(db_, QDateTime::currentDateTime(), "upload", session_->userId(), true);
    Theme::setState(uploadStatus_, "");
    uploadStatus_->setVisible(true);
    uploadStatus_->setText(tr("Uploading..."));
    uploader_->upload(date_->date().toString("yyyy-MM-dd")); // only what waits on the chosen date
}

void ReviewPage::uploadFinished(bool ok, const QString &message)
{
    Theme::setState(uploadStatus_, ok && uploader_->rejectedCount() == 0 ? "ok" : "error");
    uploadStatus_->setText(ok ? message : tr("The upload did not finish: %1 Nothing is lost; press Upload data again.").arg(message));
    reload();
    emit uploaded();
}
