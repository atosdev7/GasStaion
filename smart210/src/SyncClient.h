#ifndef SYNCCLIENT_H
#define SYNCCLIENT_H

#include "ApiClient.h"
#include "SyncStore.h"

#include <QObject>
#include <QSqlDatabase>
#include <QString>
#include <QStringList>

class ApiReply;

// Downloads from the web app. It is automatic: the app downloads every minute (uploading is the Uploader's, by the cashier or by itself in
// the modes of AutoUpload).
//
// One run: [only the first time: tell the server this database starts from nothing (sync/begin)] -> config -> every table
// page by page (store, then acknowledge) -> [first time: initial-done; later: ask which uploaded records the accountant has confirmed and
// remove those (Purger::removeConfirmed); a server that does not know the question is left alone]. The first run is the "first-sync gate":
// until it has finished, setting `initial_done` is not set and the app must not be used.
//
// Rows the cashier changed and has not uploaded yet are never overwritten (see SyncStore), and are not acknowledged, so the
// web app sends them again after the upload.
class SyncClient : public QObject
{
    Q_OBJECT

public:
    explicit SyncClient(QSqlDatabase db, QObject *parent = 0);

    void setTimeoutMs(int timeoutMs) { client_.setTimeoutMs(timeoutMs); }
    bool isRunning() const { return step_ != Idle; }

    // Also set the board's clock from the server's when they differ by more than a minute (needs root).
    void setAutoSetClock(bool on) { autoSetClock_ = on; }

    void download();

signals:
    // ok: everything was downloaded. Otherwise message says why (no connection, busy server, ...); what was stored stays.
    void downloadFinished(bool ok, const QString &message);

private slots:
    void replyFinished();

private:
    enum Step { Idle, Begin, Config, Page, Ack, InitialDone, Confirmed };

    void requestConfig();
    void requestPage();
    void afterPage();
    void finishTables();
    void requestConfirmed();
    void finish(bool ok, const QString &message);

    QSqlDatabase db_;
    SyncStore store_;
    ApiClient client_;
    Step step_;
    bool firstSync_;
    bool autoSetClock_;
    QStringList tables_;
    int tableIndex_;
    bool more_;
    int storedInPage_;
    int stored_;
    int skipped_;
    int restoreResent_; // records marked "not sent" again by a restore of the web app's database, in this download
    int confirmedRemoved_; // rows removed because the web app has them confirmed, in this download
};

#endif
