#ifndef TICKETRENDERER_H
#define TICKETRENDERER_H

#include "PrintOptions.h"

#include <QByteArray>
#include <QDateTime>
#include <QFont>
#include <QImage>
#include <QList>
#include <QString>

// What is on a ticket (80 x 50 mm), top to bottom:
//   1. the long title of the ticket type, as a bitmap (it uses a custom font), or a ready 400 x 48 black-and-white bitmap file (PrintOptions::titleBitmapPath)
//   2. the fuel, the weight and the date and time, as a bitmap
//   3. the barcode, Code 128 subset C: drawn as a bitmap (default, every printer prints it, a virtual printer too) or the printer's own command
//      (PrintOptions::barcodeMode)
//   4. the footer: a ready 400 x 32 black-and-white bitmap file (PrintOptions::footerBitmapPath), or the company logo, as a bitmap
// A bitmap is used for the text because the printer has no Korean font of its own and the title uses a font of its own.
struct TicketData {
    TicketData() : kgX100(0) {}

    QString title;
    QString fuelName; // what is printed for the fuel: its letter, A (gasoline) or D (diesel)
    qlonglong kgX100;
    QDateTime when;
    QString barcode; // 16 digits
    QString titleBitmap; // a bitmap file for the title (PrintOptions), empty or missing: the title text is drawn
};

class TicketRenderer
{
public:
    // The bytes to send to the printer for one ticket.
    static QByteArray render(const TicketData &ticket, const PrintOptions &options);

    // The pieces, also for checking how it looks.
    static QImage titleImage(const QString &title, const QFont &base, int widthDots);
    static QImage detailsImage(const TicketData &ticket, const QFont &base, int widthDots);
    static QImage logoImage(const QString &path, int widthDots, int maxHeight);
    // A black-and-white bitmap (400 x 48 for the title, 400 x 32 for the footer) on a white strip as wide as the paper: printed at its own size, never scaled (a picture that is not
    // black and white is turned into dots at the middle grey; one wider than the paper is cut at both sides). A null image when the file is missing or not a picture.
    static QImage bitmapStrip(const QString &path, int widthDots);

    // The barcode of the 16 digits as a bitmap: Code 128 subset C (start C, a symbol for every two digits, the check symbol, stop) with the digits under the bars.
    // The bars are `module` dots thin (smaller when the paper is not wide enough) and `height` dots high. A null image when the digits are not an even number.
    static QImage barcodeImage(const QString &digits, int module, int height, const QFont &base, int widthDots);
    // The widths of the bars and spaces of that barcode, in modules, bar first (what barcodeImage draws; for checking).
    static QList<int> barcodeModules(const QString &digits);

    static QFont fontFor(const PrintOptions &options);
};

#endif
