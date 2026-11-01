#include "TicketSale.h"
#include "Clock.h"
#include "Checkout.h"

#include "Barcode.h"
#include "SpotSale.h"
#include "TicketStatus.h"

#include <QObject>
#include <QSqlError>
#include <QSqlQuery>
#include <QUuid>
#include <QVariant>

namespace {

TicketSale::Result refused(const QString &reason)
{
    TicketSale::Result result;
    result.error = reason;

    return result;
}

// Why a ticket in this state cannot be sold, or an empty string when it can.
QString whyNot(int status)
{
    switch (status) {
    case TicketStatus::Publish:
        return QString();
    case TicketStatus::Sale:
        return QObject::tr("This ticket was already sold.");
    case TicketStatus::Supply:
        return QObject::tr("This ticket was already used.");
    }

    return QObject::tr("This ticket is restricted and cannot be sold.");
}

}

TicketSale::Scan TicketSale::scan(QSqlDatabase db, const QString &barcodeText, const QString &fuelType)
{
    Scan scan;

    const qlonglong key = Barcode::toKey(barcodeText.trimmed());
    if (key < 0) {
        scan.error = QObject::tr("That is not a ticket barcode (16 digits).");

        return scan;
    }

    QSqlQuery query(db);
    query.prepare("SELECT fuel_type, amount_kg, status FROM tickets WHERE barcode = ?");
    query.addBindValue(key);
    if (!query.exec() || !query.next()) {
        scan.error = QObject::tr("This ticket is not known on this terminal.");

        return scan;
    }

    const QString reason = whyNot(query.value(2).toInt());
    if (!reason.isEmpty()) {
        scan.error = reason;

        return scan;
    }
    if (!fuelType.isEmpty() && query.value(0).toString() != fuelType) {
        scan.error = QObject::tr("This ticket is another fuel type than the tickets already scanned.");

        return scan;
    }

    scan.ok = true;
    scan.fuelType = query.value(0).toString();
    scan.kgX100 = query.value(1).toLongLong();

    return scan;
}

QList<TicketSale::SpecStock> TicketSale::specs(QSqlDatabase db, const QString &fuelType)
{
    QList<SpecStock> list;

    QSqlQuery query(db);
    query.prepare("SELECT spec, MIN(amount_kg), COUNT(*) FROM tickets WHERE fuel_type = ? AND status = ? AND spec IS NOT NULL GROUP BY spec ORDER BY spec");
    query.addBindValue(fuelType);
    query.addBindValue(int(TicketStatus::Publish));
    if (query.exec()) {
        while (query.next()) {
            SpecStock one;
            one.spec = query.value(0).toInt();
            one.kgX100 = query.value(1).toLongLong();
            one.count = query.value(2).toInt();
            list << one;
        }
    }

    return list;
}

QList<TicketSale::Stock> TicketSale::stock(QSqlDatabase db, const QString &fuelType, int spec, int limit)
{
    QList<Stock> list;

    QSqlQuery query(db);
    query.prepare("SELECT barcode, amount_kg FROM tickets WHERE fuel_type = ? AND spec = ? AND status = ? ORDER BY barcode" + QString(limit > 0 ? " LIMIT ?" : ""));
    query.addBindValue(fuelType);
    query.addBindValue(spec);
    query.addBindValue(int(TicketStatus::Publish));
    if (limit > 0) {
        query.addBindValue(limit);
    }
    if (query.exec()) {
        while (query.next()) {
            Stock one;
            one.barcode = query.value(0).toLongLong();
            one.kgX100 = query.value(1).toLongLong();
            list << one;
        }
    }

    return list;
}

TicketSale::Taken TicketSale::take(QList<Stock> *inStock, int quantity, qlonglong start)
{
    Taken taken;
    if (quantity < 1 || quantity > inStock->size()) {
        return taken;
    }

    int first = -1;
    if (start > 0) {
        // the run must be in stock without a gap: the barcodes follow one another
        for (int i = 0; first < 0 && i + quantity <= inStock->size(); ++i) {
            if (inStock->at(i).serial() != start) {
                continue;
            }
            bool run = true;
            for (int k = 1; run && k < quantity; ++k) {
                run = inStock->at(i + k).barcode == inStock->at(i).barcode + k;
            }
            if (run) {
                first = i;
            }
        }
    } else {
        first = 0;
    }
    if (first < 0) {
        return taken;
    }

    for (int k = 0; k < quantity; ++k) {
        taken.barcodes << inStock->at(first + k).barcode;
        taken.kgX100 += inStock->at(first + k).kgX100;
    }
    taken.start = inStock->at(first).serial();
    taken.end = inStock->at(first + quantity - 1).serial();
    taken.ok = true;
    for (int k = 0; k < quantity; ++k) {
        inStock->removeAt(first);
    }

    return taken;
}

QList<TicketSale::Taken> TicketSale::resolve(QSqlDatabase db, const QString &fuelType, const QList<Item> &items, QMap<int, QList<Stock> > *left)
{
    QList<Taken> list;
    QMap<int, QList<Stock> > rest;

    foreach (const Item &item, items) {
        if (!rest.contains(item.spec)) {
            rest.insert(item.spec, stock(db, fuelType, item.spec));
        }
        list << take(&rest[item.spec], item.quantity, item.start);
    }
    if (left) {
        *left = rest;
    }

    return list;
}

TicketSale::Result TicketSale::create(QSqlDatabase db, const Input &in, int userId, const QDateTime &now)
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
    if (in.barcodes.isEmpty()) {
        return refused(QObject::tr("Scan the tickets first."));
    }
    if (in.barcodes.size() > MaxTickets) {
        return refused(QObject::tr("Too many tickets for one sale."));
    }
    QSqlQuery customer(db);
    customer.prepare("SELECT 1 FROM customers WHERE id = ? AND deleted = 0");
    customer.addBindValue(in.customerId);
    if (!customer.exec() || !customer.next()) {
        return refused(QObject::tr("Choose the customer."));
    }
    if (in.priceX100 < 0 || in.priceX100 > SpotSale::MaxPriceX100 || (counts && in.priceX100 == 0)) {
        return refused(QObject::tr("Enter the price per kg."));
    }

    if (!db.transaction()) {
        return refused(db.lastError().text());
    }

    // The tickets are checked again inside the transaction: a download may have changed one since it was scanned.
    qlonglong kg = 0;
    QString fuelType;
    QList<qlonglong> seen;
    foreach (qlonglong barcode, in.barcodes) {
        const Scan found = scan(db, Barcode::fromKey(barcode), fuelType);
        if (!found.ok || seen.contains(barcode)) {
            db.rollback();

            return refused(QObject::tr("Ticket %1 cannot be sold: %2")
                               .arg(Barcode::fromKey(barcode)).arg(found.ok ? QObject::tr("it is twice in the list.") : found.error));
        }
        seen << barcode;
        fuelType = found.fuelType;
        kg += found.kgX100;
    }
    if (fuelType != in.fuelType) {
        db.rollback();

        return refused(QObject::tr("The tickets are not of the chosen fuel type."));
    }

    qlonglong total = 0;
    if (counts) {
        total = SpotSale::amountFor(kg, in.priceX100);
        if (total <= 0 || total > SpotSale::MaxAmountX100) {
            db.rollback();

            return refused(QObject::tr("The amount is not allowed."));
        }
    }

    const QString saleId = QUuid::createUuid().toString().mid(1, 36);
    const QString when = now.toString("yyyy-MM-dd HH:mm:ss");
    const QString day = Checkout::businessDay(now); // the business day the record belongs to (9 AM to 9 AM): its date is filled in at once

    QSqlQuery sale(db);
    sale.prepare("INSERT INTO sales (id, created_at, updated_at, customer_id, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, "
                 "unpaid_amount, receipt, usage, comment, sale_date, user_id, deleted_at, synced) "
                 "VALUES (?, ?, ?, ?, 'ticket', ?, ?, ?, 'cash', ?, ?, 0, ?, ?, ?, '" + day + "', ?, NULL, 0)");
    sale.addBindValue(saleId);
    sale.addBindValue(when);
    sale.addBindValue(when);
    sale.addBindValue(in.customerId);
    sale.addBindValue(in.fuelType);
    sale.addBindValue(in.priceX100);
    sale.addBindValue(kg);
    sale.addBindValue(total);
    sale.addBindValue(total); // paid in full
    sale.addBindValue(in.receipt ? QVariant(QString("1")) : QVariant(QVariant::String));
    sale.addBindValue(in.usage);
    sale.addBindValue(in.comment.trimmed().isEmpty() ? QVariant(QVariant::String) : QVariant(in.comment.trimmed().left(255)));
    sale.addBindValue(userId);
    bool ok = sale.exec();

    for (int i = 0; ok && i < in.barcodes.size(); ++i) {
        QSqlQuery sold(db); // a new query each time: addBindValue adds to the values of the last one
        sold.prepare("UPDATE tickets SET status = ?, sale_id = ?, sale_date = ?, synced = 0 WHERE barcode = ? AND status = ?");
        sold.addBindValue(int(TicketStatus::Sale));
        sold.addBindValue(saleId);
        sold.addBindValue(when);
        sold.addBindValue(in.barcodes.at(i));
        sold.addBindValue(int(TicketStatus::Publish));
        ok = sold.exec() && sold.numRowsAffected() == 1;
    }

    foreach (const Line &line, in.lines) {
        QSqlQuery item(db);
        item.prepare("INSERT INTO sale_items (id, sale_id, spec, start_serial, end_serial, quantity, amount_kg) VALUES (?, ?, ?, ?, ?, ?, ?)");
        item.addBindValue(QUuid::createUuid().toString().mid(1, 36));
        item.addBindValue(saleId);
        item.addBindValue(line.spec);
        item.addBindValue(line.start);
        item.addBindValue(line.end);
        item.addBindValue(line.quantity);
        item.addBindValue(line.kgX100);
        ok = ok && item.exec();
    }

    if (ok && in.usage == "ticket_loan") {
        QSqlQuery loan(db);
        loan.prepare("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, settled_amount_kg, loaned_at, sale_id, date, user_id, deleted, synced) "
                     "VALUES (?, ?, ?, ?, 0, ?, ?, '" + day + "', ?, 0, 0)");
        loan.addBindValue(QUuid::createUuid().toString().mid(1, 36));
        loan.addBindValue(in.customerId);
        loan.addBindValue(in.fuelType);
        loan.addBindValue(kg);
        loan.addBindValue(when);
        loan.addBindValue(saleId);
        loan.addBindValue(userId);
        ok = loan.exec();
    }

    if (!ok || !db.commit()) {
        db.rollback();

        return refused(QObject::tr("The sale could not be saved."));
    }

    Result result;
    result.ok = true;
    result.saleId = saleId;
    result.totalX100 = total;
    result.kgX100 = kg;
    result.tickets = in.barcodes.size();

    return result;
}
