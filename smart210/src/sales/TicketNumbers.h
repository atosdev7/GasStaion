#ifndef TICKETNUMBERS_H
#define TICKETNUMBERS_H

#include <QSqlDatabase>
#include <QString>

// The barcode of a ticket this terminal makes itself (a spot sale's, a card's, a remainder): 5-digit business sign + 2-digit code + 9-digit serial. The code
// says what the ticket is: its FIRST digit is the fuel type (1 gasoline, 2 diesel) and its SECOND the kind (1 spot sale, 2 self use, 3 other use, 4 ticket
// loan, 5 card, 9 remainder), so 21 is a diesel spot sale and 15 a ticket made for a gasoline card. The serial counts per code. The web app numbers its own
// tickets of a code below `ticket_serial_base` and a terminal numbers above it, so the two can never collide. The sign, the digits (`pos_codes`), the base
// and the highest serial the web app already holds for each code (`ticket_serial_floor`, for a terminal that starts with an empty database) all come with
// the config download.
class TicketNumbers
{
public:
    // The settings that hold the digit of a fuel type ("A", "D") and the digit of a kind ("direct", "self", "other", "ticket_loan", "card", "remainder").
    static QString fuelDigitSetting(const QString &fuelType);
    static QString typeDigitSetting(const QString &type);

    // The kind of the ticket of a sale made for this usage (sale, self, other, ticket_loan): "direct", "self", "other" or "ticket_loan".
    static QString typeOfUsage(const QString &usage);

    // The 2-digit code of this kind of ticket for this fuel type; empty when the digits are not known (the config was not downloaded).
    static QString code(QSqlDatabase db, const QString &type, const QString &fuelType);

    // What the ticket with this barcode is: "press" (a ticket of the stock) or the kind of a ticket a terminal or the web app makes ("direct", "self", "other",
    // "ticket_loan", "card", "remainder"), from the digits of its code. A press ticket is also the answer when the digits are not known.
    static QString kindOf(QSqlDatabase db, qlonglong barcodeKey);

    // The kind in words, for a message to the cashier.
    static QString kindName(const QString &kind);

    // Are the numbers known (the config was downloaded)? When not, next() cannot give one.
    static bool available(QSqlDatabase db, const QString &type, const QString &fuelType);

    // Takes the next free serial for the code and returns the ticket's barcode number (see Barcode). Returns -1 and fills `error` when the numbers are not
    // known yet (no config downloaded) or the range is used up. Call it inside the transaction that saves the ticket: the counter moves with it.
    static qlonglong next(QSqlDatabase db, const QString &type, const QString &fuelType, QString *error);
};
#endif
