#ifndef CARDS_H
#define CARDS_H

#include <QDateTime>
#include <QSqlDatabase>
#include <QString>

// The RFID fuel cards. A card is only its id; it holds a balance in kg of ONE fuel type, kept in the database (kg x100, like everything).
//
//   registering   the cashier reads the card, types the customer's name (only a name, not a customer of the web app), picks the fuel type and the
//                 PIN the customer gives; the card waits for the upload (synced = 0). The PIN is kept as a salted SHA-256, never as it was typed.
//   PIN           the cashier types it for every supply; a wrong one counts, the 5th wrong one in a row locks the card (status "locked"): only
//                 the web app unlocks it (a manager or the accountant), or resets the PIN. A right PIN counts the wrong ones from zero again.
//   top-up        a sale of type "card": cash only, paid in full, no customer, no ticket; the balance grows by its kg.
//   fill          the cashier asks for kg with the card (the PIN is checked first): the balance falls at once and a ticket is made (a card code, e.g. 25 for a diesel card; card_id
//                 set, no sale), printed and given to the supplier, who scans it at the dispenser like the ticket of a spot sale. Fuel it did not use
//                 comes back as a remainder ticket (the usual rule); a ticket that was not used can be cancelled here while it waits for its upload, and
//                 the kg go back to the card. (Before this, a card paid a supply directly: those supplements keep their card_id.)
//
// The balance of a card is `balance_kg`: the web app's figure at the last pull plus what was topped up and taken here since (see 015_cards.sql),
// so it is right at once. All weights kg x100, money x100.
class Cards
{
public:
    static const int MaxAttempts = 5;   // wrong PINs in a row that lock a card
    static const int PinMinLength = 4;
    static const int PinMaxLength = 8;
    static const int MaxUidLength = 32;
    static const int MaxNameLength = 100;
    static const int MaxPhoneLength = 30;

    struct Card {
        Card() : failedAttempts(0), balanceX100(0), deleted(false), synced(true), exists(false), freePayment(false) {}

        QString id;
        QString uid;           // the id on the card, as the reader gives it
        QString holderName;
        QString holderPhone;   // optional
        QString fuelType;
        QString pinSalt;
        QString pinHash;
        int failedAttempts;
        QString status;        // active, locked or blocked
        qlonglong balanceX100; // what the card holds now
        bool deleted;
        bool synced;
        bool exists;
        bool freePayment;      // the web app allows a top-up for nothing (a price of 0) on this card

        bool isActive() const { return exists && !deleted && status == "active"; }
    };

    // What the reader sent, tidied: no spaces or control characters around it, capital letters (so the same card is always the same text).
    static QString normalizeUid(const QString &text);

    // True for 4 to 8 digits.
    static bool validPin(const QString &pin);

    static Card find(QSqlDatabase db, const QString &uid);
    static Card findById(QSqlDatabase db, const QString &id);

    struct Registration {
        Registration() : ok(false) {}

        bool ok;
        QString error;
        QString cardId;
    };
    // The card is new here (its uid is not known, not even to a deleted or blocked card). The fuel type must be one of the downloaded ones. The
    // holder's phone number is optional (up to MaxPhoneLength characters); it is uploaded with the card.
    static Registration registerCard(QSqlDatabase db, const QString &uid, const QString &holderName, const QString &fuelType, const QString &pin,
                                     const QString &holderPhone = QString());

    enum PinResult {
        PinRight,
        PinWrong,       // counted; triesLeft says how many are left
        PinNowLocked,   // this was the 5th: the card is locked
        PinRefused      // not counted: the card is unknown, locked or blocked, or the PIN is not 4 to 8 digits
    };
    struct PinCheck {
        PinCheck() : result(PinRefused), triesLeft(0) {}

        PinResult result;
        int triesLeft;
        QString error;  // for the cashier
    };
    static PinCheck checkPin(QSqlDatabase db, const QString &cardId, const QString &pin);

    struct TopUpResult {
        TopUpResult() : ok(false), totalX100(0), balanceX100(0) {}

        bool ok;
        QString error;
        QString saleId;
        qlonglong totalX100;
        qlonglong balanceX100; // the balance after the top-up
    };
    // A top-up of kg at a price per kg (cash, paid in full). The total is kg x price unless the cashier typed the total payment (totalX100 >= 0).
    // A locked card can be topped up, a blocked (lost) one cannot.
    static TopUpResult topUp(QSqlDatabase db, const QString &cardId, qlonglong kgX100, qlonglong priceX100, int userId, const QDateTime &now,
                             qlonglong totalX100 = -1);

    struct TicketResult {
        TicketResult() : ok(false), kgX100(0), balanceX100(0) {}

        bool ok;
        QString error;
        QString barcode;       // the 16 digits
        qlonglong kgX100;
        qlonglong balanceX100; // the balance after the fill
    };
    // The card pays kgX100 and a ticket for them is made. The PIN is checked by the caller (checkPin) before this. A locked or blocked card cannot fill.
    static TicketResult issueTicket(QSqlDatabase db, const QString &cardId, qlonglong kgX100, int userId, const QDateTime &now);

    // Takes back a ticket made for a card that was not used: it must wait for its upload (synced = 0), not be used and not be in the queue of a nozzle.
    // The kg go back to the card. False and `why` when it cannot be taken back.
    static bool cancelTicket(QSqlDatabase db, const QString &barcode, QString *why);

    // The newest ticket made for the card that is still sold (not supplied, not restricted), as its 16 digits; empty when there is none.
    static QString lastTicket(QSqlDatabase db, const QString &cardId);

    // The kg a card holds back for a fill that is open (the cashier set the kg, the supplier has not finished): a card has at most one, on one nozzle.
    // The card is charged what really came out when the result is recorded (SupplyRecord), and the hold goes with the session (SupplyQueue::end).
    struct Hold {
        Hold() : exists(false), nozzle(0), presetX100(0) {}

        bool exists;
        int nozzle;
        QString cardId;
        qlonglong presetX100;
        QString createdAt;
    };
    static Hold holdOfNozzle(QSqlDatabase db, int nozzle);
    static Hold holdOfCard(QSqlDatabase db, const QString &cardId);
    static qlonglong heldX100(QSqlDatabase db, const QString &cardId);
    // What a new fill or ticket may take: the balance less what is held.
    static qlonglong availableX100(QSqlDatabase db, const QString &cardId);

    // Holds kgX100 of the card for a fill on a nozzle. Empty when it is held, otherwise why not (the card is unknown, locked or blocked, the kg are more than it
    // holds, it has a fill open on another nozzle, the nozzle is not known, is of another fuel type, has tickets queued or a fill started). A hold on the same nozzle
    // for the same card is replaced (the cashier changes the kg before the fill starts).
    static QString beginHold(QSqlDatabase db, int nozzle, const QString &cardId, qlonglong kgX100, const QDateTime &now);
    static void releaseHold(QSqlDatabase db, int nozzle);

    // Moves the balance of a card, inside the transaction of whatever caused it (a top-up, a supply). False when there is no such card.
    static bool adjustBalance(QSqlDatabase db, const QString &cardId, qlonglong deltaX100);
};

#endif
