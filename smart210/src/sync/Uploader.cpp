#include "Uploader.h"

#include "ApiReply.h"
#include "Barcode.h"
#include "Checkout.h"
#include "Decimal.h"
#include "Settings.h"
#include "TicketStatus.h"

#include <QDate>
#include <QDateTime>
#include <QJsonArray>
#include <QJsonObject>
#include <QSqlQuery>
#include <QVariant>

namespace {

const int BatchSize = 200;

// Which rows wait for an upload, and in which order they go.
QString waiting(const QString &table)
{
    if (table == "sales") {
        return "synced = 0 AND deleted_at IS NULL";
    }
    if (table == "ticket_loans") {
        return "synced = 0 AND deleted = 0";
    }
    if (table == "cards") {
        return "synced = 0 AND deleted = 0"; // a card registered here, or one whose wrong PINs / lock were counted here
    }
    if (table == "nozzles") {
        return "synced = 0 AND tank_id > 0"; // the web app needs the tank; a nozzle that was removed goes too (the web app deletes it)
    }
    if (table == "tickets") {
        return QString("synced = 0 AND status IN (%1, %2)").arg(TicketStatus::Sale).arg(TicketStatus::Supply);
    }

    return "synced = 0";
}

// The records of one date: the same date the Review screen lists them under. A ticket goes with the sale or supplement it belongs to
// (or the loan settlement or remainder that used it). Nozzles and cards have no date: they always go (a card before the sales and supplies
// that name it).
QString dateCondition(const QString &table, const QString &date)
{
    const QString day = "'" + date + "'";

    if (table == "sales") {
        return Checkout::dateExpression("sales", "created_at", "sale_date") + " = " + day;
    }
    if (table == "supplements") {
        return Checkout::dateExpression("supplements", "supplied_at", "supply_date") + " = " + day;
    }
    if (table == "remainders") {
        return Checkout::dateExpression("remainders", "created_at", "remainder_date") + " = " + day;
    }
    if (table == "ticket_loans") {
        return Checkout::dateExpression("ticket_loans", "loaned_at") + " = " + day;
    }
    if (table == "ticket_loan_settlements") {
        return Checkout::dateExpression("ticket_loan_settlements", "settled_at") + " = " + day;
    }
    if (table == "tickets") {
        return "(sale_id IN (SELECT id FROM sales WHERE " + Checkout::dateExpression("sales", "created_at", "sale_date") + " = " + day + ") "
               "OR supplement_id IN (SELECT id FROM supplements WHERE " + Checkout::dateExpression("supplements", "supplied_at", "supply_date") + " = " + day + ") "
               "OR barcode IN (SELECT ticket_barcode FROM ticket_loan_settlements WHERE " + Checkout::dateExpression("ticket_loan_settlements", "settled_at") + " = " + day + ") "
               "OR barcode IN (SELECT ticket_barcode FROM remainders WHERE " + Checkout::dateExpression("remainders", "created_at", "remainder_date") + " = " + day + "))";
    }

    return QString();
}

// The records made before a moment (the time column of each table is the moment of the record; a ticket goes with its record, as above).
QString beforeCondition(const QString &table, const QString &before)
{
    const QString at = "'" + before + "'";

    if (table == "sales") {
        return "created_at < " + at;
    }
    if (table == "supplements") {
        return "supplied_at < " + at;
    }
    if (table == "remainders") {
        return "created_at < " + at;
    }
    if (table == "ticket_loans") {
        return "loaned_at < " + at;
    }
    if (table == "ticket_loan_settlements") {
        return "settled_at < " + at;
    }
    if (table == "tickets") {
        return "(sale_id IN (SELECT id FROM sales WHERE created_at < " + at + ") "
               "OR supplement_id IN (SELECT id FROM supplements WHERE supplied_at < " + at + ") "
               "OR barcode IN (SELECT ticket_barcode FROM ticket_loan_settlements WHERE settled_at < " + at + ") "
               "OR barcode IN (SELECT ticket_barcode FROM remainders WHERE created_at < " + at + "))";
    }

    return QString();
}

// A record the server refused stays here with its reason (upload_errors). The automatic upload leaves it alone (it would be refused again
// every minute); Upload data tries it again.
QString notRefused(const QString &table)
{
    const QString key = table == "tickets" ? QString("printf('%016d', %1.barcode)").arg(table) : table + ".id";

    return QString("NOT EXISTS (SELECT 1 FROM upload_errors e WHERE e.table_name = '%1' AND e.row_key = %2)").arg(table).arg(key);
}

// A nozzle the web app refused (upload_errors, keyed by its number) waits until something changes on Machine Config.
QString notRefusedNozzle()
{
    return "NOT EXISTS (SELECT 1 FROM upload_errors e WHERE e.table_name = 'nozzles' AND e.row_key = CAST(nozzles.number AS TEXT))";
}

// Which rows wait for an upload (of a date, when one is given; anything but a real date means all; and only those made before a moment, when
// one is given). Nozzles and cards have no date and always go.
QString whereOf(const QString &table, const QString &date = QString(), const QString &before = QString())
{
    QString where = waiting(table);
    if (table == "nozzles" || table == "cards") {
        return where;
    }
    if (!date.isEmpty() && QDate::fromString(date, "yyyy-MM-dd").isValid()) {
        where += " AND " + dateCondition(table, date);
    }
    if (!before.isEmpty() && QDateTime::fromString(before, "yyyy-MM-dd HH:mm:ss").isValid()) {
        where += " AND " + beforeCondition(table, before) + " AND " + notRefused(table);
    }

    return where;
}

QString orderOf(const QString &table)
{
    if (table == "nozzles") {
        return "id"; // a deleted nozzle and the new one with its number go in the order they were made
    }
    if (table == "cards") {
        return "id";
    }
    if (table == "sales") {
        return "created_at, id";
    }
    if (table == "supplements") {
        return "supplied_at, id";
    }
    if (table == "remainders") {
        return "created_at, id";
    }
    if (table == "ticket_loans") {
        return "loaned_at, id";
    }
    if (table == "ticket_loan_settlements") {
        return "settled_at, id";
    }

    return "barcode";
}

QString keyColumn(const QString &table)
{
    return table == "tickets" ? "barcode" : (table == "nozzles" ? "number" : "id");
}

// The rows of a table as they are read for the upload: a supplement also carries its nozzle's number (the web app names nozzles by number).
QString selectOf(const QString &table)
{
    if (table == "supplements") {
        return "SELECT supplements.*, (SELECT number FROM nozzles WHERE nozzles.id = supplements.nozzle_id) AS nozzle_number FROM supplements";
    }

    return "SELECT * FROM " + table;
}

// A key as the web app names it (tickets by their 16 digits) and as the database stores it.
QString keyText(const QString &table, QSqlQuery &query)
{
    if (table == "nozzles") {
        return query.value("number").toString();
    }

    return table == "tickets" ? Barcode::fromKey(query.value("barcode").toLongLong()) : query.value("id").toString();
}

QVariant keyValue(const QString &table, const QString &key)
{
    if (table == "nozzles") {
        return QVariant(key.toInt());
    }

    return table == "tickets" ? QVariant(Barcode::toKey(key)) : QVariant(key);
}

QString dec(QSqlQuery &query, const char *column, int scale = 2)
{
    return Decimal::format(query.value(column).toLongLong(), scale);
}

QJsonValue textOrNull(QSqlQuery &query, const char *column)
{
    const QVariant value = query.value(column);

    return value.isNull() ? QJsonValue(QJsonValue::Null) : QJsonValue(value.toString());
}

QJsonValue intOrNull(QSqlQuery &query, const char *column)
{
    const QVariant value = query.value(column);

    return value.isNull() ? QJsonValue(QJsonValue::Null) : QJsonValue(value.toInt());
}

// One local row as the push API expects it.
QJsonObject rowJson(QSqlDatabase db, const QString &table, QSqlQuery &q)
{
    QJsonObject row;

    if (table == "nozzles") {
        row.insert("number", q.value("number").toInt());
        row.insert("tank_id", q.value("tank_id").toInt());
        row.insert("board_address", q.value("number").toInt()); // the board's address is the nozzle's number
        row.insert("name", textOrNull(q, "name")); // typed on Machine Config; the web app shows it in its lists
        row.insert("deleted", q.value("deleted").toInt() != 0);
    } else if (table == "cards") {
        // The PIN goes as the salted hash it is kept as, never as typed. The web app takes from an existing card only its wrong tries and a lock.
        row.insert("id", q.value("id").toString());
        row.insert("uid", q.value("uid").toString());
        row.insert("holder_name", q.value("holder_name").toString());
        row.insert("holder_phone", textOrNull(q, "holder_phone")); // optional, asked at the registration
        row.insert("fuel_type", q.value("fuel_type").toString());
        row.insert("pin_salt", q.value("pin_salt").toString());
        row.insert("pin_hash", q.value("pin_hash").toString());
        row.insert("failed_attempts", q.value("failed_attempts").toInt());
        row.insert("status", q.value("status").toString());
    } else if (table == "sales") {
        row.insert("id", q.value("id").toString());
        row.insert("created_at", q.value("created_at").toString());
        row.insert("customer_id", intOrNull(q, "customer_id"));
        row.insert("card_id", textOrNull(q, "card_id")); // a top-up of a card names its card
        row.insert("sale_type", q.value("sale_type").toString());
        row.insert("fuel_type", q.value("fuel_type").toString());
        row.insert("price", dec(q, "price"));
        row.insert("amount_kg", dec(q, "amount_kg"));
        row.insert("payment_method", q.value("payment_method").toString());
        row.insert("total_amount", dec(q, "total_amount"));
        row.insert("paid_amount", dec(q, "paid_amount"));
        row.insert("unpaid_amount", dec(q, "unpaid_amount"));
        row.insert("receipt", textOrNull(q, "receipt"));
        row.insert("usage", q.value("usage").toString());
        row.insert("comment", textOrNull(q, "comment"));
        // the sale's date; a sale the checkout has not dated yet goes with the business day of its time
        row.insert("sale_date", q.value("sale_date").isNull()
                   ? Checkout::businessDay(QDateTime::fromString(q.value("created_at").toString(), "yyyy-MM-dd HH:mm:ss"))
                   : q.value("sale_date").toString());
        row.insert("user_id", q.value("user_id").toInt());

        // the items of a ticket sale as entered travel with the sale
        QJsonArray items;
        QSqlQuery lines(db);
        lines.prepare("SELECT spec, start_serial, end_serial, quantity, amount_kg FROM sale_items WHERE sale_id = ? ORDER BY rowid");
        lines.addBindValue(q.value("id").toString());
        if (lines.exec()) {
            while (lines.next()) {
                QJsonObject item;
                item.insert("spec", lines.value(0).toInt());
                item.insert("start_serial", lines.value(1).toLongLong());
                item.insert("end_serial", lines.value(2).toLongLong());
                item.insert("quantity", lines.value(3).toInt());
                item.insert("amount_kg", Decimal::format(lines.value(4).toLongLong(), 2));
                items.append(item);
            }
        }
        row.insert("items", items);
    } else if (table == "supplements") {
        row.insert("id", q.value("id").toString());
        row.insert("fuel_type", q.value("fuel_type").toString());
        row.insert("nozzle_number", q.value("nozzle_number").toInt());
        row.insert("card_id", textOrNull(q, "card_id")); // a supply made with a card (it has no tickets)
        row.insert("out_amount_lt", dec(q, "out_amount_lt"));
        row.insert("out_amount_kg", dec(q, "out_amount_kg"));
        row.insert("preset_kg", dec(q, "preset_kg"));
        // kg per litre, 4 decimals (the web app works it out from the litres and the kg when it is absent)
        row.insert("density", q.value("density").isNull() ? QJsonValue(QJsonValue::Null) : QJsonValue(dec(q, "density", 4)));
        row.insert("mode", q.value("mode").toString());                 // auto or manual
        row.insert("supplied_at", q.value("supplied_at").toString());
        row.insert("comment", textOrNull(q, "comment"));
        row.insert("supply_date", textOrNull(q, "supply_date"));
    } else if (table == "remainders") {
        row.insert("id", q.value("id").toString());
        row.insert("supplement_id", q.value("supplement_id").toString());
        row.insert("ticket_barcode", Barcode::fromKey(q.value("ticket_barcode").toLongLong()));
        row.insert("fuel_type", q.value("fuel_type").toString());
        row.insert("amount_kg", dec(q, "amount_kg"));
        row.insert("comment", textOrNull(q, "comment"));
        row.insert("created_at", q.value("created_at").toString());     // the moment of the remainder
        row.insert("remainder_date", textOrNull(q, "remainder_date"));
    } else if (table == "ticket_loans") {
        row.insert("id", q.value("id").toString());
        row.insert("loaned_at", q.value("loaned_at").toString());
        row.insert("customer_id", q.value("customer_id").toInt());
        row.insert("fuel_type", q.value("fuel_type").toString());
        row.insert("amount_kg", dec(q, "amount_kg"));
        row.insert("sale_id", textOrNull(q, "sale_id"));
        row.insert("comment", textOrNull(q, "comment"));
        row.insert("date", textOrNull(q, "date"));
        row.insert("user_id", q.value("user_id").toInt());
    } else if (table == "ticket_loan_settlements") {
        row.insert("id", q.value("id").toString());
        row.insert("loan_id", q.value("loan_id").toString());
        row.insert("ticket_barcode", Barcode::fromKey(q.value("ticket_barcode").toLongLong()));
        row.insert("amount_kg", dec(q, "amount_kg"));
        row.insert("settled_at", q.value("settled_at").toString());
        row.insert("comment", textOrNull(q, "comment"));
        row.insert("date", textOrNull(q, "date"));
        row.insert("user_id", q.value("user_id").toInt());
    } else {
        row.insert("barcode", Barcode::fromKey(q.value("barcode").toLongLong()));
        row.insert("fuel_type", q.value("fuel_type").toString());
        row.insert("amount_kg", dec(q, "amount_kg"));
        row.insert("status", q.value("status").toInt());
        row.insert("sale_id", textOrNull(q, "sale_id"));
        row.insert("sale_date", textOrNull(q, "sale_date"));
        row.insert("supply_date", textOrNull(q, "supply_date"));
        row.insert("supplement_id", textOrNull(q, "supplement_id"));
    }

    return row;
}

}

Uploader::Uploader(QSqlDatabase db, QObject *parent)
    : QObject(parent), db_(db), client_(this), started_(false), nozzlesOnly_(false), retryRefused_(false), tableIndex_(0), saved_(0), rejected_(0)
{
}

QStringList Uploader::tables()
{
    return QStringList() << "nozzles" << "cards" << "sales" << "supplements" << "remainders" << "ticket_loans" << "ticket_loan_settlements" << "tickets";
}

QMap<QString, int> Uploader::pending(QSqlDatabase db, const QString &date, const QString &before)
{
    QMap<QString, int> counts;

    foreach (const QString &table, tables()) {
        QSqlQuery query(db);
        query.exec("SELECT COUNT(*) FROM " + table + " WHERE " + whereOf(table, date, before));
        counts[table] = query.next() ? query.value(0).toInt() : 0;
    }

    return counts;
}

QString Uploader::waitingCondition(const QString &table, const QString &date, const QString &before)
{
    return whereOf(table, date, before);
}

int Uploader::nozzlesWaiting(QSqlDatabase db)
{
    QSqlQuery query(db);
    if (query.exec("SELECT COUNT(*) FROM nozzles WHERE " + waiting("nozzles") + " AND " + notRefusedNozzle()) && query.next()) {
        return query.value(0).toInt();
    }

    return 0;
}

QString Uploader::nozzleRefusal(QSqlDatabase db, int number)
{
    QSqlQuery query(db);
    query.prepare("SELECT message FROM upload_errors WHERE table_name = 'nozzles' AND row_key = ?");
    query.addBindValue(QString::number(number));
    if (query.exec() && query.next()) {
        return query.value(0).toString();
    }

    return QString();
}

void Uploader::forgetNozzleRefusals(QSqlDatabase db)
{
    QSqlQuery query(db);
    query.exec("DELETE FROM upload_errors WHERE table_name = 'nozzles'");
}

// What the cashier sees as waiting: records. The nozzles are not records (they go up with the next upload, so that the supplements have
// their nozzle on the web app) and are not counted.
int Uploader::pendingTotal(QSqlDatabase db, const QString &date, const QString &before)
{
    int total = 0;
    const QMap<QString, int> counts = pending(db, date, before);
    foreach (const QString &table, counts.keys()) {
        if (table != "nozzles") {
            total += counts.value(table);
        }
    }

    return total;
}

void Uploader::upload(const QString &date, const QString &before)
{
    begin(date, before, false);
}

void Uploader::uploadNozzles(bool retryRefused)
{
    if (!started_) {
        retryRefused_ = retryRefused;
    }
    begin(QString(), QString(), true);
}

void Uploader::begin(const QString &date, const QString &before, bool nozzlesOnly)
{
    if (started_) {
        return;
    }
    date_ = date;
    before_ = before;
    nozzlesOnly_ = nozzlesOnly;

    Settings settings(db_);
    if (settings.value(SettingKey::ServerHost).isEmpty()) {
        if (nozzlesOnly_) {
            nozzlesOnly_ = false;
            emit nozzlesUploaded(false);
        } else {
            emit uploadFinished(false, tr("No server is set up."));
        }

        return;
    }
    client_.setServer(settings.value(SettingKey::ServerHost), settings.intValue(SettingKey::ServerPort, 80));

    started_ = true;
    tableIndex_ = 0;
    saved_ = 0;
    rejected_ = 0;
    refused_.clear();

    sendNext();
}

// The next batch of the current table, or of the next table that has rows; finishes when none is left.
void Uploader::sendNext()
{
    const QStringList all = tables();

    while (tableIndex_ < all.size()) {
        const QString table = all.at(tableIndex_);
        if (nozzlesOnly_ && table != "nozzles") {
            ++tableIndex_;
            continue;
        }
        const QStringList refused = refused_.value(table);

        QString sql = selectOf(table) + " WHERE " + whereOf(table, date_, before_);
        if (table == "nozzles" && nozzlesOnly_ && !retryRefused_) {
            sql += " AND " + notRefusedNozzle();
        }
        if (!refused.isEmpty()) {
            QStringList marks;
            for (int i = 0; i < refused.size(); ++i) {
                marks << "?";
            }
            sql += " AND " + keyColumn(table) + " NOT IN (" + marks.join(",") + ")";
        }
        sql += " ORDER BY " + orderOf(table) + QString(" LIMIT %1").arg(BatchSize);

        QSqlQuery query(db_);
        query.prepare(sql);
        foreach (const QString &key, refused) {
            query.addBindValue(keyValue(table, key));
        }

        QJsonArray rows;
        sentKeys_.clear();
        if (query.exec()) {
            while (query.next()) {
                rows.append(rowJson(db_, table, query));
                sentKeys_ << keyText(table, query);
            }
        }

        if (rows.isEmpty()) {
            ++tableIndex_;
            continue;
        }

        running_ = table;
        QJsonObject body;
        body.insert("rows", rows);
        connect(client_.post("push/" + table, body), SIGNAL(finished()), this, SLOT(replyFinished()));

        return;
    }

    finish(true, QString());
}

void Uploader::replyFinished()
{
    ApiReply *reply = qobject_cast<ApiReply *>(sender());
    reply->deleteLater();

    if (!reply->ok()) {
        finish(false, reply->message());

        return;
    }

    const QString table = running_;
    const QJsonArray saved = reply->data().value("saved").toArray();
    const QJsonArray rejected = reply->data().value("rejected").toArray();

    db_.transaction();

    const QString uploadedAt = QDateTime::currentDateTime().toString("yyyy-MM-dd HH:mm:ss");
    for (int i = 0; i < saved.size(); ++i) {
        const QString key = saved.at(i).toString();

        // the time of the upload is kept: after a restore of the web app's database it tells what the restored dump cannot hold (nozzles have none)
        QSqlQuery update(db_);
        if (table == "nozzles") {
            // a removed nozzle and a new one can have the same number: they go in the order they were made, and each answer is for the oldest that waits
            update.prepare("UPDATE nozzles SET synced = 1 WHERE id = (SELECT MIN(id) FROM nozzles WHERE number = ? AND synced = 0)");
            update.addBindValue(keyValue(table, key));
        } else {
            update.prepare("UPDATE " + table + " SET synced = 1, uploaded_at = ? WHERE " + keyColumn(table) + " = ?");
            update.addBindValue(uploadedAt);
            update.addBindValue(keyValue(table, key));
        }
        update.exec();

        QSqlQuery clear(db_);
        clear.prepare("DELETE FROM upload_errors WHERE table_name = ? AND row_key = ?");
        clear.addBindValue(table);
        clear.addBindValue(key);
        clear.exec();
        if (table != "nozzles") {
            ++saved_; // a nozzle is not a record: it only goes along so that the supplements have theirs
        }
    }

    const QString now = QDateTime::currentDateTime().toString("yyyy-MM-dd HH:mm:ss");
    for (int i = 0; i < rejected.size(); ++i) {
        const QString key = rejected.at(i).toObject().value("key").toString();

        QSqlQuery record(db_);
        record.prepare("INSERT OR REPLACE INTO upload_errors (table_name, row_key, message, created_at) VALUES (?, ?, ?, ?)");
        record.addBindValue(table);
        record.addBindValue(key);
        record.addBindValue(rejected.at(i).toObject().value("error").toString());
        record.addBindValue(now);
        record.exec();

        refused_[table] << key;
        ++rejected_;
    }

    // A batch the server answered nothing about would be sent again for ever: treat the unanswered rows as refused.
    foreach (const QString &key, sentKeys_) {
        bool answered = false;
        for (int i = 0; !answered && i < saved.size(); ++i) {
            answered = (saved.at(i).toString() == key);
        }
        for (int i = 0; !answered && i < rejected.size(); ++i) {
            answered = (rejected.at(i).toObject().value("key").toString() == key);
        }
        if (!answered) {
            refused_[table] << key;
        }
    }

    db_.commit();

    sendNext();
}

void Uploader::finish(bool ok, const QString &message)
{
    started_ = false;
    running_.clear();

    if (nozzlesOnly_) {
        nozzlesOnly_ = false;
        emit nozzlesUploaded(ok);

        return;
    }

    QString text = message;
    if (ok) {
        text = rejected_ > 0
            ? tr("Uploaded %1 records. The server refused %2: see the list of refused records.").arg(saved_).arg(rejected_)
            : tr("Uploaded %1 records.").arg(saved_);
    }

    emit uploadFinished(ok, text);
}
