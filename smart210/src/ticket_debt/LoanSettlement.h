#ifndef LOANSETTLEMENT_H
#define LOANSETTLEMENT_H

#include <QDateTime>
#include <QList>
#include <QSqlDatabase>
#include <QString>

// Settling a ticket loan: a customer who took fuel on loan brings tickets back. Each ticket surrendered must be one the customer
// really holds (sold, not in stock, not used or restricted) of the loan's fuel; it becomes supplied (not at a nozzle: it pays the loan),
// a settlement row records it, and the loan's settled total goes up. Everything has synced = 0 until the cashier's upload. Same rules as
// the web app's loan screen. Amounts are the stored integers (x100).
class LoanSettlement
{
public:
    struct Loan {
        QString id;
        QString customer;
        QString fuelType;
        QString loanedAt;
        qlonglong amountX100;
        qlonglong settledX100;

        qlonglong balanceX100() const { return amountX100 - settledX100; }
    };

    struct Scan {
        Scan() : ok(false), kgX100(0) {}

        bool ok;
        QString error;
        qlonglong kgX100;
    };

    struct Result {
        Result() : ok(false), tickets(0), kgX100(0), balanceX100(0) {}

        bool ok;
        QString error;
        int tickets;
        qlonglong kgX100;
        qlonglong balanceX100; // what is still owed on the loan afterwards (negative when more was surrendered than lent)
    };

    // The loans that still have a balance, oldest first (what the web app calls open loans).
    static QList<Loan> openLoans(QSqlDatabase db);

    // Can this barcode be surrendered for a loan of this fuel? The text is meant for the cashier.
    static Scan scan(QSqlDatabase db, const QString &barcodeText, const QString &fuelType);

    static Result create(QSqlDatabase db, const QString &loanId, const QList<qlonglong> &barcodes, int userId, const QDateTime &now);
};

#endif
