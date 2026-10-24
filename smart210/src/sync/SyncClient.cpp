#include "SyncClient.h"

#include "ApiReply.h"
#include "Clock.h"
#include "Purger.h"
#include "Settings.h"

#include <QDateTime>
#include <QJsonArray>

namespace {

const int PageLimit = 200;
const int MaxConfirmedAsked = 500; // records asked about in one download (the web app takes 500 of each kind)

}

SyncClient::SyncClient(QSqlDatabase db, QObject *parent)
    : QObject(parent), db_(db), store_(db), client_(this), step_(Idle), firstSync_(false), autoSetClock_(false), tableIndex_(0), more_(false),
      storedInPage_(0), stored_(0), skipped_(0), restoreResent_(0), confirmedRemoved_(0)
{
}

void SyncClient::download()
{
    if (isRunning()) {
        return;
    }

    Settings settings(db_);
    if (settings.value(SettingKey::ServerHost).isEmpty()) {
        emit downloadFinished(false, tr("No server is set up."));

        return;
    }
    client_.setServer(settings.value(SettingKey::ServerHost), settings.intValue(SettingKey::ServerPort, 80));

    firstSync_ = !settings.boolValue(SettingKey::InitialDone);
    tables_ = SyncStore::tables();
    tableIndex_ = 0;
    stored_ = 0;
    restoreResent_ = 0;
    skipped_ = 0;
    confirmedRemoved_ = 0;

    if (firstSync_ && !settings.boolValue(SettingKey::SyncBegun)) {
        // A database that starts from nothing: the web app must send every row again, even those it thinks are synced.
        step_ = Begin;
        connect(client_.post("sync/begin", QJsonObject()), SIGNAL(finished()), this, SLOT(replyFinished()));

        return;
    }

    requestConfig();
}

void SyncClient::requestConfig()
{
    step_ = Config;
    connect(client_.get("pull/config"), SIGNAL(finished()), this, SLOT(replyFinished()));
}

void SyncClient::requestPage()
{
    step_ = Page;
    connect(client_.get(QString("pull/%1?limit=%2").arg(tables_.at(tableIndex_)).arg(PageLimit)), SIGNAL(finished()), this,
            SLOT(replyFinished()));
}

void SyncClient::replyFinished()
{
    ApiReply *reply = qobject_cast<ApiReply *>(sender());
    reply->deleteLater();

    if (!reply->ok()) {
        // the question about confirmed records is an extra: a server that does not know it, or is busy, does not fail the download
        finish(step_ == Confirmed, step_ == Confirmed ? QString() : reply->message());

        return;
    }

    QString error;
    Settings settings(db_);

    switch (step_) {
    case Begin:
        settings.setBool(SettingKey::SyncBegun, true);
        requestConfig();
        break;

    case Config: {
        // The config comes with the server's time. From it: the station's time zone (applied at once, and again at the next start), and how far
        // this terminal's clock is from the server's, which is set right when the program may (setSystemClock) and the clock is more than a few
        // seconds off. An older server sends only its local time: then it is compared as it is.
        const QJsonObject time = reply->data();
        const QString serverUtc = time.value("server_time_utc").toString();
        if (time.contains("utc_offset_seconds")) {
            Clock::keepTimeZone(settings, time.value("utc_offset_seconds").toInt(), time.value("timezone").toString());
        }
        if (Clock::utcOf(serverUtc).isValid()) {
            const qint64 offset = Clock::recordUtc(settings, serverUtc, QDateTime::currentDateTimeUtc());
            if (autoSetClock_ && Clock::needsSetting(offset) && Clock::setSystemClockUtc(Clock::utcOf(serverUtc))) {
                Clock::recordUtc(settings, serverUtc, QDateTime::currentDateTimeUtc());
            }
        } else {
            const QString serverTime = time.value("server_time").toString();
            const qint64 offset = Clock::record(settings, serverTime, QDateTime::currentDateTime());
            if (autoSetClock_ && Clock::isOff(offset) && Clock::setSystemClock(QDateTime::fromString(serverTime, "yyyy-MM-dd HH:mm:ss"))) {
                Clock::record(settings, serverTime, QDateTime::currentDateTime());
            }
        }

        if (!store_.applyConfig(reply->data(), &error)) {
            finish(false, tr("The downloaded settings could not be saved: %1").arg(error));

            return;
        }
        restoreResent_ += store_.restoreResent();
        requestPage();
        break;
    }

    case Page: {
        const SyncStore::PageResult result = store_.applyPage(tables_.at(tableIndex_), reply->data().value("rows").toArray());
        if (!result.ok) {
            finish(false, tr("The downloaded data could not be saved: %1").arg(result.error));

            return;
        }
        more_ = reply->data().value("more").toBool();
        storedInPage_ = result.stored;
        stored_ += result.stored;
        skipped_ += result.skipped;

        if (result.keys.isEmpty()) {
            afterPage(); // nothing to acknowledge
            break;
        }

        QJsonArray keys;
        foreach (const QString &key, result.keys) {
            keys.append(key);
        }
        QJsonObject body;
        body.insert("keys", keys);
        body.insert("pulled_at", reply->data().value("server_time").toString());
        step_ = Ack;
        connect(client_.post(QString("pull/%1/ack").arg(tables_.at(tableIndex_)), body), SIGNAL(finished()), this, SLOT(replyFinished()));
        break;
    }

    case Ack:
        afterPage();
        break;

    case InitialDone:
        settings.setBool(SettingKey::InitialDone, true);
        finish(true, QString());
        break;

    case Confirmed: {
        QStringList sales;
        QStringList supplements;
        const QJsonArray saleIds = reply->data().value("sales").toArray();
        const QJsonArray supplementIds = reply->data().value("supplements").toArray();
        for (int i = 0; i < saleIds.size(); ++i) {
            sales << saleIds.at(i).toString();
        }
        for (int i = 0; i < supplementIds.size(); ++i) {
            supplements << supplementIds.at(i).toString();
        }
        confirmedRemoved_ += Purger::removeConfirmed(db_, sales, supplements);
        finish(true, QString());
        break;
    }

    case Idle:
        break;
    }
}

// More pages of this table, or the next table. A page that stored nothing but says "more" holds only rows the cashier has
// not uploaded yet, which the web app keeps sending until they are: asking again would never end.
void SyncClient::afterPage()
{
    if (more_ && storedInPage_ > 0) {
        requestPage();

        return;
    }

    ++tableIndex_;
    if (tableIndex_ < tables_.size()) {
        requestPage();

        return;
    }

    finishTables();
}

void SyncClient::finishTables()
{
    if (firstSync_) {
        step_ = InitialDone;
        connect(client_.post("sync/initial-done", QJsonObject()), SIGNAL(finished()), this, SLOT(replyFinished()));

        return;
    }

    requestConfirmed();
}

// Which of the uploaded records that are still here has the accountant confirmed on the web app? They are final and go (Purger).
void SyncClient::requestConfirmed()
{
    const QStringList sales = Purger::keptSaleIds(db_, MaxConfirmedAsked);
    const QStringList supplements = Purger::keptSupplementIds(db_, MaxConfirmedAsked);
    if (sales.isEmpty() && supplements.isEmpty()) {
        finish(true, QString());

        return;
    }

    QJsonArray saleIds;
    foreach (const QString &id, sales) {
        saleIds.append(id);
    }
    QJsonArray supplementIds;
    foreach (const QString &id, supplements) {
        supplementIds.append(id);
    }
    QJsonObject body;
    body.insert("sales", saleIds);
    body.insert("supplements", supplementIds);

    step_ = Confirmed;
    connect(client_.post("confirmed", body), SIGNAL(finished()), this, SLOT(replyFinished()));
}

void SyncClient::finish(bool ok, const QString &message)
{
    step_ = Idle;

    QString text = message;
    if (ok) {
        Settings(db_).setValue(SettingKey::LastDownloadAt, QDateTime::currentDateTime().toString("yyyy-MM-dd HH:mm:ss"));
        text = skipped_ > 0
            ? tr("Download complete: %1 rows stored, %2 kept until they are uploaded.").arg(stored_).arg(skipped_)
            : tr("Download complete: %1 rows stored.").arg(stored_);
        if (restoreResent_ > 0) {
            text += " " + tr("The server's database was restored: %1 records will be sent again with the next upload.").arg(restoreResent_);
        }
    }

    emit downloadFinished(ok, text);
}
