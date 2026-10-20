#include "Translations.h"

#include <QCoreApplication>
#include <QTranslator>

namespace {

// The translation files, one per group of screens: i18n/smart210_<page>_<language>.qm. Keep this list, resources.qrc and
// the i18n/<page>.lst files in step.
const char *const pages[] = {
    "shell", "login", "sales", "supplies", "remainders", "cards", "dispensers", "ticket_debt", "review", "settings", "sync"
};

const int pageCount = sizeof(pages) / sizeof(pages[0]);

}

bool Translations::install(QCoreApplication *app, const QString &language)
{
    bool all = true;

    for (int i = 0; i < pageCount; ++i) {
        QTranslator *translator = new QTranslator(app);
        if (translator->load(":/i18n/smart210_" + QString::fromLatin1(pages[i]) + "_" + language + ".qm")
                && app->installTranslator(translator)) {
            continue;
        }

        delete translator;
        all = false;
    }

    return all;
}
