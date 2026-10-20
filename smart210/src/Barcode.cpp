#include "Barcode.h"

bool Barcode::isValid(const QString &barcode)
{
    if (barcode.size() != Length) {
        return false;
    }
    for (int i = 0; i < barcode.size(); ++i) {
        if (barcode.at(i) < '0' || barcode.at(i) > '9') {
            return false;
        }
    }

    return true;
}

qlonglong Barcode::toKey(const QString &barcode)
{
    return isValid(barcode) ? barcode.toLongLong() : -1;
}

QString Barcode::fromKey(qlonglong key)
{
    return QString("%1").arg(key, Length, 10, QChar('0'));
}
