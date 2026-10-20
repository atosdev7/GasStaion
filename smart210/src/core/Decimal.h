#ifndef DECIMAL_H
#define DECIMAL_H

#include <QJsonValue>
#include <QString>

// The database keeps kg, money and litres as integers x100 and density x10000 (see 001_initial.sql), so sums are exact.
// The web app's API sends and expects decimal strings such as "12.50". This converts between the two.
//
//   Decimal::parse("12.50", 2)  -> 1250        Decimal::format(1250, 2)  -> "12.50"
//   Decimal::parse("-0.05", 2)  -> -5          Decimal::format(-5, 2)    -> "-0.05"
class Decimal
{
public:
    // Digits beyond the scale are rounded (half away from zero). False for anything that is not a plain decimal number.
    static bool parse(const QString &text, int scale, qlonglong *value);
    static qlonglong parseOr(const QString &text, int scale, qlonglong defaultValue = 0);

    // A JSON string ("12.50") or number (12.5). Null and other types are not a number: ok is false.
    static qlonglong fromJson(const QJsonValue &value, int scale, bool *ok);

    static QString format(qlonglong value, int scale);
};

#endif
