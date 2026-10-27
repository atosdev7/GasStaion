#ifndef TICKETLEGACY_H
#define TICKETLEGACY_H

#include <QJsonArray>
#include <QSqlDatabase>
#include <QString>

// The press tickets published before the web app's go-live date and sold on paper before it. Nobody knows which of them a customer still holds, so
// neither the web app nor the terminal keeps a row for them, only the ranges of their publishes (the config download, `ticket_legacy`). A ticket with no
// row here whose barcode is inside a range is a ticket sold before go-live: ensure() makes its row (sold, the weight of its spec, the day of its
// publish as the day it was sold) and the scan goes on like for any sold ticket. The row counts as uploaded (synced): the web app makes the same row
// itself when the report of the use arrives. A ticket that has a row follows its row (one still in the stock room is published, never sold). The rows of
// these tickets are never purged (Purger), or a used one would be accepted again.
class TicketLegacy
{
public:
    // Replaces the ranges with the ones of the config download: [{code, from, to, fuel_type, spec, kg, date}]. `sign` is the 5-digit business sign.
    static bool replace(QSqlDatabase db, const QString &sign, const QJsonArray &ranges, QString *error);

    // Makes the row of this ticket when it has none and is inside a range. True when the ticket has a row after the call and it was made by this call.
    static bool ensure(QSqlDatabase db, qlonglong barcode);

    // Is the barcode inside a range of old publishes (whether or not it has a row)?
    static bool isOld(QSqlDatabase db, qlonglong barcode);
};

#endif
