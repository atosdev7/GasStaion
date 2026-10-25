#ifndef SETTINGS_H
#define SETTINGS_H

#include <QSqlDatabase>
#include <QString>

// Key/value settings kept in the database's `settings` table: the server address, the token, the device id and so on.
// Everything is stored as text. (The few settings needed before the database opens live in config.ini: see AppConfig.)
// Names of the settings the program keeps in the table.
namespace SettingKey {
extern const char *const ServerHost;   // the web app's address
extern const char *const ServerPort;
extern const char *const DeviceId;     // the web app's id of this device (set when the server knew this address)
extern const char *const DeviceName;
extern const char *const UploadMode;    // "manual", "close" (at the close of the day) or "continuous" (see AutoUpload), Machine Config
extern const char *const NozzlesNotOnBoard; // the numbers ("1,3") of the nozzles whose saved config the board has not confirmed yet (Update that failed or was not sent)
extern const char *const ShowSalesWithoutReceipt; // "1" (default) or "0": the Review screen lists the sales without a receipt or leaves them out, Machine Config
extern const char *const PurgeDays;    // how long synced records stay on the device
extern const char *const PinSalt;      // the Machine Config PIN, as the web app keeps it (salt and hash)
extern const char *const PinHash;
extern const char *const PinFailures;   // wrong PINs in a row on the Machine Config door
extern const char *const PinLockedUntil; // local time until which the door stays shut after too many wrong PINs
extern const char *const PrinterHost;    // the ticket printer's address (Machine Config)
extern const char *const PrinterPort;    // default 9100
extern const char *const ConverterPort;  // the port the RS485 converter connects to (this terminal listens), default 4001
extern const char *const CardReaderPort;     // the serial port of the RFID card reader (Machine Config), default /dev/ttyUSB0
extern const char *const CardReaderBaud;     // its speed, default 9600 (8N1)
extern const char *const CardReaderFormat;   // how the id is framed: "line" (ends with CR / LF) or "fixed" (CardReaderLength characters)
extern const char *const CardReaderLength;   // the characters of a fixed frame
extern const char *const CardReaderEncoding; // the bytes are the id as text ("ascii") or as hex digits ("hex")
extern const char *const CardReaderPrefix;   // the text a line starts with before the id (the Arduino reader sends "CARD: <id>"), default CARD:
extern const char *const TicketSign;      // the 5-digit business sign of a ticket barcode
extern const char *const TicketSerialBase; // tickets minted here are numbered above this
extern const char *const SyncBegun;    // 1 once the server was told this database starts from nothing
extern const char *const InitialDone;  // 1 once everything was downloaded for the first time (the first-sync gate)
extern const char *const LastDownloadAt; // local time of the last complete download
extern const char *const SessionUserId; // the cashier who is logged in (kept over restarts until Log out)
extern const char *const RestoreHandled;     // the restore of the web app's database (its restored_at) this terminal has already acted on
extern const char *const ClockOffsetSeconds; // server time minus this terminal's time, at the last download
extern const char *const UtcOffsetSeconds;   // the station's time zone as the server's offset from UTC at the last download (it follows daylight saving time)
extern const char *const TimeZoneName;       // the name of that zone, for the Settings page (Asia/Seoul)
}

// The machine configuration lives apart from the database, in an INI file on the board's own flash (see AppConfig::deviceFile), so a new SD
// card does not have to be set up again: the keys listed in isDeviceBound() are read from and written to that file. A value that only the
// database holds (a terminal made before this) is still found there, and moves to the file the next time it is saved.
class Settings
{
public:
    explicit Settings(QSqlDatabase db);

    static void setDeviceFile(const QString &path);
    static QString deviceFile();
    static bool isDeviceBound(const QString &key);
    // Free values of the device file that are not settings keys (the tank of each nozzle).
    static QString deviceValue(const QString &name);
    static void setDeviceValue(const QString &name, const QString &value);

    QString value(const QString &key, const QString &defaultValue = QString()) const;
    int intValue(const QString &key, int defaultValue = 0) const;
    bool boolValue(const QString &key, bool defaultValue = false) const;
    bool contains(const QString &key) const;

    // Each returns false when the database refused the write. (Named apart from setValue on purpose: a plain "text"
    // would pick an overload taking bool, since a pointer converts to bool more readily than to QString.)
    bool setValue(const QString &key, const QString &value);
    bool setInt(const QString &key, int value);
    bool setBool(const QString &key, bool value);
    bool remove(const QString &key);

    QSqlDatabase database() const { return db_; }

private:
    QSqlDatabase db_;
};

#endif
