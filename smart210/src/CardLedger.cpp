#include "CardLedger.h"

#include <QSqlQuery>
#include <QVariant>

namespace {

QList<CardLedger::Entry> sorted(QList<CardLedger::Entry> list, bool oldestFirst)
{
    // a small list: an insertion sort keeps the order of equal moments as they came
    for (int i = 1; i < list.size(); ++i) {
        int j = i;
        while (j > 0 && (oldestFirst ? list.at(j - 1).when > list.at(j).when : list.at(j - 1).when < list.at(j).when)) {
            list.swap(j - 1, j);
            --j;
        }
    }

    return list;
}

}

QList<CardLedger::Entry> CardLedger::entries(QSqlDatabase db, const QString &cardId, int limit)
{
    QList<Entry> authoritative;
    QSqlQuery query(db);
    query.prepare("SELECT occurred_at, type, kg, balance_kg, price, total_amount FROM card_transactions WHERE card_id = ? "
                  "ORDER BY occurred_at DESC, seq DESC, rowid DESC LIMIT ?");
    query.addBindValue(cardId);
    query.addBindValue(limit);
    if (query.exec()) {
        while (query.next()) {
            Entry entry;
            entry.when = query.value(0).toString();
            entry.type = query.value(1).toString();
            entry.kgX100 = query.value(2).toLongLong();
            entry.balanceX100 = query.value(3).toLongLong();
            entry.priceX100 = query.value(4).isNull() ? -1 : query.value(4).toLongLong();
            entry.totalX100 = query.value(5).isNull() ? -1 : query.value(5).toLongLong();
            authoritative << entry;
        }
    }

    // what the terminal made that has no entry in the ledger yet, oldest first, with a balance that goes on from the ledger's last one
    QList<Entry> pending;
    QSqlQuery sales(db);
    sales.prepare("SELECT created_at, amount_kg, price, total_amount FROM sales WHERE sale_type = 'card' AND card_id = ? AND deleted_at IS NULL "
                  "AND id NOT IN (SELECT source_id FROM card_transactions WHERE type = 'topup')");
    sales.addBindValue(cardId);
    if (sales.exec()) {
        while (sales.next()) {
            Entry entry;
            entry.when = sales.value(0).toString();
            entry.type = "topup";
            entry.kgX100 = sales.value(1).toLongLong();
            entry.priceX100 = sales.value(2).toLongLong();
            entry.totalX100 = sales.value(3).toLongLong();
            entry.provisional = true;
            pending << entry;
        }
    }
    QSqlQuery fills(db);
    fills.prepare("SELECT supplied_at, out_amount_kg FROM supplements WHERE card_id = ? "
                  "AND id NOT IN (SELECT source_id FROM card_transactions WHERE type = 'fill')");
    fills.addBindValue(cardId);
    if (fills.exec()) {
        while (fills.next()) {
            Entry entry;
            entry.when = fills.value(0).toString();
            entry.type = "fill";
            entry.kgX100 = -fills.value(1).toLongLong();
            entry.provisional = true;
            pending << entry;
        }
    }

    qlonglong balance = 0;
    QSqlQuery last(db);
    last.prepare("SELECT balance_kg FROM card_transactions WHERE card_id = ? ORDER BY occurred_at DESC, seq DESC, rowid DESC LIMIT 1");
    last.addBindValue(cardId);
    if (last.exec() && last.next()) {
        balance = last.value(0).toLongLong();
    }
    pending = sorted(pending, true);
    for (int i = 0; i < pending.size(); ++i) {
        balance += pending.at(i).kgX100;
        pending[i].balanceX100 = balance;
    }

    QList<Entry> all = sorted(authoritative + pending, false);
    while (all.size() > limit) {
        all.removeLast();
    }

    return all;
}
