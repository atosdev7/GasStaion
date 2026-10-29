#include "ReviewPage.h"

#include "Checkout.h"
#include "Decimal.h"
#include "SaleDeletion.h"
#include "Session.h"
#include "Settings.h"
#include "Uploader.h"
#include "ui_ReviewPage.h"
#include "Theme.h"

#include <QBrush>
#include <QDateEdit>
#include <QDateTime>
#include <QHeaderView>
#include <QLabel>
#include <QLineEdit>
#include <QPushButton>
#include <QSet>
#include <QTabWidget>
#include <QTimer>
#include <QTableWidget>

namespace {

const int IdRole = Qt::UserRole;
const int KindRole = Qt::UserRole + 1;

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

ReviewPage::ReviewPage(QSqlDatabase db, Uploader *uploader, Session *session, QWidget *parent)
    : QWidget(parent), ui_(new Ui::ReviewPage), db_(db), uploader_(uploader), session_(session), loading_(false)
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

    // what the cashier may change on the selected records
    selectedLabel_ = ui_->reviewSelected;
    comment_ = ui_->reviewComment;
    comment_->setMaxLength(ReviewData::MaxComment);
    saveComment_ = ui_->saveCommentButton;
    newDate_ = ui_->reviewSetDate;
    newDate_->setDate(date_->date());
    setDate_ = ui_->setDateButton;
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
    connect(saveComment_, SIGNAL(clicked()), this, SLOT(saveComment()));
    connect(setDate_, SIGNAL(clicked()), this, SLOT(setDate()));
    connect(deleteSale_, SIGNAL(clicked()), this, SLOT(deleteSale()));
    connect(deleteTimer_, SIGNAL(timeout()), this, SLOT(resetDelete()));
    connect(uploadButton_, SIGNAL(clicked()), this, SLOT(upload()));
    connect(uploader_, SIGNAL(uploadFinished(bool,QString)), this, SLOT(uploadFinished(bool,QString)));

    selectionChanged();
}

ReviewPage::~ReviewPage()
{
    delete ui_;
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
    salesTotals_->setText(tr("Spot sales: %1 records, %2 kg, amount %3\nTicket sales: %4 records, %5 kg, amount %6\nTotal paid: %7, unpaid: %8")
                              .arg(t.spotCount).arg(money(t.spotKgX100)).arg(money(t.spotAmountX100)).arg(t.ticketCount).arg(money(t.ticketKgX100))
                              .arg(money(t.ticketAmountX100)).arg(money(t.paidX100)).arg(money(t.unpaidX100)));
    const int hidden = showNoReceipt ? 0 : ReviewData::salesWithoutReceipt(db_, date);
    if (hidden > 0) {
        salesTotals_->setText(salesTotals_->text() + "\n" + tr("%1 sales without a receipt are not listed (Machine Config).").arg(hidden));
    }
    supplyTotals_->setText(tr("Supplies: %1 records, %2 litres, %3 kg\nRemainders: %4 records, %5 kg")
                               .arg(t.supplements).arg(money(t.litersX100)).arg(money(t.supplementKgX100)).arg(t.remainders).arg(money(t.remainderKgX100)));
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
        newDate_->setDate(date_->date());
        status_->clear();
        reload();
    }
}

void ReviewPage::tabChanged()
{
    status_->clear();
    selectionChanged();
}

// What is selected: one record at a time. Its comment and date are in the fields, ready to change.
void ReviewPage::selectionChanged()
{
    if (loading_) {
        return;
    }
    const QList<ReviewData::Ref> refs = selected();
    selectedLabel_->setText(refs.isEmpty() ? tr("Select a record") : tr("1 selected"));
    saveComment_->setEnabled(!refs.isEmpty());
    setDate_->setEnabled(!refs.isEmpty());

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
    if (refs.size() == 1) {
        const int row = table->selectionModel()->selectedRows().first().row();
        const int columns = table->columnCount();
        comment_->setText(table->item(row, columns - 3)->text());
        newDate_->setDate(QDate::fromString(table->item(row, columns - 2)->text(), "yyyy-MM-dd"));
    } else if (refs.isEmpty()) {
        comment_->clear();
        newDate_->setDate(date_->date());
    }
}

void ReviewPage::showStatus(const QString &message, bool ok)
{
    Theme::setState(status_, ok ? "ok" : "error");
    status_->setText(message);
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

void ReviewPage::saveComment()
{
    const QList<ReviewData::Ref> refs = selected();
    if (refs.isEmpty()) {
        return;
    }
    const int changed = ReviewData::setComment(db_, refs, comment_->text());

    reload();
    if (changed > 0) {
        showStatus(tr("The comment was saved. The record will be uploaded again."), true);
        emit edited();
    } else {
        showStatus(tr("Nothing changed."), true);
    }
}

void ReviewPage::setDate()
{
    const QList<ReviewData::Ref> refs = selected();
    if (refs.isEmpty()) {
        return;
    }
    const QString date = newDate_->date().toString("yyyy-MM-dd");
    const QString problem = ReviewData::dateProblem(db_, date, QDateTime::currentDateTime());
    if (!problem.isEmpty()) {
        showStatus(problem, false);

        return;
    }
    const int changed = ReviewData::setDate(db_, refs, date);

    reload();
    if (changed > 0) {
        showStatus(date == date_->date().toString("yyyy-MM-dd")
                       ? tr("The date was set. The record will be uploaded again.")
                       : tr("The record was moved to %1. It will be uploaded again.").arg(date),
                   true);
        emit edited();
    } else {
        showStatus(tr("Nothing changed."), true);
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
