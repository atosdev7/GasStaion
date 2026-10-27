#include "TicketLegacy.h"

#include "Barcode.h"
#include "Decimal.h"
#include "TicketStatus.h"

#include <QJsonObject>
#include <QSqlError>
#include <QSqlQuery>
#include <QVariant>

bool TicketLegacy::replace(QSqlDatabase db, const QString &sign, const QJsonArray &ranges, QString *error)
{
    QSqlQuery query(db);
    if (!query.exec("DELETE FROM ticket_legacy")) {
        *error = query.lastError().text();

        return false;
    }

    for (int i = 0; i < ranges.size(); ++i) {
        const QJsonObject range = ranges.at(i).toObject();
        const QString code = range.value("code").toString();
        const qlonglong from = static_cast<qlonglong>(range.value("from").toDouble());
        const qlonglong to = static_cast<qlonglong>(range.value("to").toDouble());
        // (the barcode is sign + code + serial of 9 digits; as a number the serial is the low part)
        const qlonglong lo = Barcode::toKey(sign + code + QString("%1").arg(from, 9, 10, QChar('0')));
        const qlonglong hi = Barcode::toKey(sign + code + QString("%1").arg(to, 9, 10, QChar('0')));
        if (lo < 0 || hi < lo) {
            continue; // not a range of this business (a wrong sign or code): it can never match
        }

        bool kgOk = false;
        const qlonglong kg = Decimal::fromJson(range.value("kg"), 2, &kgOk);
        query.prepare("INSERT INTO ticket_legacy (lo, hi, fuel_type, spec, kg, day) VALUES (?, ?, ?, ?, ?, ?)");
        query.addBindValue(lo);
        query.addBindValue(hi);
        query.addBindValue(range.value("fuel_type").toString());
        query.addBindValue(range.value("spec").isNull() ? QVariant(QVariant::Int) : QVariant(range.value("spec").toInt()));
        query.addBindValue(kgOk ? kg : 0);
        query.addBindValue(range.value("date").toString());
        if (!query.exec()) {
            *error = query.lastError().text();

            return false;
        }
    }

    return true;
}

bool TicketLegacy::isOld(QSqlDatabase db, qlonglong barcode)
{
    QSqlQuery query(db);
    query.prepare("SELECT 1 FROM ticket_legacy WHERE lo <= ? AND hi >= ? LIMIT 1");
    query.addBindValue(barcode);
    query.addBindValue(barcode);

    return query.exec() && query.next();
}

bool TicketLegacy::ensure(QSqlDatabase db, qlonglong barcode)
{
    QSqlQuery have(db);
    have.prepare("SELECT 1 FROM tickets WHERE barcode = ?");
    have.addBindValue(barcode);
    if (!have.exec() || have.next()) {
        return false; // it has a row: the row says what it is
    }

    QSqlQuery range(db);
    range.prepare("SELECT fuel_type, spec, kg, day FROM ticket_legacy WHERE lo <= ? AND hi >= ? LIMIT 1");
    range.addBindValue(barcode);
    range.addBindValue(barcode);
    if (!range.exec() || !range.next()) {
        return false;
    }

    QSqlQuery make(db);
    make.prepare("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, sale_id, sale_date, synced) VALUES (?, ?, ?, ?, ?, NULL, ?, 1)");
    make.addBindValue(barcode);
    make.addBindValue(range.value(0).toString());
    make.addBindValue(range.value(1));
    make.addBindValue(range.value(2).toLongLong());
    make.addBindValue(int(TicketStatus::Sale));
    make.addBindValue(range.value(3).toString() + " 00:00:00");

    return make.exec();
}
