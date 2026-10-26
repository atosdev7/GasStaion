#include "Migrator.h"

#include <QDir>
#include <QFile>
#include <QRegExp>
#include <QSqlError>
#include <QSqlQuery>
#include <QVariant>

QStringList Migrator::bundled()
{
    QStringList scripts;

    QDir dir(":/migrations");
    foreach (const QString &name, dir.entryList(QStringList() << "*.sql", QDir::Files, QDir::Name)) {
        QFile file(dir.filePath(name));
        if (file.open(QIODevice::ReadOnly)) {
            scripts << QString::fromUtf8(file.readAll());
        }
    }

    return scripts;
}

int Migrator::version(QSqlDatabase db)
{
    QSqlQuery query(db);
    if (query.exec("PRAGMA user_version") && query.next()) {
        return query.value(0).toInt();
    }

    return -1;
}

// The lines "-- add-column-if-missing: <table> <column> <definition>" of a script: the column is added only when the table does not have it yet. (SQLite has
// no ADD COLUMN IF NOT EXISTS, and an old database may lack a column that a new one was made with.) A table that does not exist fails the script.
bool Migrator::addMissingColumns(QSqlDatabase db, const QString &script, QString *error)
{
    QRegExp directive("--\\s*add-column-if-missing:\\s*(\\w+)\\s+(\\w+)\\s+(.+)");

    foreach (const QString &line, script.split('\n')) {
        if (!directive.exactMatch(line.trimmed())) {
            continue;
        }
        const QString table = directive.cap(1);
        const QString column = directive.cap(2);
        const QString definition = directive.cap(3).trimmed();

        QSqlQuery info(db);
        if (!info.exec("PRAGMA table_info(" + table + ")")) {
            *error = info.lastError().text();

            return false;
        }
        bool has = false;
        while (info.next()) {
            has = has || info.value(1).toString().compare(column, Qt::CaseInsensitive) == 0;
        }
        if (has) {
            continue;
        }

        QSqlQuery add(db);
        if (!add.exec("ALTER TABLE " + table + " ADD COLUMN " + column + " " + definition)) {
            *error = add.lastError().text() + " (" + table + "." + column + ")";

            return false;
        }
    }

    return true;
}

bool Migrator::apply(QSqlDatabase db, const QStringList &scripts, QString *error)
{
    const int current = version(db);
    if (current < 0) {
        *error = "Cannot read the database version";

        return false;
    }
    if (current > scripts.size()) {
        *error = QString("The database is version %1 but this program only knows %2").arg(current).arg(scripts.size());

        return false;
    }

    for (int number = current + 1; number <= scripts.size(); ++number) {
        if (!db.transaction()) {
            *error = db.lastError().text();

            return false;
        }

        QSqlQuery query(db);
        bool ok = true;
        QString columnError;
        if (!addMissingColumns(db, scripts.at(number - 1), &columnError)) {
            *error = QString("Migration %1 failed: %2").arg(number).arg(columnError);
            ok = false;
        }
        if (ok) {
            foreach (const QString &statement, splitStatements(scripts.at(number - 1))) {
                if (!query.exec(statement)) {
                    *error = QString("Migration %1 failed: %2 (%3)").arg(number).arg(query.lastError().text()).arg(statement.left(80));
                    ok = false;
                    break;
                }
            }
        }

        // The version is part of the same transaction, so it only moves when everything in the script worked.
        if (ok && !query.exec(QString("PRAGMA user_version = %1").arg(number))) {
            *error = QString("Migration %1 failed: %2").arg(number).arg(query.lastError().text());
            ok = false;
        }
        if (!ok) {
            db.rollback();

            return false;
        }
        if (!db.commit()) {
            *error = db.lastError().text();
            db.rollback();

            return false;
        }
    }

    return true;
}

QStringList Migrator::splitStatements(const QString &script)
{
    QStringList statements;
    QString current;
    QChar quote;

    for (int i = 0; i < script.size(); ++i) {
        const QChar c = script.at(i);

        if (!quote.isNull()) {
            current += c;
            if (c == quote) {
                quote = QChar(); // a doubled quote ('') ends and starts the text again, which keeps it quoted
            }
        } else if (c == '-' && i + 1 < script.size() && script.at(i + 1) == '-') {
            while (i < script.size() && script.at(i) != '\n') {
                ++i;
            }
            current += '\n';
        } else if (c == '\'' || c == '"') {
            quote = c;
            current += c;
        } else if (c == ';') {
            if (!current.trimmed().isEmpty()) {
                statements << current.trimmed();
            }
            current.clear();
        } else {
            current += c;
        }
    }

    if (!current.trimmed().isEmpty()) {
        statements << current.trimmed();
    }

    return statements;
}
