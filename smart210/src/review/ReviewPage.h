#ifndef REVIEWPAGE_H
#define REVIEWPAGE_H

#include "ReviewData.h"

#include <QDate>
#include <QPointer>
#include <QSqlDatabase>
#include <QWidget>

class QDateEdit;
class QLabel;
class QPushButton;
class QTabWidget;
class QTableWidget;
class QTimer;
class PrintService;
class Session;
class Uploader;
class ReviewUpdateDialog;

namespace Ui {
class ReviewPage;
}

// Review: the cashier checks the records of a date before they go up. The date picker chooses the day; the tabs show its Sales
// (spot and ticket, with the totals paid and unpaid), Supplies and Ticket Debt (loans and settlements). On the selected records the cashier
// may change the comment and the date (a record made near 9 AM may belong to the day before or after) with the Update button, which opens a window with those fields; a changed
// record waits for the upload again. Upload data sends everything that waits, from all dates, and only that. A mistaken spot sale is deleted here too (Delete
// sale on the Sales tab, asked twice; the rules are those of SaleDeletion): the cashier enters the correct sale and then deletes the wrong one.
// Print ticket prints the ticket of the selected record again: the ticket of a spot sale (Sales tab) or the remainder ticket a supply made (Supplies tab).
class ReviewPage : public QWidget
{
    Q_OBJECT

public:
    ReviewPage(QSqlDatabase db, Uploader *uploader, Session *session, PrintService *printer, QWidget *parent = 0);
    ~ReviewPage();

    // Called whenever the page is opened (and after an upload or an edit). The date stays what the cashier chose; it starts as today's
    // business day.
    void reload();

    QDate viewDate() const;
    void setViewDate(const QDate &date);

signals:
    void uploaded(); // an upload finished: what waits has changed
    void edited();   // a comment or a date was changed: records wait for the upload again
    void showNoReceiptChanged(bool show); // F8 turned the listing of the sales without a receipt on or off (the setting of Machine Config): the program logs it
    void printRemainderRequested(const QString &barcode); // the remainder ticket of a supply is to be printed again (the program prints it and marks it printed)

private slots:
    void toggleNoReceipt();   // F8, a hidden shortcut: the same setting as the box on Machine Config
    void dateChanged();
    void tabChanged();
    void selectionChanged();
    void updateRecord();     // the Update button: the window with the fields
    void applyUpdate(const QString &comment, const QDate &date);   // its Update button
    void printTicket();
    void printFinished(bool ok, const QString &message);
    void deleteSale();
    void resetDelete();
    void upload();
    void uploadFinished(bool ok, const QString &message);

private:
    Ui::ReviewPage *ui_;
    void prepareTable(QTableWidget *table, const QStringList &headers);
    QTableWidget *currentTable() const;
    QList<ReviewData::Ref> selected() const;
    void fillRecords(QTableWidget *table, const QList<ReviewData::Record> &records);
    void reselect(const QStringList &ids);
    void showStatus(const QString &message, bool ok);

    QSqlDatabase db_;
    Uploader *uploader_;
    Session *session_;
    PrintService *printer_;
    QDateEdit *date_;
    QLabel *dayInfo_;
    QPushButton *uploadButton_;
    QLabel *uploadStatus_;
    QTabWidget *tabs_;
    QLabel *salesTotals_;
    QLabel *supplyTotals_;
    QLabel *debtTotals_;
    QTableWidget *sales_;
    QTableWidget *supplies_;
    QTableWidget *debts_;
    QLabel *selectedLabel_;
    QPushButton *updateButton_;
    QPointer<ReviewUpdateDialog> updateDialog_;   // the window of the Update button while it is open
    QPushButton *printTicket_;
    QPushButton *deleteSale_;
    QTimer *deleteTimer_;   // the first press of Delete sale only asks: the question is taken back after this long
    QLabel *status_;
    bool loading_;
    bool printing_;   // a ticket of a sale is being sent by this page
};

#endif
