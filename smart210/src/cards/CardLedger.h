#ifndef CARDLEDGER_H
#define CARDLEDGER_H

#include <QList>
#include <QSqlDatabase>
#include <QString>

// The history of a card: its ledger as the web app keeps it (card_transactions, pulled: every top-up and fill, a reversal for a deleted one, each with the
// balance of the card after it) and, on top of it, the top-ups and fills this terminal has made that the web app has not turned into entries yet
// (not uploaded, or uploaded and not pulled back yet): those are `provisional`, and their balance is worked out from the last entry of the ledger.
class CardLedger
{
public:
    struct Entry {
        Entry() : kgX100(0), balanceX100(0), priceX100(-1), totalX100(-1), provisional(false) {}

        QString when;          // yyyy-MM-dd HH:mm:ss
        QString type;          // topup, fill or reversal
        qlonglong kgX100;      // signed: + in, - out
        qlonglong balanceX100; // the balance of the card after the entry
        qlonglong priceX100;   // -1: none (a fill)
        qlonglong totalX100;   // -1: none
        bool provisional;
    };

    // The newest `limit` entries, newest first.
    static QList<Entry> entries(QSqlDatabase db, const QString &cardId, int limit);
};

#endif
