#ifndef APPCONFIG_H
#define APPCONFIG_H

#include "PrintOptions.h"

#include <QString>
#include <QSize>

// Settings that must be known before the database is open, read from config.ini next to the program:
//   language   = en                   (the only language; default en)
//   fullscreen = true | false         (default: true on the board, false on Windows, where the program runs in a window)
//   width, height                     (window size when not fullscreen; default 1280 x 720)
//   fontDir    = folder holding the .ttf/.otf/.ttc files to load (default: fonts next to the program)
//   deviceFile = the file (INI) that keeps the machine configuration apart from the SD card, so a new card needs no setup again: server
//                address, device name, printer, converter port and the tank of each nozzle (default: smart210/device.ini in the user's config folder,
//                that is on the board's own flash, not on the SD card)
//   dataDir    = folder of the database file smart210.db (default: data next to the program; the SD card on the board); the changes made on
//                Machine Config are written as text lines to changes.log in it (DATE TIME: what: old -> new (cashier))
//   (no key)         the app downloads from the server by itself every minute: fixed, so that the terminal sees the web app's changes within a minute
//   korean           = true (default) | false: the Korean input method (the Right Alt key or Ctrl+Space switches it on at the keyboard, see KoreanInput)
//   printWidthDots   = 576 (80 mm paper) or 640: the printable width in dots
//   printBarcode     = image (default: the barcode is drawn as a bitmap, every printer prints it) or printer (the printer's own Code 128 command)
//   logo             = the logo picture (default: logo.png next to the program); ticketFont = a font file for the ticket text
//   printEnd         = cut | label | none: what the printer does after a ticket
//   ticketTitleBitmap, ticketTitleBitmap_<kind>, ticketFooterBitmap = the title and the footer as black-and-white bitmap files (400 x 48 and 400 x 32) (default: the ones in the program, :/ticket/title.bmp and :/ticket/footer.bmp)
//   ticketTitle_sale, ticketTitle_self, ticketTitle_other, ticketTitle_ticket_loan, ticketTitle_remainder = the long title of each ticket
//   setSystemClock   = true | false  set the board's clock from the server's, in UTC, and write it to the RTC chip (needs root; default true for a Linux
//                                    program that runs as root, else false: only warn)
//   supplierScannerName = the exact name of the supplier's barcode scanner in /proc/bus/input/devices (default SCANNER SCANNER; empty: none)
//   supplierScannerPhys = a part of its USB port (the Phys line, for example usb-s5p-ehci-1.1), to tell it from a scanner of the same name; default empty
//   themeFile        = a theme.qss on disk to use instead of the built-in one; it is read again whenever it is saved (to try a new look without a build)
//   boards           = true | false  listen for the RS485 converter of the nozzle boards and read the supplier scanner (default true; false for trying screens on a PC)
// Everything else (server address, device, ...) lives in the database (task 4.2).
class AppConfig
{
public:
    AppConfig();

    QString language() const { return language_; }
    bool fullscreen() const { return fullscreen_; }
    QSize windowSize() const { return windowSize_; }
    QString fontDir() const { return fontDir_; }
    QString themeFile() const { return themeFile_; }
    void setThemeFile(const QString &path) { themeFile_ = path; }
    QString dataDir() const { return dataDir_; }
    QString deviceFile() const { return deviceFile_; } // empty: the default place
    QString changeLogFile() const { return changeLogFile_; } // the text file the Machine Config changes are written to (on the SD card)
    void setChangeLogFile(const QString &path) { changeLogFile_ = path; }
    QString ticketFont() const { return ticketFont_; } // a font file for the ticket text, or empty
    int downloadMinutes() const { return downloadMinutes_; }
    bool setSystemClock() const { return setSystemClock_; }
    bool boardLink() const { return boardLink_; }
    bool koreanInput() const { return koreanInput_; }
    QString supplierScannerName() const { return supplierScannerName_; }
    QString supplierScannerPhys() const { return supplierScannerPhys_; }
    void setBoardLink(bool on) { boardLink_ = on; }
    void setKoreanInput(bool on) { koreanInput_ = on; }
    PrintOptions printOptions() const { return printOptions_; }

    static QString normalizeLanguage(const QString &value);
    static bool isSupportedLanguage(const QString &value);
    static QString languageName(const QString &language); // in its own language, for display

private:
    QString language_;
    bool fullscreen_;
    QSize windowSize_;
    QString fontDir_;
    QString themeFile_;
    QString dataDir_;
    QString deviceFile_;
    QString ticketFont_;
    QString changeLogFile_;
    int downloadMinutes_;
    bool setSystemClock_;
    bool boardLink_;
    bool koreanInput_;
    QString supplierScannerName_;
    QString supplierScannerPhys_;
    PrintOptions printOptions_;
};

#endif
