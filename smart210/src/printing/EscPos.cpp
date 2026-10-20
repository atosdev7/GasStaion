#include "EscPos.h"

#include <QObject>

QByteArray EscPos::initialize()
{
    return QByteArray("\x1B\x40", 2);
}

QByteArray EscPos::align(Align align)
{
    QByteArray bytes("\x1B\x61", 2);
    bytes.append(char(align));

    return bytes;
}

QByteArray EscPos::feed(int lines)
{
    QByteArray bytes("\x1B\x64", 2);
    bytes.append(char(qBound(0, lines, 255)));

    return bytes;
}

QByteArray EscPos::cut()
{
    QByteArray bytes("\x1D\x56\x42", 3);
    bytes.append(char(3)); // feeds 3 lines past the cutter, then cuts

    return bytes;
}

QByteArray EscPos::labelFeed()
{
    return QByteArray("\x0C", 1);
}

QByteArray EscPos::raster(const QImage &source)
{
    const int width = (source.width() + 7) / 8 * 8;
    const int bytesPerRow = width / 8;
    const QImage gray = source.convertToFormat(QImage::Format_RGB32);

    QByteArray all;
    for (int top = 0; top < gray.height(); top += 128) {
        const int rows = qMin(128, gray.height() - top);

        QByteArray band;
        band.append("\x1D\x76\x30\x00", 4);
        band.append(char(bytesPerRow & 0xFF));
        band.append(char((bytesPerRow >> 8) & 0xFF));
        band.append(char(rows & 0xFF));
        band.append(char((rows >> 8) & 0xFF));

        for (int y = top; y < top + rows; ++y) {
            for (int byte = 0; byte < bytesPerRow; ++byte) {
                int bits = 0;
                for (int bit = 0; bit < 8; ++bit) {
                    const int x = byte * 8 + bit;
                    bool black = false;
                    if (x < gray.width()) {
                        const QRgb pixel = gray.pixel(x, y);
                        black = qGray(pixel) < 128 && qAlpha(pixel) > 0;
                    }
                    bits = (bits << 1) | (black ? 1 : 0);
                }
                band.append(char(bits));
            }
        }
        all.append(band);
    }

    return all;
}

QByteArray EscPos::barcode128C(const QString &digits, int module, int height)
{
    QByteArray data("{C", 2);
    for (int i = 0; i + 1 < digits.size(); i += 2) {
        data.append(char(digits.mid(i, 2).toInt())); // two digits are one symbol: its value 0 to 99
    }

    QByteArray bytes;
    bytes.append("\x1D\x48\x02", 3);                         // GS H 2: the digits printed below the bars
    bytes.append("\x1D\x68", 2).append(char(qBound(1, height, 255)));   // GS h n: height in dots
    bytes.append("\x1D\x77", 2).append(char(qBound(2, module, 6)));     // GS w n: width of the thinnest bar
    bytes.append("\x1D\x6B\x49", 3).append(char(data.size()));        // GS k 73 n: Code 128, n bytes follow
    bytes.append(data);

    return bytes;
}

QByteArray EscPos::statusRequest()
{
    return QByteArray("\x10\x04\x01\x10\x04\x02\x10\x04\x04", 9);
}

QString EscPos::statusProblem(const QByteArray &answer)
{
    if (answer.size() < 3) {
        return QString();
    }

    const unsigned char printer = answer.at(0);
    const unsigned char offline = answer.at(1);
    const unsigned char paper = answer.at(2);

    if (offline & 0x04) {
        return QObject::tr("The printer cover is open.");
    }
    if ((paper & 0x60) || (offline & 0x20)) {
        return QObject::tr("The printer is out of paper.");
    }
    if (offline & 0x40) {
        return QObject::tr("The printer has an error.");
    }
    if (printer & 0x08) {
        return QObject::tr("The printer is offline.");
    }

    return QString();
}
