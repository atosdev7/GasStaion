#include "SyncStore.h"

#include "Barcode.h"
#include "Decimal.h"
#include "Settings.h"
#include "TicketNumbers.h"
#include "TicketStatus.h"

#include <QDateTime>
#include <QSqlError>
#include <QSqlQuery>
#include <QVariant>

namespace {

QVariant textOrNull(const QJsonValue &value)
{
    return value.isNull() || value.isUndefined() ? QVariant(QVariant::String) : QVariant(value.toString());
}

QVariant intOrNull(const QJsonValue &value)
{
    return value.isNull() || value.isUndefined() ? QVariant(QVariant::Int) : QVariant(value.toInt());
}

// A decimal the web app sent as a string or number, stored as an integer. NULL when it is absent.
QVariant decimalOrNull(const QJsonValue &value, int scale)
{
    bool ok = false;
    const qlonglong number = Decimal::fromJson(value, scale, &ok);

    return ok ? QVariant(number) : QVariant(QVariant::LongLong);
}

// The same, but 0 when it is absent (columns that cannot be NULL).
QVariant decimalOrZero(const QJsonValue &value, int scale)
{
    const QVariant number = decimalOrNull(value, scale);

    return number.isNull() ? QVariant(0) : number;
}

bool run(QSqlQuery &query, QString *error)
{
    if (query.exec()) {
        return true;
    }
    *error = query.lastError().text();

    return false;
}

}

SyncStore::SyncStore(QSqlDatabase db)
    : db_(db), restoreResent_(0)
{
}

QStringList SyncStore::tables()
{
    return QStringList() << "users" << "customers" << "tickets" << "ticket_loans" << "cards" << "card_transactions";
}

int SyncStore::resendUploadedSince(const QString &since)
{
    const int MarginSeconds = 300; // the terminal's clock and the server's may differ a little
    const QDateTime time = QDateTime::fromString(since, "yyyy-MM-dd HH:mm:ss");
    if (!time.isValid()) {
        return 0;
    }
    const QString from = time.addSecs(-MarginSeconds).toString("yyyy-MM-dd HH:mm:ss");

    int count = 0;
    QStringList tables;
    tables << "sales" << "supplements" << "remainders" << "ticket_loans" << "ticket_loan_settlements" << "tickets" << "cards";
    foreach (const QString &table, tables) {
        QSqlQuery query(db_);
        query.prepare("UPDATE " + table + " SET synced = 0, uploaded_at = NULL WHERE synced = 1 AND uploaded_at >= ?");
        query.addBindValue(from);
        if (query.exec() && query.numRowsAffected() > 0) {
            count += query.numRowsAffected();
        }
    }

    // the nozzles are few and cheap: they all go again (the web app may lack one that was added after the dump)
    QSqlQuery nozzles(db_);
    nozzles.exec("UPDATE nozzles SET synced = 0 WHERE deleted = 0 AND tank_id > 0");

    return count;
}

bool SyncStore::applyConfig(const QJsonObject &config, QString *error)
{
    restoreResent_ = 0;
    if (!db_.transaction()) {
        *error = db_.lastError().text();

        return false;
    }

    bool ok = true;
    QSqlQuery query(db_);

    ok = ok && query.exec("DELETE FROM fuel_types") && query.exec("DELETE FROM tanks");
    if (!ok) {
        *error = query.lastError().text();
    }

    const QJsonArray fuelTypes = config.value("fuel_types").toArray();
    for (int i = 0; ok && i < fuelTypes.size(); ++i) {
        const QJsonObject row = fuelTypes.at(i).toObject();
        query.prepare("INSERT INTO fuel_types (fuel_type, name, price, density) VALUES (?, ?, ?, ?)");
        query.addBindValue(row.value("fuel_type").toString());
        query.addBindValue(row.value("name").toString());
        query.addBindValue(decimalOrZero(row.value("price"), 2));
        query.addBindValue(decimalOrNull(row.value("density"), 4));
        ok = run(query, error);
    }

    const QJsonArray tanks = config.value("tanks").toArray();
    for (int i = 0; ok && i < tanks.size(); ++i) {
        const QJsonObject row = tanks.at(i).toObject();
        query.prepare("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (?, ?, ?, ?, ?)");
        query.addBindValue(row.value("id").toInt());
        query.addBindValue(row.value("name").toString());
        query.addBindValue(row.value("fuel_type").toString());
        query.addBindValue(decimalOrNull(row.value("capacity_kg"), 2));
        query.addBindValue(decimalOrZero(row.value("stock_kg"), 2));
        ok = run(query, error);
    }

    if (ok) {
        Settings settings(db_);
        const QJsonObject device = config.value("device").toObject();
        ok = settings.setInt(SettingKey::PurgeDays, device.value("purge_days").toInt(3))
            && settings.setInt(SettingKey::TicketSerialBase, config.value("ticket_serial_base").toInt())
            && (device.value("pin_salt").isNull() ? settings.remove(SettingKey::PinSalt) : settings.setValue(SettingKey::PinSalt, device.value("pin_salt").toString()))
            && (device.value("pin_hash").isNull() ? settings.remove(SettingKey::PinHash) : settings.setValue(SettingKey::PinHash, device.value("pin_hash").toString()));
        // What the terminal needs to number a ticket of its own (older servers do not send it: then the settings stay as they were).
        if (ok && config.contains("ticket_sign")) {
            ok = settings.setValue(SettingKey::TicketSign, config.value("ticket_sign").toString());
        }
        // the digits of the code of a ticket: the first is the fuel type, the second the kind (1 spot sale, 2 self use, 3 other use, 4 ticket loan, 5 card, 9 remainder)
        const QJsonObject posCodes = config.value("pos_codes").toObject();
        const QJsonObject fuelDigits = posCodes.value("fuel").toObject();
        foreach (const QString &fuel, fuelDigits.keys()) {
            ok = ok && settings.setValue(TicketNumbers::fuelDigitSetting(fuel), fuelDigits.value(fuel).toString());
        }
        const QJsonObject typeDigits = posCodes.value("type").toObject();
        foreach (const QString &type, typeDigits.keys()) {
            ok = ok && settings.setValue(TicketNumbers::typeDigitSetting(type), typeDigits.value(type).toString());
        }
        const QJsonObject floors = config.value("ticket_serial_floor").toObject();
        foreach (const QString &code, floors.keys()) {
            ok = ok && settings.setValue("serial_floor_" + code, QString::number(static_cast<qlonglong>(floors.value(code).toDouble())));
        }
        // The web app's database was restored (php artisan backup:restored): act once on each restore.
        const QJsonObject restore = config.value("restore").toObject();
        const QString restoredAt = restore.value("restored_at").toString();
        if (ok && !restoredAt.isEmpty() && restoredAt != settings.value(SettingKey::RestoreHandled)) {
            restoreResent_ = resendUploadedSince(restore.value("since").toString());
            ok = settings.setValue(SettingKey::RestoreHandled, restoredAt);
        }
        if (!ok) {
            *error = "Cannot save the device settings";
        }
    }

    if (!ok) {
        db_.rollback();

        return false;
    }
    if (!db_.commit()) {
        *error = db_.lastError().text();
        db_.rollback();

        return false;
    }

    return true;
}

SyncStore::PageResult SyncStore::applyPage(const QString &table, const QJsonArray &rows)
{
    PageResult result;

    if (!tables().contains(table)) {
        result.ok = false;
        result.error = "Unknown table " + table;

        return result;
    }
    if (!db_.transaction()) {
        result.ok = false;
        result.error = db_.lastError().text();

        return result;
    }

    for (int i = 0; i < rows.size(); ++i) {
        bool stored = false;
        QString key;
        if (!storeRow(table, rows.at(i).toObject(), &stored, &key, &result.error)) {
            db_.rollback();
            result.ok = false;
            result.stored = 0;
            result.skipped = 0;
            result.keys.clear();

            return result;
        }
        if (stored) {
            ++result.stored;
            result.keys << key;
        } else {
            ++result.skipped;
        }
    }

    if (!db_.commit()) {
        result.ok = false;
        result.error = db_.lastError().text();
        db_.rollback();
        result.stored = 0;
        result.skipped = 0;
        result.keys.clear();
    }

    return result;
}

bool SyncStore::storeRow(const QString &table, const QJsonObject &row, bool *stored, QString *key, QString *error)
{
    *stored = true;

    if (table == "users") {
        *key = QString::number(row.value("id").toInt());

        return storeUser(row, error);
    }
    if (table == "customers") {
        *key = QString::number(row.value("id").toInt());

        return storeCustomer(row, error);
    }
    if (table == "tickets") {
        *key = row.value("barcode").toString();

        return storeTicket(row, stored, error);
    }

    *key = row.value("id").toString();

    if (table == "cards") {
        return storeCard(row, stored, error);
    }
    if (table == "card_transactions") {
        return storeCardTransaction(row, error);
    }

    return storeLoan(row, stored, error);
}

bool SyncStore::storeUser(const QJsonObject &row, QString *error)
{
    QSqlQuery query(db_);
    query.prepare("INSERT OR REPLACE INTO users (id, username, name, role, pos, active, pos_salt, pos_hash, deleted) "
                  "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    query.addBindValue(row.value("id").toInt());
    query.addBindValue(row.value("username").toString());
    query.addBindValue(row.value("name").toString());
    query.addBindValue(row.value("role").toString());
    query.addBindValue(row.value("pos").toBool() ? 1 : 0);
    query.addBindValue(row.value("active").toBool() ? 1 : 0);
    query.addBindValue(textOrNull(row.value("pos_salt")));
    query.addBindValue(textOrNull(row.value("pos_hash")));
    query.addBindValue(row.value("deleted").toBool() ? 1 : 0);

    return run(query, error);
}

bool SyncStore::storeCustomer(const QJsonObject &row, QString *error)
{
    QSqlQuery query(db_);
    query.prepare("INSERT OR REPLACE INTO customers (id, name, phone, is_self, deleted) VALUES (?, ?, ?, ?, ?)");
    query.addBindValue(row.value("id").toInt());
    query.addBindValue(row.value("name").toString());
    query.addBindValue(textOrNull(row.value("phone")));
    query.addBindValue((row.contains("is_default") ? row.value("is_default") : row.value("is_self")).toBool() ? 1 : 0); // (kept in the column is_self)
    query.addBindValue(row.value("deleted").toBool() ? 1 : 0);

    return run(query, error);
}

bool SyncStore::storeTicket(const QJsonObject &row, bool *stored, QString *error)
{
    const qlonglong barcode = Barcode::toKey(row.value("barcode").toString());
    if (barcode < 0) {
        *error = "Not a ticket barcode: " + row.value("barcode").toString();

        return false;
    }

    QSqlQuery find(db_);
    find.prepare("SELECT synced FROM tickets WHERE barcode = ?");
    find.addBindValue(barcode);
    if (!run(find, error)) {
        return false;
    }
    const bool exists = find.next();
    if (exists && find.value(0).toInt() == 0) {
        *stored = false; // changed here and not uploaded yet: the local state wins until the upload is acknowledged

        return true;
    }

    // The columns the web app does not send (supply_date, supplement_id) are kept as they are.
    QSqlQuery query(db_);
    if (exists) {
        query.prepare("UPDATE tickets SET fuel_type = ?, spec = ?, amount_kg = ?, status = ?, sale_id = ?, sale_date = ?, card_id = ?, synced = 1, "
                      "last_printed_at = CASE WHEN ? > print_count THEN ? ELSE last_printed_at END, print_count = MAX(print_count, ?) "
                      "WHERE barcode = ?");
    } else {
        query.prepare("INSERT INTO tickets (fuel_type, spec, amount_kg, status, sale_id, sale_date, card_id, synced, print_count, last_printed_at, barcode) "
                      "VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)");
    }
    query.addBindValue(row.value("fuel_type").toString());
    query.addBindValue(intOrNull(row.value("spec")));
    query.addBindValue(decimalOrZero(row.value("amount_kg"), 2));
    query.addBindValue(row.value("status").toInt());
    query.addBindValue(textOrNull(row.value("sale_id")));
    query.addBindValue(textOrNull(row.value("sale_date")));
    query.addBindValue(textOrNull(row.value("card_id")));
    // the prints are counted here and on the web app; the larger count wins
    const int printed = row.value("print_count").toInt();
    if (exists) {
        query.addBindValue(printed);
        query.addBindValue(textOrNull(row.value("last_printed_at")));
        query.addBindValue(printed);
    } else {
        query.addBindValue(printed);
        query.addBindValue(textOrNull(row.value("last_printed_at")));
    }
    query.addBindValue(barcode);

    return run(query, error);
}

// A card as the web app has it. A card that waits for its upload here (a new one, or wrong PINs counted) is left as it is and not acknowledged, so
// the web app sends it again after the upload: its unlock or its new PIN then arrives. The balance is the web app's figure plus what this
// terminal topped up and took since and has not uploaded (the web app does not know those yet).
// An entry of a card's ledger as the web app has it (the terminal never changes one): the newest version replaces the old.
bool SyncStore::storeCardTransaction(const QJsonObject &row, QString *error)
{
    bool kgOk = false;
    bool balanceOk = false;
    const qlonglong kg = Decimal::fromJson(row.value("kg"), 2, &kgOk);
    const qlonglong balance = Decimal::fromJson(row.value("balance_kg"), 2, &balanceOk);
    if (!kgOk || !balanceOk) {
        *error = "Card ledger entry " + row.value("id").toString() + " has no weight or balance";

        return false;
    }

    bool priceOk = false;
    bool totalOk = false;
    const qlonglong price = Decimal::fromJson(row.value("price"), 2, &priceOk);
    const qlonglong total = Decimal::fromJson(row.value("total_amount"), 2, &totalOk);

    QSqlQuery query(db_);
    query.prepare("INSERT OR REPLACE INTO card_transactions (id, card_id, type, kg, balance_kg, price, total_amount, source_id, occurred_at, comment, seq) "
                  "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    query.addBindValue(row.value("id").toString());
    query.addBindValue(row.value("card_id").toString());
    query.addBindValue(row.value("type").toString());
    query.addBindValue(kg);
    query.addBindValue(balance);
    query.addBindValue(priceOk ? QVariant(price) : QVariant(QVariant::LongLong));
    query.addBindValue(totalOk ? QVariant(total) : QVariant(QVariant::LongLong));
    query.addBindValue(row.value("source_id").toString());
    query.addBindValue(row.value("occurred_at").toString());
    query.addBindValue(textOrNull(row.value("comment")));
    query.addBindValue(row.value("seq").toVariant().toLongLong()); // the order the web app made the entries in (0 from an older web app)

    return run(query, error);
}

bool SyncStore::storeCard(const QJsonObject &row, bool *stored, QString *error)
{
    const QString id = row.value("id").toString();

    QSqlQuery find(db_);
    find.prepare("SELECT synced FROM cards WHERE id = ?");
    find.addBindValue(id);
    if (!run(find, error)) {
        return false;
    }
    const bool exists = find.next();
    if (exists && find.value(0).toInt() == 0) {
        *stored = false;

        return true;
    }

    qlonglong balance = 0;
    bool balanceOk = false;
    const qlonglong web = Decimal::fromJson(row.value("balance_kg"), 2, &balanceOk);
    balance = balanceOk ? web : 0;

    QSqlQuery topUps(db_);
    topUps.prepare("SELECT COALESCE(SUM(amount_kg), 0) FROM sales WHERE card_id = ? AND sale_type = 'card' AND synced = 0 AND deleted_at IS NULL");
    topUps.addBindValue(id);
    QSqlQuery supplies(db_);
    supplies.prepare("SELECT COALESCE(SUM(out_amount_kg), 0) FROM supplements WHERE card_id = ? AND synced = 0");
    supplies.addBindValue(id);
    QSqlQuery tickets(db_); // the tickets made for the card here that the web app has not counted yet
    tickets.prepare(QString("SELECT COALESCE(SUM(amount_kg), 0) FROM tickets WHERE card_id = ? AND synced = 0 AND status IN (%1, %2)").arg(TicketStatus::Sale).arg(TicketStatus::Supply));
    tickets.addBindValue(id);
    if (topUps.exec() && topUps.next()) {
        balance += topUps.value(0).toLongLong();
    }
    if (supplies.exec() && supplies.next()) {
        balance -= supplies.value(0).toLongLong();
    }
    if (tickets.exec() && tickets.next()) {
        balance -= tickets.value(0).toLongLong();
    }

    QSqlQuery query(db_);
    if (exists) {
        query.prepare("UPDATE cards SET uid = ?, holder_name = ?, holder_phone = ?, fuel_type = ?, pin_salt = ?, pin_hash = ?, failed_attempts = ?, status = ?, "
                      "balance_kg = ?, deleted = ?, synced = 1 WHERE id = ?");
    } else {
        query.prepare("INSERT INTO cards (uid, holder_name, holder_phone, fuel_type, pin_salt, pin_hash, failed_attempts, status, balance_kg, deleted, synced, id) "
                      "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
    }
    query.addBindValue(row.value("uid").toString());
    query.addBindValue(row.value("holder_name").toString());
    query.addBindValue(row.value("holder_phone").toString().isEmpty() ? QVariant(QVariant::String) : QVariant(row.value("holder_phone").toString()));
    query.addBindValue(row.value("fuel_type").toString());
    query.addBindValue(row.value("pin_salt").toString());
    query.addBindValue(row.value("pin_hash").toString());
    query.addBindValue(row.value("failed_attempts").toInt());
    query.addBindValue(row.value("status").toString());
    query.addBindValue(balance);
    query.addBindValue(row.value("deleted").toBool() ? 1 : 0);
    query.addBindValue(id);

    return run(query, error);
}

bool SyncStore::storeLoan(const QJsonObject &row, bool *stored, QString *error)
{
    QSqlQuery find(db_);
    find.prepare("SELECT synced FROM ticket_loans WHERE id = ?");
    find.addBindValue(row.value("id").toString());
    if (!run(find, error)) {
        return false;
    }
    const bool exists = find.next();
    if (exists && find.value(0).toInt() == 0) {
        *stored = false;

        return true;
    }

    // A settlement entered here and not uploaded yet is not in the web app's total: the local total stands until it is.
    QSqlQuery pending(db_);
    pending.prepare("SELECT 1 FROM ticket_loan_settlements WHERE loan_id = ? AND synced = 0 LIMIT 1");
    pending.addBindValue(row.value("id").toString());
    if (exists && pending.exec() && pending.next()) {
        *stored = false;

        return true;
    }

    // date and user_id are not sent by the web app: a loan made here keeps them.
    QSqlQuery query(db_);
    if (exists) {
        query.prepare("UPDATE ticket_loans SET customer_id = ?, fuel_type = ?, amount_kg = ?, settled_amount_kg = ?, loaned_at = ?, "
                      "sale_id = ?, deleted = ?, synced = 1 WHERE id = ?");
    } else {
        query.prepare("INSERT INTO ticket_loans (customer_id, fuel_type, amount_kg, settled_amount_kg, loaned_at, sale_id, deleted, synced, id) "
                      "VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)");
    }
    query.addBindValue(row.value("customer_id").toInt());
    query.addBindValue(row.value("fuel_type").toString());
    query.addBindValue(decimalOrZero(row.value("amount_kg"), 2));
    query.addBindValue(decimalOrZero(row.value("settled_amount_kg"), 2));
    query.addBindValue(row.value("loaned_at").toString());
    query.addBindValue(textOrNull(row.value("sale_id")));
    query.addBindValue(row.value("deleted").toBool() ? 1 : 0);
    query.addBindValue(row.value("id").toString());

    return run(query, error);
}
