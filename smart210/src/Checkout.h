#ifndef CHECKOUT_H
#define CHECKOUT_H

#include <QDateTime>
#include <QList>
#include <QSqlDatabase>
#include <QString>
#include <QStringList>

// The checkout: records made on the terminal (sales, supplements, remainders, ticket loans, loan settlements) get a date, the
// business day they belong to. A business day runs from 9 AM to 9 AM, so a sale at 02:00 belongs to the day before. The web app confirms
// records by this date, so everything has it before it is uploaded.
//
//   manual   the cashier presses Checkout: everything not checked out yet is stamped, the current business day included
//   auto     at 9 AM (checked every minute, and when the app starts, which is the catch-up): the business days that are over are stamped
//   upload   Upload data checks out what is still open first
//
// Each record gets the business day of its own time, whenever the checkout runs. Only records made on this terminal that are not uploaded
// and not deleted are stamped. The cashier may move the records of a day to the next or previous day for one day after the checkout, while
// none of them is uploaded.
class Checkout
{
public:
    static const int StartHour = 9;

    struct Run {
        Run() : records(0) {}

        int records;
        QStringList dates; // the business days that got records
    };

    struct Line {
        QString label; // usage or fuel type
        int count;
        qlonglong kgX100;
        qlonglong amountX100;
    };

    struct Report {
        Report() : sales(0), salesKgX100(0), salesAmountX100(0), salesPaidX100(0), supplements(0), litersX100(0), supplementKgX100(0),
                   remainders(0), remainderKgX100(0), loans(0), loanKgX100(0), settlements(0), settlementKgX100(0) {}

        QString date;
        int sales;
        qlonglong salesKgX100;
        qlonglong salesAmountX100;
        qlonglong salesPaidX100;
        QList<Line> byUsage; // sale, self, other, ticket_loan
        QList<Line> byFuel;
        int supplements;
        qlonglong litersX100;
        qlonglong supplementKgX100;
        int remainders;
        qlonglong remainderKgX100;
        int loans;
        qlonglong loanKgX100;
        int settlements;
        qlonglong settlementKgX100;
    };

    // "2026-10-07" for 07:00 on 2026-10-08 as well as for 15:00 on 2026-10-07: the 9 AM to 9 AM business day.
    static QString businessDay(const QDateTime &time);

    // SQL for the date a record shows under and is uploaded under: its date, or the business day of its time while it has none.
    // (a sale keeps its date in sale_date, a supplement in supply_date, a remainder in remainder_date, the other records in date)
    static QString dateExpression(const QString &alias, const QString &timeColumn, const QString &dateColumn = QString("date"));

    // Stamps the open records. `includeCurrentDay` is for a manual checkout and the upload; the automatic one leaves the business day that
    // is still running. Writes the log (one line per business day) and returns what was stamped.
    static Run run(QSqlDatabase db, const QDateTime &now, const QString &mode, int userId, bool includeCurrentDay);

    // Records still waiting for a date (not deleted, not uploaded), and how many of them belong to a business day that is over.
    static int openRecords(QSqlDatabase db);
    static int overdueRecords(QSqlDatabase db, const QDateTime &now);

    // The business days that have checked-out records here, newest first.
    static QStringList days(QSqlDatabase db);

    static Report report(QSqlDatabase db, const QString &date);

    // Moving the records of a day to the previous (-1) or next (+1) one: only for a day checked out less than 24 hours ago whose records
    // are all still here and not uploaded. Returns the reason when not allowed, or an empty string.
    static QString shiftBlocker(QSqlDatabase db, const QString &date, const QDateTime &now);
    static bool shift(QSqlDatabase db, const QString &date, int days, const QDateTime &now, QString *error);
};

#endif
