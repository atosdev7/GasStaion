#include "UploadSummary.h"

#include "Uploader.h"

#include <QSqlQuery>
#include <QVariant>

namespace {

// COUNT and SUM of one column over the rows of a table that wait for an upload.
void totals(QSqlDatabase db, const QString &table, const QString &column, int *count, qlonglong *sum)
{
    QSqlQuery query(db);
    const QString what = column.isEmpty() ? QString("COUNT(*), 0") : QString("COUNT(*), COALESCE(SUM(%1), 0)").arg(column);
    if (query.exec("SELECT " + what + " FROM " + table + " WHERE " + Uploader::waitingCondition(table)) && query.next()) {
        *count = query.value(0).toInt();
        *sum = query.value(1).toLongLong();
    }
}

}

UploadSummary UploadSummary::compute(QSqlDatabase db)
{
    UploadSummary summary;
    qlonglong ignored = 0;

    totals(db, "sales", "amount_kg", &summary.sales, &summary.salesKg);
    int unused = 0;
    totals(db, "sales", "total_amount", &unused, &summary.salesAmount);
    totals(db, "sales", "paid_amount", &unused, &summary.salesPaid);
    totals(db, "supplements", "out_amount_kg", &summary.supplements, &summary.supplementKg);
    totals(db, "remainders", "amount_kg", &summary.remainders, &summary.remainderKg);
    totals(db, "ticket_loans", "amount_kg", &summary.loans, &summary.loanKg);
    totals(db, "ticket_loan_settlements", "amount_kg", &summary.settlements, &summary.settlementKg);
    totals(db, "tickets", QString(), &summary.tickets, &ignored);

    QSqlQuery query(db);
    if (query.exec("SELECT COUNT(*) FROM upload_errors") && query.next()) {
        summary.refused = query.value(0).toInt();
    }
    if (query.exec("SELECT COUNT(*) FROM sales WHERE deleted_at IS NOT NULL AND synced = 0") && query.next()) {
        summary.deletedSales = query.value(0).toInt();
    }

    return summary;
}
