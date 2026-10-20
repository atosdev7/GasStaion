#ifndef SALEDELETION_H
#define SALEDELETION_H

#include <QDateTime>
#include <QSqlDatabase>
#include <QString>

// Deleting a mistaken spot sale on the terminal. The cashier enters the correct sale again and then deletes the wrong one; it is for
// wrong input only, so it is allowed only while nothing has moved on:
//   * the sale is a spot sale made by the logged-in cashier and has not been uploaded (an uploaded sale is final here: the web app
//     deletes it or enters a counter entry);
//   * its ticket was not used (no fuel was dispensed for it);
//   * for a ticket-loan sale, nothing was settled against the loan yet.
// The sale is only marked deleted (it stays in the "deleted" list for the purge days), its ticket is restricted so it can never be
// used, and its loan is deleted. None of the three is ever uploaded: the web app never learns of the sale.
class SaleDeletion
{
public:
    // Empty when the sale may be deleted, otherwise why not (text for the cashier).
    static QString blocker(QSqlDatabase db, const QString &saleId, int userId);

    // Deletes the sale; false and `error` filled when it may not be (the same checks as blocker(), made again inside the transaction).
    static bool remove(QSqlDatabase db, const QString &saleId, int userId, const QDateTime &now, QString *error);
};

#endif
