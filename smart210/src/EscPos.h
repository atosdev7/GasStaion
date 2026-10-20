#ifndef ESCPOS_H
#define ESCPOS_H

#include <QByteArray>
#include <QImage>
#include <QString>

// The ESC/POS commands of the RongTa 80 mm thermal printer that the terminal needs. Everything returns bytes: nothing here talks to a
// printer, so it can be tested.
class EscPos
{
public:
    enum Align { Left = 0, Center = 1, Right = 2 };

    static QByteArray initialize();                  // ESC @
    static QByteArray align(Align align);            // ESC a n
    static QByteArray feed(int lines);               // ESC d n
    static QByteArray cut();                         // GS V 66 n: feed and partial cut
    static QByteArray labelFeed();                   // FF: to the start of the next label (printers with a label sensor)

    // A black-and-white picture as raster rows (GS v 0), in bands of at most 128 rows because some printers refuse taller ones. The
    // image is thresholded; its width is rounded up to a multiple of 8 with white.
    static QByteArray raster(const QImage &image);

    // A native Code 128 barcode of digits, in subset C (two digits a symbol), printed with the digits below it. `digits` must be an even
    // number of digits. `module` is the width of the thinnest bar in dots (2 to 6), `height` the bar height in dots.
    static QByteArray barcode128C(const QString &digits, int module, int height);

    // Asks for the real-time status: transmit printer status, offline status and paper roll status (DLE EOT 1, 2, 4).
    static QByteArray statusRequest();

    // The three status bytes answered to statusRequest(). Returns an empty string when the printer is fine, otherwise what is wrong.
    static QString statusProblem(const QByteArray &answer);
};

#endif
