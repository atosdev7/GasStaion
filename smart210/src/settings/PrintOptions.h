#ifndef PRINTOPTIONS_H
#define PRINTOPTIONS_H

#include <QMap>
#include <QString>

// How the ticket is printed (config.ini, see AppConfig): the paper, the logo, the font of the title line and the title of each kind of ticket.
struct PrintOptions {
    PrintOptions() : widthDots(576), barcodeModule(3), barcodeHeight(80), barcodeMode("image") {}

    int widthDots;        // printable width: 576 dots is 72 mm at 203 dpi (80 mm paper), 640 for some printers
    int barcodeModule;    // thinnest bar of the barcode in dots
    int barcodeHeight;    // height of the bars in dots
    QString barcodeMode;  // "image" (default): the barcode is drawn as a bitmap like the text, so every printer prints it; "printer": the printer's own Code 128 command
    QString logoPath;     // a picture file (png, bmp, jpg); printed last. No file: no logo (the footer bitmap, when there is one, takes its place)
    QString titleBitmapPath;  // the title of every ticket as a black-and-white bitmap (192 x 48), printed as it is instead of the title text. No file: the text
    QMap<QString, QString> titleBitmaps; // the same by use (sale, self, other, ticket_loan, remainder, card): the file of a use wins over titleBitmapPath
    QString footerBitmapPath; // the footer of every ticket as a black-and-white bitmap (192 x 48), under the barcode, instead of the logo. No file: the logo
    QString fontFamily;   // the font of the title and the details; empty is the application font (the one with Korean in fonts/)
    QString endMode;      // "cut" (default), "label" (form feed to the next label) or "none"
    QMap<QString, QString> titles; // the long title of the ticket by use: sale, self, other, ticket_loan, remainder
};

#endif
