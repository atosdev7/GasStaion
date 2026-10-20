#include "TicketNumbers.h"

#include "Barcode.h"
#include "Settings.h"

#include <QSqlQuery>
#include <QVariant>

QString TicketNumbers::codeSetting(const QString &name)
{
    return "reserved_code_" + name;
}

bool TicketNumbers::available(QSqlDatabase db, const QString &codeName)
{
    Settings settings(db);

    return settings.value(SettingKey::TicketSign).size() == 5 && settings.value(codeSetting(codeName)).size() == 2
        && settings.value(SettingKey::TicketSerialBase).toLongLong() > 0;
}

qlonglong TicketNumbers::next(QSqlDatabase db, const QString &codeName, QString *error)
{
    Settings settings(db);
    const QString sign = settings.value(SettingKey::TicketSign);
    const QString code = settings.value(codeSetting(codeName));
    const qlonglong base = settings.value(SettingKey::TicketSerialBase).toLongLong();
    if (sign.size() != 5 || code.size() != 2 || base <= 0) {
        *error = QObject::tr("The ticket numbers are not known yet. Download from the server first.");

        return -1;
    }

    const qlonglong signNumber = sign.toLongLong();
    const qlonglong codeNumber = code.toLongLong();
    const qlonglong first = signNumber * 100000000000LL + codeNumber * 1000000000LL;

    // The next number is above everything this terminal has used, everything the web app held at the last download, and the
    // counter, which also covers tickets that were purged from the database.
    qlonglong serial = base + 1;
    const qlonglong floor = settings.value("serial_floor_" + code).toLongLong();
    if (floor + 1 > serial) {
        serial = floor + 1;
    }
    const qlonglong counter = settings.value("serial_next_" + code).toLongLong();
    if (counter > serial) {
        serial = counter;
    }

    QSqlQuery query(db);
    query.prepare("SELECT MAX(barcode) FROM tickets WHERE barcode BETWEEN ? AND ?");
    query.addBindValue(first);
    query.addBindValue(first + 999999999LL);
    if (query.exec() && query.next() && !query.value(0).isNull()) {
        const qlonglong used = query.value(0).toLongLong() - first;
        if (used + 1 > serial) {
            serial = used + 1;
        }
    }

    if (serial > 999999999LL) {
        *error = QObject::tr("The ticket numbers of this terminal are used up.");

        return -1;
    }

    settings.setValue("serial_next_" + code, QString::number(serial + 1));

    return first + serial;
}
