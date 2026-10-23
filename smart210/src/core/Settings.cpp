#include "Settings.h"

#include <QDir>
#include <QFileInfo>
#include <QScopedPointer>
#include <QSettings>
#include <QSqlQuery>
#include <QStandardPaths>
#include <QVariant>

namespace SettingKey {
const char *const ServerHost = "server_host";
const char *const ServerPort = "server_port";
const char *const DeviceId = "device_id";
const char *const DeviceName = "device_name";
const char *const UploadMode = "upload_mode";
const char *const ShowSalesWithoutReceipt = "show_sales_without_receipt";
const char *const PurgeDays = "purge_days";
const char *const PinSalt = "pin_salt";
const char *const PinHash = "pin_hash";
const char *const PinFailures = "pin_failures";
const char *const PinLockedUntil = "pin_locked_until";
const char *const PrinterHost = "printer_host";
const char *const PrinterPort = "printer_port";
const char *const ConverterPort = "converter_port";
const char *const CardReaderPort = "card_reader_port";
const char *const CardReaderBaud = "card_reader_baud";
const char *const CardReaderFormat = "card_reader_format";
const char *const CardReaderLength = "card_reader_length";
const char *const CardReaderEncoding = "card_reader_encoding";
const char *const CardReaderPrefix = "card_reader_prefix";
const char *const TicketSign = "ticket_sign";
const char *const TicketSerialBase = "ticket_serial_base";
const char *const SyncBegun = "sync_begun";
const char *const InitialDone = "initial_done";
const char *const LastDownloadAt = "last_download_at";
const char *const SessionUserId = "session_user_id";
const char *const RestoreHandled = "restore_handled";
const char *const ClockOffsetSeconds = "clock_offset_seconds";
}

namespace {

QString &filePath()
{
    static QString path;

    return path;
}

QSettings *openDeviceFile()
{
    QString path = filePath();
    if (path.isEmpty()) {
        path = QStandardPaths::writableLocation(QStandardPaths::ConfigLocation) + "/smart210/device.ini";
    }
    QDir().mkpath(QFileInfo(path).absolutePath());

    return new QSettings(path, QSettings::IniFormat);
}

}

void Settings::setDeviceFile(const QString &path)
{
    filePath() = path;
}

QString Settings::deviceFile()
{
    return filePath();
}

bool Settings::isDeviceBound(const QString &key)
{
    return key == SettingKey::ServerHost || key == SettingKey::ServerPort || key == SettingKey::DeviceName
        || key == SettingKey::PrinterHost || key == SettingKey::PrinterPort || key == SettingKey::ConverterPort
        || key == SettingKey::CardReaderPort || key == SettingKey::CardReaderBaud || key == SettingKey::CardReaderFormat
        || key == SettingKey::CardReaderLength || key == SettingKey::CardReaderEncoding || key == SettingKey::CardReaderPrefix
        || key == SettingKey::UploadMode || key == SettingKey::ShowSalesWithoutReceipt;
}

QString Settings::deviceValue(const QString &name)
{
    QScopedPointer<QSettings> file(openDeviceFile());

    return file->value("device/" + name).toString();
}

void Settings::setDeviceValue(const QString &name, const QString &value)
{
    QScopedPointer<QSettings> file(openDeviceFile());
    if (value.isEmpty()) {
        file->remove("device/" + name);
    } else {
        file->setValue("device/" + name, value);
    }
    file->sync();
}

Settings::Settings(QSqlDatabase db)
    : db_(db)
{
}

QString Settings::value(const QString &key, const QString &defaultValue) const
{
    if (isDeviceBound(key)) {
        QScopedPointer<QSettings> file(openDeviceFile());
        if (file->contains("device/" + key)) {
            return file->value("device/" + key).toString();
        }
    }

    QSqlQuery query(db_);
    query.prepare("SELECT value FROM settings WHERE key = ?");
    query.addBindValue(key);
    if (query.exec() && query.next()) {
        return query.value(0).toString();
    }

    return defaultValue;
}

int Settings::intValue(const QString &key, int defaultValue) const
{
    bool ok = false;
    const int number = value(key).toInt(&ok);

    return ok ? number : defaultValue;
}

bool Settings::boolValue(const QString &key, bool defaultValue) const
{
    if (!contains(key)) {
        return defaultValue;
    }

    return value(key) == "1";
}

bool Settings::contains(const QString &key) const
{
    if (isDeviceBound(key)) {
        QScopedPointer<QSettings> file(openDeviceFile());
        if (file->contains("device/" + key)) {
            return true;
        }
    }

    QSqlQuery query(db_);
    query.prepare("SELECT 1 FROM settings WHERE key = ?");
    query.addBindValue(key);

    return query.exec() && query.next();
}

bool Settings::setValue(const QString &key, const QString &value)
{
    if (isDeviceBound(key)) {
        QScopedPointer<QSettings> file(openDeviceFile());
        file->setValue("device/" + key, value);
        file->sync();
        if (file->status() != QSettings::NoError) {
            return false;
        }
        // the database copy of an older terminal goes, so there is one place only
        QSqlQuery drop(db_);
        drop.prepare("DELETE FROM settings WHERE key = ?");
        drop.addBindValue(key);
        drop.exec();

        return true;
    }

    QSqlQuery query(db_);
    query.prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
    query.addBindValue(key);
    query.addBindValue(value);

    return query.exec();
}

bool Settings::setInt(const QString &key, int value)
{
    return setValue(key, QString::number(value));
}

bool Settings::setBool(const QString &key, bool value)
{
    return setValue(key, QString(value ? "1" : "0"));
}

bool Settings::remove(const QString &key)
{
    if (isDeviceBound(key)) {
        QScopedPointer<QSettings> file(openDeviceFile());
        file->remove("device/" + key);
        file->sync();
    }

    QSqlQuery query(db_);
    query.prepare("DELETE FROM settings WHERE key = ?");
    query.addBindValue(key);

    return query.exec();
}
