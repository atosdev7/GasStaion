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
//   --nozzles    open Machine Config (Settings on the login page, the PIN) and add nozzles 1 to 5 (1, 2 gasoline on T1; 3, 4 diesel and 5 diesel manual on T2), Update and Read each
//   --pin        the PIN of Machine Config (default 4821); --converter-port the port the simulated boards connect to (default 4001)
//   --supplies=N   N supplies at the nozzles 1 to 4 (auto): the supplier's tickets are sold at the Ticket sale screen, the nozzle is chosen with the select key of the
//                  board, the tickets are typed at the Supply screen, the gun is lifted on the simulated board and the fill runs (most to the preset, some stopped
//                  short: a remainder ticket, some using a remainder ticket)
//   --manual-supplies=M  M supplies at the manual nozzle 5: the supplier types the amount at the board, the cashier types the reading and the tickets at Manual input
//   --ticket-sales=N  N ticket sales at the Ticket sale screen to customers who keep the tickets (the supplier's own purchases are made as the supplies need them)
//   --cards=N    N card sales (top-ups) at the Cards screen: the card number is typed (a new card is registered with a name, phone and PIN first), the weight and the
//                price are typed and OK is pressed in the question; most are followed by a card fill (kg and PIN typed) and the supply of the card ticket at a nozzle
//   --first-run-only  stop after the first run and the login
//
// The first run is what a new terminal does: the server address page, the test of the connection, the first download of the web app's data, the login page.

#include "AppConfig.h"
#include "Barcode.h"
#include "Cards.h"
#include "FakeBoards.h"
#include "Database.h"
#include "MachineConfig.h"
#include "MainWindow.h"
#include "Migrator.h"
#include "Settings.h"
#include "SupplyQueue.h"
#include "Uploader.h"

#include <QAbstractButton>
#include <QApplication>
#include <QComboBox>
#include <QDateTime>
#include <QDir>
#include <QElapsedTimer>
#include <QLabel>
#include <QLineEdit>
#include <QMessageBox>
#include <QTimer>
#include <QPushButton>
#include <QSqlDatabase>
#include <QSqlQuery>
#include <QTabBar>
#include <QTabWidget>
#include <QTableWidget>
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
            emit combo->activated(i);   // what a click on the item gives

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

// Machine Config, as the technician does it: from the login screen (Settings), the PIN, the Nozzle tab; each nozzle is added, its config typed and sent to its
// board (Update: the board must answer and keep it) and read back (Read).
static bool machineConfigStage(MainWindow &window, QSqlDatabase db, FakeBoards &boards, const QString &pin)
{
    out << "\nMachine Config\n";
    if (window.currentPage() != "login") {
        press(shown<QPushButton>(window, "navSettings"));
        QTest::qWait(300);
        QPushButton *logout = shown<QPushButton>(window, "logoutButton");
        ok(logout != 0, "the cashier opens Settings and finds Logout");
        if (!logout) {
            return false;
        }
        press(logout);
        ok(waitUntil([&]() { return window.currentPage() == "login"; }, 10000), "after Logout the login page is open");
    }
    press(shown<QPushButton>(window, "loginSettingsButton"));
    ok(waitUntil([&]() { return window.currentPage() == "pin"; }, 10000), "the Settings button of the login page asks for the PIN");
    QLineEdit *pinField = shown<QLineEdit>(window, "pinField");
    if (!pinField) {
        return false;
    }
    typeInto(pinField, pin);
    press(shown<QPushButton>(window, "pinOkButton"));
    ok(waitUntil([&]() { return window.currentPage() == "machine"; }, 10000), "the PIN is accepted and Machine Config opens");
    if (window.currentPage() != "machine") {
        return false;
    }
    QTabWidget *tabs = shown<QTabWidget>(window, "machineTabs");
    QWidget *nozzleTab = window.findChild<QWidget *>("machineNozzleTab");
    if (tabs && nozzleTab) {
        tabs->setCurrentWidget(nozzleTab);   // the technician clicks the Nozzle tab
    }
    QLabel *status = shown<QLabel>(window, "machineStatus");
    QTableWidget *table = shown<QTableWidget>(window, "nozzleTable");
    ok(status && table, "the Nozzle tab is open");
    if (!status || !table) {
        return false;
    }

    // the boards: five, straight from the factory, behind the converter that connects to this terminal
    ok(waitUntil([&]() { return boards.isConnected(); }, 20000), "the converter with the five boards connects to the terminal");

    const struct { int number; const char *fuel; const char *tank; bool manual; } plan[] = {
        {1, "Gasoline", "T1", false}, {2, "Gasoline", "T1", false}, {3, "Diesel", "T2", false}, {4, "Diesel", "T2", false}, {5, "Diesel", "T2", true}
    };
    for (int i = 0; i < 5; ++i) {
        QComboBox *number = shown<QComboBox>(window, "addNozzleNumber");
        QComboBox *fuel = shown<QComboBox>(window, "addNozzleFuel");
        QComboBox *tank = shown<QComboBox>(window, "addNozzleTank");
        QPushButton *add = shown<QPushButton>(window, "addNozzleButton");
        if (!number || !fuel || !tank || !add) {
            ok(false, "the Add a nozzle form is on the screen");

            return false;
        }
        if (number->findText(QString::number(plan[i].number)) < 0) {
            out << "  --    nozzle " << plan[i].number << " is already added\n";

            continue;
        }
        pick(number, QString::number(plan[i].number));
        pick(fuel, plan[i].fuel);
        pick(tank, plan[i].tank);
        status->setText(QString());
        press(add);
        ok(status->text().contains("Nozzle " + QString::number(plan[i].number) + " was added"), "nozzle " + QString::number(plan[i].number) + " added: " + plan[i].fuel + " on " + plan[i].tank + " (" + status->text() + ")");
    }
    ok(table->rowCount() == 5, "the nozzle table lists five nozzles");
    for (int i = 0; i < 5; ++i) {
        table->selectRow(i);   // a click on the row of the nozzle
        QComboBox *mode = shown<QComboBox>(window, "nozzleMode");
        QComboBox *fuel = shown<QComboBox>(window, "nozzleFuel");
        QLineEdit *density = shown<QLineEdit>(window, "nozzleDensity");
        QLineEdit *pulses = shown<QLineEdit>(window, "nozzlePulses");
        QPushButton *update = shown<QPushButton>(window, "updateNozzleButton");
        QPushButton *read = shown<QPushButton>(window, "readNozzlesButton");
        if (!mode || !fuel || !density || !pulses || !update || !read) {
            ok(false, "the nozzle form is on the screen");

            return false;
        }
        pick(mode, plan[i].manual ? "Manual" : "Auto");
        pick(fuel, plan[i].fuel);
        typeInto(density, QString(plan[i].fuel) == "Gasoline" ? "0.7400" : "0.8300");
        typeInto(pulses, "1000");
        press(update);
        const bool verified = waitUntil([&]() { return status->text().contains("Verified"); }, 20000);
        ok(verified, "nozzle " + QString::number(plan[i].number) + ": Update is sent and the board keeps it (" + status->text() + ")");
        status->setText(QString());
        QTest::qWait(500);
        press(read);
        const bool readBack = waitUntil([&]() { return status->text().contains("was read from its board and saved"); }, 20000);
        ok(readBack, "nozzle " + QString::number(plan[i].number) + ": Read brings the same config back (" + status->text() + ")");
        const BoardProtocol::BoardConfig held = boards.board(plan[i].number).config;
        ok(held.mode == (plan[i].manual ? BoardProtocol::ModeManual : BoardProtocol::ModeAuto) && held.fuelType == (QString(plan[i].fuel) == "Gasoline" ? "A" : "D") && held.pulsesX100 == 100000,
           "board " + QString::number(plan[i].number) + " holds " + (plan[i].manual ? "manual" : "auto") + " " + held.fuelType + ", " + QString::number(held.pulsesX100 / 100) + " counts per litre");
    }
    ok(count(db, "SELECT COUNT(*) FROM nozzles WHERE deleted = 0") == 5, "five nozzles are saved on the terminal");
    ok(count(db, "SELECT COUNT(*) FROM nozzles WHERE deleted = 0 AND mode = " + QString::number(BoardProtocol::ModeManual)) == 1, "one of them is manual");
    for (int i = 0; i < 5; ++i) {
        const QString want = plan[i].manual ? "Not polled" : "Online";
        ok(waitUntil([&]() { return table->item(i, 2) && table->item(i, 2)->text() == want; }, 20000),
           "nozzle " + QString::number(i + 1) + " shows " + want + (table->item(i, 2) ? " (" + table->item(i, 2)->text() + ")" : QString()));
    }

    press(shown<QPushButton>(window, "machineBackButton"));
    ok(waitUntil([&]() { return window.currentPage() == "login"; }, 10000), "Back leaves Machine Config for the login page");

    return true;
}

// ---- supplies ----

// the screen of a sidebar button, with the tab named: the button is pressed and the tab is clicked
static bool openTab(MainWindow &window, const char *nav, const QString &tabText)
{
    QPushButton *button = shown<QPushButton>(window, nav);
    if (!button) {
        return false;
    }
    press(button);
    QTest::qWait(150);
    foreach (QTabWidget *tabs, window.findChildren<QTabWidget *>()) {
        if (!tabs->isVisibleTo(&window)) {
            continue;
        }
        for (int i = 0; i < tabs->count(); ++i) {
            if (tabs->tabText(i) == tabText) {
                if (tabs->currentIndex() != i) {
                    QTest::mouseClick(tabs->tabBar(), Qt::LeftButton, Qt::NoModifier, tabs->tabBar()->tabRect(i).center());
                    QTest::qWait(150);
                }

                return tabs->currentIndex() == i;
            }
        }
    }

    return false;
}

// the tickets that are sold and not used yet (what the supplier holds), of a fuel and a size, lowest numbers first
static QStringList heldTickets(QSqlDatabase db, const QString &fuel, int spec, int howMany)
{
    QStringList list;
    QSqlQuery q(db);
    q.prepare("SELECT barcode FROM tickets WHERE status = 1 AND fuel_type = ? AND spec = ? ORDER BY barcode LIMIT ?");
    q.addBindValue(fuel);
    q.addBindValue(spec);
    q.addBindValue(howMany);
    if (q.exec()) {
        while (q.next()) {
            list << Barcode::fromKey(q.value(0).toLongLong());
        }
    }

    return list;
}

static int specKg(int spec)
{
    static const int kg[] = {5, 10, 15, 50};

    return kg[spec];
}

// the customer buys tickets at the Ticket sale screen: customer, fuel, the size, the count, Add, Save
static bool sellTicketsAtScreen(MainWindow &window, QSqlDatabase db, const QString &customerName, const QString &fuelName, int spec, int howMany)
{
    if (!openTab(window, "navSales", "Ticket sale")) {
        return false;
    }
    QComboBox *customer = shown<QComboBox>(window, "tsCustomerCombo");
    QComboBox *fuel = shown<QComboBox>(window, "tsFuelCombo");
    QComboBox *size = shown<QComboBox>(window, "tsSpecCombo");
    QLineEdit *countField = shown<QLineEdit>(window, "tsCountField");
    QPushButton *add = shown<QPushButton>(window, "tsAddButton");
    QPushButton *save = shown<QPushButton>(window, "saveTicketSaleButton");
    if (!customer || !fuel || !size || !countField || !add || !save) {
        return false;
    }
    const qlonglong before = count(db, "SELECT COUNT(*) FROM sales WHERE sale_type = 'ticket'");
    pick(customer, customerName);
    pick(fuel, fuelName);
    if (!pick(size, QString::number(specKg(spec)) + ".00 kg")) {
        return false;
    }
    typeInto(countField, QString::number(howMany));
    press(add);
    press(save);

    return waitUntil([&]() { return count(db, "SELECT COUNT(*) FROM sales WHERE sale_type = 'ticket'") > before; }, 15000);
}

// one supply at an auto nozzle, as the supplier does it
struct SupplyResult {
    SupplyResult() : done(false), remainder(false), grams(0) {}
    bool done;
    bool remainder;
    int grams;
    QString why;
};

static SupplyResult autoSupply(MainWindow &window, QSqlDatabase db, FakeBoards &boards, int nozzle, const QStringList &tickets, int presetKgX100, int stopPercent)
{
    SupplyResult r;
    if (!openTab(window, "navSupplies", "Sessions")) {
        r.why = "the Supplies screen did not open";

        return r;
    }
    if (boards.board(nozzle).state != BoardProtocol::StateIdle || SupplyQueue::totalX100(db, nozzle) > 0) {
        QPushButton *clear = shown<QPushButton>(window, "supplyClearButton");
        if (clear) {
            press(clear);   // a queue that is left from before is cleared
        }
    }
    if (!waitUntil([&]() { return boards.board(nozzle).state == BoardProtocol::StateIdle; }, 20000)) {
        r.why = "the nozzle is not idle";

        return r;
    }
    QTest::qWait(200);   // the terminal has polled the idle board
    boards.pressSelect(nozzle);   // the supplier presses the select key of the dispenser (the screen follows: a nozzle that is chosen already stays chosen)
    QLineEdit *field = shown<QLineEdit>(window, "supplyTicketField");
    QPushButton *addTicket = shown<QPushButton>(window, "supplyTicketButton");
    QTableWidget *queue = shown<QTableWidget>(window, "supplyQueueTable");
    QLabel *current = shown<QLabel>(window, "supplyCurrent");
    if (!field || !addTicket || !queue || !current) {
        r.why = "the ticket box is not on the screen";

        return r;
    }
    // the supplier looks at the screen: it names the nozzle that was chosen
    if (!waitUntil([&]() { return current->text().contains(QRegExp("\\b" + QString::number(nozzle) + "\\b")); }, 10000)) {
        r.why = "the screen does not show nozzle " + QString::number(nozzle) + " as chosen (it says: " + current->text() + ")";

        return r;
    }
    QTest::qWait(150);
    Q_UNUSED(queue);
    foreach (const QString &ticket, tickets) {
        const qlonglong before = SupplyQueue::totalX100(db, nozzle);
        typeInto(field, ticket);
        press(addTicket);
        if (!waitUntil([&]() { return SupplyQueue::totalX100(db, nozzle) > before; }, 5000)) {
            QLabel *message = shown<QLabel>(window, "supplyMessage");
            r.why = "ticket " + ticket + " was refused" + (message ? ": " + message->text() : QString());

            return r;
        }
    }
    if (!waitUntil([&]() { return boards.board(nozzle).state == BoardProtocol::StateArmed && boards.board(nozzle).presetG > 0; }, 10000)) {
        r.why = "the preset did not reach the board";

        return r;
    }
    const qlonglong supplies = count(db, "SELECT COUNT(*) FROM supplements");
    const qlonglong remainders = count(db, "SELECT COUNT(*) FROM remainders");
    boards.simulator().liftGun(nozzle);   // the supplier takes the gun up; the terminal sees it and starts the fill
    if (!waitUntil([&]() { return boards.board(nozzle).state == BoardProtocol::StateDispensing; }, 10000)) {
        r.why = "the fill did not start";

        return r;
    }
    QTest::qWait(150);
    const int preset = int(boards.board(nozzle).presetG);
    if (stopPercent > 0 && stopPercent < 100) {
        const int grams = preset * stopPercent / 100;
        boards.simulator().tick(grams * 1000 / qMax(1, boards.board(nozzle).flowGramsPerSecond));
        boards.simulator().stopFill(nozzle);
    } else {
        boards.simulator().tick(1800000);
    }
    if (!waitUntil([&]() { return count(db, "SELECT COUNT(*) FROM supplements") > supplies && boards.board(nozzle).state == BoardProtocol::StateIdle; }, 30000)) {
        r.why = "the result was not recorded";

        return r;
    }
    r.done = true;
    r.grams = int(boards.board(nozzle).dispensedG);
    r.remainder = count(db, "SELECT COUNT(*) FROM remainders") > remainders;
    Q_UNUSED(presetKgX100);

    return r;
}

static bool supplyStage(MainWindow &window, QSqlDatabase db, FakeBoards &boards, int supplies, int manualSupplies)
{
    out << "\nsupplies\n";
    // the boards keep their config in flash: the simulated ones are given back what Machine Config stored
    MachineConfig mc(db);
    foreach (const MachineConfig::Nozzle &nozzle, mc.nozzles()) {
        BoardProtocol::BoardConfig config;
        config.mode = nozzle.mode;
        config.fuelType = nozzle.fuelType;
        config.density = quint16(nozzle.density);
        config.pulsesX100 = quint32(nozzle.pulsesPerLiter);
        boards.board(nozzle.number).config = config;
        boards.board(nozzle.number).flowGramsPerSecond = 600 + next(800);
    }
    ok(waitUntil([&]() {
        for (int n = 1; n <= 4; ++n) {
            if (!boards.isConnected() || boards.board(n).config.pulsesX100 == 0) {
                return false;
            }
        }

        return true;
    }, 20000), "the simulated boards hold the config of the nozzles");
    QTest::qWait(1500);   // the terminal polls them

    QStringList people;
    QSqlQuery q(db);
    q.exec("SELECT name FROM customers WHERE deleted = 0 AND is_self = 0 ORDER BY name");
    while (q.next()) {
        people << q.value(0).toString();
    }

    // sizes and counts of the tickets of a supply
    const struct { int spec; int howMany; } shapes[] = {{1, 1}, {1, 1}, {1, 2}, {2, 1}, {2, 2}, {0, 2}, {0, 3}, {3, 1}, {1, 3}, {2, 1}};
    int done = 0;
    int short_ = 0;
    int reused = 0;
    int remainders = 0;
    int failed = 0;
    QElapsedTimer timer;
    timer.start();
    for (int i = 0; i < supplies; ++i) {
        const int nozzle = 1 + next(4);
        const QString fuelCode = nozzle <= 2 ? "A" : "D";
        const QString fuelName = nozzle <= 2 ? "Gasoline" : "Diesel";
        const int shape = next(10);
        const int spec = shapes[shape].spec;
        const int howMany = shapes[shape].howMany;

        QStringList tickets;
        // sometimes the supplier brings a remainder ticket of the last supply
        if (next(100) < 8) {
            QSqlQuery rq(db);
            rq.prepare("SELECT r.ticket_barcode FROM remainders r JOIN tickets t ON t.barcode = r.ticket_barcode WHERE t.status = 1 AND r.fuel_type = ? ORDER BY r.created_at LIMIT 1");
            rq.addBindValue(fuelCode);
            if (rq.exec() && rq.next()) {
                tickets << Barcode::fromKey(rq.value(0).toLongLong());
                ++reused;
            }
        }
        // the tickets are held, or bought at the Ticket sale screen
        QStringList held = heldTickets(db, fuelCode, spec, howMany);
        if (held.size() < howMany) {
            if (!sellTicketsAtScreen(window, db, people.isEmpty() ? "Individual" : people.at(next(people.size())), fuelName, spec, qMax(10, howMany))) {
                out << "  FAIL  supply " << i + 1 << ": the tickets could not be bought at the Ticket sale screen\n";
                ++failed;

                continue;
            }
            held = heldTickets(db, fuelCode, spec, howMany);
        }
        tickets += held;
        const int stop = next(100) < 12 ? 40 + next(55) : 0;
        const SupplyResult r = autoSupply(window, db, boards, nozzle, tickets, 0, stop);
        if (!r.done) {
            out << "  FAIL  supply " << i + 1 << " at nozzle " << nozzle << ": " << r.why << "\n";
            ++failed;

            continue;
        }
        ++done;
        short_ += stop > 0 ? 1 : 0;
        remainders += r.remainder ? 1 : 0;
        if ((i + 1) % 10 == 0 || i + 1 == supplies) {
            out << "        " << i + 1 << " supplies, " << QString::number(timer.elapsed() / 1000.0 / (i + 1), 'f', 1) << " s each\n";
            out.flush();
        }
    }
    if (supplies > 0) {
        ok(failed == 0 && done == supplies, QString::number(done) + " of " + QString::number(supplies) + " supplies recorded (" + QString::number(short_) + " stopped short, "
                                                + QString::number(remainders) + " remainder tickets made, " + QString::number(reused) + " remainder tickets used again)");
    }

    // the manual nozzle: the supplier types the amount at the board, fills; the cashier types the reading and the tickets at Manual input
    int manualDone = 0;
    for (int i = 0; i < manualSupplies; ++i) {
        const quint32 grams = quint32(10000 + next(30) * 1000);
        if (!waitUntil([&]() { return boards.board(5).state == BoardProtocol::StateIdle; }, 10000)) {
            ok(false, "manual nozzle 5 is idle");

            break;
        }
        boards.simulator().typeAmount(5, grams);
        boards.simulator().liftGun(5);
        QTest::qWait(300);
        boards.simulator().tick(1800000);
        waitUntil([&]() { return boards.board(5).state == BoardProtocol::StateIdle; }, 10000);
        const qlonglong dispensedG = boards.board(5).dispensedG;
        const qlonglong dispensedMl = boards.board(5).dispensedMl;
        // the reading from the dispenser's display, and tickets that cover it (15 kg tickets)
        const int needed = int((dispensedG + 14999) / 15000);
        QStringList held = heldTickets(db, "D", 2, needed);
        if (held.size() < needed) {
            sellTicketsAtScreen(window, db, "Individual", "Diesel", 2, qMax(10, needed));
            held = heldTickets(db, "D", 2, needed);
        }
        if (!openTab(window, "navSupplies", "Manual input") || held.size() < needed) {
            ok(false, "the Manual input screen opens and the tickets are held");

            break;
        }
        QComboBox *nozzle = shown<QComboBox>(window, "msNozzleCombo");
        QLineEdit *kg = shown<QLineEdit>(window, "msKgField");
        QLineEdit *liters = shown<QLineEdit>(window, "msLitersField");
        QLineEdit *preset = shown<QLineEdit>(window, "msPresetField");
        QLineEdit *barcode = shown<QLineEdit>(window, "msBarcode");
        QPushButton *addTicket = shown<QPushButton>(window, "msAddButton");
        QPushButton *save = shown<QPushButton>(window, "msSaveButton");
        if (!nozzle || !kg || !liters || !barcode || !addTicket || !save) {
            ok(false, "the fields of the Manual input screen are on the screen");

            break;
        }
        pick(nozzle, "5");
        typeInto(kg, QString::number(dispensedG / 1000.0, 'f', 2));
        typeInto(liters, QString::number(dispensedMl / 1000.0, 'f', 2));
        if (preset) {
            typeInto(preset, QString::number(grams / 1000.0, 'f', 2));
        }
        foreach (const QString &ticket, held) {
            typeInto(barcode, ticket);
            press(addTicket);
        }
        const qlonglong before = count(db, "SELECT COUNT(*) FROM supplements");
        press(save);
        if (waitUntil([&]() { return count(db, "SELECT COUNT(*) FROM supplements") > before; }, 10000)) {
            ++manualDone;
        } else {
            QLabel *status = shown<QLabel>(window, "msStatus");
            out << "  FAIL  manual supply " << i + 1 << ": " << (status ? status->text() : QString("not saved")) << "\n";
        }
    }
    if (manualSupplies > 0) {
        ok(manualDone == manualSupplies, QString::number(manualDone) + " of " + QString::number(manualSupplies) + " manual-nozzle supplies typed in at Manual input");
    }
    ok(count(db, "SELECT COUNT(*) FROM supplements WHERE synced = 0") >= done + manualDone, "the supplies wait for the upload");

    return failed == 0;
}

// ---- ticket sales ----

static bool ticketSalesStage(MainWindow &window, QSqlDatabase db, int sales)
{
    out << "\nticket sales\n";
    QStringList people;
    QSqlQuery q(db);
    q.exec("SELECT name FROM customers WHERE deleted = 0 ORDER BY is_self DESC, name");
    while (q.next()) {
        people << q.value(0).toString();
    }
    const qlonglong before = count(db, "SELECT COUNT(*) FROM sales WHERE sale_type = 'ticket'");
    int saved = 0;
    for (int i = 0; i < sales; ++i) {
        const bool diesel = next(10) < 6;
        const int spec = next(10) < 7 ? 1 : (next(2) ? 0 : 2);
        if (sellTicketsAtScreen(window, db, people.at(next(people.size())), diesel ? "Diesel" : "Gasoline", spec, 1 + next(6))) {
            ++saved;
        } else {
            out << "  FAIL  ticket sale " << i + 1 << " could not be saved at the Ticket sale screen\n";
        }
    }
    ok(saved == sales, QString::number(saved) + " of " + QString::number(sales) + " ticket sales saved");
    ok(count(db, "SELECT COUNT(*) FROM sales WHERE sale_type = 'ticket'") - before == saved, "the database holds " + QString::number(saved) + " new ticket sales");

    return saved == sales;
}

// ---- card sales ----

// a question window (Confirm, or a message box) is answered the way a person does: OK is pressed
static void answerQuestions()
{
    QWidget *modal = QApplication::activeModalWidget();
    if (!modal) {
        return;
    }
    if (QPushButton *okButton = modal->findChild<QPushButton *>("confirmOkButton")) {
        QTest::mouseClick(okButton, Qt::LeftButton);
    } else if (QMessageBox *box = qobject_cast<QMessageBox *>(modal)) {
        if (QAbstractButton *b = box->defaultButton()) {
            QTest::mouseClick(b, Qt::LeftButton);
        } else {
            box->accept();
        }
    }
}

static bool cardStage(MainWindow &window, QSqlDatabase db, FakeBoards &boards, int cards)
{
    out << "\ncard sales\n";
    QTimer questions;
    QObject::connect(&questions, &QTimer::timeout, &answerQuestions);
    questions.start(40);

    int topUps = 0;
    int fills = 0;
    int supplied = 0;
    int registered = 0;
    int failed = 0;
    const qlonglong cardsBefore = count(db, "SELECT COUNT(*) FROM cards");
    for (int i = 0; i < cards; ++i) {
        const bool diesel = next(10) < 6;
        const QString fuelName = diesel ? "Diesel" : "Gasoline";
        const QString fuelCode = diesel ? "D" : "A";
        const QString price = diesel ? "750" : "820";

        // a card that exists (about half of the time) or a new one
        QString uid;
        QSqlQuery q(db);
        q.prepare("SELECT uid FROM cards WHERE fuel_type = ? AND status = 'active' AND deleted = 0 ORDER BY uid");
        q.addBindValue(fuelCode);
        QStringList owned;
        if (q.exec()) {
            while (q.next()) {
                owned << q.value(0).toString();
            }
        }
        if (!owned.isEmpty() && next(100) < 50) {
            uid = owned.at(next(owned.size()));
        } else {
            uid = QString("GD%1").arg(count(db, "SELECT COUNT(*) FROM cards") + 1000001);
        }

        QPushButton *nav = shown<QPushButton>(window, "navCards");
        if (nav) {
            press(nav);
            QTest::qWait(250);
        }
        QLineEdit *idField = shown<QLineEdit>(window, "cardIdField");
        if (!idField) {
            out << "  FAIL  card " << i + 1 << ": the id box is not on the screen\n";
            ++failed;

            continue;
        }
        typeInto(idField, uid);
        QTest::keyClick(idField, Qt::Key_Return);   // no reader on this PC: the typed number is read like one from the reader
        QTest::qWait(300);

        // a new card is registered first
        if (QLineEdit *name = shown<QLineEdit>(window, "registerNameField")) {
            QComboBox *fuel = shown<QComboBox>(window, "registerFuelCombo");
            typeInto(name, "Holder " + uid);
            typeInto(shown<QLineEdit>(window, "registerPhoneField"), "01000" + uid.right(5));
            pick(fuel, fuelName);
            typeInto(shown<QLineEdit>(window, "registerPinField"), "1234");
            typeInto(shown<QLineEdit>(window, "registerPinAgainField"), "1234");
            press(shown<QPushButton>(window, "registerButton"));
            QTest::qWait(300);
            ++registered;
        }
        QLabel *balanceLabel = shown<QLabel>(window, "cardBalance");
        if (!balanceLabel) {
            out << "  FAIL  card " << i + 1 << " (" << uid << "): the card did not open\n";
            ++failed;

            continue;
        }

        // top up: the weight and the price are typed, the question is answered with OK
        QPushButton *switchButton = shown<QPushButton>(window, "cardTopUpButton");
        if (!shown<QLineEdit>(window, "topUpKgField") && switchButton) {
            press(switchButton);
            QTest::qWait(200);
        }
        QLineEdit *topKg = shown<QLineEdit>(window, "topUpKgField");
        QLineEdit *topPrice = shown<QLineEdit>(window, "topUpPriceField");
        QPushButton *topButton = shown<QPushButton>(window, "topUpButton");
        if (!topKg || !topPrice || !topButton) {
            out << "  FAIL  card " << i + 1 << ": the top-up form is not on the screen\n";
            ++failed;

            continue;
        }
        const qlonglong salesBefore = count(db, "SELECT COUNT(*) FROM sales WHERE sale_type = 'card'");
        const int topKgValue = 20 + next(81);
        typeInto(topKg, QString::number(topKgValue));
        typeInto(topPrice, price);
        press(topButton);
        if (!waitUntil([&]() { return count(db, "SELECT COUNT(*) FROM sales WHERE sale_type = 'card'") > salesBefore; }, 10000)) {
            out << "  FAIL  card " << i + 1 << " (" << uid << "): the top-up was not saved\n";
            ++failed;

            continue;
        }
        ++topUps;

        // most card sales are followed by a fill: the ticket is made on the Fill page and the supplier uses it at a nozzle
        if (next(100) < 75) {
            QPushButton *toFill = shown<QPushButton>(window, "cardTopUpButton");
            if (!shown<QLineEdit>(window, "fillKgField") && toFill) {
                press(toFill);
                QTest::qWait(200);
            }
            QLineEdit *fillKg = shown<QLineEdit>(window, "fillKgField");
            QLineEdit *fillPin = shown<QLineEdit>(window, "fillPinField");
            QPushButton *fillOk = shown<QPushButton>(window, "fillOkButton");
            if (!fillKg || !fillPin || !fillOk) {
                out << "  FAIL  card " << i + 1 << ": the fill form is not on the screen\n";
                ++failed;

                continue;
            }
            const qlonglong ticketsBefore = count(db, "SELECT COUNT(*) FROM tickets WHERE card_id = (SELECT id FROM cards WHERE uid = '" + uid + "')");
            const int fillKgValue = 10 + next(21);
            typeInto(fillKg, QString::number(fillKgValue));
            typeInto(fillPin, "1234");
            press(fillOk);
            if (!waitUntil([&]() { return count(db, "SELECT COUNT(*) FROM tickets WHERE card_id = (SELECT id FROM cards WHERE uid = '" + uid + "')") > ticketsBefore; }, 10000)) {
                out << "  FAIL  card " << i + 1 << " (" << uid << "): the card ticket was not made\n";
                ++failed;

                continue;
            }
            ++fills;
            QSqlQuery idq(db);
            idq.exec("SELECT id FROM cards WHERE uid = '" + uid + "'");
            const QString ticket = idq.next() ? Cards::lastTicket(db, idq.value(0).toString()) : QString();
            const int nozzle = diesel ? 3 + next(2) : 1 + next(2);
            const SupplyResult r = autoSupply(window, db, boards, nozzle, QStringList() << ticket, 0, next(100) < 20 ? 60 + next(35) : 0);
            if (r.done) {
                ++supplied;
            } else {
                out << "  FAIL  card " << i + 1 << " (" << uid << "): the card ticket " << ticket << " could not be supplied: " << r.why << "\n";
                ++failed;
            }
        }
    }
    questions.stop();
    ok(failed == 0 && topUps == cards, QString::number(topUps) + " of " + QString::number(cards) + " card sales saved (" + QString::number(registered) + " new cards, "
                                           + QString::number(fills) + " card tickets made, " + QString::number(supplied) + " supplied at a nozzle)");
    ok(count(db, "SELECT COUNT(*) FROM cards") - cardsBefore == registered, QString::number(registered) + " new cards are in the database");

    return failed == 0;
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

    const bool withBoards = args.contains("--nozzles") || args.contains("--boards") || !arg(args, "supplies").isEmpty() || !arg(args, "manual-supplies").isEmpty() || !arg(args, "cards").isEmpty();
    const int converterPort = arg(args, "converter-port", "4001").toInt();
    if (withBoards) {
        Settings(db).setInt(SettingKey::ConverterPort, converterPort);
    }
    AppConfig config;
    config.setBoardLink(withBoards);
    config.setChangeLogFile(data + "/changes.log");
    MainWindow window(config, QString(), "db", db);
    window.resize(1280, 720);
    window.show();
    QTest::qWaitForWindowExposed(&window);
    out << "the terminal window is open (" << data << ")\n";
    FakeBoards boards;
    if (withBoards) {
        for (int number = 1; number <= 5; ++number) {
            boards.addBlank(number);   // straight from the factory: the technician gives them their config on Machine Config
        }
        boards.connectTo(quint16(converterPort));
    }

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

    if (args.contains("--nozzles")) {
        if (!machineConfigStage(window, db, boards, arg(args, "pin", "4821"))) {
            out << "\n" << checks << " checks, " << fails << " failed\n";

            return 1;
        }
    }

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

    const int ticketSaleCount = arg(args, "ticket-sales", "0").toInt();
    if (ticketSaleCount > 0) {
        ticketSalesStage(window, db, ticketSaleCount);
    }

    const int supplyCount = arg(args, "supplies", "0").toInt();
    const int manualCount = arg(args, "manual-supplies", "0").toInt();
    if (supplyCount > 0 || manualCount > 0) {
        supplyStage(window, db, boards, supplyCount, manualCount);
    }

    const int cardCount = arg(args, "cards", "0").toInt();
    if (cardCount > 0) {
        if (supplyCount == 0 && manualCount == 0) {
            // the boards are given their config back (see the supplies)
            MachineConfig mc(db);
            foreach (const MachineConfig::Nozzle &nozzle, mc.nozzles()) {
                BoardProtocol::BoardConfig config;
                config.mode = nozzle.mode;
                config.fuelType = nozzle.fuelType;
                config.density = quint16(nozzle.density);
                config.pulsesX100 = quint32(nozzle.pulsesPerLiter);
                boards.board(nozzle.number).config = config;
                boards.board(nozzle.number).flowGramsPerSecond = 600 + next(800);
            }
            QTest::qWait(2000);
        }
        cardStage(window, db, boards, cardCount);
    }

    // ---- check and upload ----
    if (args.contains("--upload")) {
        out << "\nupload\n";
        press(shown<QPushButton>(window, "navReview"));
        QTest::qWait(300);
        QPushButton *upload = shown<QPushButton>(window, "uploadButton");
        ok(upload && window.currentPage() == "review", "the Review screen is open with the Upload data button");
        if (upload) {
            const int waiting = Uploader::pendingTotal(db);
            press(upload);
            ok(waitUntil([&]() { return Uploader::pendingTotal(db) == 0; }, 1800000, 500),
               QString::number(waiting) + " records waited (sales, supplies, remainders, tickets, loans) and all of them were uploaded");
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
