#include "SupplyRecord.h"
#include "Checkout.h"

#include "Barcode.h"
#include "BoardProtocol.h"
#include "Cards.h"
#include "Decimal.h"
#include "SupplyQueue.h"
#include "TicketNumbers.h"
#include "TicketStatus.h"

#include <QObject>
#include <QSqlError>
#include <QSqlQuery>
#include <QUuid>
#include <QVariant>

namespace {

SupplyRecord::Outcome refused(const QString &reason)
{
    SupplyRecord::Outcome outcome;
    outcome.error = reason;

    return outcome;
}

QString newId()
{
    return QUuid::createUuid().toString().mid(1, 36);
}

// The tickets are still sold, of the fuel, and not in another queue: the checks of a scan, again, because a download may have changed one.
bool stillGood(QSqlDatabase db, qlonglong barcode, const QString &fuelType, int ownNozzle)
{
    QSqlQuery query(db);
    query.prepare("SELECT 1 FROM tickets WHERE barcode = ? AND status = ? AND fuel_type = ?");
    query.addBindValue(barcode);
    query.addBindValue(int(TicketStatus::Sale));
    query.addBindValue(fuelType);
    if (!query.exec() || !query.next()) {
        return false;
    }

    const int queued = SupplyQueue::nozzleOf(db, barcode);

    return queued == 0 || queued == ownNozzle;
}

bool supplyTicket(QSqlDatabase db, qlonglong barcode, const QString &supplementId, const QString &when)
{
    QSqlQuery query(db);
    query.prepare("UPDATE tickets SET status = ?, supply_date = ?, supplement_id = ?, synced = 0 WHERE barcode = ? AND status = ?");
    query.addBindValue(int(TicketStatus::Supply));
    query.addBindValue(when);
    query.addBindValue(supplementId);
    query.addBindValue(barcode);
    query.addBindValue(int(TicketStatus::Sale));

    return query.exec() && query.numRowsAffected() == 1;
}

}

const qlonglong SupplyRecord::ToleranceX100;

SupplyRecord::Outcome SupplyRecord::record(QSqlDatabase db, int nozzleNumber, const Reading &reading, const QDateTime &now)
{
    if (!db.transaction()) {
        return refused(db.lastError().text());
    }

    QSqlQuery nozzle(db);
    nozzle.prepare("SELECT id, fuel_type, mode, density FROM nozzles WHERE number = ? AND deleted = 0");
    nozzle.addBindValue(nozzleNumber);
    if (!nozzle.exec() || !nozzle.next()) {
        db.rollback();

        return refused(QObject::tr("Nozzle %1 is not known yet.").arg(nozzleNumber));
    }
    const int nozzleId = nozzle.value(0).toInt();
    const QString fuelType = nozzle.value(1).toString();
    // the nozzle's mode (auto 0, manual 1 as the board reports it); a reading typed in by hand is manual too
    const bool manualMode = reading.byHand || nozzle.value(2).toInt() == BoardProtocol::ModeManual;
    // kg per litre x10000 of the board's config; without one, what the litres and the kg give
    qlonglong density = nozzle.value(3).isNull() ? -1 : nozzle.value(3).toLongLong();
    if (density <= 0 && reading.litersX100 > 0) {
        density = (reading.dispensedX100 * 10000 + reading.litersX100 / 2) / reading.litersX100;
    }

    if (reading.resultId > 0) {
        QSqlQuery seen(db);
        seen.prepare("SELECT id FROM supplements WHERE nozzle_id = ? AND board_result_id = ?");
        seen.addBindValue(nozzleId);
        seen.addBindValue(reading.resultId);
        if (seen.exec() && seen.next()) {
            db.rollback();
            Outcome outcome;
            outcome.ok = true;
            outcome.duplicate = true;
            outcome.supplementId = seen.value(0).toString();

            return outcome;
        }
    }

    // The queue of a started fill goes with its result, if it still holds up. A fill paid with a card has no tickets: the card's balance falls
    // by what really came out (and no remainder is made: the card keeps what was not taken).
    const SupplyQueue::Session session = SupplyQueue::session(db, nozzleNumber);
    const bool byCard = session.byCard();
    QList<SupplyQueue::Entry> queue = (session.exists && !byCard) ? SupplyQueue::entries(db, nozzleNumber) : QList<SupplyQueue::Entry>();
    qlonglong total = 0;
    bool withTickets = !queue.isEmpty();
    foreach (const SupplyQueue::Entry &entry, queue) {
        total += entry.kgX100;
        withTickets = withTickets && stillGood(db, entry.barcode, fuelType, nozzleNumber);
    }
    if (withTickets && reading.dispensedX100 > total) {
        withTickets = false; // more came out than the tickets cover: the station never accepts that, the cashier sorts the tickets out
    }

    // The tickets typed in with the reading replace the queue: each must pass the checks of a scan, and together they must cover what was
    // dispensed. What is left of them is a remainder (a new ticket), unless it is within the tolerance: the station keeps that.
    const bool typed = !reading.tickets.isEmpty();
    if (typed) {
        queue.clear();
        total = 0;
        foreach (qlonglong barcode, reading.tickets) {
            const SupplyQueue::Scan scan = SupplyQueue::check(db, Barcode::fromKey(barcode), fuelType);
            bool twice = false;
            foreach (const SupplyQueue::Entry &have, queue) {
                twice = twice || have.barcode == barcode;
            }
            if (!scan.ok || twice) {
                db.rollback();

                return refused(QObject::tr("Ticket %1 cannot be used: %2").arg(Barcode::fromKey(barcode)).arg(scan.ok ? QObject::tr("it is twice in the list.") : scan.error));
            }
            SupplyQueue::Entry entry;
            entry.barcode = barcode;
            entry.kgX100 = scan.kgX100;
            queue.append(entry);
            total += scan.kgX100;
        }
        if (total < reading.dispensedX100) {
            db.rollback();

            return refused(QObject::tr("The tickets add up to %1 kg but %2 kg was dispensed: the tickets must cover everything that was dispensed.")
                               .arg(Decimal::format(total, 2)).arg(Decimal::format(reading.dispensedX100, 2)));
        }
        withTickets = true;
    }

    qlonglong remainder = 0;
    if (withTickets) {
        const qlonglong rest = total - reading.dispensedX100;
        const bool atPreset = typed || (reading.reason == BoardProtocol::ReasonPresetReached); // a typed supply is as good as one that ended at its preset
        remainder = (rest > 0 && !(atPreset && rest <= ToleranceX100)) ? rest : 0;
    }

    const QString when = now.toString("yyyy-MM-dd HH:mm:ss");
    const QString day = Checkout::businessDay(now); // the business day the record belongs to (9 AM to 9 AM): its date is filled in at once
    Outcome outcome;
    outcome.supplementId = newId();

    QSqlQuery supplement(db);
    supplement.prepare("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, density, mode, supplied_at, "
                       "supply_date, synced, board_result_id, card_id, comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '" + day + "', 0, ?, ?, ?)");
    supplement.addBindValue(outcome.supplementId);
    supplement.addBindValue(fuelType);
    supplement.addBindValue(nozzleId);
    supplement.addBindValue(reading.litersX100);
    supplement.addBindValue(reading.dispensedX100);
    supplement.addBindValue(byCard ? (session.presetX100 > 0 ? session.presetX100 : reading.dispensedX100)
                                   : (withTickets ? total : (reading.presetX100 > 0 ? reading.presetX100 : reading.dispensedX100)));
    supplement.addBindValue(density > 0 ? QVariant(density) : QVariant(QVariant::LongLong));
    supplement.addBindValue(manualMode ? "manual" : "auto");
    supplement.addBindValue(when);
    supplement.addBindValue(reading.resultId > 0 ? QVariant(reading.resultId) : QVariant(QVariant::Int));
    supplement.addBindValue(byCard ? QVariant(session.cardId) : QVariant(QVariant::String));
    supplement.addBindValue(reading.comment.trimmed().isEmpty() ? QVariant(QVariant::String) : QVariant(reading.comment.trimmed().left(255)));
    bool ok = supplement.exec();

    if (ok && byCard) {
        ok = Cards::adjustBalance(db, session.cardId, -reading.dispensedX100);
        outcome.kgX100 = reading.dispensedX100;
    }

    if (ok && withTickets) {
        foreach (const SupplyQueue::Entry &entry, queue) {
            ok = ok && supplyTicket(db, entry.barcode, outcome.supplementId, when);
        }
        outcome.tickets = queue.size();
        outcome.kgX100 = total;
    }

    if (ok && remainder > 0) {
        QString error;
        const qlonglong barcode = TicketNumbers::next(db, "remainder", fuelType, &error);
        if (barcode < 0) {
            db.rollback();

            return refused(error);
        }

        QSqlQuery ticket(db);
        ticket.prepare("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, sale_id, sale_date, synced) VALUES (?, ?, NULL, ?, ?, NULL, ?, 0)");
        ticket.addBindValue(barcode);
        ticket.addBindValue(fuelType);
        ticket.addBindValue(remainder);
        ticket.addBindValue(int(TicketStatus::Sale));
        ticket.addBindValue(when);

        QSqlQuery row(db);
        row.prepare("INSERT INTO remainders (id, supplement_id, ticket_barcode, fuel_type, amount_kg, created_at, remainder_date, synced) "
                    "VALUES (?, ?, ?, ?, ?, ?, '" + day + "', 0)");
        row.addBindValue(newId());
        row.addBindValue(outcome.supplementId);
        row.addBindValue(barcode);
        row.addBindValue(fuelType);
        row.addBindValue(remainder);
        row.addBindValue(when);
        ok = ticket.exec() && row.exec();

        outcome.remainderBarcode = Barcode::fromKey(barcode);
        outcome.remainderKgX100 = remainder;
    }

    if (ok && session.exists) {
        SupplyQueue::end(db, nozzleNumber);
    }

    if (!ok || !db.commit()) {
        db.rollback();

        return refused(QObject::tr("The supply could not be saved."));
    }

    outcome.ok = true;

    return outcome;
}

QList<SupplyRecord::RemainderRow> SupplyRecord::remainders(QSqlDatabase db, int limit)
{
    QList<RemainderRow> list;

    QSqlQuery query(db);
    query.prepare("SELECT r.created_at, COALESCE((SELECT COALESCE(NULLIF(name, ''), number) FROM nozzles WHERE id = s.nozzle_id), ''), r.fuel_type, r.amount_kg, r.ticket_barcode, "
                  "COALESCE(r.printed_at, ''), COALESCE(t.status, 1), r.id, COALESCE(r.comment, '') "
                  "FROM remainders r LEFT JOIN supplements s ON s.id = r.supplement_id LEFT JOIN tickets t ON t.barcode = r.ticket_barcode "
                  "ORDER BY r.created_at DESC, r.rowid DESC LIMIT ?");
    query.addBindValue(limit);
    query.exec();
    while (query.next()) {
        RemainderRow row;
        row.time = query.value(0).toString();
        row.nozzle = query.value(1).toString();
        row.fuelType = query.value(2).toString();
        row.kgX100 = query.value(3).toLongLong();
        row.barcode = Barcode::fromKey(query.value(4).toLongLong());
        row.printedAt = query.value(5).toString();
        row.ticketStatus = query.value(6).toInt();
        row.id = query.value(7).toString();
        row.comment = query.value(8).toString();
        list.append(row);
    }

    return list;
}

bool SupplyRecord::setRemainderComment(QSqlDatabase db, const QString &id, const QString &comment)
{
    const QString text = comment.trimmed().left(255);
    QSqlQuery query(db);
    query.prepare("UPDATE remainders SET comment = ?, synced = 0, uploaded_at = NULL WHERE id = ? AND COALESCE(comment, '') <> ?");
    query.addBindValue(text.isEmpty() ? QVariant(QVariant::String) : QVariant(text));
    query.addBindValue(id);
    query.addBindValue(text);

    return query.exec() && query.numRowsAffected() > 0;
}

int SupplyRecord::unprintedRemainders(QSqlDatabase db)
{
    QSqlQuery query(db);

    // a remainder ticket that was used already needs no print
    return query.exec("SELECT COUNT(*) FROM remainders r WHERE r.printed_at IS NULL AND COALESCE((SELECT status FROM tickets WHERE barcode = r.ticket_barcode), 1) = 1")
            && query.next() ? query.value(0).toInt() : 0;
}

void SupplyRecord::markRemainderPrinted(QSqlDatabase db, const QString &barcode, const QDateTime &now)
{
    QSqlQuery query(db);
    query.prepare("UPDATE remainders SET printed_at = ? WHERE ticket_barcode = ?");
    query.addBindValue(now.toString("yyyy-MM-dd HH:mm:ss"));
    query.addBindValue(Barcode::toKey(barcode));
    query.exec();
}
