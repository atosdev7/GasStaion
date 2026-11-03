#include "LoanSettlement.h"

#include "Decimal.h"
#include "Clock.h"
#include "Checkout.h"

#include "Barcode.h"
#include "SupplyQueue.h"
#include "TicketLegacy.h"
#include "TicketStatus.h"

#include <QObject>
#include <QSqlError>
#include <QSqlQuery>
#include <QUuid>
#include <QVariant>

namespace {

LoanSettlement::Result refused(const QString &reason)
{
    LoanSettlement::Result result;
    result.error = reason;

    return result;
}

}

QList<LoanSettlement::Loan> LoanSettlement::openLoans(QSqlDatabase db)
{
    QList<Loan> list;

    // More than 0.01 kg left, like the web app.
    QSqlQuery query(db);
    query.exec("SELECT l.id, COALESCE((SELECT name FROM customers WHERE id = l.customer_id), ''), l.fuel_type, l.loaned_at, l.amount_kg, l.settled_amount_kg "
               "FROM ticket_loans l WHERE l.deleted = 0 AND l.amount_kg - l.settled_amount_kg > 1 ORDER BY l.loaned_at, l.id");
    while (query.next()) {
        Loan loan;
        loan.id = query.value(0).toString();
        loan.customer = query.value(1).toString();
        loan.fuelType = query.value(2).toString();
        loan.loanedAt = query.value(3).toString();
        loan.amountX100 = query.value(4).toLongLong();
        loan.settledX100 = query.value(5).toLongLong();
        list.append(loan);
    }

    return list;
}

LoanSettlement::Scan LoanSettlement::scan(QSqlDatabase db, const QString &barcodeText, const QString &fuelType)
{
    Scan scan;

    const qlonglong key = Barcode::toKey(barcodeText.trimmed());
    if (key < 0) {
        scan.error = QObject::tr("That is not a ticket barcode (16 digits).");

        return scan;
    }

    TicketLegacy::ensure(db, key); // a ticket sold on paper before go-live has no row until it is used here

    QSqlQuery query(db);
    query.prepare("SELECT fuel_type, amount_kg, status FROM tickets WHERE barcode = ?");
    query.addBindValue(key);
    if (!query.exec() || !query.next()) {
        scan.error = QObject::tr("This ticket is not known on this terminal.");

        return scan;
    }

    switch (query.value(2).toInt()) {
    case TicketStatus::Sale:
        break;
    case TicketStatus::Publish:
        scan.error = QObject::tr("This ticket was never sold, so it cannot settle a loan.");

        return scan;
    case TicketStatus::Supply:
        scan.error = QObject::tr("This ticket was already used.");

        return scan;
    default:
        scan.error = QObject::tr("This ticket is restricted and cannot be used.");

        return scan;
    }
    if (query.value(0).toString() != fuelType) {
        scan.error = QObject::tr("This ticket is another fuel type than the loan.");

        return scan;
    }
    const int queued = SupplyQueue::nozzleOf(db, key);
    if (queued > 0) {
        scan.error = QObject::tr("This ticket is in the queue of nozzle %1.").arg(queued);

        return scan;
    }

    scan.ok = true;
    scan.kgX100 = query.value(1).toLongLong();

    return scan;
}

QString LoanSettlement::resultText(const Result &result)
{
    const QString settled = QObject::tr("Settled %1 tickets, %2 kg.").arg(result.tickets).arg(Decimal::format(result.kgX100, 2));
    if (result.balanceX100 > 1) {
        return settled + " " + QObject::tr("Still owed: %1 kg").arg(Decimal::format(result.balanceX100, 2));
    }
    if (result.balanceX100 < -1) {
        return settled + " " + QObject::tr("The loan is paid off. The tickets were worth %1 kg more than was owed.").arg(Decimal::format(-result.balanceX100, 2));
    }

    return settled + " " + QObject::tr("The loan is paid off.");
}

LoanSettlement::Result LoanSettlement::create(QSqlDatabase db, const QString &loanId, const QList<qlonglong> &barcodes, int userId, const QDateTime &now)
{
    if (userId <= 0) {
        return refused(QObject::tr("Log in first."));
    }
    const QString behind = Clock::behindRecords(db, now);
    if (!behind.isEmpty()) {
        return refused(behind);
    }
    if (barcodes.isEmpty()) {
        return refused(QObject::tr("Scan the tickets first."));
    }
    if (!db.transaction()) {
        return refused(db.lastError().text());
    }

    QSqlQuery loan(db);
    loan.prepare("SELECT fuel_type, amount_kg, settled_amount_kg FROM ticket_loans WHERE id = ? AND deleted = 0");
    loan.addBindValue(loanId);
    if (!loan.exec() || !loan.next()) {
        db.rollback();

        return refused(QObject::tr("Choose the loan first."));
    }
    const QString fuelType = loan.value(0).toString();
    const qlonglong amount = loan.value(1).toLongLong();
    const qlonglong settled = loan.value(2).toLongLong();

    // Every ticket is checked again inside the transaction: a download may have changed one since it was scanned.
    qlonglong kg = 0;
    QList<qlonglong> seen;
    foreach (qlonglong barcode, barcodes) {
        const Scan found = scan(db, Barcode::fromKey(barcode), fuelType);
        if (!found.ok || seen.contains(barcode)) {
            db.rollback();

            return refused(QObject::tr("Ticket %1 cannot settle the loan: %2")
                               .arg(Barcode::fromKey(barcode)).arg(found.ok ? QObject::tr("it is twice in the list.") : found.error));
        }
        seen << barcode;
        kg += found.kgX100;
    }

    const QString when = now.toString("yyyy-MM-dd HH:mm:ss");
    const QString day = Checkout::businessDay(now); // the business day the record belongs to (9 AM to 9 AM): its date is filled in at once
    bool ok = true;
    for (int i = 0; ok && i < barcodes.size(); ++i) {
        QSqlQuery ticket(db); // a new query each time: addBindValue adds to the values of the last one
        ticket.prepare("SELECT amount_kg FROM tickets WHERE barcode = ?");
        ticket.addBindValue(barcodes.at(i));
        ok = ticket.exec() && ticket.next();
        const qlonglong ticketKg = ok ? ticket.value(0).toLongLong() : 0;

        QSqlQuery settlement(db);
        settlement.prepare("INSERT INTO ticket_loan_settlements (id, loan_id, ticket_barcode, amount_kg, settled_at, date, user_id, synced) "
                           "VALUES (?, ?, ?, ?, ?, '" + day + "', ?, 0)");
        settlement.addBindValue(QUuid::createUuid().toString().mid(1, 36));
        settlement.addBindValue(loanId);
        settlement.addBindValue(barcodes.at(i));
        settlement.addBindValue(ticketKg);
        settlement.addBindValue(when);
        settlement.addBindValue(userId);
        ok = ok && settlement.exec();

        // Supplied, but not at a nozzle: the ticket paid the loan (no supplement).
        QSqlQuery supplied(db);
        supplied.prepare("UPDATE tickets SET status = ?, supply_date = ?, synced = 0 WHERE barcode = ? AND status = ?");
        supplied.addBindValue(int(TicketStatus::Supply));
        supplied.addBindValue(when);
        supplied.addBindValue(barcodes.at(i));
        supplied.addBindValue(int(TicketStatus::Sale));
        ok = ok && supplied.exec() && supplied.numRowsAffected() == 1;
    }

    // The loan row stays as it is (a loan downloaded from the web is not uploaded again); only its settled total moves, until the
    // next download brings the web app's own sum.
    QSqlQuery total(db);
    total.prepare("UPDATE ticket_loans SET settled_amount_kg = settled_amount_kg + ? WHERE id = ?");
    total.addBindValue(kg);
    total.addBindValue(loanId);

    if (!ok || !total.exec() || !db.commit()) {
        db.rollback();

        return refused(QObject::tr("The settlement could not be saved."));
    }

    Result result;
    result.ok = true;
    result.tickets = barcodes.size();
    result.kgX100 = kg;
    result.balanceX100 = amount - settled - kg;

    return result;
}
