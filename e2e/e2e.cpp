// The Smart210 side of the end-to-end tests. It does what a cashier and a supplier do in a shift, with the app's own classes (the screens are
// not involved), against a running web app: download, sales of every kind, ticket loans and their settlement, supplies on simulated boards
// (auto with a remainder, a remainder ticket used again, manual typed in with its tickets, a supply typed in for an auto nozzle), checkout, upload, a
// second upload after edits, and a download again. It writes what the terminal holds to a JSON file; verify.php compares that with the web app's
// database.
//
//   e2e <scenario> <server host> <server port> <facts.json>
//
// Scenarios: basic (9.1), offline (9.2; see Offline.inc), restore, cards (the ledger of a card; see Cards.inc), cardsmany (five cards, top-ups and fills several times; see Cards.inc). Exit code 0 when every local check passed.
#include <QCoreApplication>
#include <QDateTime>
#include <QEventLoop>
#include <QFile>
#include <QJsonArray>
#include <QJsonDocument>
#include <QJsonObject>
#include <QSqlError>
#include <QSqlQuery>
#include <QSqlRecord>
#include <QProcess>
#include <QTcpServer>
#include <QTemporaryDir>
#include <QTextStream>
#include <QTimer>
#include <functional>

#include "Barcode.h"
#include "BoardBus.h"
#include "BoardProtocol.h"
#include "CardLedger.h"
#include "Cards.h"
#include "Checkout.h"
#include "Database.h"
#include "Decimal.h"
#include "FakeBoards.h"
#include "LoanSettlement.h"
#include "MachineConfig.h"
#include "Migrator.h"
#include "Purger.h"
#include "ReviewData.h"
#include "Session.h"
#include "Settings.h"
#include "SpotSale.h"
#include "SupplyQueue.h"
#include "SupplyRecord.h"
#include "SupplySelection.h"
#include "SupplySession.h"
#include "SyncClient.h"
#include "TicketNumbers.h"
#include "TicketSale.h"
#include "TicketStatus.h"
#include "Uploader.h"

static QTextStream out(stdout);
static int failures = 0;
static int checks = 0;

static void check(bool ok, const QString &what)
{
    ++checks;
    if (!ok) {
        ++failures;
    }
    out << (ok ? "  ok    " : "  FAIL  ") << what << "\n";
    out.flush();
}

static void step(const QString &title)
{
    out << "\n== " << title << "\n";
    out.flush();
}

static bool waitFor(const std::function<bool()> &done, int timeoutMs = 15000)
{
    QEventLoop loop;
    QTimer poll;
    QTimer limit;
    limit.setSingleShot(true);
    bool timedOut = false;
    QObject::connect(&poll, &QTimer::timeout, [&]() { if (done()) { loop.quit(); } });
    QObject::connect(&limit, &QTimer::timeout, [&]() { timedOut = true; loop.quit(); });
    if (done()) {
        return true;
    }
    poll.start(10);
    limit.start(timeoutMs);
    loop.exec();

    return !timedOut;
}

// The supplier takes the gun up: the board that has its preset (SET) starts by itself.
static bool takeTheGunUp(FakeBoards &boards, int nozzle, quint32 presetG)
{
    return waitFor([&]() { return boards.board(nozzle).state == BoardProtocol::StateArmed && boards.board(nozzle).presetG == presetG; }) && boards.simulator().liftGun(nozzle);
}

static void pause(int ms)
{
    QEventLoop loop;
    QTimer::singleShot(ms, &loop, SLOT(quit()));
    loop.exec();
}

static int freePort()
{
    QTcpServer probe;
    probe.listen(QHostAddress::LocalHost, 0);
    const int port = probe.serverPort();
    probe.close();

    return port;
}

// ---- the terminal ----

struct Terminal {
    Terminal(const QString &file_, const QString &host, int port)
        : name("e2e"), file(file_), nowShift(0), userId(0), uploadTimeoutMs(20000), downloadTimeoutMs(60000)
    {
        QString error;
        if (!Database::open(file_, name, &error) || !Migrator::apply(QSqlDatabase::database(name), Migrator::bundled(), &error)) {
            out << "cannot open the database: " << error << "\n";
            ::exit(3);
        }
        Settings settings(db());
        settings.setValue(SettingKey::ServerHost, host);
        settings.setInt(SettingKey::ServerPort, port);
        settings.setInt(SettingKey::DeviceId, 1);
    }

    ~Terminal() { Database::close(name); }

    QSqlDatabase db() const { return QSqlDatabase::database(name); }

    QVariant one(const QString &sql) const
    {
        QSqlQuery q(db());
        if (!q.exec(sql) || !q.next()) {
            return QVariant();
        }

        return q.value(0);
    }

    bool download(QString *message = 0)
    {
        SyncClient client(db());
        client.setTimeoutMs(20000);
        bool ok = false;
        bool done = false;
        QString text;
        QObject::connect(&client, &SyncClient::downloadFinished, [&](bool o, const QString &m) { ok = o; text = m; done = true; });
        client.download();
        waitFor([&]() { return done; }, downloadTimeoutMs);
        if (message) {
            *message = text;
        }

        return ok;
    }

    // What the Review screen's Upload button does: everything open gets its date, then what waits goes up (of one date, or all).
    bool upload(const QString &date = QString(), QString *message = 0, int *rejected = 0, int *saved = 0)
    {
        Checkout::run(db(), QDateTime::currentDateTime(), "upload", userId, true);
        Uploader uploader(db());
        uploader.setTimeoutMs(uploadTimeoutMs);
        bool ok = false;
        bool done = false;
        QString text;
        QObject::connect(&uploader, &Uploader::uploadFinished, [&](bool o, const QString &m) { ok = o; text = m; done = true; });
        uploader.upload(date);
        waitFor([&]() { return done; }, 120000);
        if (message) {
            *message = text;
        }
        if (rejected) {
            *rejected = uploader.rejectedCount();
        }
        if (saved) {
            *saved = uploader.savedCount();
        }

        return ok;
    }

    int customer(const QString &name_) const
    {
        QSqlQuery q(db());
        q.prepare("SELECT id FROM customers WHERE name = ? AND deleted = 0");
        q.addBindValue(name_);

        return q.exec() && q.next() ? q.value(0).toInt() : 0;
    }

    QString name;
    QString file;
    int nowShift;
    int userId;
    int uploadTimeoutMs;
    int downloadTimeoutMs;
};

// The boards behind the converter, the supply session on top of them: a nozzle 1 in auto mode and a nozzle 2 in manual mode.
struct Plant {
    static void addBoards(FakeBoards *b)
    {
        b->add(1);
        b->add(2);
        b->board(2).config.mode = BoardProtocol::ModeManual;
        b->board(1).flowGramsPerSecond = 500;
        b->board(2).flowGramsPerSecond = 1000;
    }

    Plant(Terminal *t, FakeBoards *b)
        : terminal(t), boards(*b), config(t->db()), session(t->db()), selection(&bus, &config), supply(t->db(), &bus, &config, &selection, &session), recorded(0), lastGood(true)
    {
        bus.setListenPort(freePort());
        bus.setCycleMs(5);
        bus.setAnswerTimeoutMs(100);
        bus.setOfflineRetryMs(100);
        bus.setMissLimit(3);
        bus.addBoard(1);
        bus.addBoard(2);
        QObject::connect(&supply, &SupplySession::recorded, [this](int, const QString &id) { ++recorded; lastSupplement = id; });
        QObject::connect(&supply, &SupplySession::remainderMade, [this](const QString &barcode) { remainders << barcode; });
        QObject::connect(&supply, &SupplySession::message, [this](const QString &text, bool good) { lastMessage = text; lastGood = good; });
    }

    // The nozzles have answered with their config; the tank is the one the web app told us about.
    bool start(bool firstTime = true)
    {
        BoardProtocol::BoardConfig autoConfig;
        autoConfig.mode = BoardProtocol::ModeAuto;
        autoConfig.fuelType = "D";
        autoConfig.density = 8300;
        autoConfig.pulsesX100 = 100000;
        BoardProtocol::BoardConfig manualConfig = autoConfig;
        manualConfig.mode = BoardProtocol::ModeManual;
        if (firstTime) {
            QString why;
            config.addNozzle(1, "D", 0, 0, &why); // the nozzles are added by hand on Machine Config; their configs are set here (a technician would Send or Read them)
            config.addNozzle(2, "D", 0, 0, &why);
            config.storeBoardConfig(1, autoConfig);
            config.storeBoardConfig(2, manualConfig);
            const int tank = terminal->one("SELECT id FROM tanks ORDER BY id LIMIT 1").toInt();
            config.setTank(1, tank);
            config.setTank(2, tank);
        }
        if (!bus.start()) {
            return false;
        }
        boards.connectTo(quint16(bus.listenPort()));

        return waitFor([&]() { return bus.isOnline(1) && bus.isOnline(2); });
    }

    bool login()
    {
        return session.login(terminal->userId, "secret1");
    }

    // Auto mode: the supplier chooses the nozzle, scans the tickets, takes the gun up; the board runs until `stopAtGrams` (0: to the preset).
    bool autoFill(int nozzle, const QList<qlonglong> &tickets, qlonglong stopAtGrams)
    {
        const int before = recorded;
        if (!waitFor([&]() { return bus.lastStatus(nozzle).state == BoardProtocol::StateIdle; })) {
            return false;
        }
        QString why;
        if (!selection.select(nozzle, &why)) {
            out << "    select: " << why << "\n";

            return false;
        }
        if (!waitFor([&]() { return selection.current() == nozzle && selection.isSelected(nozzle); })) {
            return false;
        }
        foreach (qlonglong barcode, tickets) {
            supply.scanned(Barcode::fromKey(barcode));
            if (!lastGood) {
                out << "    scan: " << lastMessage << "\n";

                return false;
            }
        }
        // the preset follows the scans by itself (the dispenser shows it); the supplier takes the gun up and the board starts
        if (!takeTheGunUp(boards, nozzle, quint32(SupplyQueue::totalX100(terminal->db(), nozzle) * 10))) {
            out << "    the preset did not arrive or the gun could not be lifted\n";

            return false;
        }
        if (!waitFor([&]() { return boards.board(nozzle).state == BoardProtocol::StateDispensing; })) {
            return false;
        }
        if (!waitFor([&]() { return SupplyQueue::session(terminal->db(), nozzle).exists; })) { // the terminal must see the fill start (it polls)
            return false;
        }
        if (stopAtGrams > 0) {
            boards.simulator().tick(int(stopAtGrams * 1000 / boards.board(nozzle).flowGramsPerSecond));
            boards.simulator().stopFill(nozzle);
        } else {
            boards.simulator().tick(1000000);
        }

        return waitFor([&]() { return recorded > before && boards.board(nozzle).state == BoardProtocol::StateIdle; });
    }

    Terminal *terminal;
    FakeBoards &boards;
    MachineConfig config;
    Session session;
    BoardBus bus;
    SupplySelection selection;
    SupplySession supply;
    int recorded;
    QString lastSupplement;
    QStringList remainders;
    QString lastMessage;
    bool lastGood;
};

static QJsonArray dump(const Terminal &t, const QString &sql)
{
    QJsonArray rows;
    QSqlQuery q(t.db());
    q.exec(sql);
    while (q.next()) {
        QJsonObject row;
        const QSqlRecord record = q.record();
        for (int i = 0; i < record.count(); ++i) {
            row.insert(record.fieldName(i), q.value(i).isNull() ? QJsonValue(QJsonValue::Null) : QJsonValue::fromVariant(q.value(i)));
        }
        rows.append(row);
    }

    return rows;
}

// What this terminal holds, for verify.php. Numbers are the stored integers (kg, money and litres x 100).
static void writeFacts(const Terminal &t, const QString &file, const QJsonObject &extra = QJsonObject())
{
    QJsonObject facts;
    facts.insert("sales", dump(t, "SELECT id, created_at, customer_id, sale_type, fuel_type, price, amount_kg, total_amount, paid_amount, unpaid_amount, usage, comment, sale_date, deleted_at FROM sales"));
    facts.insert("supplements", dump(t, "SELECT s.id, s.fuel_type, n.number AS nozzle_number, s.out_amount_lt, s.out_amount_kg, s.preset_kg, s.density, s.mode, s.comment, s.supply_date FROM supplements s JOIN nozzles n ON n.id = s.nozzle_id"));
    facts.insert("remainders", dump(t, "SELECT id, supplement_id, ticket_barcode, amount_kg, remainder_date FROM remainders"));
    facts.insert("loans", dump(t, "SELECT id, customer_id, fuel_type, amount_kg, settled_amount_kg, sale_id FROM ticket_loans"));
    facts.insert("settlements", dump(t, "SELECT id, loan_id, ticket_barcode, amount_kg, date FROM ticket_loan_settlements"));
    facts.insert("tickets", dump(t, "SELECT barcode, fuel_type, amount_kg, status, sale_id, supplement_id FROM tickets WHERE status IN (1, 2)"));
    facts.insert("pending", Uploader::pendingTotal(t.db()));
    for (QJsonObject::const_iterator it = extra.begin(); it != extra.end(); ++it) {
        facts.insert(it.key(), it.value());
    }
    QFile f(file);
    if (f.open(QIODevice::WriteOnly)) {
        f.write(QJsonDocument(facts).toJson());
    }
}

#include "Proxy.inc"
#include "Scenarios.inc"
#include "Offline.inc"
#include "Cards.inc"
#include "Day.inc"
#include "Dev.inc"

int main(int argc, char *argv[])
{
    QCoreApplication app(argc, argv);
    ApiClient::setMinIntervalMs(0); // a test does not wait a second between calls
    if (argc < 5) {
        out << "usage: e2e <scenario> <server host> <server port> <facts.json>\n";

        return 2;
    }
    const QString scenario = argv[1];
    if (scenario == "writer" || scenario == "uploader") {
        Settings::setDeviceFile(QString(argv[2]) + ".device.ini"); // the machine configuration stays out of the real user's folder
        return childProcess(scenario, argv[2], argv[3], QString(argv[4]).toInt());
    }
    QTemporaryDir dir;
    Settings::setDeviceFile(dir.path() + "/terminal.db.device.ini");
    // devrun works on the terminal's own database (the 7th argument): the dev web app and a terminal that has been used before
    const QString databaseFile = (scenario == "devrun" && argc > 7) ? QString(argv[7]) : dir.path() + "/terminal.db";
    Terminal terminal(databaseFile, argv[2], QString(argv[3]).toInt());

    int code = 0;
    if (scenario == "basic") {
        code = basicScenario(terminal, argv[4]);
    } else if (scenario == "offline") {
        code = offlineScenario(terminal, argv[4], argv[2], QString(argv[3]).toInt());
    } else if (scenario == "restore") {
        code = restoreScenario(terminal, argv[4], argc > 5 ? argv[5] : "php", argc > 6 ? argv[6] : ".");
    } else if (scenario == "cards") {
        code = cardsScenario(terminal, argv[4], argc > 5 ? argv[5] : "php", argc > 6 ? argv[6] : ".");
    } else if (scenario == "cardsmany") {
        code = cardsManyScenario(terminal, argv[4], argc > 5 ? argv[5] : "php", argc > 6 ? argv[6] : ".");
    } else if (scenario == "devrun") {
        code = devrunScenario(terminal, argv[4]);
    } else if (scenario == "day") {
        code = dayScenario(terminal, argv[4], argc > 5 ? argv[5] : "php", argc > 6 ? argv[6] : ".", argv[2], QString(argv[3]).toInt());
    } else {
        out << "unknown scenario " << scenario << "\n";
        code = 2;
    }

    out << "\n" << (checks - failures) << " of " << checks << " checks passed\n";
    if (scenario == "day") {
        out << knownIssues << " known issue(s) of REVIEW_2026-10-20.md still show (ISSUE lines)\n";
    }
    out.flush();

    return code != 0 ? code : (failures > 0 ? 1 : 0);
}
