#include "TicketNumbers.h"

#include "Barcode.h"
#include "Settings.h"

#include <QObject>
#include <QSqlQuery>
#include <QVariant>

QString TicketNumbers::fuelDigitSetting(const QString &fuelType)
{
    return "ticket_fuel_digit_" + fuelType;
}

QString TicketNumbers::typeDigitSetting(const QString &type)
{
    return "ticket_type_digit_" + type;
}

QString TicketNumbers::typeOfUsage(const QString &usage)
{
    return usage == "sale" ? QString("direct") : usage;
}

QString TicketNumbers::code(QSqlDatabase db, const QString &type, const QString &fuelType)
{
    Settings settings(db);
    const QString fuel = settings.value(fuelDigitSetting(fuelType));
    const QString kind = settings.value(typeDigitSetting(type));

    return fuel.size() == 1 && kind.size() == 1 ? fuel + kind : QString();
}

QString TicketNumbers::kindOf(QSqlDatabase db, qlonglong barcodeKey)
{
    const QString code = Barcode::fromKey(barcodeKey).mid(5, 2);   // 5 digits of sign, then the code
    if (code.size() != 2) {
        return "press";
    }

    Settings settings(db);
    bool fuelKnown = false;
    QSqlQuery fuels(db);
    if (fuels.exec("SELECT fuel_type FROM fuel_types")) {
        while (fuels.next()) {
            fuelKnown = fuelKnown || settings.value(fuelDigitSetting(fuels.value(0).toString())) == code.left(1);
        }
    }
    if (!fuelKnown) {
        return "press";
    }

    const char *const kinds[] = {"direct", "self", "other", "ticket_loan", "card", "remainder"};
    for (int i = 0; i < 6; ++i) {
        if (settings.value(typeDigitSetting(kinds[i])) == code.right(1)) {
            return kinds[i];
        }
    }

    return "press";
}

QString TicketNumbers::kindName(const QString &kind)
{
    if (kind == "direct") {
        return QObject::tr("spot sale");
    }
    if (kind == "self") {
        return QObject::tr("self use");
    }
    if (kind == "other") {
        return QObject::tr("other use");
    }
    if (kind == "ticket_loan") {
        return QObject::tr("ticket loan");
    }
    if (kind == "card") {
        return QObject::tr("card");
    }
    if (kind == "remainder") {
        return QObject::tr("remainder");
    }

    return QObject::tr("press");
}

bool TicketNumbers::available(QSqlDatabase db, const QString &type, const QString &fuelType)
{
    Settings settings(db);

    return settings.value(SettingKey::TicketSign).size() == 5 && code(db, type, fuelType).size() == 2
        && settings.value(SettingKey::TicketSerialBase).toLongLong() > 0;
}

qlonglong TicketNumbers::next(QSqlDatabase db, const QString &type, const QString &fuelType, QString *error)
{
    Settings settings(db);
    const QString sign = settings.value(SettingKey::TicketSign);
    const QString code = TicketNumbers::code(db, type, fuelType);
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
