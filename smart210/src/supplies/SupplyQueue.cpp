#include "SupplyQueue.h"

#include "Barcode.h"
#include "Settings.h"
#include "TicketLegacy.h"
#include "TicketNumbers.h"
#include "TicketStatus.h"

#include <QObject>
#include <QSqlQuery>
#include <QVariant>

SupplyQueue::Scan SupplyQueue::check(QSqlDatabase db, const QString &barcodeText, const QString &fuelType)
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
        scan.error = QObject::tr("This ticket was never sold.");

        return scan;
    case TicketStatus::Supply:
        scan.error = QObject::tr("This ticket was already used.");

        return scan;
    default:
        scan.error = QObject::tr("This ticket is restricted and cannot be used.");

        return scan;
    }
    if (query.value(0).toString() != fuelType) {
        scan.error = QObject::tr("This ticket is another fuel type (%1) than the nozzle (%2).").arg(query.value(0).toString()).arg(fuelType);

        return scan;
    }

    const int queued = nozzleOf(db, key);
    if (queued > 0) {
        scan.error = QObject::tr("This ticket is already in the queue of nozzle %1.").arg(queued);

        return scan;
    }

    scan.ok = true;
    scan.barcode = key;
    scan.kgX100 = query.value(1).toLongLong();

    return scan;
}

QString SupplyQueue::mixProblem(QSqlDatabase db, const QList<qlonglong> &have, qlonglong candidate)
{
    if (have.isEmpty()) {
        return QString();
    }

    const QString kind = TicketNumbers::kindOf(db, candidate);
    // the kind of the tickets that are there: a remainder ticket goes with press and spot sale tickets, so the kind is that of the first ticket that is not a remainder
    QString base;
    qlonglong baseTicket = 0;
    foreach (qlonglong barcode, have) {
        const QString other = TicketNumbers::kindOf(db, barcode);
        if (other != "remainder") {
            base = other;
            baseTicket = barcode;
            break;
        }
    }
    const QString remainderRule = QObject::tr("A remainder ticket goes with press and spot sale tickets only, not with %1 tickets.");

    if (kind == "remainder") {
        if (!base.isEmpty() && base != "press" && base != "direct") {
            return remainderRule.arg(TicketNumbers::kindName(base));
        }

        return QString();
    }
    if (base.isEmpty()) {
        // only remainder tickets so far: press and spot sale tickets may join them
        return kind == "press" || kind == "direct" ? QString() : remainderRule.arg(TicketNumbers::kindName(kind));
    }
    if (kind != base) {
        return QObject::tr("A supply takes tickets of one kind only: this is a %1 ticket and the others are %2 tickets.")
            .arg(TicketNumbers::kindName(kind)).arg(TicketNumbers::kindName(base));
    }
    if (kind == "card") {
        QSqlQuery cards(db);
        cards.prepare("SELECT card_id FROM tickets WHERE barcode = ?");
        cards.addBindValue(candidate);
        const QString card = cards.exec() && cards.next() ? cards.value(0).toString() : QString();
        cards.prepare("SELECT card_id FROM tickets WHERE barcode = ?");
        cards.addBindValue(baseTicket);
        const QString other = cards.exec() && cards.next() ? cards.value(0).toString() : QString();
        if (card != other) {
            return QObject::tr("A supply takes the tickets of one card only: this ticket was made for another card.");
        }
    }

    return QString();
}

SupplyQueue::Scan SupplyQueue::add(QSqlDatabase db, int nozzle, const QString &barcodeText, const QString &fuelType, const QDateTime &now)
{
    Scan scan = check(db, barcodeText, fuelType);
    if (!scan.ok) {
        return scan;
    }

    QList<qlonglong> have;
    foreach (const Entry &entry, entries(db, nozzle)) {
        have << entry.barcode;
    }
    const QString mixed = mixProblem(db, have, scan.barcode);
    if (!mixed.isEmpty()) {
        scan.ok = false;
        scan.error = mixed;

        return scan;
    }

    QSqlQuery position(db);
    position.prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM supply_queue WHERE nozzle_number = ?");
    position.addBindValue(nozzle);
    position.exec();
    position.next();

    QSqlQuery insert(db);
    insert.prepare("INSERT INTO supply_queue (barcode, nozzle_number, amount_kg, position, added_at) VALUES (?, ?, ?, ?, ?)");
    insert.addBindValue(scan.barcode);
    insert.addBindValue(nozzle);
    insert.addBindValue(scan.kgX100);
    insert.addBindValue(position.value(0).toInt());
    insert.addBindValue(now.toString("yyyy-MM-dd HH:mm:ss"));
    if (!insert.exec()) {
        scan.ok = false;
        scan.error = QObject::tr("The ticket could not be added.");
    }

    return scan;
}

bool SupplyQueue::remove(QSqlDatabase db, int nozzle, qlonglong barcode)
{
    QSqlQuery query(db);
    query.prepare("DELETE FROM supply_queue WHERE nozzle_number = ? AND barcode = ?");
    query.addBindValue(nozzle);
    query.addBindValue(barcode);

    return query.exec() && query.numRowsAffected() == 1;
}

void SupplyQueue::clear(QSqlDatabase db, int nozzle)
{
    QSqlQuery query(db);
    query.prepare("DELETE FROM supply_queue WHERE nozzle_number = ?");
    query.addBindValue(nozzle);
    query.exec();
}

QList<SupplyQueue::Entry> SupplyQueue::entries(QSqlDatabase db, int nozzle)
{
    QList<Entry> list;

    QSqlQuery query(db);
    query.prepare("SELECT barcode, amount_kg FROM supply_queue WHERE nozzle_number = ? ORDER BY position");
    query.addBindValue(nozzle);
    query.exec();
    while (query.next()) {
        Entry entry;
        entry.barcode = query.value(0).toLongLong();
        entry.kgX100 = query.value(1).toLongLong();
        list.append(entry);
    }

    return list;
}

qlonglong SupplyQueue::totalX100(QSqlDatabase db, int nozzle)
{
    QSqlQuery query(db);
    query.prepare("SELECT COALESCE(SUM(amount_kg), 0) FROM supply_queue WHERE nozzle_number = ?");
    query.addBindValue(nozzle);

    return query.exec() && query.next() ? query.value(0).toLongLong() : 0;
}

int SupplyQueue::nozzleOf(QSqlDatabase db, qlonglong barcode)
{
    QSqlQuery query(db);
    query.prepare("SELECT nozzle_number FROM supply_queue WHERE barcode = ?");
    query.addBindValue(barcode);

    return query.exec() && query.next() ? query.value(0).toInt() : 0;
}

SupplyQueue::Session SupplyQueue::session(QSqlDatabase db, int nozzle)
{
    Session session;

    QSqlQuery query(db);
    query.prepare("SELECT preset_kg, session_id, started_at, card_id FROM supply_sessions WHERE nozzle_number = ?");
    query.addBindValue(nozzle);
    if (query.exec() && query.next()) {
        session.exists = true;
        session.nozzle = nozzle;
        session.presetX100 = query.value(0).toLongLong();
        session.sessionId = query.value(1).toUInt();
        session.startedAt = query.value(2).toString();
        session.cardId = query.value(3).toString(); // NULL gives an empty text
    }

    return session;
}

quint32 SupplyQueue::begin(QSqlDatabase db, int nozzle, qlonglong presetX100, const QDateTime &now, const QString &cardId, quint32 adoptId)
{
    quint32 sessionId = adoptId;
    if (sessionId == 0) {
        // the next number: above the counter and above every session and supply this terminal holds (a lost counter cannot give a number twice)
        Settings settings(db);
        qlonglong highest = qMax(qlonglong(0), settings.value("supply_session_next").toLongLong() - 1);   // 0 is no session: the first id is 1
        QSqlQuery top(db);
        if (top.exec("SELECT MAX(m) FROM (SELECT MAX(session_id) AS m FROM supply_sessions UNION ALL SELECT MAX(session_id) FROM supplements)") && top.next() && !top.value(0).isNull()) {
            highest = qMax(highest, top.value(0).toLongLong());
        }
        sessionId = quint32(highest + 1);
        settings.setValue("supply_session_next", QString::number(qlonglong(sessionId) + 1));
    }

    QSqlQuery query(db);
    query.prepare("INSERT OR REPLACE INTO supply_sessions (nozzle_number, preset_kg, session_id, started_at, card_id) VALUES (?, ?, ?, ?, ?)");
    query.addBindValue(nozzle);
    query.addBindValue(presetX100);
    query.addBindValue(sessionId);
    query.addBindValue(now.toString("yyyy-MM-dd HH:mm:ss"));
    query.addBindValue(cardId.isEmpty() ? QVariant(QVariant::String) : QVariant(cardId));

    return query.exec() ? sessionId : 0;
}

QList<qlonglong> SupplyQueue::ticketsOfSession(QSqlDatabase db, quint32 sessionId)
{
    QList<qlonglong> list;
    if (sessionId == 0) {
        return list;
    }

    // the fill runs (or waits for its result): the queue of the nozzle that holds the session
    QSqlQuery live(db);
    live.prepare("SELECT q.barcode FROM supply_queue q JOIN supply_sessions s ON s.nozzle_number = q.nozzle_number WHERE s.session_id = ? ORDER BY q.position, q.rowid");
    live.addBindValue(sessionId);
    if (live.exec()) {
        while (live.next()) {
            list << live.value(0).toLongLong();
        }
    }
    if (!list.isEmpty()) {
        return list;
    }

    // recorded: the tickets of the supply of that session
    QSqlQuery done(db);
    done.prepare("SELECT t.barcode FROM tickets t JOIN supplements p ON p.id = t.supplement_id WHERE p.session_id = ? ORDER BY t.barcode");
    done.addBindValue(sessionId);
    if (done.exec()) {
        while (done.next()) {
            list << done.value(0).toLongLong();
        }
    }

    return list;
}

void SupplyQueue::release(QSqlDatabase db, int nozzle)
{
    QSqlQuery session(db);
    session.prepare("DELETE FROM supply_sessions WHERE nozzle_number = ?");
    session.addBindValue(nozzle);
    session.exec();
}

void SupplyQueue::end(QSqlDatabase db, int nozzle)
{
    release(db, nozzle);
    clear(db, nozzle);
}
