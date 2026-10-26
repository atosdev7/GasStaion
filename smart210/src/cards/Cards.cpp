#include "Cards.h"
#include "Checkout.h"

#include "Barcode.h"
#include "PosHash.h"
#include "SpotSale.h"
#include "TicketNumbers.h"
#include "TicketStatus.h"

#include <QCryptographicHash>
#include <QObject>
#include <QSqlError>
#include <QSqlQuery>
#include <QUuid>
#include <QVariant>

const int Cards::MaxAttempts;
const int Cards::PinMinLength;
const int Cards::PinMaxLength;
const int Cards::MaxUidLength;
const int Cards::MaxNameLength;

namespace {

QString newId()
{
    return QUuid::createUuid().toString().mid(1, 36);
}

// 16 hex characters of randomness for a PIN's salt.
QString newSalt()
{
    QString text = QUuid::createUuid().toString();
    text.remove('{').remove('}').remove('-');

    return text.left(16);
}

Cards::Card fromQuery(QSqlQuery &query)
{
    Cards::Card card;
    card.exists = true;
    card.id = query.value(0).toString();
    card.uid = query.value(1).toString();
    card.holderName = query.value(2).toString();
    card.fuelType = query.value(3).toString();
    card.pinSalt = query.value(4).toString();
    card.pinHash = query.value(5).toString();
    card.failedAttempts = query.value(6).toInt();
    card.status = query.value(7).toString();
    card.balanceX100 = query.value(8).toLongLong();
    card.deleted = query.value(9).toInt() != 0;
    card.synced = query.value(10).toInt() != 0;
    card.holderPhone = query.value(11).toString();

    return card;
}

const char *const Columns = "id, uid, holder_name, fuel_type, pin_salt, pin_hash, failed_attempts, status, balance_kg, deleted, synced, holder_phone";

}

QString Cards::normalizeUid(const QString &text)
{
    QString clean;
    for (int i = 0; i < text.size(); ++i) {
        const QChar c = text.at(i);
        if (!c.isSpace() && c.isPrint()) {
            clean.append(c);
        }
    }

    return clean.toUpper();
}

bool Cards::validPin(const QString &pin)
{
    if (pin.size() < PinMinLength || pin.size() > PinMaxLength) {
        return false;
    }
    for (int i = 0; i < pin.size(); ++i) {
        if (!pin.at(i).isDigit() || pin.at(i).unicode() > 127) {
            return false; // ASCII digits only
        }
    }

    return true;
}

Cards::Card Cards::find(QSqlDatabase db, const QString &uid)
{
    QSqlQuery query(db);
    query.prepare(QString("SELECT %1 FROM cards WHERE uid = ?").arg(Columns));
    query.addBindValue(normalizeUid(uid));
    if (query.exec() && query.next()) {
        return fromQuery(query);
    }

    return Card();
}

Cards::Card Cards::findById(QSqlDatabase db, const QString &id)
{
    QSqlQuery query(db);
    query.prepare(QString("SELECT %1 FROM cards WHERE id = ?").arg(Columns));
    query.addBindValue(id);
    if (query.exec() && query.next()) {
        return fromQuery(query);
    }

    return Card();
}

Cards::Registration Cards::registerCard(QSqlDatabase db, const QString &rawUid, const QString &holderName, const QString &fuelType, const QString &pin,
                                        const QString &holderPhone)
{
    Registration result;
    const QString uid = normalizeUid(rawUid);
    const QString name = holderName.trimmed();
    const QString phone = holderPhone.trimmed();

    if (uid.isEmpty() || uid.size() > MaxUidLength) {
        result.error = QObject::tr("Read the card first.");

        return result;
    }
    if (name.isEmpty() || name.size() > MaxNameLength) {
        result.error = QObject::tr("Enter the name of the customer.");

        return result;
    }
    if (phone.size() > MaxPhoneLength) {
        result.error = QObject::tr("The phone number is too long.");

        return result;
    }
    if (!validPin(pin)) {
        result.error = QObject::tr("The PIN is 4 to 8 digits.");

        return result;
    }

    QSqlQuery fuel(db);
    fuel.prepare("SELECT 1 FROM fuel_types WHERE fuel_type = ?");
    fuel.addBindValue(fuelType);
    if (!fuel.exec() || !fuel.next()) {
        result.error = QObject::tr("Choose the fuel type.");

        return result;
    }

    QSqlQuery known(db);
    known.prepare("SELECT 1 FROM cards WHERE uid = ?");
    known.addBindValue(uid);
    if (known.exec() && known.next()) {
        result.error = QObject::tr("This card is registered already.");

        return result;
    }

    const QString salt = newSalt();
    const QString hash = QString::fromLatin1(QCryptographicHash::hash((salt + pin).toUtf8(), QCryptographicHash::Sha256).toHex());

    QSqlQuery insert(db);
    insert.prepare("INSERT INTO cards (id, uid, holder_name, holder_phone, fuel_type, pin_salt, pin_hash, failed_attempts, status, balance_kg, deleted, synced) "
                   "VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'active', 0, 0, 0)");
    result.cardId = newId();
    insert.addBindValue(result.cardId);
    insert.addBindValue(uid);
    insert.addBindValue(name);
    insert.addBindValue(phone.isEmpty() ? QVariant(QVariant::String) : QVariant(phone));
    insert.addBindValue(fuelType);
    insert.addBindValue(salt);
    insert.addBindValue(hash);
    if (!insert.exec()) {
        result.cardId.clear();
        result.error = QObject::tr("The card could not be saved.");

        return result;
    }

    result.ok = true;

    return result;
}

Cards::PinCheck Cards::checkPin(QSqlDatabase db, const QString &cardId, const QString &pin)
{
    PinCheck check;
    const Card card = findById(db, cardId);

    if (!card.exists || card.deleted) {
        check.error = QObject::tr("This card is not known.");

        return check;
    }
    if (card.status == "blocked") {
        check.error = QObject::tr("This card is blocked (lost).");

        return check;
    }
    if (card.status != "active") {
        check.error = QObject::tr("This card is locked after wrong PINs. The manager or the accountant unlocks it on the web app.");

        return check;
    }
    if (!validPin(pin)) {
        check.error = QObject::tr("The PIN is 4 to 8 digits.");

        return check;
    }

    if (PosHash::matches(card.pinSalt, pin, card.pinHash)) {
        if (card.failedAttempts > 0) {
            QSqlQuery reset(db);
            reset.prepare("UPDATE cards SET failed_attempts = 0, synced = 0 WHERE id = ?");
            reset.addBindValue(card.id);
            reset.exec();
        }
        check.result = PinRight;
        check.triesLeft = MaxAttempts;

        return check;
    }

    const int attempts = card.failedAttempts + 1;
    const bool lock = attempts >= MaxAttempts;
    QSqlQuery wrong(db);
    wrong.prepare("UPDATE cards SET failed_attempts = ?, status = ?, synced = 0 WHERE id = ?");
    wrong.addBindValue(attempts);
    wrong.addBindValue(lock ? "locked" : "active");
    wrong.addBindValue(card.id);
    wrong.exec();

    check.result = lock ? PinNowLocked : PinWrong;
    check.triesLeft = lock ? 0 : MaxAttempts - attempts;
    check.error = lock
        ? QObject::tr("Wrong PIN. The card is locked now: the manager or the accountant unlocks it on the web app.")
        : QObject::tr("Wrong PIN. %1 tries left.").arg(check.triesLeft);

    return check;
}

Cards::TopUpResult Cards::topUp(QSqlDatabase db, const QString &cardId, qlonglong kgX100, qlonglong priceX100, int userId, const QDateTime &now,
                                qlonglong totalX100)
{
    TopUpResult result;

    if (userId <= 0) {
        result.error = QObject::tr("Log in first.");

        return result;
    }
    if (kgX100 < 1 || kgX100 > SpotSale::MaxKgX100) {
        result.error = QObject::tr("Enter the weight in kg, above 0 and up to 999999.");

        return result;
    }
    if (priceX100 < 1 || priceX100 > SpotSale::MaxPriceX100) {
        result.error = QObject::tr("Enter the price per kg.");

        return result;
    }
    const qlonglong total = totalX100 >= 0 ? totalX100 : SpotSale::amountFor(kgX100, priceX100);
    if (total < 1 || total > SpotSale::MaxAmountX100) {
        result.error = QObject::tr("The amount is not allowed.");

        return result;
    }

    if (!db.transaction()) {
        result.error = db.lastError().text();

        return result;
    }

    const Card card = findById(db, cardId);
    if (!card.exists || card.deleted) {
        db.rollback();
        result.error = QObject::tr("This card is not known.");

        return result;
    }
    if (card.status == "blocked") {
        db.rollback();
        result.error = QObject::tr("This card is blocked (lost): it cannot be topped up.");

        return result;
    }

    const QString when = now.toString("yyyy-MM-dd HH:mm:ss");
    const QString day = Checkout::businessDay(now); // the business day the record belongs to (9 AM to 9 AM): its date is filled in at once
    result.saleId = newId();

    // A card is paid in cash, in full; it has no customer and no ticket. Its date is the business day of the moment.
    QSqlQuery sale(db);
    sale.prepare("INSERT INTO sales (id, created_at, updated_at, customer_id, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, "
                 "unpaid_amount, receipt, usage, comment, sale_date, user_id, deleted_at, synced, card_id) "
                 "VALUES (?, ?, ?, NULL, 'card', ?, ?, ?, 'cash', ?, ?, 0, NULL, 'sale', NULL, '" + day + "', ?, NULL, 0, ?)");
    sale.addBindValue(result.saleId);
    sale.addBindValue(when);
    sale.addBindValue(when);
    sale.addBindValue(card.fuelType);
    sale.addBindValue(priceX100);
    sale.addBindValue(kgX100);
    sale.addBindValue(total);
    sale.addBindValue(total);
    sale.addBindValue(userId);
    sale.addBindValue(card.id);

    if (!sale.exec() || !adjustBalance(db, card.id, kgX100) || !db.commit()) {
        db.rollback();
        result.saleId.clear();
        result.error = QObject::tr("The top-up could not be saved.");

        return result;
    }

    result.ok = true;
    result.totalX100 = total;
    result.balanceX100 = card.balanceX100 + kgX100;

    return result;
}

Cards::TicketResult Cards::issueTicket(QSqlDatabase db, const QString &cardId, qlonglong kgX100, int userId, const QDateTime &now)
{
    TicketResult result;

    if (userId <= 0) {
        result.error = QObject::tr("Log in first.");

        return result;
    }
    if (kgX100 < 1 || kgX100 > SpotSale::MaxKgX100) {
        result.error = QObject::tr("Enter the weight in kg, above 0 and up to 999999.");

        return result;
    }
    if (!db.transaction()) {
        result.error = db.lastError().text();

        return result;
    }

    const Card card = findById(db, cardId);
    if (!card.exists || card.deleted) {
        db.rollback();
        result.error = QObject::tr("This card is not known.");

        return result;
    }
    if (!card.isActive()) {
        db.rollback();
        result.error = card.status == "blocked" ? QObject::tr("This card is blocked (lost).") : QObject::tr("This card is locked after wrong PINs. The manager or the accountant unlocks it on the web app.");

        return result;
    }
    if (kgX100 > card.balanceX100) {
        db.rollback();
        result.error = QObject::tr("The card holds only %1 kg.").arg(QString::number(card.balanceX100 / 100.0, 'f', 2));

        return result;
    }

    QString error;
    const qlonglong barcode = TicketNumbers::next(db, "direct", &error);
    if (barcode < 0) {
        db.rollback();
        result.error = error;

        return result;
    }

    // Sold in the moment it is made: the card has paid for it, so there is no sale behind it.
    QSqlQuery ticket(db);
    ticket.prepare("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, sale_id, sale_date, synced, card_id) VALUES (?, ?, NULL, ?, ?, NULL, ?, 0, ?)");
    ticket.addBindValue(barcode);
    ticket.addBindValue(card.fuelType);
    ticket.addBindValue(kgX100);
    ticket.addBindValue(int(TicketStatus::Sale));
    ticket.addBindValue(now.toString("yyyy-MM-dd HH:mm:ss"));
    ticket.addBindValue(card.id);

    if (!ticket.exec() || !adjustBalance(db, card.id, -kgX100) || !db.commit()) {
        db.rollback();
        result.error = QObject::tr("The ticket could not be saved.");

        return result;
    }

    result.ok = true;
    result.barcode = Barcode::fromKey(barcode);
    result.kgX100 = kgX100;
    result.balanceX100 = card.balanceX100 - kgX100;

    return result;
}

bool Cards::cancelTicket(QSqlDatabase db, const QString &barcode, QString *why)
{
    const qlonglong key = Barcode::toKey(barcode);
    QSqlQuery find(db);
    find.prepare("SELECT card_id, status, synced, amount_kg FROM tickets WHERE barcode = ?");
    find.addBindValue(key);
    if (key < 0 || !find.exec() || !find.next() || find.value(0).isNull()) {
        *why = QObject::tr("This is not a ticket made for a card.");

        return false;
    }

    const QString cardId = find.value(0).toString();
    const qlonglong kg = find.value(3).toLongLong();
    if (find.value(1).toInt() != int(TicketStatus::Sale)) {
        *why = QObject::tr("This ticket was used already: it cannot be cancelled.");

        return false;
    }
    if (find.value(2).toInt() == 0) {
        QSqlQuery queued(db);
        queued.prepare("SELECT 1 FROM supply_queue WHERE barcode = ?");
        queued.addBindValue(key);
        if (queued.exec() && queued.next()) {
            *why = QObject::tr("This ticket is in the queue of a nozzle: take it out first.");

            return false;
        }
    } else {
        *why = QObject::tr("This ticket was uploaded already: the manager cancels it on the web app by restricting it, and the kg go back to the card.");

        return false;
    }

    if (!db.transaction()) {
        *why = db.lastError().text();

        return false;
    }
    QSqlQuery remove(db);
    remove.prepare("DELETE FROM tickets WHERE barcode = ?");
    remove.addBindValue(key);
    if (!remove.exec() || !adjustBalance(db, cardId, kg) || !db.commit()) {
        db.rollback();
        *why = QObject::tr("The ticket could not be cancelled.");

        return false;
    }

    return true;
}

QString Cards::lastTicket(QSqlDatabase db, const QString &cardId)
{
    QSqlQuery query(db);
    query.prepare("SELECT barcode FROM tickets WHERE card_id = ? AND status = ? ORDER BY sale_date DESC, barcode DESC LIMIT 1");
    query.addBindValue(cardId);
    query.addBindValue(int(TicketStatus::Sale));
    if (query.exec() && query.next()) {
        return Barcode::fromKey(query.value(0).toLongLong());
    }

    return QString();
}

bool Cards::adjustBalance(QSqlDatabase db, const QString &cardId, qlonglong deltaX100)
{
    QSqlQuery query(db);
    query.prepare("UPDATE cards SET balance_kg = balance_kg + ? WHERE id = ?");
    query.addBindValue(deltaX100);
    query.addBindValue(cardId);

    return query.exec() && query.numRowsAffected() == 1;
}
