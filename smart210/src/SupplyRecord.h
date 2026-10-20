#ifndef SUPPLYRECORD_H
#define SUPPLYRECORD_H

#include <QDateTime>
#include <QList>
#include <QSqlDatabase>
#include <QString>

// Recording what a nozzle dispensed. A supplement is one supply event: the fuel that came out, and the tickets that paid for it.
//
//   auto mode    the supplier's tickets are the nozzle's queue (SupplyQueue). They all go to the supplement (supplied); when less came out
//                than the tickets cover, the rest is a remainder: a new ticket (reserved code "remainder", sold, to be printed) and a
//                remainders row. A fill that ended at the preset, within the tolerance of it, leaves no remainder.
//                When the tickets of the queue no longer hold up (a download changed one) or more came out than they cover, the supplement is saved
//                without tickets: nothing waits for them.
//   manual mode  the program never records a supplement for a manual-mode nozzle: its result is only acknowledged (SupplySession).
//   by hand      the cashier types a supply in on the Manual input screen: the nozzle, the kg and litres that came out, when, and the tickets
//                that pay for it (Reading::tickets). Their total weight must cover the dispensed weight and exceed it by the tolerance at most.
//                The supplement is saved with them at once (mode manual, no remainder).
//
// A result of the board is recorded once: the result id is kept with the supplement, and a result seen again only reports "duplicate".
// All weights are kg x100, litres x100.
class SupplyRecord
{
public:
    // The most the station may keep: 0.05 kg. The tolerance only ever favours the station. A fill that ends at its preset up to this far short
    // gives no remainder; the tickets of a manual supply may exceed what was dispensed by up to this much, never fall short of it; more
    // dispensed than the tickets cover is never accepted.
    static const qlonglong ToleranceX100 = 5; // 0.05 kg

    struct Reading {
        Reading() : resultId(0), dispensedX100(0), litersX100(0), presetX100(0), reason(-1), byHand(false) {}

        quint32 resultId;        // the board's id of the fill; 0 for a reading typed in
        QList<qlonglong> tickets; // typed in with the reading (barcodes as keys): they must cover the weight, see record()
        qlonglong dispensedX100;
        qlonglong litersX100;
        qlonglong presetX100;    // what the board was asked for (the amount typed at the dispenser in manual mode); 0 when unknown
        int reason;              // BoardProtocol::ResultReason, -1 when not known
        bool byHand;
        QString comment;         // typed with a reading (the Manual input screen); empty: none
    };

    struct Outcome {
        Outcome() : ok(false), duplicate(false), tickets(0), kgX100(0), remainderKgX100(0) {}

        bool ok;
        QString error;
        bool duplicate;          // this result was recorded before: nothing was done
        QString supplementId;
        int tickets;             // tickets assigned
        qlonglong kgX100;        // what the tickets add up to
        QString remainderBarcode; // the remainder ticket to print, 16 digits; empty when none
        qlonglong remainderKgX100;
    };

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
