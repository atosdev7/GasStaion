#ifndef SUPPLYQUEUE_H
#define SUPPLYQUEUE_H

#include <QDateTime>
#include <QList>
#include <QSqlDatabase>
#include <QString>

// The tickets the supplier scanned for a nozzle: the queue the next fill is made of. It is kept in the database, so a restart does not
// lose it. A ticket may be in one queue only. Once the fill is started the nozzle has a session: its queue is locked (nothing can be added
// or removed) until the result is recorded. All weights are kg x100.
//
// The same checks serve the manual-mode supplements, whose tickets the cashier types in afterwards (see SupplyRecord).
class SupplyQueue
{
public:
    struct Entry {
        Entry() : barcode(0), kgX100(0) {}

        qlonglong barcode;
        qlonglong kgX100;
    };

    struct Scan {
        Scan() : ok(false), barcode(0), kgX100(0) {}

        bool ok;
        QString error;      // for the cashier or the supplier
        qlonglong barcode;
        qlonglong kgX100;
    };

    struct Session {
        Session() : exists(false), nozzle(0), presetX100(0), resultId(0) {}

        bool exists;
        int nozzle;
        qlonglong presetX100;
        quint32 resultId;   // 0 while the board has not said which fill it is
        QString startedAt;
        QString cardId;     // the fill is paid with this card (no tickets); empty for a fill of tickets

        bool byCard() const { return exists && !cardId.isEmpty(); }
    };

    // Can this barcode be used for a fill of this fuel type? It must be a sold ticket (not in stock, not used, not restricted) of that fuel and
    // not in a queue already. Changes nothing.
    static Scan check(QSqlDatabase db, const QString &barcodeText, const QString &fuelType);

    // The rules of one supply: all its tickets are of one kind (press, spot sale, self use, other use, ticket loan, card, remainder: TicketNumbers::kindOf) and the
    // tickets of a card are all of one card. Why `candidate` may not join the tickets `have`; empty when it may. Changes nothing.
    static QString mixProblem(QSqlDatabase db, const QList<qlonglong> &have, qlonglong candidate);

    // check() and, when it is good, the ticket joins the end of the nozzle's queue.
    static Scan add(QSqlDatabase db, int nozzle, const QString &barcodeText, const QString &fuelType, const QDateTime &now);

    static bool remove(QSqlDatabase db, int nozzle, qlonglong barcode);
    static void clear(QSqlDatabase db, int nozzle);

    static QList<Entry> entries(QSqlDatabase db, int nozzle);
    static qlonglong totalX100(QSqlDatabase db, int nozzle);

    // The nozzle whose queue holds the ticket; 0 when none does.
    static int nozzleOf(QSqlDatabase db, qlonglong barcode);

    // The session of a nozzle (a fill started and not recorded yet).
    static Session session(QSqlDatabase db, int nozzle);
    static bool begin(QSqlDatabase db, int nozzle, qlonglong presetX100, const QDateTime &now, const QString &cardId = QString());
    static void setResultId(QSqlDatabase db, int nozzle, quint32 resultId);
    static void release(QSqlDatabase db, int nozzle); // the fill did not start: the session ends, the queue stays
    static void end(QSqlDatabase db, int nozzle);     // the session is over; the queue is cleared as well
};

#endif
