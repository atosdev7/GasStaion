#include "Decimal.h"

namespace {

qlonglong power10(int scale)
{
    qlonglong result = 1;
    for (int i = 0; i < scale; ++i) {
        result *= 10;
    }

    return result;
}

}

bool Decimal::parse(const QString &text, int scale, qlonglong *value)
{
    const QString s = text.trimmed();
    int i = 0;
    bool negative = false;
    if (i < s.size() && (s.at(i) == '-' || s.at(i) == '+')) {
        negative = (s.at(i) == '-');
        ++i;
    }

    QString whole;
    QString fraction;
    bool seenDot = false;
    for (; i < s.size(); ++i) {
        const QChar c = s.at(i);
        if (c >= '0' && c <= '9') {
            (seenDot ? fraction : whole) += c;
        } else if (c == '.' && !seenDot) {
            seenDot = true;
        } else {
            return false;
        }
    }
    if (whole.isEmpty() && fraction.isEmpty()) {
        return false;
    }
    if (whole.size() > 14) { // far more than any amount here, and it keeps the arithmetic inside 64 bits
        return false;
    }

    qlonglong number = whole.isEmpty() ? 0 : whole.toLongLong() * power10(scale);

    const QString kept = fraction.left(scale).leftJustified(scale, '0');
    if (scale > 0) {
        number += kept.toLongLong();
    }
    if (fraction.size() > scale && fraction.at(scale) >= '5') {
        number += 1; // rounded up by the first digit that was dropped
    }

    *value = negative ? -number : number;

    return true;
}

qlonglong Decimal::parseOr(const QString &text, int scale, qlonglong defaultValue)
{
    qlonglong value = 0;

    return parse(text, scale, &value) ? value : defaultValue;
}

qlonglong Decimal::fromJson(const QJsonValue &value, int scale, bool *ok)
{
    qlonglong number = 0;

    if (value.isString()) {
        *ok = parse(value.toString(), scale, &number);
    } else if (value.isDouble()) {
        *ok = parse(QString::number(value.toDouble(), 'f', scale + 2), scale, &number);
    } else {
        *ok = false;
    }

    return *ok ? number : 0;
}

QString Decimal::format(qlonglong value, int scale)
{
    const bool negative = value < 0;
    const qlonglong magnitude = negative ? -value : value;
    const qlonglong unit = power10(scale);

    QString text = QString::number(magnitude / unit);
    if (scale > 0) {
        text += "." + QString::number(magnitude % unit).rightJustified(scale, '0');
    }

    return negative ? "-" + text : text;
}
