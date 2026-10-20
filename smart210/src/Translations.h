#ifndef TRANSLATIONS_H
#define TRANSLATIONS_H

#include <QString>

class QCoreApplication;

// Loads the translation of the configured language from the resources: one file per group of screens
// (i18n/smart210_<page>_<language>.qm). The program's own strings are English, so a missing file just leaves English.
class Translations
{
public:
    // True when every file of the language was found and installed.
    static bool install(QCoreApplication *app, const QString &language);
};

#endif
