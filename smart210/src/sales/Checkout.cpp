#include "Checkout.h"

#include <QDate>
#include <QMap>
#include <QObject>
#include <QSqlError>
#include <QSqlQuery>
#include <QVariant>

namespace {

struct Kind {
    const char *table;
    const char *time;   // the column with the time of the record
    const char *open;   // which rows are open: made here, not uploaded, not deleted, no date yet
    const char *date;   // the column with the date of the record
};

const Kind kinds[] = {
    {"sales", "created_at", "synced = 0 AND deleted_at IS NULL AND sale_date IS NULL", "sale_date"},
    {"supplements", "supplied_at", "synced = 0 AND supply_date IS NULL", "supply_date"},
    {"remainders", "created_at", "synced = 0 AND remainder_date IS NULL", "remainder_date"},
    {"ticket_loans", "loaned_at", "synced = 0 AND deleted = 0 AND date IS NULL", "date"},
    {"ticket_loan_settlements", "settled_at", "synced = 0 AND date IS NULL", "date"},
};
const int kindCount = sizeof(kinds) / sizeof(kinds[0]);

// SQL for the business day of a time column.
QString dayOf(const char *column)
{
    return QString("date(%1, '-%2 hours')").arg(column).arg(Checkout::StartHour);
}

}

QString Checkout::businessDay(const QDateTime &time)
{
    return time.addSecs(-StartHour * 3600).toString("yyyy-MM-dd");
}

QString Checkout::dateExpression(const QString &alias, const QString &timeColumn, const QString &dateColumn)
{
    return QString("COALESCE(%1.%4, date(%1.%2, '-%3 hours'))").arg(alias).arg(timeColumn).arg(StartHour).arg(dateColumn);
}

Checkout::Run Checkout::run(QSqlDatabase db, const QDateTime &now, const QString &mode, int userId, bool includeCurrentDay)
{
    Run result;
    const QString today = businessDay(now);

    if (!db.transaction()) {
        return result;
    }

    QMap<QString, int> perDay;
    for (int i = 0; i < kindCount; ++i) {
        const QString day = dayOf(kinds[i].time);
        const QString where = QString(kinds[i].open) + (includeCurrentDay ? QString() : " AND " + day + " < ?");

        // which days get records, for the log
        QSqlQuery count(db);
        count.prepare(QString("SELECT %1, COUNT(*) FROM %2 WHERE %3 GROUP BY 1").arg(day).arg(kinds[i].table).arg(where));
        if (!includeCurrentDay) {
            count.addBindValue(today);
        }
        if (count.exec()) {
            while (count.next()) {
                perDay[count.value(0).toString()] += count.value(1).toInt();
            }
        }

        QSqlQuery stamp(db);
        stamp.prepare(QString("UPDATE %1 SET %4 = %2 WHERE %3").arg(kinds[i].table).arg(day).arg(where).arg(kinds[i].date));
        if (!includeCurrentDay) {
            stamp.addBindValue(today);
        }
        if (!stamp.exec()) {
            db.rollback();

            return Run();
        }
    }

    const QString when = now.toString("yyyy-MM-dd HH:mm:ss");
    foreach (const QString &date, perDay.keys()) {
        QSqlQuery log(db);
        log.prepare("INSERT INTO checkouts (performed_at, mode, user_id, date, records) VALUES (?, ?, ?, ?, ?)");
        log.addBindValue(when);
        log.addBindValue(mode);
        log.addBindValue(userId > 0 ? QVariant(userId) : QVariant(QVariant::Int));
        log.addBindValue(date);
        log.addBindValue(perDay.value(date));
        log.exec();
        result.records += perDay.value(date);
        result.dates << date;
    }

    if (!db.commit()) {
        db.rollback();

        return Run();
    }

    return result;
}

int Checkout::openRecords(QSqlDatabase db)
{
    int total = 0;
    for (int i = 0; i < kindCount; ++i) {
        QSqlQuery query(db);
        if (query.exec(QString("SELECT COUNT(*) FROM %1 WHERE %2").arg(kinds[i].table).arg(kinds[i].open)) && query.next()) {
            total += query.value(0).toInt();
        }
    }

    return total;
}

int Checkout::overdueRecords(QSqlDatabase db, const QDateTime &now)
{
    int total = 0;
    for (int i = 0; i < kindCount; ++i) {
        QSqlQuery query(db);
        query.prepare(QString("SELECT COUNT(*) FROM %1 WHERE %2 AND %3 < ?").arg(kinds[i].table).arg(kinds[i].open).arg(dayOf(kinds[i].time)));
        query.addBindValue(businessDay(now));
        if (query.exec() && query.next()) {
            total += query.value(0).toInt();
        }
    }

    return total;
}

QStringList Checkout::days(QSqlDatabase db)
{
    QStringList list;

    QSqlQuery query(db);
    query.exec("SELECT sale_date FROM sales WHERE sale_date IS NOT NULL AND deleted_at IS NULL AND hidden = 0 "
               "UNION SELECT supply_date FROM supplements WHERE supply_date IS NOT NULL AND hidden = 0 "
               "UNION SELECT remainder_date FROM remainders WHERE remainder_date IS NOT NULL "
               "UNION SELECT date FROM ticket_loans WHERE date IS NOT NULL AND deleted = 0 "
               "UNION SELECT date FROM ticket_loan_settlements WHERE date IS NOT NULL "
               "ORDER BY 1 DESC");
    while (query.next()) {
        list << query.value(0).toString();
    }

    return list;
}

Checkout::Report Checkout::report(QSqlDatabase db, const QString &date)
{
    Report report;
    report.date = date;

    QSqlQuery sales(db);
    sales.prepare("SELECT COUNT(*), COALESCE(SUM(amount_kg), 0), COALESCE(SUM(total_amount), 0), COALESCE(SUM(paid_amount), 0) "
                  "FROM sales WHERE sale_date = ? AND deleted_at IS NULL AND hidden = 0");
    sales.addBindValue(date);
    if (sales.exec() && sales.next()) {
        report.sales = sales.value(0).toInt();
        report.salesKgX100 = sales.value(1).toLongLong();
        report.salesAmountX100 = sales.value(2).toLongLong();
        report.salesPaidX100 = sales.value(3).toLongLong();
    }

    const char *groups[] = {"usage", "fuel_type"};
    for (int g = 0; g < 2; ++g) {
        QSqlQuery query(db);
        query.prepare(QString("SELECT %1, COUNT(*), SUM(amount_kg), SUM(total_amount) FROM sales WHERE sale_date = ? AND deleted_at IS NULL AND hidden = 0 "
                              "GROUP BY %1 ORDER BY %1").arg(groups[g]));
        query.addBindValue(date);
        if (query.exec()) {
            while (query.next()) {
                Line line;
                line.label = query.value(0).toString();
                line.count = query.value(1).toInt();
                line.kgX100 = query.value(2).toLongLong();
                line.amountX100 = query.value(3).toLongLong();
                (g == 0 ? report.byUsage : report.byFuel).append(line);
            }
        }
    }

    QSqlQuery supplements(db);
    supplements.prepare("SELECT COUNT(*), COALESCE(SUM(out_amount_lt), 0), COALESCE(SUM(out_amount_kg), 0) FROM supplements WHERE supply_date = ? AND hidden = 0");
    supplements.addBindValue(date);
    if (supplements.exec() && supplements.next()) {
        report.supplements = supplements.value(0).toInt();
        report.litersX100 = supplements.value(1).toLongLong();
        report.supplementKgX100 = supplements.value(2).toLongLong();
    }

    QSqlQuery remainders(db);
    remainders.prepare("SELECT COUNT(*), COALESCE(SUM(amount_kg), 0) FROM remainders WHERE remainder_date = ?");
    remainders.addBindValue(date);
    if (remainders.exec() && remainders.next()) {
        report.remainders = remainders.value(0).toInt();
        report.remainderKgX100 = remainders.value(1).toLongLong();
    }

    QSqlQuery loans(db);
    loans.prepare("SELECT COUNT(*), COALESCE(SUM(amount_kg), 0) FROM ticket_loans WHERE date = ? AND deleted = 0");
    loans.addBindValue(date);
    if (loans.exec() && loans.next()) {
        report.loans = loans.value(0).toInt();
        report.loanKgX100 = loans.value(1).toLongLong();
    }

    QSqlQuery settlements(db);
    settlements.prepare("SELECT COUNT(*), COALESCE(SUM(amount_kg), 0) FROM ticket_loan_settlements WHERE date = ?");
    settlements.addBindValue(date);
    if (settlements.exec() && settlements.next()) {
        report.settlements = settlements.value(0).toInt();
        report.settlementKgX100 = settlements.value(1).toLongLong();
    }

    return report;
}

QString Checkout::shiftBlocker(QSqlDatabase db, const QString &date, const QDateTime &now)
{
    QSqlQuery last(db);
    last.prepare("SELECT MAX(performed_at) FROM checkouts WHERE date = ?");
    last.addBindValue(date);
    if (!last.exec() || !last.next() || last.value(0).isNull()) {
        return QObject::tr("This day was not checked out on this terminal.");
    }
    const QDateTime performed = QDateTime::fromString(last.value(0).toString(), "yyyy-MM-dd HH:mm:ss");
    if (!performed.isValid() || performed.secsTo(now) > 24 * 3600) {
        return QObject::tr("The day can only be moved for one day after its checkout.");
    }

    // every record of the day must still be here and not uploaded
    for (int i = 0; i < kindCount; ++i) {
        QSqlQuery uploaded(db);
        uploaded.prepare(QString("SELECT COUNT(*) FROM %1 WHERE %2 = ? AND synced = 1").arg(kinds[i].table).arg(kinds[i].date));
        uploaded.addBindValue(date);
        if (uploaded.exec() && uploaded.next() && uploaded.value(0).toInt() > 0) {
            return QObject::tr("Some records of this day were already uploaded.");
        }
    }

    return QString();
}

bool Checkout::shift(QSqlDatabase db, const QString &date, int days, const QDateTime &now, QString *error)
{
    if (days != 1 && days != -1) {
        *error = QObject::tr("A day can be moved by one day.");

        return false;
    }

    const QString reason = shiftBlocker(db, date, now);
    if (!reason.isEmpty()) {
        *error = reason;

        return false;
    }

    const QString target = QDate::fromString(date, "yyyy-MM-dd").addDays(days).toString("yyyy-MM-dd");
    if (!db.transaction()) {
        *error = db.lastError().text();

        return false;
    }

    bool ok = true;
    for (int i = 0; ok && i < kindCount; ++i) {
        QSqlQuery update(db);
        update.prepare(QString("UPDATE %1 SET %2 = ? WHERE %2 = ? AND synced = 0").arg(kinds[i].table).arg(kinds[i].date));
        update.addBindValue(target);
        update.addBindValue(date);
        ok = update.exec();
    }

    QSqlQuery log(db);
    log.prepare("UPDATE checkouts SET date = ? WHERE date = ?");
    log.addBindValue(target);
    log.addBindValue(date);
    ok = ok && log.exec();

    if (!ok || !db.commit()) {
        db.rollback();
        *error = QObject::tr("The day could not be moved.");

        return false;
    }

    return true;
}
