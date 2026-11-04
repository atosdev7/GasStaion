// The screen-driving generator of the test plan (TEST_PLAN.md 0.4 and part 3). It opens the real Smart210 window (MainWindow, with the real pages) and works
// at it like a cashier: types into the fields (key by key, so the fields see what a keyboard gives them), picks from the boxes, presses the buttons. Nothing
// is written into the database by the program itself. The web app is the real one (http://dev.rrdgasstaion.com/ by default), reached through the
// terminal's own download and upload.
//
//   guiday.exe --data=<folder of the terminal> [--server=dev.rrdgasstaion.com] [--port=80] [--user="Cashier One"] [--password=secret1]
//              [--spot=N] [--seed=N] [--upload] [--first-run-only]
//
//   --data       the folder that holds the terminal's smart210.db (made if it is not there); --device=<file> names another settings file than the usual one
//   --spot       how many spot sales to make at the Spot sale screen (60% diesel, 40% gasoline, 1 to 40 kg; a few own uses, gifts and loans)
//   --upload     at the end open Review and press Upload data
//   --first-run-only  stop after the first run and the login
//
// The first run is what a new terminal does: the server address page, the test of the connection, the first download of the web app's data, the login page.

#include "AppConfig.h"
#include "Database.h"
#include "MainWindow.h"
#include "Migrator.h"
#include "Settings.h"

#include <QAbstractButton>
#include <QApplication>
#include <QComboBox>
#include <QDateTime>
#include <QDir>
#include <QElapsedTimer>
#include <QLabel>
#include <QLineEdit>
#include <QPushButton>
#include <QSqlDatabase>
#include <QSqlQuery>
#include <QTest>
#include <QTextStream>

#include <functional>

static QTextStream out(stdout);
static int checks = 0;
static int fails = 0;

static void ok(bool good, const QString &text)
{
    ++checks;
    if (!good) {
        ++fails;
    }
    out << (good ? "  ok    " : "  FAIL  ") << text << "\n";
    out.flush();
}

// a widget by name that is on the screen now (the same form may sit on two pages)
template <class T>
static T *shown(QWidget &window, const char *name)
{
    foreach (T *w, window.findChildren<T *>(name)) {
        if (w->isVisibleTo(&window)) {
            return w;
        }
    }

    return 0;
}

static bool waitUntil(const std::function<bool()> &done, int timeoutMs, int stepMs = 25)
{
    QElapsedTimer timer;
    timer.start();
    while (!done()) {
        if (timer.elapsed() > timeoutMs) {
            return false;
        }
        QTest::qWait(stepMs);
    }

    return true;
}

static void press(QAbstractButton *button)
{
    QTest::mouseClick(button, Qt::LeftButton);
}

// types into a field as a keyboard does: the field is emptied first, then the keys come one by one
static void typeInto(QLineEdit *field, const QString &text)
{
    field->setFocus();
    field->selectAll();
    QTest::keyClick(field, Qt::Key_Delete);
    QTest::keyClicks(field, text);
}

static bool pick(QComboBox *combo, const QString &textPart)
{
    for (int i = 0; i < combo->count(); ++i) {
        if (combo->itemText(i).contains(textPart, Qt::CaseInsensitive)) {
            combo->setCurrentIndex(i);

            return true;
        }
    }

    return false;
}

static QString arg(const QStringList &args, const QString &name, const QString &fallback = QString())
{
    foreach (const QString &a, args) {
        if (a.startsWith("--" + name + "=")) {
            return a.mid(name.size() + 3);
        }
    }

    return fallback;
}

// a fixed sequence of numbers, so that a run can be made again
static unsigned long long seedState = 1;
static int next(int below)
{
    seedState = seedState * 6364136223846793005ULL + 1442695040888963407ULL;

    return int((seedState >> 33) % (unsigned long long)below);
}

static qlonglong count(QSqlDatabase db, const QString &sql)
{
    QSqlQuery q(db);
    q.exec(sql);

    return q.next() ? q.value(0).toLongLong() : -1;
}

int main(int argc, char *argv[])
{
    QApplication app(argc, argv);
    app.setApplicationName("smart210");
    const QStringList args = app.arguments();

    const QString data = arg(args, "data");
    if (data.isEmpty()) {
        out << "usage: guiday --data=<folder of the terminal> [--spot=N] [--upload] (see guiday.cpp)\n";

        return 2;
    }
    QDir().mkpath(data);
    seedState = arg(args, "seed", "1").toULongLong();

    if (!arg(args, "device").isEmpty()) {
        Settings::setDeviceFile(arg(args, "device"));   // otherwise the terminal's usual settings file (the one the real program uses)
    }
    QString error;
    if (!Database::open(data + "/smart210.db", Database::DefaultConnection, &error)
        || !Migrator::apply(QSqlDatabase::database(Database::DefaultConnection), Migrator::bundled(), &error)) {
        out << "database: " << error << "\n";

        return 2;
    }
    QSqlDatabase db = QSqlDatabase::database(Database::DefaultConnection);

    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(data + "/changes.log");
    MainWindow window(config, QString(), "db", db);
    window.resize(1280, 720);
    window.show();
    QTest::qWaitForWindowExposed(&window);
    out << "the terminal window is open (" << data << ")\n";

    // ---- the first run ----
    out << "\nfirst run\n";
    if (window.currentPage() == "setup") {
        QLineEdit *host = shown<QLineEdit>(window, "serverHost");
        QLineEdit *port = shown<QLineEdit>(window, "serverPort");
        QPushButton *test = shown<QPushButton>(window, "testConnectionButton");
        ok(host && port && test, "the server address page is open");
        if (!host || !port || !test) {
            return 1;
        }
        typeInto(host, arg(args, "server", "dev.rrdgasstaion.com"));
        typeInto(port, arg(args, "port", "80"));
        press(test);
        ok(waitUntil([&]() { return window.currentPage() != "setup"; }, 30000), "the connection test passes and the first download starts");
    } else {
        out << "  --    the terminal is already set up (" << window.currentPage() << ")\n";
    }
    if (window.currentPage() == "gate") {
        QLabel *status = shown<QLabel>(window, "firstSyncStatus");
        QString last;
        const bool done = waitUntil([&]() {
            if (status && status->text() != last) {
                last = status->text();
                out << "        " << last << "\n";
                out.flush();
            }

            return window.currentPage() != "gate";
        }, 3600000, 200);
        ok(done && window.currentPage() == "login", "the first download finishes and the login page opens");
    }
    ok(count(db, "SELECT COUNT(*) FROM tickets") > 40000, "the terminal holds the tickets of the web app (" + QString::number(count(db, "SELECT COUNT(*) FROM tickets")) + ")");
    ok(count(db, "SELECT COUNT(*) FROM users WHERE role = 'cashier'") >= 2, "the cashiers came down with the download");

    // ---- login ----
    out << "\nlogin\n";
    if (window.currentPage() == "login") {
        QComboBox *cashier = shown<QComboBox>(window, "cashierCombo");
        QLineEdit *password = shown<QLineEdit>(window, "password");
        QPushButton *login = shown<QPushButton>(window, "loginButton");
        ok(cashier && password && login && pick(cashier, arg(args, "user", "Cashier One")), "the cashier is picked in the box");
        if (!cashier || !password || !login) {
            return 1;
        }
        typeInto(password, arg(args, "password", "secret1"));
        press(login);
        ok(waitUntil([&]() { return window.currentPage() != "login"; }, 20000), "the password is accepted and the Sales screen opens");
    }
    if (args.contains("--first-run-only")) {
        out << "\n" << checks << " checks, " << fails << " failed\n";

        return fails ? 1 : 0;
    }

    // ---- spot sales at the Spot sale screen ----
    const int spot = arg(args, "spot", "0").toInt();
    if (spot > 0) {
        out << "\nspot sales\n";
        press(shown<QPushButton>(window, "navSales"));
        QTest::qWait(300);
        ok(window.currentPage() == "spot", "the Sales screen shows the Spot sale tab");
        QComboBox *usage = shown<QComboBox>(window, "usageCombo");
        QComboBox *fuel = shown<QComboBox>(window, "fuelCombo");
        QComboBox *customer = shown<QComboBox>(window, "customerCombo");
        QLineEdit *kg = shown<QLineEdit>(window, "kgField");
        QLineEdit *comment = shown<QLineEdit>(window, "commentField");
        QPushButton *save = shown<QPushButton>(window, "saveSaleButton");
        QLabel *saleStatus = shown<QLabel>(window, "saleStatus");
        if (!usage || !fuel || !customer || !kg || !comment || !save || !saleStatus) {
            ok(false, "the fields of the Spot sale tab are on the screen");

            return 1;
        }
        const qlonglong before = count(db, "SELECT COUNT(*) FROM sales WHERE sale_type = 'spot'");
        int saved = 0;
        int loans = 0;
        QStringList people;
        QSqlQuery q(db);
        q.exec("SELECT name FROM customers WHERE deleted = 0 AND is_self = 0 ORDER BY name");
        while (q.next()) {
            people << q.value(0).toString();
        }
        for (int i = 0; i < spot; ++i) {
            const int which = next(100);
            const char *use = which < 4 ? "Self use" : (which < 7 ? "Other" : (which < 11 ? "Ticket loan" : "Sale"));
            pick(usage, use);
            pick(fuel, next(10) < 6 ? "Diesel" : "Gasoline");
            if (QString(use) == "Ticket loan") {
                pick(customer, people.at(next(people.size())));
                ++loans;
            } else {
                pick(customer, "Individual");
            }
            typeInto(kg, QString::number(1 + next(40)) + "." + QString("%1").arg(next(100), 2, 10, QChar('0')));
            if (next(10) == 0) {
                typeInto(comment, "spot sale no. " + QString::number(i + 1));
            }
            press(save);
            if (saleStatus->text().startsWith("Saved")) {
                ++saved;
            } else {
                out << "        sale " << i + 1 << ": " << saleStatus->text() << "\n";
            }
            QTest::qWait(5);
        }
        ok(saved == spot, QString::number(saved) + " of " + QString::number(spot) + " spot sales saved (" + QString::number(loans) + " on a ticket loan)");
        ok(count(db, "SELECT COUNT(*) FROM sales WHERE sale_type = 'spot'") - before == spot, "the database holds " + QString::number(spot) + " new spot sales");
    }

    // ---- check and upload ----
    if (args.contains("--upload")) {
        out << "\nupload\n";
        press(shown<QPushButton>(window, "navReview"));
        QTest::qWait(300);
        QPushButton *upload = shown<QPushButton>(window, "uploadButton");
        ok(upload && window.currentPage() == "review", "the Review screen is open with the Upload data button");
        if (upload) {
            const qlonglong waiting = count(db, "SELECT COUNT(*) FROM sales WHERE synced = 0");
            press(upload);
            ok(waitUntil([&]() { return count(db, "SELECT COUNT(*) FROM sales WHERE synced = 0") == 0; }, 600000, 200),
               QString::number(waiting) + " sales waited and all of them were uploaded");
            QLabel *status = shown<QLabel>(window, "uploadStatus");
            if (status) {
                out << "        " << status->text() << "\n";
            }
        }
    }

    out << "\n" << checks << " checks, " << fails << " failed\n";
    out.flush();
    db.close();

    return fails ? 1 : 0;
}
