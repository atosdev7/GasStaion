#ifndef SUPPLYRECORD_H
#define SUPPLYRECORD_H

#include <QDateTime>
#include <QList>
#include <QSqlDatabase>
#include <QString>

// Recording what a nozzle dispensed. A supplement is one supply event: the fuel that came out, and the tickets that paid for it.
//
//   auto mode    the supplier's tickets are the nozzle's queue (SupplyQueue). They all go to the supplement (supplied); when less came out
//                than the tickets cover, the rest is a remainder: a new ticket (a remainder code of the fuel type, e.g. 29 for diesel; sold, to be printed) and a
//                remainders row. A fill that ended at the preset, within the tolerance of it, leaves no remainder.
//                When the tickets of the queue no longer hold up (a download changed one) or more came out than they cover, the supplement is saved
//                without tickets: nothing waits for them.
//   manual mode  the program never records a supplement for a manual-mode nozzle: its result is only acknowledged (SupplySession).
//   by hand      the cashier types a supply in on the Manual input screen: the nozzle, the kg and litres that came out, when, and the tickets
//                that pay for it (Reading::tickets). Their total weight must cover the dispensed weight; what is left of it is a remainder
//                (a new ticket, as in auto mode), unless it is within the tolerance. The supplement is saved with them at once (mode manual).
//
// A result of the board is recorded once: the result id is kept with the supplement, and a result seen again only reports "duplicate".
// All weights are kg x100, litres x100.
class SupplyRecord
{
public:
    // There is no tolerance: whatever is left of the tickets is a remainder ticket, and more dispensed than the tickets cover is never accepted. (The firmware of the
    // board makes it stop at the preset.)

    struct Reading {
        Reading() : sessionId(0), dispensedX100(0), litersX100(0), presetX100(0), reason(-1), byHand(false) {}

        quint32 sessionId;        // the id of the session the fill was started with (the board keeps it with the result); 0 for a reading typed in
        QList<qlonglong> tickets; // typed in with the reading (barcodes as keys): they must cover the weight, see record()
        qlonglong dispensedX100;
        qlonglong litersX100;
        qlonglong presetX100;    // what the board was asked for (the amount typed at the dispenser in manual mode); 0 when unknown
        int reason;              // BoardProtocol::ResultReason, -1 when not known
        bool byHand;
        QString comment;         // typed with a reading (the Manual input screen); empty: none
    };

    struct Outcome {
        Outcome() : ok(false), duplicate(false), tickets(0), kgX100(0), remainderKgX100(0), refundKgX100(0) {}

        bool ok;
        QString error;
        bool duplicate;          // this result was recorded before: nothing was done
        QString supplementId;
        int tickets;             // tickets assigned
        qlonglong kgX100;        // what the tickets add up to
        QString remainderBarcode; // the remainder ticket to print, 16 digits; empty when none
        qlonglong remainderKgX100;
        QString refundCardId;     // the card that paid for the tickets when the unused part of them went back to it (no remainder ticket then); empty when none
        qlonglong refundKgX100;
    };

    // The card that paid for all of these tickets (tickets made for a card, Cards::issueTicket): empty when one of them is no card's ticket or they are of several
    // cards. The unused part of such tickets goes back to the card, not into a remainder ticket (the web app does the same: CardLedger::refund).
    static QString cardOf(QSqlDatabase db, const QList<qlonglong> &barcodes);

    static Outcome record(QSqlDatabase db, int nozzle, const Reading &reading, const QDateTime &now);

    // The remainder tickets, newest first: for the screen that prints them again.
    struct RemainderRow {
        RemainderRow() : kgX100(0), ticketStatus(0) {}

        QString time;
        QString nozzle;
        QString fuelType;
        qlonglong kgX100;
        QString barcode;     // 16 digits
        QString printedAt;   // empty: not printed yet
        QString id;
        QString comment;
        int ticketStatus;    // TicketStatus: sold (can still be used) or supplied (used)
    };
    static QList<RemainderRow> remainders(QSqlDatabase db, int limit = 300);
    static int unprintedRemainders(QSqlDatabase db);

    // Sets the comment of a remainder (empty: none). It is marked not uploaded, so that the web app gets the new comment with the next upload.
    static bool setRemainderComment(QSqlDatabase db, const QString &id, const QString &comment);
    static void markRemainderPrinted(QSqlDatabase db, const QString &barcode, const QDateTime &now);
};

#endif
