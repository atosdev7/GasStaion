#ifndef UPLOADER_H
#define UPLOADER_H

#include "ApiClient.h"

#include <QMap>
#include <QObject>
#include <QSqlDatabase>
#include <QString>
#include <QStringList>

class ApiReply;

// Uploads what this terminal recorded to the web app. It runs when the cashier presses Upload data, and by itself in the modes
// "at the close of the day" and "continuous" (AutoUpload, Machine Config). Tables go in the order the API needs: nozzles, cards, sales, supplements, remainders, ticket loans, loan settlements, tickets;
// 200 rows a batch. The web app answers per row: saved rows are marked synced, refused rows stay unsynced with the reason in
// `upload_errors` (and are not sent again during the same run).
//
// Never sent: a sale deleted here before the upload, the ticket loan made with it, and tickets that are not sold or supplied
// (for example the restricted ticket of a deleted spot sale). The web app never hears of them.
class Uploader : public QObject
{
    Q_OBJECT

public:
    explicit Uploader(QSqlDatabase db, QObject *parent = 0);

    void setTimeoutMs(int timeoutMs) { client_.setTimeoutMs(timeoutMs); }
    bool isRunning() const { return started_; }

    // Sends what waits. With a date (yyyy-MM-dd) only the records of that date go, and the tickets that belong to them; the nozzles
    // always go. Without one everything that waits goes.
    // With `before` (yyyy-MM-dd HH:mm:ss) only the records made before that moment go (what the automatic modes send).
    void upload(const QString &date = QString(), const QString &before = QString());

    // Sends only the nozzles that wait (added, removed or changed on Machine Config), whatever the upload mode is: the web app should know the
    // nozzles of the station without waiting for the cashier to upload the records. nozzlesUploaded() follows instead of uploadFinished().
    // A nozzle the web app refused is left alone (it would be refused again every minute) unless retryRefused: it is tried again when something is
    // changed on Machine Config, and by Upload data.
    void uploadNozzles(bool retryRefused = false);

    // Tells the web app that nothing waits for an upload from before the running business day (a record the web app refused counts as waiting): the backup of the web
    // app waits for this, not for a batch of records (an upload can stop between two tables). No answer is waited for; it is said again at the next beat.
    void reportNothingWaiting();

    // How many records made before a moment still wait for an upload, the refused ones included (pendingTotal leaves those out when it is given a moment).
    static int unsentBefore(QSqlDatabase db, const QString &before);

    int savedCount() const { return saved_; }
    int rejectedCount() const { return rejected_; }

    // The tables in upload order, and how many rows of each wait for an upload.
    static QStringList tables();
    static QMap<QString, int> pending(QSqlDatabase db, const QString &date = QString(), const QString &before = QString());
    static int nozzlesWaiting(QSqlDatabase db); // the nozzles that wait for their upload and were not refused (they are not records, see pendingTotal)
    static QString nozzleRefusal(QSqlDatabase db, int number); // why the web app refused the nozzle (empty: not refused)
    static void forgetNozzleRefusals(QSqlDatabase db);         // every refused nozzle is tried again by the next uploadNozzles()
    static int pendingTotal(QSqlDatabase db, const QString &date = QString(), const QString &before = QString());

    // The SQL condition of the rows of a table that wait for an upload (what pending() counts and an upload sends).
    static QString waitingCondition(const QString &table, const QString &date = QString(), const QString &before = QString());

signals:
    // The result of uploadNozzles(): ok when the web app answered (a nozzle it refused stays waiting, with the reason in `upload_errors`).
    void nozzlesUploaded(bool ok);

    // ok: the web app answered every batch (rejected rows are not a failure of the upload; see rejectedCount()).
    // Otherwise message says why it stopped (no connection, busy server, ...).
    void uploadFinished(bool ok, const QString &message);

private slots:
    void replyFinished();

private:
    void begin(const QString &date, const QString &before, bool nozzlesOnly);

    void sendNext();
    void finish(bool ok, const QString &message);

    QSqlDatabase db_;
    ApiClient client_;
    bool started_;
    bool nozzlesOnly_; // this run sends the nozzles only
    bool retryRefused_; // ... and also the ones the web app refused
    QString date_;    // the date this upload is limited to (empty: all)
    QString before_;  // the moment this upload is limited to: only records made before it (empty: all)
    QString running_; // the table of the batch in flight
    int tableIndex_;
    QStringList sentKeys_;
    QMap<QString, QStringList> refused_; // keys refused during this run, by table
    int saved_;
    int rejected_;
};

#endif
