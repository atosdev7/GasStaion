#include "Database.h"

#include <QDir>
#include <QFileInfo>
#include <QSqlError>
#include <QSqlQuery>
#include <QVariant>

const char *Database::DefaultConnection = "smart210";

bool Database::open(const QString &file, const QString &connectionName, QString *error)
{
    if (!QDir().mkpath(QFileInfo(file).absolutePath())) {
        *error = QString("Cannot create the folder of %1").arg(file);

        return false;
    }

    {
        QSqlDatabase db = QSqlDatabase::addDatabase("QSQLITE", connectionName);
        db.setDatabaseName(file);
        if (!db.open()) {
            *error = db.lastError().text();
        } else {
            QSqlQuery query(db);
            // journal_mode answers with the mode it ended up in: a file system without shared memory may refuse WAL.
            const bool ok = query.exec("PRAGMA busy_timeout = 5000")
                && query.exec("PRAGMA journal_mode = WAL")
                && query.exec("PRAGMA synchronous = FULL")
                && query.exec("PRAGMA foreign_keys = ON");
            if (ok) {
                return true;
            }
            *error = query.lastError().text();
        }
    }

    close(connectionName);

    return false;
}

void Database::close(const QString &connectionName)
{
    {
        QSqlDatabase db = QSqlDatabase::database(connectionName, false);
        if (db.isValid() && db.isOpen()) {
            db.close();
        }
    }
    QSqlDatabase::removeDatabase(connectionName);
}

QString Database::pragma(QSqlDatabase db, const QString &name)
{
    QSqlQuery query(db);
    if (query.exec("PRAGMA " + name) && query.next()) {
        return query.value(0).toString();
    }

    return QString();
}
