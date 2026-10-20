#ifndef TICKETSALE_H
#define TICKETSALE_H

#include <QDateTime>
#include <QList>
#include <QMap>
#include <QSqlDatabase>
#include <QString>

// A ticket sale: tickets that are in stock (published) are sold. The screen builds the sale from items like the web form does
// (a spec, a start serial and a count: that run of serials), and an item without a start serial takes the lowest ones in stock. All
// tickets of a sale are the same fuel type. It writes, in one transaction, the sale, every ticket turned from "in stock" to "sold" and, for a
// ticket-loan sale, the loan; all with synced = 0 until the cashier's upload. Amounts are the stored integers (x100).
// The sale is cash and paid in full, and only usage "sale" has an amount (see SpotSale for the four usages).
class TicketSale
{
public:
    // One item as it was entered: what the sale keeps (and sends to the web app) besides its tickets.
    struct Line {
        Line() : spec(0), quantity(0), start(0), end(0), kgX100(0) {}

        int spec;
        int quantity;
        qlonglong start;
        qlonglong end;
        qlonglong kgX100;
    };

    struct Input {
        Input() : customerId(0), priceX100(0), receipt(false) {}

        QString fuelType;
        int customerId;
        QString usage;
        qlonglong priceX100;
        QList<qlonglong> barcodes; // the tickets sold (barcode numbers, see Barcode)
        QString comment;
        bool receipt;      // a receipt was handed over
        QList<Line> lines; // the items of the sale (the screen fills them; the tickets above are what is sold)
    };

    struct Result {
        Result() : ok(false), totalX100(0), kgX100(0), tickets(0) {}

        bool ok;
        QString error;
        QString saleId;
        qlonglong totalX100;
        qlonglong kgX100;
        int tickets;
    };

    struct Scan {
        Scan() : ok(false), kgX100(0) {}

        bool ok;
        QString error;
        QString fuelType;
        qlonglong kgX100;
    };

    static const int MaxTickets = 2000;

    // One ticket in stock: its barcode and its weight; the serial is the last 9 digits of the barcode.
    struct Stock {
        Stock() : barcode(0), kgX100(0) {}

        qlonglong serial() const { return barcode % 1000000000LL; }

        qlonglong barcode;
        qlonglong kgX100;
    };

    // The specs that have tickets in stock for a fuel type: the weight of one ticket and how many there are.
    struct SpecStock {
        SpecStock() : spec(0), kgX100(0), count(0) {}

        int spec;
        qlonglong kgX100;
        int count;
    };

    // An item of the sale: `quantity` tickets of a spec from the serial `start` on (0: the lowest ones in stock).
    struct Item {
        Item() : spec(0), quantity(0), start(0) {}

        int spec;
        int quantity;
        qlonglong start;
    };

    // What an item gets: the first and the last serial and the tickets.
    struct Taken {
        Taken() : ok(false), start(0), end(0), kgX100(0) {}

        bool ok;
        qlonglong start;
        qlonglong end;
        qlonglong kgX100;
        QList<qlonglong> barcodes;
    };

    static QList<SpecStock> specs(QSqlDatabase db, const QString &fuelType);
    static QList<Stock> stock(QSqlDatabase db, const QString &fuelType, int spec); // by barcode, lowest first

    // Takes `quantity` tickets out of the stock list: from the serial `start` (all of the run must be in stock) or, with start 0, the lowest.
    // Not ok (and the list unchanged) when they are not there.
    static Taken take(QList<Stock> *inStock, int quantity, qlonglong start);

    // What each item gets, worked out in the order of the items like the web form: a ticket is given once. `left` gets what is left in
    // stock of every spec used.
    static QList<Taken> resolve(QSqlDatabase db, const QString &fuelType, const QList<Item> &items, QMap<int, QList<Stock> > *left = 0);

    // Can this barcode be sold now? The text is meant for the cashier ("already sold", "another fuel type", ...).
    // `fuelType` is the fuel of the tickets scanned so far (empty for the first one).
    static Scan scan(QSqlDatabase db, const QString &barcodeText, const QString &fuelType);

    static Result create(QSqlDatabase db, const Input &input, int userId, const QDateTime &now);
};

#endif
