#ifndef UPLOADSUMMARY_H
#define UPLOADSUMMARY_H

#include <QSqlDatabase>

// What waits for the cashier's Upload, added up for the check screen. Amounts are the stored integers (x100). The same
// conditions as the upload itself decide what counts (see Uploader::waitingCondition), so the screen shows exactly what would be sent.
struct UploadSummary {
    UploadSummary()
        : sales(0), salesKg(0), salesAmount(0), salesPaid(0), supplements(0), supplementKg(0), remainders(0), remainderKg(0),
          loans(0), loanKg(0), settlements(0), settlementKg(0), tickets(0), refused(0), deletedSales(0)
    {
    }

    int sales;
    qlonglong salesKg;
    qlonglong salesAmount; // total_amount
    qlonglong salesPaid;   // paid_amount
    int supplements;
    qlonglong supplementKg;
    int remainders;
    qlonglong remainderKg;
    int loans;
    qlonglong loanKg;
    int settlements;
    qlonglong settlementKg;
    int tickets;      // ticket changes (sold or supplied) that go with the records above
    int refused;      // records the server refused at an earlier upload and that are still waiting
    int deletedSales; // sales deleted before an upload: not sent, and not counted above

    int total() const { return sales + supplements + remainders + loans + settlements + tickets; }

    static UploadSummary compute(QSqlDatabase db);
};

#endif
