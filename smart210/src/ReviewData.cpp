#include "ReviewData.h"

#include "Barcode.h"
#include "Checkout.h"

#include <QDate>
#include <QSqlQuery>
#include <QVariant>

const int ReviewData::GraceMinutes;

namespace {

// SQL for the date a record shows under: its date, or the business day of its time while it has none.
QString dateOf(const QString &alias, const QString &time, const QString &date = QString("date"))
{
    return Checkout::dateExpression(alias, time, date);
}

// The column of a record that holds its date (a sale calls it sale_date, a supplement supply_date).
const char *dateColumnOf(ReviewData::Kind kind)
{
    if (kind == ReviewData::Sale) {
        return "sale_date";
    }

    return kind == ReviewData::Supplement ? "supply_date" : "date";
}

// A change of a sale is stamped on the sale.
QString touchOf(ReviewData::Kind kind)
{
    return kind == ReviewData::Sale ? QString(", updated_at = datetime('now', 'localtime')") : QString();
}

ReviewData::State stateOf(int synced, const QString &refusal)
{
    if (!refusal.isEmpty()) {
        return ReviewData::Refused;
    }

    return synced == 0 ? ReviewData::Waiting : ReviewData::Uploaded;
}

void finish(ReviewData::Record *r, int synced, const QString &refusal)
{
    r->state = stateOf(synced, refusal);
    r->refusal = refusal;
    r->nearShiftLine = ReviewData::nearShiftLine(r->time);
}

const char *tableOf(ReviewData::Kind kind)
{
    switch (kind) {
    case ReviewData::Sale:
        return "sales";
    case ReviewData::Supplement:
        return "supplements";
    case ReviewData::Loan:
        return "ticket_loans";
    case ReviewData::Settlement:
        break;
    }

    return "ticket_loan_settlements";
}

// The rows of this kind that may be edited at all (a sale deleted before the upload, or a loan deleted, is gone).
QString liveOf(ReviewData::Kind kind)
{
    switch (kind) {
    case ReviewData::Sale:
        return " AND deleted_at IS NULL";
    case ReviewData::Loan:
        return " AND deleted = 0";
    default:
        break;
    }

    return QString();
}

}

bool ReviewData::nearShiftLine(const QString &dateTime)
{
    const QString clock = dateTime.mid(11, 5); // "HH:mm"
    if (clock.size() != 5) {
        return false;
    }
    const int minutes = clock.left(2).toInt() * 60 + clock.mid(3, 2).toInt();
    const int line = Checkout::StartHour * 60;

    return minutes >= line - GraceMinutes && minutes <= line + GraceMinutes;
}

QList<ReviewData::Record> ReviewData::sales(QSqlDatabase db, const QString &date)
{
    QList<Record> list;

    QSqlQuery query(db);
    query.prepare("SELECT s.id, s.created_at, s.sale_type, s.usage, COALESCE(c.name, ''), s.fuel_type, s.amount_kg, s.total_amount, s.paid_amount, "
                  "s.unpaid_amount, COALESCE(u.name, ''), COALESCE(s.comment, ''), " + dateOf("s", "created_at", "sale_date") + ", s.synced, COALESCE(e.message, '') "
                  "FROM sales s LEFT JOIN customers c ON c.id = s.customer_id LEFT JOIN users u ON u.id = s.user_id "
                  "LEFT JOIN upload_errors e ON e.table_name = 'sales' AND e.row_key = s.id "
                  "WHERE s.deleted_at IS NULL AND " + dateOf("s", "created_at", "sale_date") + " = ? ORDER BY s.created_at, s.id");
    query.addBindValue(date);
    query.exec();
    while (query.next()) {
        Record r;
        r.kind = Sale;
        r.id = query.value(0).toString();
        r.time = query.value(1).toString();
        r.saleType = query.value(2).toString();
        r.use = query.value(3).toString();
        r.customer = query.value(4).toString();
        r.fuel = query.value(5).toString();
        r.kgX100 = query.value(6).toLongLong();
        r.amountX100 = query.value(7).toLongLong();
        r.paidX100 = query.value(8).toLongLong();
        r.unpaidX100 = query.value(9).toLongLong();
        r.user = query.value(10).toString();
        r.comment = query.value(11).toString();
        r.date = query.value(12).toString();
        finish(&r, query.value(13).toInt(), query.value(14).toString());
        list.append(r);
    }

    return list;
}

QList<ReviewData::Record> ReviewData::supplements(QSqlDatabase db, const QString &date)
{
    QList<Record> list;

    QSqlQuery query(db);
    query.prepare("SELECT s.id, s.supplied_at, COALESCE((SELECT number FROM nozzles WHERE id = s.nozzle_id), s.nozzle_id), s.fuel_type, "
                  "s.out_amount_lt, s.out_amount_kg, s.preset_kg, (s.mode = 'manual'), COALESCE(s.comment, ''), " + dateOf("s", "supplied_at", "supply_date") +
                  ", s.synced, COALESCE(e.message, '') "
                  "FROM supplements s "
                  "LEFT JOIN upload_errors e ON e.table_name = 'supplements' AND e.row_key = s.id "
                  "WHERE " + dateOf("s", "supplied_at", "supply_date") + " = ? ORDER BY s.supplied_at, s.id");
    query.addBindValue(date);
    query.exec();
    while (query.next()) {
        Record r;
        r.kind = Supplement;
        r.id = query.value(0).toString();
        r.time = query.value(1).toString();
        r.nozzle = query.value(2).toString();
        r.fuel = query.value(3).toString();
        r.litersX100 = query.value(4).toLongLong();
        r.kgX100 = query.value(5).toLongLong();
        r.presetKgX100 = query.value(6).toLongLong();
        r.manual = query.value(7).toInt() != 0;
        r.comment = query.value(8).toString();
        r.date = query.value(9).toString();
        finish(&r, query.value(10).toInt(), query.value(11).toString());
        list.append(r);
    }

    return list;
}

QList<ReviewData::Record> ReviewData::debts(QSqlDatabase db, const QString &date)
{
    QList<Record> list;

    // the loans made on this terminal (a loan that came from the web app has no cashier here)
    QSqlQuery loans(db);
    loans.prepare("SELECT l.id, l.loaned_at, COALESCE(c.name, ''), l.fuel_type, l.amount_kg, l.settled_amount_kg, COALESCE(u.name, ''), "
                  "COALESCE(l.comment, ''), " + dateOf("l", "loaned_at") + ", l.synced, COALESCE(e.message, '') "
                  "FROM ticket_loans l LEFT JOIN customers c ON c.id = l.customer_id LEFT JOIN users u ON u.id = l.user_id "
                  "LEFT JOIN upload_errors e ON e.table_name = 'ticket_loans' AND e.row_key = l.id "
                  "WHERE l.deleted = 0 AND l.user_id IS NOT NULL AND " + dateOf("l", "loaned_at") + " = ?");
    loans.addBindValue(date);
    loans.exec();
    while (loans.next()) {
        Record r;
        r.kind = Loan;
        r.id = loans.value(0).toString();
        r.time = loans.value(1).toString();
        r.customer = loans.value(2).toString();
        r.fuel = loans.value(3).toString();
        r.kgX100 = loans.value(4).toLongLong();
        r.settledKgX100 = loans.value(5).toLongLong();
        r.user = loans.value(6).toString();
        r.comment = loans.value(7).toString();
        r.date = loans.value(8).toString();
        finish(&r, loans.value(9).toInt(), loans.value(10).toString());
        list.append(r);
    }

    QSqlQuery settlements(db);
    settlements.prepare("SELECT s.id, s.settled_at, COALESCE(c.name, ''), COALESCE(l.fuel_type, ''), s.amount_kg, s.ticket_barcode, COALESCE(u.name, ''), "
                        "COALESCE(s.comment, ''), " + dateOf("s", "settled_at") + ", s.synced, COALESCE(e.message, '') "
                        "FROM ticket_loan_settlements s LEFT JOIN ticket_loans l ON l.id = s.loan_id LEFT JOIN customers c ON c.id = l.customer_id "
                        "LEFT JOIN users u ON u.id = s.user_id "
                        "LEFT JOIN upload_errors e ON e.table_name = 'ticket_loan_settlements' AND e.row_key = s.id "
                        "WHERE " + dateOf("s", "settled_at") + " = ?");
    settlements.addBindValue(date);
    settlements.exec();
    while (settlements.next()) {
        Record r;
        r.kind = Settlement;
        r.id = settlements.value(0).toString();
        r.time = settlements.value(1).toString();
        r.customer = settlements.value(2).toString();
        r.fuel = settlements.value(3).toString();
        r.kgX100 = settlements.value(4).toLongLong();
        r.ticket = Barcode::fromKey(settlements.value(5).toLongLong());
        r.user = settlements.value(6).toString();
        r.comment = settlements.value(7).toString();
        r.date = settlements.value(8).toString();
        finish(&r, settlements.value(9).toInt(), settlements.value(10).toString());
        list.append(r);
    }

    // by time (the list is short: an insertion sort will do)
    for (int i = 1; i < list.size(); ++i) {
        const Record r = list.at(i);
        int j = i - 1;
        while (j >= 0 && list.at(j).time > r.time) {
            list[j + 1] = list.at(j);
            --j;
        }
        list[j + 1] = r;
    }

    return list;
}

ReviewData::Totals ReviewData::totals(QSqlDatabase db, const QString &date)
{
    Totals t;

    foreach (const Record &r, sales(db, date)) {
        if (r.saleType == "spot") {
            ++t.spotCount;
            t.spotKgX100 += r.kgX100;
            t.spotAmountX100 += r.amountX100;
        } else {
            ++t.ticketCount;
            t.ticketKgX100 += r.kgX100;
            t.ticketAmountX100 += r.amountX100;
        }
        t.paidX100 += r.paidX100;
        t.unpaidX100 += r.unpaidX100;
        t.waiting += r.state == Waiting ? 1 : 0;
        t.refused += r.state == Refused ? 1 : 0;
    }

    foreach (const Record &r, supplements(db, date)) {
        ++t.supplements;
        t.litersX100 += r.litersX100;
        t.supplementKgX100 += r.kgX100;
        t.waiting += r.state == Waiting ? 1 : 0;
        t.refused += r.state == Refused ? 1 : 0;
    }

    QSqlQuery remainders(db);
    remainders.prepare("SELECT COUNT(*), COALESCE(SUM(r.amount_kg), 0) FROM remainders r WHERE " + dateOf("r", "created_at", "remainder_date") + " = ?");
    remainders.addBindValue(date);
    if (remainders.exec() && remainders.next()) {
        t.remainders = remainders.value(0).toInt();
        t.remainderKgX100 = remainders.value(1).toLongLong();
    }

    foreach (const Record &r, debts(db, date)) {
        if (r.kind == Loan) {
            ++t.loans;
            t.loanKgX100 += r.kgX100;
        } else {
            ++t.settlements;
            t.settlementKgX100 += r.kgX100;
        }
        t.waiting += r.state == Waiting ? 1 : 0;
        t.refused += r.state == Refused ? 1 : 0;
    }

    return t;
}

int ReviewData::setComment(QSqlDatabase db, const QList<Ref> &records, const QString &comment)
{
    const QString text = comment.trimmed().left(MaxComment);
    int changed = 0;

    db.transaction();
    foreach (const Ref &ref, records) {
        QSqlQuery update(db);
        update.prepare(QString("UPDATE %1 SET comment = NULLIF(?, ''), synced = 0%3 WHERE id = ? AND COALESCE(comment, '') <> ?%2")
                           .arg(tableOf(ref.kind)).arg(liveOf(ref.kind)).arg(touchOf(ref.kind)));
        update.addBindValue(text);
        update.addBindValue(ref.id);
        update.addBindValue(text);
        if (update.exec() && update.numRowsAffected() > 0) {
            ++changed;
        }
    }
    db.commit();

    return changed;
}

int ReviewData::setDate(QSqlDatabase db, const QList<Ref> &records, const QString &date)
{
    if (!QDate::fromString(date, "yyyy-MM-dd").isValid()) {
        return 0;
    }

    int changed = 0;
    db.transaction();
    foreach (const Ref &ref, records) {
        QSqlQuery update(db);
        update.prepare(QString("UPDATE %1 SET %3 = ?, synced = 0%4 WHERE id = ? AND COALESCE(%3, '') <> ?%2")
                           .arg(tableOf(ref.kind)).arg(liveOf(ref.kind)).arg(dateColumnOf(ref.kind)).arg(touchOf(ref.kind)));
        update.addBindValue(date);
        update.addBindValue(ref.id);
        update.addBindValue(date);
        if (update.exec() && update.numRowsAffected() > 0) {
            ++changed;
            if (ref.kind == Supplement) {
                // the remainder of a supplement belongs to the same day
                QSqlQuery follow(db);
                follow.prepare("UPDATE remainders SET remainder_date = ?, synced = 0 WHERE supplement_id = ? AND COALESCE(remainder_date, '') <> ?");
                follow.addBindValue(date);
                follow.addBindValue(ref.id);
                follow.addBindValue(date);
                follow.exec();
            }
        }
    }
    db.commit();

    return changed;
}
