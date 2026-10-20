#include "Session.h"

#include "PosHash.h"
#include "Settings.h"

#include <QSqlQuery>
#include <QVariant>

Session::Session(QSqlDatabase db)
    : db_(db)
{
}

QList<Session::Cashier> Session::cashiers() const
{
    QList<Cashier> list;

    QSqlQuery query(db_);
    query.exec("SELECT id, username, name, role FROM users WHERE pos = 1 AND active = 1 AND deleted = 0 AND role IN ('cashier', 'assistant') ORDER BY name COLLATE NOCASE, id");
    while (query.next()) {
        Cashier cashier;
        cashier.id = query.value(0).toInt();
        cashier.username = query.value(1).toString();
        cashier.name = query.value(2).toString();
        cashier.role = query.value(3).toString();
        list.append(cashier);
    }

    return list;
}

bool Session::login(int userId, const QString &password)
{
    logout(); // a failed attempt must not leave the previous cashier in

    QSqlQuery query(db_);
    query.prepare("SELECT pos_salt, pos_hash FROM users WHERE id = ? AND pos = 1 AND active = 1 AND deleted = 0 AND role IN ('cashier', 'assistant')");
    query.addBindValue(userId);
    if (!query.exec() || !query.next()) {
        return false;
    }
    if (!PosHash::matches(query.value(0).toString(), password, query.value(1).toString())) {
        return false;
    }

    return Settings(db_).setInt(SettingKey::SessionUserId, userId);
}

void Session::logout()
{
    Settings(db_).remove(SettingKey::SessionUserId);
}

bool Session::isLoggedIn() const
{
    return Settings(db_).contains(SettingKey::SessionUserId);
}

int Session::userId() const
{
    return Settings(db_).intValue(SettingKey::SessionUserId, 0);
}

QString Session::userName() const
{
    QSqlQuery query(db_);
    query.prepare("SELECT name FROM users WHERE id = ?");
    query.addBindValue(userId());

    return query.exec() && query.next() ? query.value(0).toString() : QString();
}

bool Session::validate()
{
    if (!isLoggedIn()) {
        return false;
    }

    QSqlQuery query(db_);
    query.prepare("SELECT 1 FROM users WHERE id = ? AND pos = 1 AND active = 1 AND deleted = 0 AND role IN ('cashier', 'assistant')");
    query.addBindValue(userId());
    if (!query.exec() || !query.next()) {
        logout();

        return false;
    }

    return true;
}
