#include "TicketRenderer.h"

#include "Decimal.h"
#include "EscPos.h"

#include <QFontMetrics>
#include <QPainter>
#include <QRect>

namespace {

const int Margin = 8;

QImage blank(int width, int height)
{
    QImage image(width, height, QImage::Format_RGB32);
    image.fill(Qt::white);

    return image;
}

}

QFont TicketRenderer::fontFor(const PrintOptions &options)
{
    QFont font;
    if (!options.fontFamily.isEmpty()) {
        font.setFamily(options.fontFamily);
    }

    return font;
}

// One line if it fits at some size from large to small; if not, as many lines as it needs at the smallest size.
QImage TicketRenderer::titleImage(const QString &title, const QFont &base, int widthDots)
{
    const int usable = widthDots - 2 * Margin;

    QFont font = base;
    font.setBold(true);
    for (int pixels = 40; pixels >= 22; pixels -= 2) {
        font.setPixelSize(pixels);
        const QFontMetrics metrics(font);
        if (metrics.boundingRect(title).width() <= usable) {
            QImage image = blank(widthDots, metrics.height() + 12);
            QPainter painter(&image);
            painter.setFont(font);
            painter.setPen(Qt::black);
            painter.drawText(QRect(Margin, 6, usable, metrics.height()), Qt::AlignCenter, title);

            return image;
        }
    }

    font.setPixelSize(22);
    const QFontMetrics metrics(font);
    const int flags = Qt::AlignCenter | Qt::TextWordWrap | Qt::TextWrapAnywhere;
    const QRect needed = metrics.boundingRect(QRect(0, 0, usable, 1000), flags, title);
    QImage image = blank(widthDots, needed.height() + 12);
    QPainter painter(&image);
    painter.setFont(font);
    painter.setPen(Qt::black);
    painter.drawText(QRect(Margin, 6, usable, needed.height()), flags, title);

    return image;
}

QImage TicketRenderer::detailsImage(const TicketData &ticket, const QFont &base, int widthDots)
{
    const QString text = ticket.fuelName + "   " + Decimal::format(ticket.kgX100, 2) + " kg   " + ticket.when.toString("yyyy-MM-dd HH:mm");

    QFont font = base;
    font.setPixelSize(28);
    const QFontMetrics metrics(font);
    QImage image = blank(widthDots, metrics.height() + 10);
    QPainter painter(&image);
    painter.setFont(font);
    painter.setPen(Qt::black);
    painter.drawText(QRect(Margin, 5, widthDots - 2 * Margin, metrics.height()), Qt::AlignCenter, text);

    return image;
}

// The logo scaled down to fit (never up), centred on a white strip as wide as the paper.
QImage TicketRenderer::logoImage(const QString &path, int widthDots, int maxHeight)
{
    QImage logo(path);
    if (path.isEmpty() || logo.isNull()) {
        return QImage();
    }

    if (logo.width() > widthDots - 2 * Margin || logo.height() > maxHeight) {
        logo = logo.scaled(widthDots - 2 * Margin, maxHeight, Qt::KeepAspectRatio, Qt::SmoothTransformation);
    }

    QImage strip = blank(widthDots, logo.height() + 8);
    QPainter painter(&strip);
    painter.drawImage((widthDots - logo.width()) / 2, 4, logo);

    return strip;
}

QByteArray TicketRenderer::render(const TicketData &ticket, const PrintOptions &options)
{
    const QFont font = fontFor(options);

    QByteArray job;
    job.append(EscPos::initialize());
    job.append(EscPos::align(EscPos::Center));
    job.append(EscPos::raster(titleImage(ticket.title, font, options.widthDots)));
    job.append(EscPos::raster(detailsImage(ticket, font, options.widthDots)));
    job.append(EscPos::feed(1));
    job.append(EscPos::barcode128C(ticket.barcode, options.barcodeModule, options.barcodeHeight));
    job.append(EscPos::feed(1));

    const QImage logo = logoImage(options.logoPath, options.widthDots, 100);
    if (!logo.isNull()) {
        job.append(EscPos::raster(logo));
    }

    if (options.endMode == "label") {
        job.append(EscPos::labelFeed());
    } else if (options.endMode != "none") {
        job.append(EscPos::cut());
    }

    return job;
}
