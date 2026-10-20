#ifndef PRINTOPTIONS_H
#define PRINTOPTIONS_H

#include <QMap>
#include <QString>

// How the ticket is printed (config.ini, see AppConfig): the paper, the logo, the font of the title line and the title of each kind of ticket.
struct PrintOptions {
    PrintOptions() : widthDots(576), barcodeModule(3), barcodeHeight(80) {}

    int widthDots;        // printable width: 576 dots is 72 mm at 203 dpi (80 mm paper), 640 for some printers
    int barcodeModule;    // thinnest bar of the barcode in dots
    int barcodeHeight;    // height of the bars in dots
    QString logoPath;     // a picture file (png, bmp, jpg); printed last. No file: no logo
    QString fontFamily;   // the font of the title and the details; empty is the application font (the one with Chinese in fonts/)
    QString endMode;      // "cut" (default), "label" (form feed to the next label) or "none"
    QMap<QString, QString> titles; // the long title of the ticket by use: sale, self, other, ticket_loan, remainder
};

#endif
