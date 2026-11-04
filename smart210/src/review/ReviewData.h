#ifndef REVIEWDATA_H
#define REVIEWDATA_H

#include <QDateTime>
#include <QList>
#include <QSqlDatabase>
#include <QString>

// What the Review screen shows and edits: the records made on this terminal for one date (sales, supplements, remainders, ticket
// loans and loan settlements), and the two things the cashier may change on a record: its comment and its date. A record that was
// changed is marked "not uploaded" again, so the next upload sends it once more (the web app stores a record by its id, so it is updated).
//
// A record without a date yet belongs to the business day of its own time (9 AM to 9 AM, see Checkout), so it shows under that
// date; the checkout fills the date in later. A record made within ReviewData::GraceMinutes of the 9 AM line may belong to either day: it
// is marked (nearShiftLine) so that the cashier checks its date before the upload.
class ReviewData
{
public:
    enum Kind { Sale, Supplement, Loan, Settlement };
    enum State { Waiting, Uploaded, Refused };

    static const int GraceMinutes = 30; // either side of the 9 AM line
    static const int MaxComment = 255;

    // One line of a table. Not every field means something for every kind (see the functions that fill them).
    struct Record {
        Record() : kind(Sale), kgX100(0), amountX100(0), paidX100(0), unpaidX100(0), litersX100(0), presetKgX100(0), settledKgX100(0), manual(false),
                   nearShiftLine(false), state(Waiting) {}

        Kind kind;
        QString id;
        QString time;       // "yyyy-MM-dd HH:mm:ss"
        QString saleType;   // sales: spot or ticket
        QString use;        // sales: sale, self, other, ticket_loan
        QString customer;
        QString fuel;
        QString nozzle;     // supplements: the nozzle number
        QString ticket;     // settlements: the 16 digits
        QString remainderTicket; // supplements: the 16 digits of the remainder ticket the supply made (empty: it made none)
        qlonglong kgX100;
        qlonglong amountX100;
        qlonglong paidX100;
        qlonglong unpaidX100;
        qlonglong litersX100;
        qlonglong presetKgX100;
        qlonglong settledKgX100; // loans
        bool manual;        // supplements: the reading was typed in
        QString user;
        QString comment;
        QString date;       // the date (or the business day of the time while there is none yet)
        bool nearShiftLine;
        State state;
        QString refusal;    // why the web app refused it, for State Refused
    };

    struct Ref {
        Ref() : kind(Sale) {}
        Ref(Kind k, const QString &i) : kind(k), id(i) {}

        Kind kind;
        QString id;
    };

    struct Totals {
        Totals() : spotCount(0), spotKgX100(0), spotAmountX100(0), ticketCount(0), ticketKgX100(0), ticketAmountX100(0), cardCount(0), cardKgX100(0), cardAmountX100(0), paidX100(0), unpaidX100(0),
                   supplements(0), litersX100(0), supplementKgX100(0), remainders(0), remainderKgX100(0), loans(0), loanKgX100(0), settlements(0),
                   settlementKgX100(0), waiting(0), refused(0) {}

        int spotCount;
        qlonglong spotKgX100;
        qlonglong spotAmountX100;
        int ticketCount;
        qlonglong ticketKgX100;
        qlonglong ticketAmountX100;
        int cardCount;                  // the fills paid from a card (sale type card): not counted with the ticket sales
        qlonglong cardKgX100;
        qlonglong cardAmountX100;
        qlonglong paidX100;
        qlonglong unpaidX100;
        int supplements;
        qlonglong litersX100;
        qlonglong supplementKgX100;
        int remainders;
        qlonglong remainderKgX100;
        int loans;
        qlonglong loanKgX100;
        int settlements;
        qlonglong settlementKgX100;
        int waiting;  // records of this date that wait for an upload
        int refused;  // records of this date the web app refused
    };

    // The records of the date ("yyyy-MM-dd"), oldest first.
    // withoutReceipt false leaves out the sales without a receipt (the setting ShowSalesWithoutReceipt of Machine Config); the totals stay whole.
    static QList<Record> sales(QSqlDatabase db, const QString &date, bool withoutReceipt = true);
    static int salesWithoutReceipt(QSqlDatabase db, const QString &date); // how many sales of the date have none
    static QList<Record> supplements(QSqlDatabase db, const QString &date);
    static QList<Record> debts(QSqlDatabase db, const QString &date); // loans and settlements together, by time

    // The totals shown above the tables (remainders are counted by their own date, which follows their supplement).
    static Totals totals(QSqlDatabase db, const QString &date);

    // Changes the comment (an empty text clears it) of the records. Returns how many really changed; those are marked not uploaded.
    static int setComment(QSqlDatabase db, const QList<Ref> &records, const QString &comment);

    // Gives the records a date ("yyyy-MM-dd"). The remainders of a supplement follow it. Returns how many really changed (an
    // invalid date changes nothing); those are marked not uploaded.
    static int setDate(QSqlDatabase db, const QList<Ref> &records, const QString &date);

    // Why a record may not be dated `date` (an empty text: it may): not after today's business day, and not more days back than the web app allows a cashier
    // (SettingKey::SaleBackdateDays, 3 until the web app says). The Review screen asks before it moves a record.
    static QString dateProblem(QSqlDatabase db, const QString &date, const QDateTime &now);

    // Is this time within GraceMinutes of the 9 AM line?
    static bool nearShiftLine(const QString &dateTime);
};

#endif
