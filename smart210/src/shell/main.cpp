#include <QApplication>
#include <QDebug>
#include <QMessageBox>
#include <QProcess>

#include "AppConfig.h"
#include "Clock.h"
#include "Database.h"
#include "Fonts.h"
#include "MainWindow.h"
#include "Migrator.h"
#include "Settings.h"
#include "Theme.h"
#include "Translations.h"

int main(int argc, char *argv[])
{
    QApplication app(argc, argv);
    app.setApplicationName("smart210");
    app.setApplicationVersion(APP_VERSION);

    const AppConfig config;

    if (!Translations::install(&app, config.language())) {
        qWarning() << "No translation for" << config.language();
    }

    const QString fontFamily = Fonts::install(config.fontDir(), Theme::BodyPx);
    if (fontFamily.isEmpty()) {
        qWarning() << "No Korean font found in" << config.fontDir();
    }

    if (!config.deviceFile().isEmpty()) {
        Settings::setDeviceFile(config.deviceFile());
    }

    QString error;
    const QString databaseFile = config.dataDir() + "/smart210.db";
    if (!Database::open(databaseFile, Database::DefaultConnection, &error)
        || !Migrator::apply(QSqlDatabase::database(Database::DefaultConnection), Migrator::bundled(), &error)) {
        qCritical() << "Database" << databaseFile << ":" << error;
        QMessageBox::critical(0, QObject::tr("Gas Station Cashier"),
                              QObject::tr("The database cannot be opened.\n%1\n%2").arg(databaseFile).arg(error));

        return 1;
    }
    QSqlDatabase db = QSqlDatabase::database(Database::DefaultConnection);
    Settings clockSettings(db);
    Clock::applyStoredTimeZone(clockSettings); // the station's time zone of the last download, before any screen shows a time

    MainWindow window(config, fontFamily,
                      QObject::tr("Database: version %1, journal %2")
                          .arg(Migrator::version(db)).arg(Database::pragma(db, "journal_mode")),
                      db);
    if (config.fullscreen()) {
        window.showFullScreen();
    } else {
        window.resize(config.windowSize());
        window.show();
    }

    // Clean Data / Clear Config: the program starts again (after the database is closed, so that nothing is held open)
    QObject::connect(&window, SIGNAL(restartRequested()), &app, SLOT(quit()));

    const int result = app.exec();
    Database::close(Database::DefaultConnection);
    if (window.restartWanted()) {
        QProcess::startDetached(QCoreApplication::applicationFilePath(), QCoreApplication::arguments().mid(1));
    }

    return result;
}
