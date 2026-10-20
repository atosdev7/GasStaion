#ifndef MIGRATOR_H
#define MIGRATOR_H

#include <QSqlDatabase>
#include <QString>
#include <QStringList>

// Brings the database up to date. A migration is one SQL script; script number N (counting from 1) takes the database
// from PRAGMA user_version N-1 to N. Each runs in its own transaction, so a failing one leaves the database as it was.
// The bundled scripts are the numbered .sql files in the resources (resources/migrations/NNN_name.sql).
class Migrator
{
public:
    static QStringList bundled();

    // Runs the scripts after the database's current version. On failure returns false and fills `error`.
    static bool apply(QSqlDatabase db, const QStringList &scripts, QString *error);

    static int version(QSqlDatabase db);

    // Splits a script into statements at ';', ignoring comments ("-- ...") and quoted text.
    static QStringList splitStatements(const QString &script);
};

#endif
