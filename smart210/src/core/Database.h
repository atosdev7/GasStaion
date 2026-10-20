#ifndef DATABASE_H
#define DATABASE_H

#include <QSqlDatabase>
#include <QString>

// Opens the SQLite file on the board (the SD card) and sets it up for a terminal that can lose power at any moment:
// WAL journal with full synchronous writes, so a committed sale survives a power cut, and a busy timeout so a second
// connection (the sync) waits instead of failing. Use one connection per thread, each with its own name.
class Database
{
public:
    // Creates the folder and file if needed, opens the connection and sets the pragmas. Returns false and fills
    // `error` when it cannot. Get the connection back with QSqlDatabase::database(connectionName).
    static bool open(const QString &file, const QString &connectionName, QString *error);

    static void close(const QString &connectionName);

    // The current value of a pragma, for example "journal_mode".
    static QString pragma(QSqlDatabase db, const QString &name);

    static const char *DefaultConnection;
};

#endif
