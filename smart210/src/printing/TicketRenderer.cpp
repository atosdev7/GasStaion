#include "TicketRenderer.h"

#include "Decimal.h"
#include "EscPos.h"

#include <QFontMetrics>
#include <QPainter>
#include <QRect>

namespace {

const int Margin = 8;

// Code 128: the widths (in modules) of the bars and spaces of the symbols 0 to 106, bar first (105 is Start C, 106 is Stop, 7 elements)
const char *const Code128Patterns[107] = {
    "212222", "222122", "222221", "121223", "121322", "131222",
    "122213", "122312", "132212", "221213", "221312", "231212",
    "112232", "122132", "122231", "113222", "123122", "123221",
    "223211", "221132", "221231", "213212", "223112", "312131",
    "311222", "321122", "321221", "312212", "322112", "322211",
    "212123", "212321", "232121", "111323", "131123", "131321",
    "112313", "132113", "132311", "211313", "231113", "231311",
    "112133", "112331", "132131", "113123", "113321", "133121",
    "313121", "211331", "231131", "213113", "213311", "213131",
    "311123", "311321", "331121", "312113", "312311", "332111",
    "314111", "221411", "431111", "111224", "111422", "121124",
    "121421", "141122", "141221", "112214", "112412", "122114",
    "122411", "142112", "142211", "241211", "221114", "413111",
    "241112", "134111", "111242", "121142", "121241", "114212",
    "124112", "124211", "411212", "421112", "421211", "212141",
    "214121", "412121", "111143", "111341", "131141", "114113",
    "114311", "411113", "411311", "113141", "114131", "311141",
    "411131", "211412", "211214", "211232", "2331112"
};

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

QImage TicketRenderer::bitmapStrip(const QString &path, int widthDots)
{
    QImage picture(path);
    if (path.isEmpty() || picture.isNull()) {
        return QImage();
    }
    // dots only: a grey picture is not dithered, so the 192 x 48 bitmap that was drawn for the printer comes out the way it was drawn
    picture = picture.convertToFormat(QImage::Format_Mono, Qt::MonoOnly | Qt::ThresholdDither | Qt::AvoidDither).convertToFormat(QImage::Format_RGB32);

    QImage strip = blank(widthDots, picture.height() + 8);
    QPainter painter(&strip);
    painter.drawImage((widthDots - picture.width()) / 2, 4, picture);

    return strip;
}

// The symbols of the barcode: Start C, one symbol for every two digits (its value 0 to 99), the check symbol (Start C plus each symbol times its place, modulo 103), Stop.
QList<int> TicketRenderer::barcodeModules(const QString &digits)
{
    QList<int> widths;
    if (digits.isEmpty() || digits.size() % 2 != 0) {
        return widths;
    }
    for (int i = 0; i < digits.size(); ++i) {
        if (!digits.at(i).isDigit()) {
            return widths;
        }
    }

    QList<int> symbols;
    symbols << 105;
    int sum = 105;
    for (int i = 0; i < digits.size(); i += 2) {
        const int value = digits.mid(i, 2).toInt();
        symbols << value;
        sum += value * (symbols.size() - 1);
    }
    symbols << sum % 103 << 106;

    foreach (int symbol, symbols) {
        const QByteArray pattern(Code128Patterns[symbol]);
        for (int i = 0; i < pattern.size(); ++i) {
            widths << pattern.at(i) - '0';
        }
    }

    return widths;
}

QImage TicketRenderer::barcodeImage(const QString &digits, int module, int height, const QFont &base, int widthDots)
{
    const QList<int> widths = barcodeModules(digits);
    if (widths.isEmpty()) {
        return QImage();
    }

    int modules = 0;
    foreach (int width, widths) {
        modules += width;
    }
    const int quiet = 10; // modules of white on each side
    module = qBound(1, module, 6);
    while (module > 1 && (modules + 2 * quiet) * module > widthDots) {
        --module;
    }

    QFont font = base;
    font.setPixelSize(22);
    const QFontMetrics metrics(font);
    const int bars = qMax(20, height);
    QImage image = blank(widthDots, 4 + bars + 2 + metrics.height() + 4);
    QPainter painter(&image);
    int x = (widthDots - modules * module) / 2;
    for (int i = 0; i < widths.size(); ++i) {
        const int width = widths.at(i) * module;
        if (i % 2 == 0) {
            painter.fillRect(QRect(x, 4, width, bars), Qt::black);
        }
        x += width;
    }
    painter.setFont(font);
    painter.setPen(Qt::black);
    painter.drawText(QRect(0, 4 + bars + 2, widthDots, metrics.height()), Qt::AlignCenter, digits);

    return image;
}

QByteArray TicketRenderer::render(const TicketData &ticket, const PrintOptions &options)
{
    const QFont font = fontFor(options);

    QByteArray job;
    job.append(EscPos::initialize());
    job.append(EscPos::align(EscPos::Center));
    const QImage titleBitmap = bitmapStrip(ticket.titleBitmap, options.widthDots);
    job.append(EscPos::raster(titleBitmap.isNull() ? titleImage(ticket.title, font, options.widthDots) : titleBitmap));
    job.append(EscPos::raster(detailsImage(ticket, font, options.widthDots)));
    if (options.barcodeMode == "printer") {
        job.append(EscPos::barcode128C(ticket.barcode, options.barcodeModule, options.barcodeHeight));
    } else {
        const QImage barcode = barcodeImage(ticket.barcode, options.barcodeModule, options.barcodeHeight, font, options.widthDots);
        if (!barcode.isNull()) {
            job.append(EscPos::raster(barcode));
        }
    }

    const QImage footer = bitmapStrip(options.footerBitmapPath, options.widthDots);
    const QImage logo = footer.isNull() ? logoImage(options.logoPath, options.widthDots, 100) : footer;
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
