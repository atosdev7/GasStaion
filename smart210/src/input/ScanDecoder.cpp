#include "ScanDecoder.h"

#include <cstring>

namespace {

// Linux key codes (<linux/input-event-codes.h>) that matter for a scanner.
const int KeyEnter = 28;
const int KeyKpEnter = 96;
const int KeyLeftShift = 42;
const int KeyRightShift = 54;

struct Tables {
    char lower[128];
    char upper[128];

    Tables()
    {
        std::memset(lower, 0, sizeof(lower));
        std::memset(upper, 0, sizeof(upper));

        const char *digits = "1234567890";
        const char *shifted = "!@#$%^&*()";
        for (int i = 0; i < 10; ++i) {
            lower[2 + i] = digits[i];
            upper[2 + i] = shifted[i];
        }
        letters("qwertyuiop", 16);
        letters("asdfghjkl", 30);
        letters("zxcvbnm", 44);

        pair(12, '-', '_');
        pair(13, '=', '+');
        pair(26, '[', '{');
        pair(27, ']', '}');
        pair(39, ';', ':');
        pair(40, '\'', '"');
        pair(41, '`', '~');
        pair(43, '\\', '|');
        pair(51, ',', '<');
        pair(52, '.', '>');
        pair(53, '/', '?');
        pair(57, ' ', ' ');

        // the number pad
        pair(71, '7', '7');
        pair(72, '8', '8');
        pair(73, '9', '9');
        pair(74, '-', '-');
        pair(75, '4', '4');
        pair(76, '5', '5');
        pair(77, '6', '6');
        pair(78, '+', '+');
        pair(79, '1', '1');
        pair(80, '2', '2');
        pair(81, '3', '3');
        pair(82, '0', '0');
        pair(83, '.', '.');
        pair(55, '*', '*');
        pair(98, '/', '/');
    }

    void pair(int code, char plain, char withShift)
    {
        lower[code] = plain;
        upper[code] = withShift;
    }

    void letters(const char *row, int firstCode)
    {
        for (int i = 0; row[i] != '\0'; ++i) {
            lower[firstCode + i] = row[i];
            upper[firstCode + i] = char(row[i] - 'a' + 'A');
        }
    }
};

const Tables &tables()
{
    static const Tables t;

    return t;
}

}

ScanDecoder::ScanDecoder()
    : shift_(false), lastKeyMs_(0)
{
}

void ScanDecoder::reset()
{
    buffer_.clear();
    shift_ = false;
}

QChar ScanDecoder::charOf(int code, bool shift)
{
    if (code < 0 || code >= 128) {
        return QChar();
    }
    const char c = shift ? tables().upper[code] : tables().lower[code];

    return c == '\0' ? QChar() : QChar(c);
}

bool ScanDecoder::event(int type, int code, int value, qint64 nowMs, QString *barcode)
{
    if (type != EvKey) {
        return false;
    }
    if (code == KeyLeftShift || code == KeyRightShift) {
        shift_ = (value != 0);

        return false;
    }
    if (value != 1) {
        return false; // only a key going down counts (not its release, not the repeat)
    }

    if (!buffer_.isEmpty() && nowMs - lastKeyMs_ > IdleMs) {
        buffer_.clear(); // too long since the last key: that was not part of this scan
    }
    lastKeyMs_ = nowMs;

    if (code == KeyEnter || code == KeyKpEnter) {
        if (buffer_.isEmpty()) {
            return false;
        }
        *barcode = buffer_;
        buffer_.clear();

        return true;
    }

    const QChar c = charOf(code, shift_);
    if (!c.isNull() && buffer_.size() < MaxLength) {
        buffer_.append(c);
    }

    return false;
}
