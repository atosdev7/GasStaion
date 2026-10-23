#ifndef TICKETRENDERER_H
#define TICKETRENDERER_H

#include "PrintOptions.h"

#include <QByteArray>
#include <QDateTime>
#include <QFont>
#include <QImage>
#include <QString>

// What is on a ticket (80 x 50 mm), top to bottom:
//   1. the long title of the ticket type, as a bitmap (it uses a custom font)
//   2. the fuel, the weight and the date and time, as a bitmap
//   3. the barcode, the printer's own Code 128 subset C
//   4. the company logo, as a bitmap
// A bitmap is used for the text because the printer has no Korean font of its own and the title uses a font of its own.
struct TicketData {
    TicketData() : kgX100(0) {}

    QString title;
    QString fuelName;
    qlonglong kgX100;
    QDateTime when;
    QString barcode; // 16 digits
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

    static QFont fontFor(const PrintOptions &options);
};

#endif
