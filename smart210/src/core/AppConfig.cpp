#include "AppConfig.h"

#include <QCoreApplication>
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

    fullscreen_ = settings.value("fullscreen", true).toBool();
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
    setSystemClock_ = settings.value("setSystemClock", false).toBool();
    boardLink_ = settings.value("boards", true).toBool();
    hangulInput_ = settings.value("hangul", true).toBool();
    supplierScannerName_ = settings.value("supplierScannerName", "SCANNER SCANNER").toString().trimmed();
    supplierScannerPhys_ = settings.value("supplierScannerPhys").toString().trimmed();

    printOptions_.widthDots = qBound(256, settings.value("printWidthDots", 576).toInt(), 832);
    printOptions_.logoPath = settings.value("logo", dir + "/logo.png").toString();
    printOptions_.fontFamily = QString();
    printOptions_.endMode = settings.value("printEnd", "cut").toString().toLower();
    if (printOptions_.endMode != "label" && printOptions_.endMode != "none") {
        printOptions_.endMode = "cut";
    }
    const char *kinds[] = {"sale", "self", "other", "ticket_loan", "remainder"};
    for (int i = 0; i < 5; ++i) {
        const QString title = settings.value(QString("ticketTitle_") + kinds[i]).toString();
        if (!title.isEmpty()) {
            printOptions_.titles[kinds[i]] = title;
        }
    }
    ticketFont_ = settings.value("ticketFont").toString();
}

// "zh-cn", "zh_CN" and "ZH-CN" all mean zh_CN.
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
    return value == "en" || value == "zh_CN" || value == "zh_TW";
}

QString AppConfig::languageName(const QString &language)
{
    if (language == "zh_CN") {
        return QString::fromUtf8("简体中文");
    }
    if (language == "zh_TW") {
        return QString::fromUtf8("繁體中文");
    }

    return "English";
}
