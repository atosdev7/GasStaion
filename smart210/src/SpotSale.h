#ifndef SPOTSALE_H
#define SPOTSALE_H

#include <QDateTime>
#include <QSqlDatabase>
#include <QString>

// A spot sale: fuel of any weight sold at once, without a ticket from stock. It writes, in one transaction, the sale, the ticket
// minted for it (reserved code 21, status sold) and, for a ticket-loan sale, the loan. Everything is stored with synced = 0 and
// waits for the cashier's upload. Amounts are the stored integers (x100).
//
// usage: "sale" (the money counts), "self" (own use), "other", "ticket_loan" (fuel given against tickets to be brought back
// later). Only a "sale" has an amount; the others are kg only. A sale made at the pump is always paid in full and in cash.
class SpotSale
{
public:
    struct Input {
        Input() : customerId(0), priceX100(0), kgX100(0), amountX100(-1), receipt(false) {}

        QString fuelType;
        int customerId;
        QString usage;
        qlonglong priceX100;
        qlonglong kgX100;
        qlonglong amountX100; // what the customer pays when the cashier typed the amount; -1 to work it out from kg and price
        QString comment;
        bool receipt;         // a receipt was handed over
    };

    struct Result {
        Result() : ok(false), totalX100(0), kgX100(0) {}

        bool ok;
        QString error;
        QString saleId;
        QString barcode; // the minted ticket, 16 digits
        qlonglong totalX100;
        qlonglong kgX100;
    };

    static const qlonglong MaxKgX100 = 99999900;          // 999,999.00 kg, what the web app allows
    static const qlonglong MaxPriceX100 = 999999900;      // 9,999,999.00
    static const qlonglong MaxAmountX100 = 9999999900LL;  // 99,999,999.00

    // price x kg, and kg from an amount and a price, both rounded to the cent / 10 g.
    static qlonglong amountFor(qlonglong kgX100, qlonglong priceX100);
    static qlonglong kgFor(qlonglong amountX100, qlonglong priceX100);

    static Result create(QSqlDatabase db, const Input &input, int userId, const QDateTime &now);
};

#endif
