#ifndef TICKETNUMBERS_H
#define TICKETNUMBERS_H

#include <QSqlDatabase>
#include <QString>

// The barcode of a ticket this terminal makes itself (a spot sale's ticket): 5-digit business sign + 2-digit reserved code
// (21 direct, 22 self, 23 other, 24 ticket loan, 25 remainder) + 9-digit serial. The web app numbers its own reserved tickets
// below `ticket_serial_base` and a terminal numbers above it, so the two can never collide. The sign, the codes, the base and the
// highest serial the web app already holds (`ticket_serial_floor`, for a terminal that starts with an empty database) all
// come with the config download.
class TicketNumbers
{
public:
    // The setting that holds the reserved code with this name ("direct", "self", ...).
    static QString codeSetting(const QString &name);

    // Are the numbers known (the config was downloaded)? When not, next() cannot give one.
    static bool available(QSqlDatabase db, const QString &codeName);

    // Takes the next free serial for the reserved code and returns the ticket's barcode number (see Barcode). Returns -1 and
    // fills `error` when the numbers are not known yet (no config downloaded) or the range is used up. Call it inside the
    // transaction that saves the ticket: the counter moves with it.
    static qlonglong next(QSqlDatabase db, const QString &codeName, QString *error);
};

#endif
