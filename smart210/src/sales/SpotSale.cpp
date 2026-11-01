#include "SpotSale.h"
#include "Clock.h"
#include "Checkout.h"

#include "Barcode.h"
#include "TicketNumbers.h"
#include "TicketStatus.h"

#include <QObject>
#include <QSqlError>
#include <QSqlQuery>
#include <QUuid>
#include <QVariant>

qlonglong SpotSale::amountFor(qlonglong kgX100, qlonglong priceX100)
{
    return (kgX100 * priceX100 + 50) / 100;
}

qlonglong SpotSale::kgFor(qlonglong amountX100, qlonglong priceX100)
{
    return priceX100 > 0 ? (amountX100 * 100 + priceX100 / 2) / priceX100 : 0;
}

namespace {

SpotSale::Result refused(const QString &reason)
{
    SpotSale::Result result;
    result.error = reason;

    return result;
}

bool exists(QSqlDatabase db, const QString &sql, const QVariant &value)
{
    QSqlQuery query(db);
    query.prepare(sql);
    query.addBindValue(value);

    return query.exec() && query.next();
}

}

SpotSale::Result SpotSale::create(QSqlDatabase db, const Input &in, int userId, const QDateTime &now)
{
    const bool counts = (in.usage == "sale");

    if (userId <= 0) {
        return refused(QObject::tr("Log in first."));
    }
    const QString behind = Clock::behindRecords(db, now);
    if (!behind.isEmpty()) {
        return refused(behind);
    }
    if (!(counts || in.usage == "self" || in.usage == "other" || in.usage == "ticket_loan")) {
        return refused(QObject::tr("Choose what the fuel is for."));
    }
    if (!exists(db, "SELECT 1 FROM fuel_types WHERE fuel_type = ?", in.fuelType)) {
        return refused(QObject::tr("Choose the fuel type."));
    }
    if (!exists(db, "SELECT 1 FROM customers WHERE id = ? AND deleted = 0", in.customerId)) {
        return refused(QObject::tr("Choose the customer."));
    }
    if (in.kgX100 < 1 || in.kgX100 > MaxKgX100) {
        return refused(QObject::tr("Enter the weight in kg, above 0 and up to 999999."));
    }
    if (in.priceX100 < 0 || in.priceX100 > MaxPriceX100 || (counts && in.priceX100 == 0)) {
        return refused(QObject::tr("Enter the price per kg."));
    }

    qlonglong total = 0;
    if (counts) {
        total = in.amountX100 >= 0 ? in.amountX100 : amountFor(in.kgX100, in.priceX100);
        if (total <= 0 || total > MaxAmountX100) {
            return refused(QObject::tr("The amount is not allowed."));
        }
    }

    if (!db.transaction()) {
        return refused(db.lastError().text());
    }

    QString error;
    const qlonglong barcode = TicketNumbers::next(db, TicketNumbers::typeOfUsage(in.usage), in.fuelType, &error);
    if (barcode < 0) {
        db.rollback();

        return refused(error);
    }

    const QString saleId = QUuid::createUuid().toString().mid(1, 36);
    const QString when = now.toString("yyyy-MM-dd HH:mm:ss");
    const QString day = Checkout::businessDay(now); // the business day the record belongs to (9 AM to 9 AM): its date is filled in at once

    // The date is the business day of the moment of the sale.
    QSqlQuery sale(db);
    sale.prepare("INSERT INTO sales (id, created_at, updated_at, customer_id, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, "
                 "unpaid_amount, receipt, usage, comment, sale_date, user_id, deleted_at, synced) "
                 "VALUES (?, ?, ?, ?, 'spot', ?, ?, ?, 'cash', ?, ?, 0, ?, ?, ?, '" + day + "', ?, NULL, 0)");
    sale.addBindValue(saleId);
    sale.addBindValue(when);
    sale.addBindValue(when);
    sale.addBindValue(in.customerId);
    sale.addBindValue(in.fuelType);
    sale.addBindValue(in.priceX100);
    sale.addBindValue(in.kgX100);
    sale.addBindValue(total);
    sale.addBindValue(total); // paid in full
    sale.addBindValue(in.receipt ? QVariant(QString("1")) : QVariant(QVariant::String));
    sale.addBindValue(in.usage);
    sale.addBindValue(in.comment.trimmed().isEmpty() ? QVariant(QVariant::String) : QVariant(in.comment.trimmed().left(255)));
    sale.addBindValue(userId);

    // A spot sale's ticket is minted on the spot and sold in the same moment; there is no stock ticket behind it.
    QSqlQuery ticket(db);
    ticket.prepare("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, sale_id, sale_date, synced) VALUES (?, ?, NULL, ?, ?, ?, ?, 0)");
    ticket.addBindValue(barcode);
    ticket.addBindValue(in.fuelType);
    ticket.addBindValue(in.kgX100);
    ticket.addBindValue(int(TicketStatus::Sale));
    ticket.addBindValue(saleId);
    ticket.addBindValue(when);

    bool ok = sale.exec() && ticket.exec();
    if (ok && in.usage == "ticket_loan") {
        QSqlQuery loan(db);
        loan.prepare("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, settled_amount_kg, loaned_at, sale_id, date, user_id, deleted, synced) "
                     "VALUES (?, ?, ?, ?, 0, ?, ?, '" + day + "', ?, 0, 0)");
        loan.addBindValue(QUuid::createUuid().toString().mid(1, 36));
        loan.addBindValue(in.customerId);
        loan.addBindValue(in.fuelType);
        loan.addBindValue(in.kgX100);
        loan.addBindValue(when);
        loan.addBindValue(saleId);
        loan.addBindValue(userId);
        ok = loan.exec();
    }

    if (!ok || !db.commit()) {
        db.rollback();

        return refused(QObject::tr("The sale could not be saved."));
    }

    SpotSale::Result result;
    result.ok = true;
    result.saleId = saleId;
    result.barcode = Barcode::fromKey(barcode);
    result.totalX100 = total;
    result.kgX100 = in.kgX100;

    return result;
}
