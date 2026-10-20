#include "SaleDeletion.h"

#include "TicketStatus.h"

#include <QObject>
#include <QSqlError>
#include <QSqlQuery>
#include <QVariant>

QString SaleDeletion::blocker(QSqlDatabase db, const QString &saleId, int userId)
{
    QSqlQuery sale(db);
    sale.prepare("SELECT sale_type, user_id, synced, deleted_at, usage FROM sales WHERE id = ?");
    sale.addBindValue(saleId);
    if (!sale.exec() || !sale.next()) {
        return QObject::tr("This sale does not exist.");
    }
    if (!sale.value(3).isNull()) {
        return QObject::tr("This sale was already deleted.");
    }
    if (sale.value(2).toInt() != 0) {
        return QObject::tr("This sale was already uploaded, so it can no longer be deleted here.");
    }
    if (sale.value(0).toString() != "spot") {
        return QObject::tr("Only a spot sale can be deleted on the terminal.");
    }
    if (sale.value(1).toInt() != userId || userId <= 0) {
        return QObject::tr("This sale was made by another cashier.");
    }

    // The ticket must still be unused: fuel dispensed for it cannot be taken back.
    QSqlQuery ticket(db);
    ticket.prepare("SELECT COUNT(*) FROM tickets WHERE sale_id = ? AND status <> ?");
    ticket.addBindValue(saleId);
    ticket.addBindValue(int(TicketStatus::Sale));
    if (ticket.exec() && ticket.next() && ticket.value(0).toInt() > 0) {
        return QObject::tr("Fuel was already dispensed for the ticket of this sale.");
    }

    // A loan that has been settled against has moved on.
    QSqlQuery loan(db);
    loan.prepare("SELECT COUNT(*) FROM ticket_loans l WHERE l.sale_id = ? AND (l.settled_amount_kg > 0 "
                 "OR EXISTS (SELECT 1 FROM ticket_loan_settlements s WHERE s.loan_id = l.id))");
    loan.addBindValue(saleId);
    if (loan.exec() && loan.next() && loan.value(0).toInt() > 0) {
        return QObject::tr("Tickets were already surrendered for the loan of this sale.");
    }

    return QString();
}

bool SaleDeletion::remove(QSqlDatabase db, const QString &saleId, int userId, const QDateTime &now, QString *error)
{
    if (!db.transaction()) {
        *error = db.lastError().text();

        return false;
    }

    const QString reason = blocker(db, saleId, userId);
    if (!reason.isEmpty()) {
        db.rollback();
        *error = reason;

        return false;
    }

    QSqlQuery sale(db);
    sale.prepare("UPDATE sales SET deleted_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL AND synced = 0");
    sale.addBindValue(now.toString("yyyy-MM-dd HH:mm:ss"));
    sale.addBindValue(now.toString("yyyy-MM-dd HH:mm:ss"));
    sale.addBindValue(saleId);

    // The ticket was minted for this sale and sold with it: it can never be sold or used now. It stays linked to the sale.
    QSqlQuery ticket(db);
    ticket.prepare("UPDATE tickets SET status = ?, synced = 0 WHERE sale_id = ?");
    ticket.addBindValue(int(TicketStatus::Restrict));
    ticket.addBindValue(saleId);

    QSqlQuery loan(db);
    loan.prepare("UPDATE ticket_loans SET deleted = 1, synced = 0 WHERE sale_id = ?");
    loan.addBindValue(saleId);

    if (!sale.exec() || sale.numRowsAffected() != 1 || !ticket.exec() || !loan.exec() || !db.commit()) {
        db.rollback();
        *error = QObject::tr("The sale could not be deleted.");

        return false;
    }

    return true;
}
