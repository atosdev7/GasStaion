#include "Purger.h"

#include "TicketStatus.h"

#include <QSqlQuery>
#include <QStringList>
#include <QVariant>

int Purger::purge(QSqlDatabase db, int days, const QDateTime &now)
{
    if (days < 1) {
        days = 1;
    }
    const QString cutoff = now.addDays(-days).toString("yyyy-MM-dd HH:mm:ss");
    const QString unusedTicket = QString("SELECT 1 FROM tickets WHERE tickets.barcode = remainders.ticket_barcode AND tickets.status = %1").arg(TicketStatus::Sale);
    const QString deletedSales = "SELECT id FROM sales WHERE deleted_at IS NOT NULL AND deleted_at < ?";

    QStringList statements;
    // a deleted sale takes its ticket and loan with it
    statements << "DELETE FROM tickets WHERE sale_id IN (" + deletedSales + ")"
               << "DELETE FROM ticket_loans WHERE sale_id IN (" + deletedSales + ")"
               << "DELETE FROM sales WHERE deleted_at IS NOT NULL AND deleted_at < ?"
               << "DELETE FROM sales WHERE synced = 1 AND card_id IS NULL AND created_at < ?"            // a card's history stays
               << "DELETE FROM supplements WHERE synced = 1 AND card_id IS NULL AND supplied_at < ?"
               // (a remainder ticket that was not supplied yet stays for as long as it is unused: the cashier reprints it from the Remainder tickets list)
               << QString("DELETE FROM remainders WHERE synced = 1 AND created_at < ? AND NOT EXISTS (%1)").arg(unusedTicket)
               << "DELETE FROM ticket_loan_settlements WHERE synced = 1 AND settled_at < ?"
               // (not the tickets of the old publishes: with no row they would count as sold on paper before go-live, so a used one would be accepted again)
               << QString("DELETE FROM tickets WHERE synced = 1 AND status IN (%1, %2) AND COALESCE(supply_date, sale_date) < ? "
                          "AND NOT EXISTS (SELECT 1 FROM ticket_legacy l WHERE tickets.barcode BETWEEN l.lo AND l.hi)")
                      .arg(TicketStatus::Supply).arg(TicketStatus::Restrict);

    int removed = 0;
    db.transaction();
    foreach (const QString &sql, statements) {
        QSqlQuery query(db);
        query.prepare(sql);
        query.addBindValue(cutoff);
        if (query.exec()) {
            removed += query.numRowsAffected();
        }
    }
    // the items of the sales that are gone
    QSqlQuery orphans(db);
    if (orphans.exec("DELETE FROM sale_items WHERE sale_id NOT IN (SELECT id FROM sales)")) {
        removed += orphans.numRowsAffected();
    }
    db.commit();

    return removed;
}

QStringList Purger::keptSaleIds(QSqlDatabase db, int limit)
{
    QStringList ids;
    QSqlQuery query(db);
    query.prepare("SELECT id FROM sales WHERE synced = 1 AND deleted_at IS NULL AND hidden = 0 LIMIT ?");
    query.addBindValue(limit);
    if (query.exec()) {
        while (query.next()) {
            ids << query.value(0).toString();
        }
    }

    return ids;
}

QStringList Purger::keptSupplementIds(QSqlDatabase db, int limit)
{
    QStringList ids;
    QSqlQuery query(db);
    query.prepare("SELECT id FROM supplements WHERE synced = 1 AND hidden = 0 LIMIT ?");
    query.addBindValue(limit);
    if (query.exec()) {
        while (query.next()) {
            ids << query.value(0).toString();
        }
    }

    return ids;
}

namespace {

// "?, ?, ?" for a list.
QString marks(int count)
{
    QStringList list;
    for (int i = 0; i < count; ++i) {
        list << "?";
    }

    return list.join(", ");
}

int run(QSqlDatabase db, const QString &sql, const QStringList &ids)
{
    if (ids.isEmpty()) {
        return 0;
    }
    QSqlQuery query(db);
    query.prepare(sql.arg(marks(ids.size())));
    foreach (const QString &id, ids) {
        query.addBindValue(id);
    }

    return query.exec() ? query.numRowsAffected() : 0;
}

}

int Purger::removeConfirmed(QSqlDatabase db, const QStringList &saleIds, const QStringList &supplementIds)
{
    int removed = 0;

    db.transaction();
    // the finished tickets and the printed remainders of the supplies go before the supplies (they are found through them)
    removed += run(db, QString("DELETE FROM tickets WHERE synced = 1 AND status IN (%1, %2) AND supplement_id IN (%3) "
                               "AND NOT EXISTS (SELECT 1 FROM ticket_legacy l WHERE tickets.barcode BETWEEN l.lo AND l.hi)").arg(TicketStatus::Supply).arg(TicketStatus::Restrict).arg("%1"), supplementIds);
    // (a remainder ticket that nobody has used yet stays, printed or not)
    removed += run(db, QString("DELETE FROM remainders WHERE synced = 1 AND "
                               "NOT EXISTS (SELECT 1 FROM tickets WHERE tickets.barcode = remainders.ticket_barcode AND tickets.status = %1) AND supplement_id IN (%2)").arg(TicketStatus::Sale).arg("%1"), supplementIds);
    removed += run(db, "DELETE FROM supplements WHERE synced = 1 AND card_id IS NULL AND id IN (%1)", supplementIds);
    removed += run(db, "DELETE FROM sale_items WHERE sale_id IN (SELECT id FROM sales WHERE synced = 1 AND card_id IS NULL AND sale_type <> 'card' AND id IN (%1))", saleIds);
    removed += run(db, "DELETE FROM sales WHERE synced = 1 AND card_id IS NULL AND sale_type <> 'card' AND id IN (%1)", saleIds);
    // a card's top-ups and supplies are kept (its balance and history) and hidden
    removed += run(db, "UPDATE sales SET hidden = 1 WHERE synced = 1 AND hidden = 0 AND (card_id IS NOT NULL OR sale_type = 'card') AND id IN (%1)", saleIds);
    removed += run(db, "UPDATE supplements SET hidden = 1 WHERE synced = 1 AND hidden = 0 AND card_id IS NOT NULL AND id IN (%1)", supplementIds);
    db.commit();

    return removed;
}
