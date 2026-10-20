// 9.2: the terminal without a network, with a bad network, and without power (appended to Scenarios.inc by e2e.cpp; included after it).

static QDateTime at(int daysAgo, int hour, int minute = 0)
{
    return QDateTime(QDate::currentDate().addDays(-daysAgo), QTime(hour, minute));
}

// An auto supply as the board and the supplier would have made it, written with a time of our own (no board is involved).
static bool fakeSupply(Terminal &t, int nozzle, const QList<qlonglong> &tickets, qlonglong dispensedX100, quint32 resultId, const QDateTime &when, int reason,
                       SupplyRecord::Outcome *outcome = 0)
{
    foreach (qlonglong barcode, tickets) {
        if (!SupplyQueue::add(t.db(), nozzle, Barcode::fromKey(barcode), "D", when).ok) {
            return false;
        }
    }
    if (!tickets.isEmpty() && !SupplyQueue::begin(t.db(), nozzle, SupplyQueue::totalX100(t.db(), nozzle), when)) {
        return false;
    }
    SupplyRecord::Reading reading;
    reading.resultId = resultId;
    reading.dispensedX100 = dispensedX100;
    reading.litersX100 = dispensedX100 * 10000 / 8300;
    reading.reason = reason;
    const SupplyRecord::Outcome made = SupplyRecord::record(t.db(), nozzle, reading, when);
    if (outcome) {
        *outcome = made;
    }

    return made.ok;
}

// A working day (the day is `daysAgo` days back): two sales, own use, a ticket sale from the stock.
static void dayWork(Terminal &t, int daysAgo, int acme, int self)
{
    SpotSale::create(t.db(), spot("sale", acme, 500 + 100 * daysAgo), t.userId, at(daysAgo, 10, 0));
    SpotSale::create(t.db(), spot("sale", acme, 500 + 100 * daysAgo), t.userId, at(daysAgo, 10, 30));
    SpotSale::create(t.db(), spot("self", self, 300), t.userId, at(daysAgo, 11, 0));
    TicketSale::Input ticketSale;
    ticketSale.fuelType = "D";
    ticketSale.customerId = acme;
    ticketSale.usage = "sale";
    ticketSale.priceX100 = 750;
    ticketSale.barcodes << press(4 - daysAgo);
    TicketSale::create(t.db(), ticketSale, t.userId, at(daysAgo, 12, 0));
}

static int salesCount(Terminal &t)
{
    return t.one("SELECT COUNT(*) FROM sales WHERE deleted_at IS NULL").toInt();
}

// Some more sales of today (each has its ticket: two records that wait).
static void moreSales(Terminal &t, int n, int customer, int minuteOfDay)
{
    for (int i = 0; i < n; ++i) {
        SpotSale::create(t.db(), spot("sale", customer, 100 + i), t.userId, at(0, 15, 0).addSecs(minuteOfDay * 60 + i));
    }
}

// ---- processes that are killed ----

// The child of the power-cut tests: keeps writing (writer) or uploading (uploader) until it is killed.
static int childProcess(const QString &mode, const QString &file, const QString &host, int port)
{
    QCoreApplication::processEvents();
    QString error;
    if (!Database::open(file, "child", &error)) {
        out << "child: " << error << "\n";

        return 3;
    }
    QSqlDatabase db = QSqlDatabase::database("child");
    if (mode == "writer") {
        for (int i = 1;; ++i) {
            const QString usage = i % 3 == 0 ? "ticket_loan" : (i % 3 == 1 ? "sale" : "self");
            const SpotSale::Result r = SpotSale::create(db, spot(usage, 1, 100 + i), 1, QDateTime::currentDateTime());
            if (i % 7 == 0) {
                Checkout::run(db, QDateTime::currentDateTime(), "upload", 1, true);
            }
            out << (r.ok ? "ok " : "bad ") << i << "\n";
            out.flush();
        }
    }

    Settings settings(db);
    settings.setValue(SettingKey::ServerHost, host);
    settings.setInt(SettingKey::ServerPort, port);
    Checkout::run(db, QDateTime::currentDateTime(), "upload", 1, true);
    Uploader uploader(db);
    uploader.setTimeoutMs(20000);
    bool done = false;
    QObject::connect(&uploader, &Uploader::uploadFinished, [&](bool, const QString &) { done = true; });
    uploader.upload();
    out << "started\n";
    out.flush();
    waitFor([&]() { return done; }, 60000);
    out << "finished\n";
    out.flush();
    Database::close("child");

    return 0;
}

// The database after a kill: sound, and every transaction is all there or not there at all.
static bool soundAfterKill(const QString &file, int minimumSales, QString *why)
{
    QString error;
    if (!Database::open(file, "killcheck", &error)) {
        *why = error;

        return false;
    }
    QSqlDatabase db = QSqlDatabase::database("killcheck");
    QSqlQuery q(db);
    bool good = true;
    auto number = [&](const QString &sql) { return q.exec(sql) && q.next() ? q.value(0).toInt() : -1; };

    q.exec("PRAGMA integrity_check");
    q.next();
    if (q.value(0).toString() != "ok") {
        *why = "integrity_check: " + q.value(0).toString();
        good = false;
    } else if (number("SELECT COUNT(*) FROM sales s WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.sale_id = s.id)") != 0) {
        *why = "a sale without its ticket";
        good = false;
    } else if (number("SELECT COUNT(*) FROM tickets t WHERE t.sale_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM sales s WHERE s.id = t.sale_id)") != 0) {
        *why = "a ticket without its sale";
        good = false;
    } else if (number("SELECT COUNT(*) FROM sales s WHERE s.usage = 'ticket_loan' AND (SELECT COUNT(*) FROM ticket_loans l WHERE l.sale_id = s.id) <> 1") != 0) {
        *why = "a loan sale without exactly one loan";
        good = false;
    } else if (number("SELECT COUNT(*) FROM ticket_loans l WHERE l.sale_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM sales s WHERE s.id = l.sale_id)") != 0) {
        *why = "a loan without its sale";
        good = false;
    } else if (number("SELECT COUNT(*) FROM sales") < minimumSales) {
        *why = "a sale that was reported saved is missing";
        good = false;
    }
    Database::close("killcheck");

    return good;
}

// ---- the scenario ----

static int offlineScenario(Terminal &t, const QString &factsFile, const QString &host, int port)
{
    QString message;
    srand(uint(QDateTime::currentMSecsSinceEpoch()));

    step("the first download");
    const bool first = t.download(&message);
    check(first, "the first download finishes (" + message + ")");
    t.userId = t.one("SELECT id FROM users WHERE username = 'cash'").toInt();
    const int acme = t.customer("ACME");
    const int self = t.customer("Self");
    MachineConfig config(t.db());
    {
        BoardProtocol::BoardConfig autoConfig;
        autoConfig.mode = BoardProtocol::ModeAuto;
        autoConfig.fuelType = "D";
        autoConfig.density = 8300;
        autoConfig.pulsesX100 = 100000;
        BoardProtocol::BoardConfig manualConfig = autoConfig;
        manualConfig.mode = BoardProtocol::ModeManual;
        config.storeBoardConfig(1, autoConfig);
        config.storeBoardConfig(2, manualConfig);
        const int tank = t.one("SELECT id FROM tanks ORDER BY id LIMIT 1").toInt();
        config.setTank(1, tank);
        config.setTank(2, tank);
    }

    // ----------------------------------------------------------------------------------------------------------------------------------
    step("A. four days with no network at all (the terminal runs on its own)");
    Settings(t.db()).setInt(SettingKey::ServerPort, freePort()); // nobody listens there
    for (int d = 3; d >= 0; --d) {
        dayWork(t, d, acme, self);
    }
    check(fakeSupply(t, 1, QList<qlonglong>() << press(301), 1500, 1, at(3, 14), BoardProtocol::ReasonPresetReached), "day -3: a 15 kg supply");
    SupplyRecord::Outcome partial;
    check(fakeSupply(t, 1, QList<qlonglong>() << press(302), 1000, 2, at(2, 14), BoardProtocol::ReasonTankFullOrNoFlow, &partial) && !partial.remainderBarcode.isEmpty(),
          "day -2: a supply that stopped at 10 of 15 kg (a remainder ticket)");
    check(fakeSupply(t, 1, QList<qlonglong>() << press(201) << press(202), 1000, 3, at(1, 14), BoardProtocol::ReasonPresetReached), "day -1: a 10 kg supply");
    SupplyRecord::Reading manual;
    manual.byHand = true;                 // a supply typed in with its ticket
    manual.tickets << press(203);
    manual.dispensedX100 = 500;
    manual.litersX100 = 602;
    manual.presetX100 = 500;
    manual.reason = BoardProtocol::ReasonPresetReached;
    const SupplyRecord::Outcome typed = SupplyRecord::record(t.db(), 1, manual, at(0, 14));
    check(typed.ok && typed.tickets == 1, "today: a manual supply typed in with its ticket");
    check(salesCount(t) == 16 && t.one("SELECT COUNT(*) FROM supplements").toInt() == 4, "16 sales and 4 supplies are recorded");

    const int waiting = Uploader::pendingTotal(t.db());
    check(waiting > 40, "many records wait (" + QString::number(waiting) + ")");
    // every record got its date (the business day of its time) when it was made: the catch-up checkout of 9:30 has nothing left to stamp
    check(Checkout::openRecords(t.db()) == 0, "no record waits for a date");
    const Checkout::Run run = Checkout::run(t.db(), QDateTime(QDate::currentDate(), QTime(9, 30)), "auto", t.userId, false);
    check(run.records == 0, "the catch-up checkout has nothing to stamp (" + QString::number(run.records) + ")");
    check(Checkout::overdueRecords(t.db(), QDateTime(QDate::currentDate(), QTime(9, 31))) == 0, "no finished day is left open");

    const bool offlineDownload = t.download(&message);
    check(!offlineDownload, "a download fails without the network");
    const bool offlineUpload = t.upload(QString(), &message);
    check(!offlineUpload, "an upload fails without the network (" + message + ")");
    check(Uploader::pendingTotal(t.db()) == waiting, "nothing was lost or marked uploaded by the failures");
    check(t.one("SELECT COUNT(*) FROM upload_errors").toInt() == 0, "no refusal was kept for a network failure");

    // the clean-up that runs after a download must never remove what has not been uploaded
    const int salesBefore = salesCount(t);
    Purger::purge(t.db(), 3, QDateTime(QDate::currentDate().addDays(10), QTime(12, 0)));
    check(salesCount(t) == salesBefore && Uploader::pendingTotal(t.db()) == waiting, "ten days later the clean-up still keeps everything that waits");

    // back online: the download changes nothing that waits, then each day goes up on its own
    Settings(t.db()).setInt(SettingKey::ServerPort, port);
    const bool online = t.download(&message);
    check(online, "the network is back: the download finishes (" + message + ")");
    check(Uploader::pendingTotal(t.db()) == waiting, "the download left every waiting record alone");
    for (int d = 3; d >= 0; --d) {
        const QString day = QDate::currentDate().addDays(-d).toString("yyyy-MM-dd");
        const int expected = Uploader::pendingTotal(t.db(), day);
        int saved = 0;
        int rejected = 0;
        const bool done = t.upload(day, &message, &rejected, &saved);
        check(done && rejected == 0 && saved == expected && expected > 0, "day " + day + ": " + QString::number(saved) + " records go up, none refused (" + message + ")");
    }
    check(Uploader::pendingTotal(t.db()) == 0, "everything of the four days is uploaded");

    // ----------------------------------------------------------------------------------------------------------------------------------
    step("B. a bad network between the terminal and the web app");
    Proxy proxy(host, port);
    check(proxy.listen(), "the proxy listens");
    const int proxyPort = proxy.port();
    Settings(t.db()).setInt(SettingKey::ServerPort, proxyPort);
    int rejected = 0;
    int saved = 0;

    proxy.log.clear();
    proxy.add("POST /api/push/sales", Proxy::DropResponse, 10); // Qt sends a request again once when the connection closes before an answer
    moreSales(t, 3, acme, 0);
    bool done = t.upload(QString(), &message, &rejected, &saved);
    check(!done, "the answer to the sales is lost: the upload fails (" + message + ")");
    check(proxy.answered("push/sales", 200) >= 1 && proxy.dropped("push/sales") >= 1, "the server had answered 200 (the sales are saved there)");
    check(Uploader::pendingTotal(t.db()) == 6, "the terminal does not know: its 6 records still wait");
    proxy.clearRules();
    done = t.upload(QString(), &message, &rejected, &saved);
    check(done && saved == 6 && rejected == 0 && Uploader::pendingTotal(t.db()) == 0, "sent again, accepted again: nothing is doubled, nothing is lost (" + message + ")");

    proxy.log.clear();
    proxy.clearRules();
    proxy.add("POST /api/push/tickets", Proxy::DropResponse, 10);
    moreSales(t, 3, acme, 10);
    done = t.upload(QString(), &message, &rejected, &saved);
    check(!done && Uploader::pendingTotal(t.db()) == 3, "the answer to the tickets (the last table) is lost: the 3 sales are done, the 3 tickets wait (" + message + ", waiting " + QString::number(Uploader::pendingTotal(t.db())) + ", " + proxy.log.join(" | ") + ")");
    proxy.clearRules();
    done = t.upload(QString(), &message, &rejected, &saved);
    check(done && saved == 3 && Uploader::pendingTotal(t.db()) == 0, "the tickets go up on the next try (" + message + ", saved " + QString::number(saved) + ", waiting " + QString::number(Uploader::pendingTotal(t.db())) + ")");

    proxy.log.clear();
    proxy.clearRules();
    proxy.add("POST /api/push/supplements", Proxy::DropRequest, 10);
    moreSales(t, 2, acme, 20);
    check(fakeSupply(t, 1, QList<qlonglong>() << press(204), 300, 5, at(0, 16), BoardProtocol::ReasonTankFullOrNoFlow), "a supply with a remainder (5.00 kg ticket, 3.00 kg dispensed)");
    const int before = Uploader::pendingTotal(t.db());
    done = t.upload(QString(), &message, &rejected, &saved);
    out << "    supplies: " << message << " before " << before << " now " << Uploader::pendingTotal(t.db()) << " " << proxy.log.join(" | ") << QChar(10);
    check(!done && Uploader::pendingTotal(t.db()) == before - 2 && proxy.answered("push/supplements", 200) == 0, "the request for the supplies is cut before the server sees it: the sales are done, the rest waits");
    proxy.clearRules();
    done = t.upload(QString(), &message, &rejected, &saved);
    check(done && rejected == 0 && Uploader::pendingTotal(t.db()) == 0, "the rest goes up on the next try");

    proxy.log.clear();
    proxy.clearRules();
    proxy.add("POST /api/push/sales", Proxy::Busy, 10);
    moreSales(t, 2, acme, 30);
    done = t.upload(QString(), &message, &rejected, &saved);
    check(!done && message.contains("busy") && Uploader::pendingTotal(t.db()) == 4, "the server says busy (503): the upload says so and keeps everything (" + message + ")");
    proxy.clearRules();
    done = t.upload(QString(), &message, &rejected, &saved);
    check(done && Uploader::pendingTotal(t.db()) == 0, "and works when the server is free");

    proxy.log.clear();
    proxy.clearRules();
    proxy.add("POST /api/push/sales", Proxy::Delay, 1, 3000);
    moreSales(t, 2, acme, 40);
    t.uploadTimeoutMs = 1000;
    done = t.upload(QString(), &message, &rejected, &saved);
    check(!done, "a slow network: the upload gives up after one second (" + message + ")");
    check(waitFor([&]() { return proxy.answered("push/sales", 200) >= 1; }, 10000), "the delayed request still reached the server and was saved there");
    t.uploadTimeoutMs = 20000;
    check(Uploader::pendingTotal(t.db()) == 4, "the terminal did not hear of it: its records still wait");
    done = t.upload(QString(), &message, &rejected, &saved);
    check(done && Uploader::pendingTotal(t.db()) == 0, "the next upload settles it");

    proxy.log.clear();
    proxy.clearRules();
    const int customersBefore = t.one("SELECT COUNT(*) FROM customers").toInt();
    proxy.add("GET /api/pull/config", Proxy::DropResponse, 10);
    check(!t.download(&message), "a download whose first answer is lost fails");
    proxy.add("GET /api/pull/customers", Proxy::DropRequest, 10);
    check(!t.download(&message), "a download cut in the middle fails");
    proxy.clearRules();
    check(t.download(&message) && t.one("SELECT COUNT(*) FROM customers").toInt() == customersBefore, "the next download finishes and stores nothing twice (" + message + ")");

    proxy.log.clear();
    proxy.clearRules();
    moreSales(t, 2, acme, 50);
    proxy.close();
    done = t.upload(QString(), &message, &rejected, &saved);
    check(!done && Uploader::pendingTotal(t.db()) == 4, "the web server is not there at all: the upload fails and keeps everything (" + message + ")");
    Settings(t.db()).setInt(SettingKey::ServerPort, port);
    done = t.upload(QString(), &message, &rejected, &saved);
    check(done && Uploader::pendingTotal(t.db()) == 0, "and the server is back: everything goes up");

    // ----------------------------------------------------------------------------------------------------------------------------------
    step("C. the power is cut");
    // C1: a process writing sales and tickets as fast as it can is killed at random moments; the database must stay sound and complete
    {
        QTemporaryDir dir;
        const QString file = dir.path() + "/killed.db";
        QString error;
        Database::open(file, "seed", &error);
        Migrator::apply(QSqlDatabase::database("seed"), Migrator::bundled(), &error);
        QSqlQuery q(QSqlDatabase::database("seed"));
        q.exec("INSERT INTO fuel_types (fuel_type, name, price, density) VALUES ('D', 'Diesel', 750, 8300)");
        q.exec("INSERT INTO customers (id, name, is_self, deleted) VALUES (1, 'Customer', 0, 0)");
        q.exec("INSERT INTO users (id, username, name, role, pos, active, pos_salt, pos_hash, deleted) VALUES (1, 'w', 'W', 'cashier', 1, 1, 'aa', 'bb', 0)");
        Settings seedSettings(QSqlDatabase::database("seed"));
        seedSettings.setValue(SettingKey::TicketSign, "00000");
        seedSettings.setValue(SettingKey::TicketSerialBase, "500000000");
        seedSettings.setValue("reserved_code_direct", "21");
        seedSettings.setValue("reserved_code_self", "22");
        seedSettings.setValue("reserved_code_other", "23");
        seedSettings.setValue("reserved_code_ticket_loan", "24");
        Database::close("seed");

        int worst = 0;
        bool allSound = true;
        QString why;
        for (int round = 0; round < 8; ++round) {
            QProcess child;
            child.start(QCoreApplication::applicationFilePath(), QStringList() << "writer" << file << "-" << "0");
            child.waitForStarted();
            int lines = 0;
            const int killAfter = 80 + rand() % 400;
            QTimer killer;
            killer.setSingleShot(true);
            QEventLoop loop;
            QObject::connect(&killer, &QTimer::timeout, &loop, &QEventLoop::quit);
            killer.start(killAfter);
            loop.exec();
            child.kill();
            child.waitForFinished();
            const QStringList printed = QString::fromLocal8Bit(child.readAllStandardOutput()).split('\n', QString::SkipEmptyParts);
            foreach (const QString &line, printed) {
                lines += line.startsWith("ok ") ? 1 : 0;
            }
            worst = qMax(worst, lines);
            if (!soundAfterKill(file, lines, &why)) {
                allSound = false;
                out << "    round " << round << ": " << why << "\n";
            }
        }
        check(worst > 0, "the writer got work done before each kill (up to " + QString::number(worst) + " sales in one run)");
        check(allSound, "8 kills at random moments: the database is sound, every sale has its ticket (and loan), nothing reported saved is missing");
    }

    // C2: the upload process is killed at random moments; what the server saved and the terminal did not note is simply sent again
    {
        int killedRuns = 0;
        for (int round = 0; round < 4; ++round) {
            moreSales(t, 25, acme, 60 + round * 30);
            const int waitingNow = Uploader::pendingTotal(t.db());
            QProcess child;
            child.start(QCoreApplication::applicationFilePath(), QStringList() << "uploader" << t.file << host << QString::number(port));
            child.waitForStarted();
            QTimer killer;
            killer.setSingleShot(true);
            QEventLoop loop;
            QObject::connect(&killer, &QTimer::timeout, &loop, &QEventLoop::quit);
            killer.start(30 + rand() % 150);
            loop.exec();
            const bool alive = child.state() == QProcess::Running;
            child.kill();
            child.waitForFinished();
            killedRuns += alive ? 1 : 0;
            QString why;
            const int left = Uploader::pendingTotal(t.db());
            check(left <= waitingNow && left >= 0, "upload killed" + QString(alive ? "" : " (it had finished)") + ": " + QString::number(waitingNow) + " waited, " + QString::number(left) + " still wait");
            QSqlQuery q(t.db());
            q.exec("PRAGMA integrity_check");
            q.next();
            check(q.value(0).toString() == "ok", "the database is sound after the kill");
        }
        check(killedRuns > 0, "at least one upload was really cut short (" + QString::number(killedRuns) + " of 4)");
        int saved2 = 0;
        const bool settled = t.upload(QString(), &message, &rejected, &saved2);
        check(settled && rejected == 0 && Uploader::pendingTotal(t.db()) == 0, "an upload after the kills finishes and nothing waits any more (" + message + ")");
    }

    // ----------------------------------------------------------------------------------------------------------------------------------
    step("D. the terminal restarts and the boards lose power during fills");
    FakeBoards boards;
    Plant::addBoards(&boards);
    {
        // D1: the terminal is switched off while a fill runs, and on again after the fill has ended
        QString why;
        {
            Plant p(&t, &boards);
            p.login();
            p.start(false);
            const int rec = p.recorded;
            p.selection.select(1);
            waitFor([&]() { return p.selection.current() == 1 && p.selection.isSelected(1); });
            p.supply.scanned(Barcode::fromKey(press(401)));
            const bool started = takeTheGunUp(boards, 1, 10000);
            check(started, "a 10 kg ticket is queued and the fill starts (" + why + ")");
            check(waitFor([&]() { return boards.board(1).state == BoardProtocol::StateDispensing; }), "the board runs");
        check(waitFor([&]() { return SupplyQueue::session(t.db(), 1).exists; }), "the terminal saw the fill start: the session is saved");
            boards.simulator().tick(8000); // 4.00 kg
            check(p.recorded == rec && SupplyQueue::session(t.db(), 1).exists, "the session is saved");
        } // the terminal is switched off here: the connection to the boards is gone, the fill goes on
        boards.simulator().stopFill(1);
        check(boards.board(1).state == BoardProtocol::StateResult, "the board holds the result of the fill");
        Plant again(&t, &boards);
        check(again.login() && again.start(false), "the terminal starts again");
        check(waitFor([&]() { return again.recorded == 1 && boards.board(1).state == BoardProtocol::StateIdle; }), "after the restart the result is read, saved and acknowledged");
        check(t.one("SELECT out_amount_kg FROM supplements WHERE board_result_id IS NOT NULL ORDER BY rowid DESC LIMIT 1").toInt() == 400, "4.00 kg were dispensed");
        check(t.one("SELECT amount_kg FROM remainders ORDER BY rowid DESC LIMIT 1").toInt() == 600, "the saved session still had its ticket: 6.00 kg come back as a remainder");
        check(!SupplyQueue::session(t.db(), 1).exists && SupplyQueue::entries(t.db(), 1).isEmpty(), "the session and the queue are cleared");

        // D2: the board loses power in the middle of a fill
        const int rec = again.recorded;
        waitFor([&]() { return again.bus.lastStatus(1).state == BoardProtocol::StateIdle; });
        again.selection.select(1);
        waitFor([&]() { return again.selection.current() == 1 && again.selection.isSelected(1); });
        again.supply.scanned(Barcode::fromKey(press(402)));
        { const bool started_ = takeTheGunUp(boards, 1, 10000); check(started_, "another 10 kg fill starts (" + why + ")"); }
        check(waitFor([&]() { return boards.board(1).state == BoardProtocol::StateDispensing; }), "the board runs");
        check(waitFor([&]() { return SupplyQueue::session(t.db(), 1).exists; }), "the terminal saw the fill start: the session is saved");
        boards.simulator().tick(6000); // 3.00 kg
        boards.simulator().powerCut(1, 500);
        boards.simulator().tick(600);
        check(waitFor([&]() { return again.recorded > rec && boards.board(1).state == BoardProtocol::StateIdle; }, 20000), "the board comes back with the interrupted result: recorded and acknowledged");
        check(t.one("SELECT out_amount_kg FROM supplements ORDER BY rowid DESC LIMIT 1").toInt() == 300, "3.00 kg are recorded (what the board had stored; got " + t.one("SELECT out_amount_kg FROM supplements ORDER BY rowid DESC LIMIT 1").toString() + ")");
        check(t.one("SELECT amount_kg FROM remainders ORDER BY rowid DESC LIMIT 1").toInt() == 700, "7.00 kg come back as a remainder");

        // D3: the converter link drops in the middle of a fill
        const int rec3 = again.recorded;
        waitFor([&]() { return again.bus.lastStatus(1).state == BoardProtocol::StateIdle; });
        again.selection.select(1);
        waitFor([&]() { return again.selection.current() == 1 && again.selection.isSelected(1); });
        again.supply.scanned(Barcode::fromKey(press(206)));
        again.supply.scanned(Barcode::fromKey(press(207)));
        check(SupplyQueue::totalX100(t.db(), 1) == 1000, "two 5 kg tickets are queued");
        { const bool started_ = takeTheGunUp(boards, 1, 10000); check(started_, "a 10 kg fill of two tickets starts (" + why + ")"); }
        check(waitFor([&]() { return boards.board(1).state == BoardProtocol::StateDispensing; }), "the board runs");
        check(waitFor([&]() { return SupplyQueue::session(t.db(), 1).exists; }), "the terminal saw the fill start: the session is saved");
        boards.simulator().tick(8000);
        boards.dropConnection();
        check(waitFor([&]() { return again.bus.isConnected() && again.bus.isOnline(1); }, 20000), "the converter connects again by itself");
        boards.simulator().tick(100000);
        check(waitFor([&]() { return again.recorded > rec3 && boards.board(1).state == BoardProtocol::StateIdle; }, 20000), "the fill ends and is recorded");
        check(t.one("SELECT out_amount_kg FROM supplements ORDER BY rowid DESC LIMIT 1").toInt() == 1000, "10.00 kg dispensed, once (got " + t.one("SELECT out_amount_kg FROM supplements ORDER BY rowid DESC LIMIT 1").toString() + ")");
        check(again.recorded == rec3 + 1, "one result, one record");
    }

    // ----------------------------------------------------------------------------------------------------------------------------------
    step("the last upload and what the web app must hold");
    done = t.upload(QString(), &message, &rejected, &saved);
    check(done && rejected == 0 && Uploader::pendingTotal(t.db()) == 0, "everything goes up (" + message + ")");
    check(t.upload(QString(), &message, &rejected, &saved) && saved == 0, "and a repeated upload sends nothing");
    QJsonObject extra;
    extra.insert("base", QDate::currentDate().toString("yyyy-MM-dd"));
    writeFacts(t, factsFile, extra);

    step("after the upload the clean-up may remove the old records");
    const int removed = Purger::purge(t.db(), 3, QDateTime(QDate::currentDate().addDays(10), QTime(12, 0)));
    check(removed > 0 && salesCount(t) == 0, "ten days later the uploaded records are removed (" + QString::number(removed) + " rows)");
    check(t.download(&message) && salesCount(t) == 0, "a download afterwards brings none of them back (" + message + ")");

    return 0;
}

// ---- the web app's database is restored from a dump ----

// "key=value" lines of a PHP step (restore_step.php)
static QMap<QString, QString> phpStep(const QString &php, const QString &dir, const QString &action, bool *ok)
{
    QProcess p;
    p.start(php, QStringList() << dir + "/restore_step.php" << action);
    *ok = p.waitForFinished(300000) && p.exitCode() == 0;
    QMap<QString, QString> values;
    foreach (const QString &line, QString::fromLocal8Bit(p.readAllStandardOutput()).split('\n', QString::SkipEmptyParts)) {
        values.insert(line.section('=', 0, 0).trimmed(), line.section('=', 1).trimmed());
    }
    if (!*ok) {
        out << "    step " << action << ": " << QString::fromLocal8Bit(p.readAllStandardError()) << "\n";
    }

    return values;
}

static int restoreScenario(Terminal &t, const QString &factsFile, const QString &php, const QString &dir)
{
    QString message;
    bool ok = false;

    step("the first download");
    const bool first = t.download(&message);
    check(first, "the first download finishes (" + message + ")");
    t.userId = t.one("SELECT id FROM users WHERE username = 'cash'").toInt();
    const int acme = t.customer("ACME");
    MachineConfig config(t.db());
    BoardProtocol::BoardConfig autoConfig;
    autoConfig.mode = BoardProtocol::ModeAuto;
    autoConfig.fuelType = "D";
    autoConfig.density = 8300;
    autoConfig.pulsesX100 = 100000;
    config.storeBoardConfig(1, autoConfig);
    config.setTank(1, t.one("SELECT id FROM tanks ORDER BY id LIMIT 1").toInt());

    step("work that is uploaded before the dump");
    moreSales(t, 4, acme, 0);
    TicketSale::Input ticketSale;
    ticketSale.fuelType = "D";
    ticketSale.customerId = acme;
    ticketSale.usage = "sale";
    ticketSale.priceX100 = 750;
    ticketSale.barcodes << press(1);
    check(TicketSale::create(t.db(), ticketSale, t.userId, QDateTime::currentDateTime()).ok, "a ticket sale");
    check(fakeSupply(t, 1, QList<qlonglong>() << press(301), 1500, 1, QDateTime::currentDateTime(), BoardProtocol::ReasonPresetReached), "a 15 kg supply");
    int rejected = 0;
    int saved = 0;
    const bool firstUpload = t.upload(QString(), &message, &rejected, &saved);
    check(firstUpload && rejected == 0 && Uploader::pendingTotal(t.db()) == 0, "it goes up (" + message + ")");
    const int salesBefore = salesCount(t);

    step("the server dumps its database");
    QMap<QString, QString> dumped = phpStep(php, dir, "dump", &ok);
    check(ok && dumped.value("sales").toInt() == salesBefore, "the dump holds the " + QString::number(salesBefore) + " sales (" + dumped.value("dump_time") + ")");
    pause(1500); // the dump time has a resolution of a second

    step("work that is uploaded after the dump");
    moreSales(t, 3, acme, 10);
    ticketSale.barcodes.clear();
    ticketSale.barcodes << press(2);
    check(TicketSale::create(t.db(), ticketSale, t.userId, QDateTime::currentDateTime()).ok, "a ticket sale");
    SupplyRecord::Outcome partial;
    check(fakeSupply(t, 1, QList<qlonglong>() << press(302), 1000, 2, QDateTime::currentDateTime(), BoardProtocol::ReasonTankFullOrNoFlow, &partial)
          && !partial.remainderBarcode.isEmpty(), "a supply that stopped at 10 of 15 kg (a remainder ticket)");
    const bool secondUpload = t.upload(QString(), &message, &rejected, &saved);
    check(secondUpload && rejected == 0 && Uploader::pendingTotal(t.db()) == 0, "it goes up (" + message + ")");
    const int salesAfter = salesCount(t);
    check(salesAfter == salesBefore + 4, "the terminal holds " + QString::number(salesAfter) + " sales, all uploaded");

    step("the server dies and comes back from the dump");
    QMap<QString, QString> restored = phpStep(php, dir, "restore", &ok);
    check(ok && restored.value("sales").toInt() == salesBefore, "the restored database holds only the " + QString::number(salesBefore) + " sales of the dump (the later ones are gone)");
    check(Uploader::pendingTotal(t.db()) == 0, "the terminal believes everything is uploaded");

    step("the terminal learns about the restore at its next download");
    const bool downloaded = t.download(&message);
    check(downloaded && message.contains("was restored"), "the download says the database was restored (" + message + ")");
    const int again = Uploader::pendingTotal(t.db());
    check(again >= 4 + 2 + 2, "the work uploaded after the dump waits again (" + QString::number(again) + " records)");
    const bool third = t.upload(QString(), &message, &rejected, &saved);
    check(third && rejected == 0, "it goes up again, nothing is refused (" + message + ")");
    check(Uploader::pendingTotal(t.db()) == 0, "nothing waits any more");
    check(t.download(&message) && !message.contains("restored") && Uploader::pendingTotal(t.db()) == 0, "the next download does not act on the same restore again");

    QJsonObject extra;
    extra.insert("base", QDate::currentDate().toString("yyyy-MM-dd"));
    writeFacts(t, factsFile, extra);

    return 0;
}
