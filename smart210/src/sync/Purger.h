#ifndef PURGER_H
#define PURGER_H

#include <QDateTime>
#include <QSqlDatabase>
#include <QStringList>

// Keeps the database small: removes what is older than the device's purge days (set on the web app's Devices screen) and is
// no longer needed. Only records that are safe to lose go:
//   * sales, supplements, remainders and loan settlements already uploaded (synced) and older than the limit;
//   * sales deleted here before an upload and older than the limit, with their loan and ticket (the cashier sees deleted
//     sales for that long);
//   * tickets that are finished (supplied or restricted), uploaded or downloaded, and whose sale or supply is older than the limit.
// Never removed: anything not uploaded yet (a refused record included), tickets still in stock or sold, ticket loans (they stay until the
// web app removes them), and the top-ups and fills of cards (sales of type card, supplements with a card). The history of a card is its ledger
// (card_transactions, pulled and never purged, see CardLedger); the card records stay besides it, which costs little.
//
// Besides age there is confirmation: when the accountant confirms a day on the web app, the records of it that were uploaded are final
// and are removed at the next download (removeConfirmed), however young they are.
class Purger
{
public:
    // Returns how many rows were removed.
    static int purge(QSqlDatabase db, int days, const QDateTime &now);

    // The ids of the uploaded sales and supplements that are still kept (a card's records are never removed, so they are not asked about):
    // the terminal asks the web app which of them are confirmed.
    static QStringList keptSaleIds(QSqlDatabase db, int limit);
    static QStringList keptSupplementIds(QSqlDatabase db, int limit);

    // Removes the uploaded sales and supplements named (confirmed on the web app) with their items, their remainders that are printed and
    // their finished tickets. A ticket that is sold and not supplied yet stays: the supplier still needs it. Returns how many rows were removed.
    static int removeConfirmed(QSqlDatabase db, const QStringList &saleIds, const QStringList &supplementIds);
};

#endif
