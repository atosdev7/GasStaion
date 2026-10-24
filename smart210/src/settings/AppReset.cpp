#include "AppReset.h"

#include "Settings.h"
#include "Uploader.h"

#include <QFile>
#include <QSqlError>
#include <QSqlQuery>
#include <QStringList>

int AppReset::waiting(QSqlDatabase db)
{
    return Uploader::pendingTotal(db);
}

bool AppReset::cleanData(QSqlDatabase db, QString *why)
{
    const int waits = waiting(db);
    if (waits > 0) {
        *why = QObject::tr("%1 records wait for the upload. Upload them first (Review screen), or they would be lost.").arg(waits);

        return false;
    }

    QStringList tables;
    QSqlQuery list(db);
    list.exec("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
    while (list.next()) {
        tables << list.value(0).toString();
    }

    // the order of the deletes must not matter
    QSqlQuery(db).exec("PRAGMA foreign_keys = OFF");
    bool ok = db.transaction();
    foreach (const QString &table, tables) {
        ok = ok && QSqlQuery(db).exec("DELETE FROM \"" + table + "\"");
    }
    if (ok) {
        ok = db.commit();
    } else {
        db.rollback();
    }
    QSqlQuery(db).exec("PRAGMA foreign_keys = ON");
    if (!ok) {
        *why = QObject::tr("The data could not be erased: %1").arg(db.lastError().text());

        return false;
    }

    QSqlQuery(db).exec("DELETE FROM sqlite_sequence"); // the counters of the tables start again (no error when the table does not exist)
    QSqlQuery(db).exec("VACUUM");                       // the file becomes small again

    return true;
}

bool AppReset::clearConfig(QSqlDatabase db, QString *why)
{
    // an older terminal kept these in its database, and reads them from there until they are saved once; the device id is what says that a server was set
    QStringList keys;
    keys << SettingKey::ServerHost << SettingKey::ServerPort << SettingKey::DeviceId << SettingKey::DeviceName << SettingKey::UploadMode
         << SettingKey::ShowSalesWithoutReceipt << SettingKey::PrinterHost << SettingKey::PrinterPort << SettingKey::ConverterPort
         << SettingKey::CardReaderPort << SettingKey::CardReaderBaud << SettingKey::CardReaderFormat << SettingKey::CardReaderLength
         << SettingKey::CardReaderEncoding << SettingKey::CardReaderPrefix;
    Settings settings(db);
    bool ok = true;
    foreach (const QString &key, keys) {
        ok = settings.remove(key) && ok;
    }

    // the device file: the settings above and the tank of each nozzle
    const QString file = Settings::deviceFile();
    if (!file.isEmpty() && QFile::exists(file)) {
        ok = QFile::remove(file) && ok;
    }

    if (!ok) {
        *why = QObject::tr("The machine settings could not be erased.");
    }

    return ok;
}
