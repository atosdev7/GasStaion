#include "AppConfig.h"

#include "Clock.h"

#include <QCoreApplication>
#include <QFile>
#include <QSettings>
#include <QStringList>

AppConfig::AppConfig()
{
    const QString dir = QCoreApplication::applicationDirPath();
    QSettings settings(dir + "/config.ini", QSettings::IniFormat);

    language_ = normalizeLanguage(settings.value("language", "en").toString());
    if (!isSupportedLanguage(language_)) {
        language_ = "en";
    }

    // The board shows the program in full screen; on a Windows PC it is a window (fullscreen = true in config.ini still gives full screen there).
#ifdef Q_OS_WIN
    const bool defaultFullscreen = false;
#else
    const bool defaultFullscreen = true;
#endif
    fullscreen_ = settings.value("fullscreen", defaultFullscreen).toBool();
    windowSize_ = QSize(settings.value("width", 1280).toInt(), settings.value("height", 720).toInt());
    if (windowSize_.width() < 320 || windowSize_.height() < 240) {
        windowSize_ = QSize(1280, 720);
    }
    fontDir_ = settings.value("fontDir", dir + "/fonts").toString();
    themeFile_ = settings.value("themeFile").toString();
    dataDir_ = settings.value("dataDir", dir + "/data").toString();
    deviceFile_ = settings.value("deviceFile").toString();
    changeLogFile_ = dataDir_ + "/changes.log";
    downloadMinutes_ = 1; // the terminal downloads every minute (fixed)
    setSystemClock_ = settings.value("setSystemClock", Clock::mayDefaultToSettingTheClock()).toBool();
    boardLink_ = settings.value("boards", true).toBool();
    koreanInput_ = settings.value("korean", true).toBool();
    supplierScannerName_ = settings.value("supplierScannerName", "SCANNER SCANNER").toString().trimmed();
    supplierScannerPhys_ = settings.value("supplierScannerPhys").toString().trimmed();

    printOptions_.widthDots = qBound(256, settings.value("printWidthDots", 576).toInt(), 832);
    printOptions_.logoPath = settings.value("logo", dir + "/logo.png").toString();
    // the title and the footer bitmaps are part of the program (resources/ticket, in resources.qrc); config.ini can name other files
    printOptions_.titleBitmapPath = settings.value("ticketTitleBitmap", ":/ticket/title.bmp").toString();
    printOptions_.footerBitmapPath = settings.value("ticketFooterBitmap", ":/ticket/footer.bmp").toString();
    printOptions_.fontFamily = QString();
    printOptions_.barcodeMode = settings.value("printBarcode", "image").toString().toLower() == "printer" ? "printer" : "image";
    printOptions_.endMode = settings.value("printEnd", "cut").toString().toLower();
    if (printOptions_.endMode != "label" && printOptions_.endMode != "none") {
        printOptions_.endMode = "cut";
    }
    const char *kinds[] = {"sale", "self", "other", "ticket_loan", "remainder", "card"};
    for (int i = 0; i < 6; ++i) {
        const QString title = settings.value(QString("ticketTitle_") + kinds[i]).toString();
        if (!title.isEmpty()) {
            printOptions_.titles[kinds[i]] = title;
        }
        // a title of its own for a kind of ticket: resources/ticket/title_<kind>.bmp, when it is in resources.qrc, or the file config.ini names
        const QString embedded = QString(":/ticket/title_%1.bmp").arg(kinds[i]);
        const QString bitmap = settings.value(QString("ticketTitleBitmap_") + kinds[i], QFile::exists(embedded) ? embedded : QString()).toString();
        if (!bitmap.isEmpty()) {
            printOptions_.titleBitmaps[kinds[i]] = bitmap;
        }
    }
    ticketFont_ = settings.value("ticketFont").toString();
}

// "en-us", "en_US" and "EN-US" all mean en_US.
QString AppConfig::normalizeLanguage(const QString &value)
{
    QStringList parts = value.trimmed().replace('-', '_').split('_');
    if (parts.size() == 2) {
        return parts.at(0).toLower() + "_" + parts.at(1).toUpper();
    }

    return value.trimmed().toLower();
}

bool AppConfig::isSupportedLanguage(const QString &value)
{
    return value == "en";
}

QString AppConfig::languageName(const QString &)
{
    return "English";
}
