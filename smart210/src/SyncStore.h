#ifndef SYNCSTORE_H
#define SYNCSTORE_H

#include <QJsonArray>
#include <QJsonObject>
#include <QSqlDatabase>
#include <QString>
#include <QStringList>

// Puts what the web app sent into the local database. No network here, so it is easy to test.
//
// Config (fuel types, tanks, device settings) is replaced whole each time. The nozzles are not part of it: they are set up on the terminal. The big tables come in
// pages. A pulled row never overwrites a ticket or ticket loan that was changed here and not uploaded yet (synced = 0):
// the web app sends it again after the upload, and only then does it replace the local row.
class SyncStore
{
public:
    struct PageResult {
        PageResult() : ok(true), stored(0), skipped(0) {}
        bool ok;
        QString error;
        int stored;
        int skipped;
        QStringList keys; // the rows to acknowledge: those that were stored
    };

    explicit SyncStore(QSqlDatabase db);

    static QStringList tables(); // the tables pulled page by page, in the order they are pulled

    bool applyConfig(const QJsonObject &config, QString *error);

    // How many records the last applyConfig marked "not sent" again because the web app's database was restored (0 when it was not).
    int restoreResent() const { return restoreResent_; }

    // One transaction per page: all of it or none.
    PageResult applyPage(const QString &table, const QJsonArray &rows);

private:
    bool storeRow(const QString &table, const QJsonObject &row, bool *stored, QString *key, QString *error);
    bool storeUser(const QJsonObject &row, QString *error);
    bool storeCustomer(const QJsonObject &row, QString *error);
    bool storeTicket(const QJsonObject &row, bool *stored, QString *error);
    bool storeLoan(const QJsonObject &row, bool *stored, QString *error);
    bool storeCard(const QJsonObject &row, bool *stored, QString *error);
    bool storeCardTransaction(const QJsonObject &row, QString *error);

    // A restore of the web app's database: everything this terminal uploaded after `since` (the time of the restored dump, minus a margin for
    // clocks that differ a little) may be gone from there, so it is marked "not sent" and goes up with the next Upload data. Returns how many.
    int resendUploadedSince(const QString &since);

    QSqlDatabase db_;
    int restoreResent_;
};

#endif
