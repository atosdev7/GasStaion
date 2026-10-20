#ifndef SCANDECODER_H
#define SCANDECODER_H

#include <QChar>
#include <QString>

// Turns the key events of a barcode scanner that works as a USB keyboard into the barcode it typed. An event is what the Linux input
// subsystem reports (type, code, value as in <linux/input.h>; the numbers are used as they are, so this builds and is tested anywhere).
// The scanner types the characters and ends with Enter: that completes a barcode. Keys that come further apart than IdleMs are not one
// scan (a stray key must not stick to the next barcode).
class ScanDecoder
{
public:
    enum { EvKey = 1, MaxLength = 128, IdleMs = 1000 };

    ScanDecoder();

    // Returns true and the barcode when this event was the Enter that ends one. nowMs is any steady clock in milliseconds.
    bool event(int type, int code, int value, qint64 nowMs, QString *barcode);

    void reset();

    // The character of a key (US layout, with or without Shift); a null character for keys that type nothing.
    static QChar charOf(int code, bool shift);

private:
    QString buffer_;
    bool shift_;
    qint64 lastKeyMs_;
};

#endif
