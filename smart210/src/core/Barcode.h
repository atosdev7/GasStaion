#ifndef BARCODE_H
#define BARCODE_H

#include <QString>

// A ticket barcode is 16 digits: sign (5) + code (2) + serial (9), for example 0000003000000011. The database keeps it
// as an INTEGER (its number), which is smaller and faster to index than text. The leading zeros are not stored, so a
// barcode is always turned back into 16 digits before it is printed or sent to the server.
class Barcode
{
public:
    static const int Length = 16;

    // True for exactly 16 digits.
    static bool isValid(const QString &barcode);

    // The number for the database, or -1 when the text is not a barcode.
    static qlonglong toKey(const QString &barcode);

    // The 16 digits for a key (padded with zeros on the left).
    static QString fromKey(qlonglong key);
};

#endif
