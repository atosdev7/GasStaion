#include "Fonts.h"

#include <QApplication>
#include <QDir>
#include <QFont>
#include <QFontDatabase>
#include <QStringList>

QString Fonts::install(const QString &dir, int pixelSize)
{
    QStringList filters;
    filters << "*.ttf" << "*.otf" << "*.ttc";

    QDir fonts(dir);
    QStringList families;
    foreach (const QString &file, fonts.entryList(filters, QDir::Files, QDir::Name)) {
        const int id = QFontDatabase::addApplicationFont(fonts.filePath(file));
        if (id >= 0) {
            families << QFontDatabase::applicationFontFamilies(id);
        }
    }

    foreach (const QString &family, families) {
        QFontDatabase database;
        const QList<QFontDatabase::WritingSystem> systems = database.writingSystems(family);
        if (systems.contains(QFontDatabase::SimplifiedChinese) || systems.contains(QFontDatabase::TraditionalChinese)) {
            QFont font(family);
            font.setPixelSize(pixelSize);
            QApplication::setFont(font);

            return family;
        }
    }

    return QString();
}
