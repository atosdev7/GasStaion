// The "devrun" scenario of e2e.cpp (included there): the cashier's part of a big working day on the DEV web app, with the real Smart210 classes and
// simulated nozzle boards (no screens). It runs on the terminal's own database (the file is given as the 7th argument), so the ticket numbers
// the terminal makes go on from where they were, and it never touches the scratch database of the other scenarios.
//
//   e2e.exe devrun <host> <port> <facts.json> <php> <e2e dir> <terminal database>
//
// What it does: downloads everything (the stock of tickets the web app published, the sales the assistant made), small ticket sales, 100 spot sales
// (some for own use, some given away, five on a ticket loan), supplies on two boards by scanning tickets (a fill to the preset, a fill stopped
// early with a remainder ticket, a remainder used again, tickets sold on the web, a 50 kg ticket, scans that must be refused), ticket loans settled
// with tickets, then everything is uploaded.

// the highest tickets of one size that are in stock and follow each other without a gap (a ticket sale takes a run of serials)
static QList<qlonglong> topRun(Terminal &t, const QString &fuel, int spec, int count)
{
    QSqlQuery q(t.db());
    q.prepare("SELECT barcode FROM tickets WHERE fuel_type = ? AND spec = ? AND status = 0 ORDER BY barcode DESC LIMIT 600");
    q.addBindValue(fuel);
    q.addBindValue(spec);
    QList<qlonglong> stock;
    if (q.exec()) {
        while (q.next()) {
            stock << q.value(0).toLongLong();
        }
    }
    for (int start = 0; start + count <= stock.size(); ++start) {
        bool run = true;
        for (int i = 1; i < count; ++i) {
            if (stock.at(start + i - 1) - stock.at(start + i) != 1) {
                run = false;
                break;
            }
        }
        if (run) {
            QList<qlonglong> result;
            for (int i = count - 1; i >= 0; --i) {
                result << stock.at(start + i); // ascending
            }

            return result;
        }
    }

    return QList<qlonglong>();
}

static int specKg(int spec)
{
    static const int kg[] = {5, 10, 15, 50};

    return kg[spec];
}

// sells a run of tickets of one size at the terminal, cash; the tickets sold are returned (empty when it failed)
static QList<qlonglong> sellTickets(Terminal &t, const QString &fuel, int spec, int count, int customer, const QString &usage, QString *error = 0)
{
    const QList<qlonglong> run = topRun(t, fuel, spec, count);
    if (run.isEmpty()) {
        if (error) {
            *error = "no run of " + QString::number(count) + " tickets of " + fuel + " " + QString::number(specKg(spec)) + " kg in stock";
        }

        return run;
    }
    TicketSale::Input input;
    input.fuelType = fuel;
    input.customerId = customer;
    input.usage = usage;
    input.priceX100 = fuel == "A" ? 820 : 750;
    input.barcodes = run;
    TicketSale::Line line;
    line.spec = spec;
    line.quantity = count;
    line.start = run.first() % 1000000000LL;
    line.end = run.last() % 1000000000LL;
    line.kgX100 = qlonglong(count) * specKg(spec) * 100;
    input.lines << line;
    const TicketSale::Result r = TicketSale::create(t.db(), input, t.userId, QDateTime::currentDateTime());
    if (!r.ok) {
        if (error) {
            *error = r.error;
        }

        return QList<qlonglong>();
    }

    return run;
}

// the boards: nozzle 1 Gasoline, nozzle 2 Diesel, both in auto mode (the supplier scans the tickets)
static bool startDevBoards(Plant &plant, Terminal &t)
{
    BoardProtocol::BoardConfig gasoline;
    gasoline.mode = BoardProtocol::ModeAuto;
    gasoline.fuelType = "A";
    gasoline.density = 7400;
    gasoline.pulsesX100 = 100000;
    BoardProtocol::BoardConfig diesel = gasoline;
    diesel.fuelType = "D";
    diesel.density = 8300;
    QString why;
    plant.config.addNozzle(1, "A", 0, 0, &why); // added by hand, as the technician does on Machine Config
    plant.config.addNozzle(2, "D", 0, 0, &why);
    plant.config.storeBoardConfig(1, gasoline);
    plant.config.storeBoardConfig(2, diesel);
    plant.config.setTank(1, t.one("SELECT id FROM tanks WHERE name LIKE 'T1%' LIMIT 1").toInt());
    plant.config.setTank(2, t.one("SELECT id FROM tanks WHERE name LIKE 'T2%' LIMIT 1").toInt());
    if (!plant.bus.start()) {
        return false;
    }
    plant.boards.connectTo(quint16(plant.bus.listenPort()));

    return waitFor([&]() { return plant.bus.isOnline(1) && plant.bus.isOnline(2); });
}

static int devrunScenario(Terminal &t, const QString &factsFile)
{
    QString message;
    const QDateTime started = QDateTime::currentDateTime();
    t.userId = t.one("SELECT id FROM users WHERE username = 'cashier1'").toInt();
    t.downloadTimeoutMs = 1800000;
    t.uploadTimeoutMs = 120000;

    step("download: the stock of tickets and what the web staff did");
    const bool downloaded = t.download(&message);
    check(downloaded, "the download finishes (" + message + ")");
    check(t.one("SELECT COUNT(*) FROM tickets").toInt() > 50000, "the terminal holds the tickets of the web app (" + t.one("SELECT COUNT(*) FROM tickets").toString() + ")");
    t.userId = t.one("SELECT id FROM users WHERE username = 'cashier1'").toInt();
    check(t.userId > 0, "cashier1 is among the users of the terminal");
    QList<int> customers;
    {
        QSqlQuery q(t.db());
        q.exec("SELECT id FROM customers WHERE deleted = 0 AND name <> 'Individual' ORDER BY id");
        while (q.next()) {
            customers << q.value(0).toInt();
        }
    }
    const int self = t.one("SELECT id FROM customers WHERE name = 'Individual'").toInt();
    check(customers.size() >= 4 && self > 0, "the customers came down (" + QString::number(customers.size()) + ")");
    if (!downloaded || customers.isEmpty()) {
        return 1;
    }

    // ---- 3. small ticket sales by the cashier
    step("small ticket sales by the cashier: 25 sales of 1 to 4 tickets");
    const struct { const char *fuel; int spec; } kinds[] = {{"A", 0}, {"A", 1}, {"A", 2}, {"D", 0}, {"D", 1}, {"D", 2}, {"D", 3}};
    int ticketSales = 0;
    int ticketsSold = 0;
    qlonglong ticketKgX100 = 0;
    QString why;
    for (int i = 0; i < 25; ++i) {
        const int count = 1 + (i % 4);
        const int k = i % 7;
        const QList<qlonglong> sold = sellTickets(t, kinds[k].fuel, kinds[k].spec, count, customers.at(i % customers.size()), "sale", &why);
        if (sold.isEmpty()) {
            check(false, "ticket sale " + QString::number(i + 1) + " (" + why + ")");
        } else {
            ++ticketSales;
            ticketsSold += count;
            ticketKgX100 += qlonglong(count) * specKg(kinds[k].spec) * 100;
        }
    }
    check(ticketSales == 25, "25 ticket sales saved (" + QString::number(ticketsSold) + " tickets, " + Decimal::format(ticketKgX100, 2) + " kg)");

    // ---- 4. 100 spot sales
    step("100 spot sales");
    int spotSales = 0;
    qlonglong spotKgX100 = 0;
    qlonglong spotTotalX100 = 0;
    int loans = 0;
    for (int i = 0; i < 100; ++i) {
        SpotSale::Input in;
        in.fuelType = (i % 2 == 0) ? "A" : "D";
        in.priceX100 = in.fuelType == "A" ? 820 : 750;
        in.kgX100 = 500 + (i * 137) % 3500;
        in.customerId = customers.at(i % customers.size());
        in.usage = "sale";
        if (i % 20 == 19) {
            in.usage = "ticket_loan";
            ++loans;
        } else if (i % 25 == 24) {
            in.usage = "self";
            in.customerId = self;
        } else if (i % 33 == 32) {
            in.usage = "other";
        }
        in.comment = (i % 10 == 0) ? "spot sale no. " + QString::number(i + 1) : QString();
        const SpotSale::Result r = SpotSale::create(t.db(), in, t.userId, QDateTime::currentDateTime());
        if (r.ok) {
            ++spotSales;
            spotKgX100 += in.kgX100;
            spotTotalX100 += r.totalX100;
        } else {
            check(false, "spot sale " + QString::number(i + 1) + " (" + r.error + ")");
        }
    }
    check(spotSales == 100, "100 spot sales saved: " + Decimal::format(spotKgX100, 2) + " kg, " + Decimal::format(spotTotalX100, 2) + " paid, " + QString::number(loans) + " on a ticket loan");

    // ---- 5. supplies on the simulated boards
    step("supplies: the supplier scans tickets at the dispenser (simulated boards)");
    FakeBoards boards;
    boards.add(1);
    boards.add(2);
    boards.board(1).flowGramsPerSecond = 500;
    boards.board(2).flowGramsPerSecond = 1000;
    Plant plant(&t, &boards);
    check(startDevBoards(plant, t), "both boards are online");
    check(plant.login(), "the cashier is signed in for the supplies");
    const int acme = customers.at(0);
    int supplies = 0;
    qlonglong dispensedX100 = 0;
    // the stop point of autoFill is in GRAMS (17500 = 17.50 kg)
    // 1: two 15 kg Diesel tickets, to the preset
    QList<qlonglong> tk = sellTickets(t, "D", 2, 2, acme, "sale", &why);
    check(!tk.isEmpty() && plant.autoFill(2, tk, 0), "supply 1 (Diesel, nozzle 2): two 15 kg tickets, filled to the preset 30 kg (" + plant.lastMessage + ")");
    ++supplies;
    // 2: 3 x 5 kg + 10 kg Diesel (25 kg), stopped at 17.5 kg: a remainder ticket of 7.5 kg
    QList<qlonglong> d5 = sellTickets(t, "D", 0, 3, acme, "sale", &why);
    QList<qlonglong> d10 = sellTickets(t, "D", 1, 1, acme, "sale", &why);
    tk = d5 + d10;
    const int remaindersBefore = plant.remainders.size();
    check(!tk.isEmpty() && plant.autoFill(2, tk, 17500), "supply 2 (Diesel): 25 kg of tickets, stopped at 17.50 kg (" + plant.lastMessage + ")");
    ++supplies;
    check(plant.remainders.size() == remaindersBefore + 1, "a remainder ticket was made");
    const qlonglong remainderD = plant.remainders.isEmpty() ? 0 : Barcode::toKey(plant.remainders.last());
    check(remainderD > 0 && t.one("SELECT amount_kg FROM tickets WHERE barcode = " + QString::number(remainderD)).toInt() == 750, "the remainder ticket holds 7.50 kg");
    // 3: the remainder ticket and one 5 kg ticket (12.5 kg), to the preset
    tk = sellTickets(t, "D", 0, 1, acme, "sale", &why);
    tk.prepend(remainderD);
    check(plant.autoFill(2, tk, 0), "supply 3 (Diesel): the remainder ticket 7.50 kg and a 5 kg ticket, filled to 12.50 kg (" + plant.lastMessage + ")");
    ++supplies;
    // 4: two 15 kg Gasoline tickets (30 kg), stopped at 22 kg
    tk = sellTickets(t, "A", 2, 2, acme, "sale", &why);
    const int remaindersBeforeA = plant.remainders.size();
    check(!tk.isEmpty() && plant.autoFill(1, tk, 22000), "supply 4 (Gasoline, nozzle 1): two 15 kg tickets, stopped at 22.00 kg (" + plant.lastMessage + ")");
    ++supplies;
    check(plant.remainders.size() == remaindersBeforeA + 1, "a Gasoline remainder ticket was made");
    const qlonglong remainderA = plant.remainders.isEmpty() ? 0 : Barcode::toKey(plant.remainders.last());
    // 5: the Gasoline remainder (8 kg) and a 5 kg + a 10 kg Gasoline ticket (23 kg), to the preset
    tk = sellTickets(t, "A", 0, 1, acme, "sale", &why) + sellTickets(t, "A", 1, 1, acme, "sale", &why);
    tk.prepend(remainderA);
    check(plant.autoFill(1, tk, 0), "supply 5 (Gasoline): the remainder 8 kg, a 5 kg and a 10 kg ticket, filled to 23 kg (" + plant.lastMessage + ")");
    ++supplies;
    // 6: a 50 kg Diesel ticket, stopped at 45 kg
    tk = sellTickets(t, "D", 3, 1, acme, "sale", &why);
    check(!tk.isEmpty() && plant.autoFill(2, tk, 45000), "supply 6 (Diesel): a 50 kg ticket, stopped at 45.00 kg (" + plant.lastMessage + ")");
    ++supplies;
    // 7 and 8: tickets the assistant sold on the web (bank sales): the terminal knows them from its download
    QList<qlonglong> web;
    {
        QSqlQuery q(t.db());
        q.exec("SELECT barcode FROM tickets WHERE status = 1 AND fuel_type = 'D' AND spec = 2 AND sale_id IS NOT NULL AND sale_id NOT IN (SELECT id FROM sales) ORDER BY barcode LIMIT 3");
        while (q.next()) {
            web << q.value(0).toLongLong();
        }
    }
    check(web.size() == 3, "three 15 kg Diesel tickets sold on the web are known to the terminal");
    check(web.size() == 3 && plant.autoFill(2, web, 0), "supply 7 (Diesel): three tickets sold by the assistant on the web, filled to the preset 45 kg (" + plant.lastMessage + ")");
    ++supplies;
    QList<qlonglong> webA;
    {
        QSqlQuery q(t.db());
        q.exec("SELECT barcode FROM tickets WHERE status = 1 AND fuel_type = 'A' AND spec = 2 AND sale_id IS NOT NULL AND sale_id NOT IN (SELECT id FROM sales) ORDER BY barcode LIMIT 2");
        while (q.next()) {
            webA << q.value(0).toLongLong();
        }
    }
    check(webA.size() == 2 && plant.autoFill(1, webA, 28000), "supply 8 (Gasoline): two tickets sold on the web, stopped at 28.00 kg (" + plant.lastMessage + ")");
    ++supplies;
    // refused scans
    if (!(waitFor([&]() { return plant.bus.lastStatus(2).state == BoardProtocol::StateIdle; }) && plant.selection.select(2, &why))) {
        check(false, "nozzle 2 can be chosen for the scans that must be refused (" + why + ")");
    } else {
        waitFor([&]() { return plant.selection.current() == 2 && plant.selection.isSelected(2); });
        const QList<qlonglong> stock = topRun(t, "D", 0, 1);
        plant.supply.scanned(Barcode::fromKey(stock.isEmpty() ? 1 : stock.first()));
        check(!plant.lastGood, "a ticket that was never sold is refused (" + plant.lastMessage + ")");
        plant.supply.scanned(Barcode::fromKey(tk.isEmpty() ? 1 : tk.first())); // the 50 kg ticket was used in supply 6
        check(!plant.lastGood, "a ticket that was already used is refused (" + plant.lastMessage + ")");
        const QList<qlonglong> gas = sellTickets(t, "A", 0, 1, acme, "sale", &why);
        plant.supply.scanned(Barcode::fromKey(gas.isEmpty() ? 1 : gas.first()));
        check(!plant.lastGood, "a Gasoline ticket on the Diesel nozzle is refused (" + plant.lastMessage + ")");
        plant.selection.deselect(2);
    }
    check(plant.recorded == supplies, QString::number(plant.recorded) + " supplies were recorded");
    {
        QSqlQuery q(t.db());
        q.exec("SELECT SUM(out_amount_kg) FROM supplements");
        if (q.next()) {
            dispensedX100 = q.value(0).toLongLong();
        }
    }
    out << "    dispensed: " << Decimal::format(dispensedX100, 2) << " kg in " << plant.recorded << " supplies, " << plant.remainders.size() << " remainder tickets\n";

    // ---- 7. ticket loans settled with tickets
    step("ticket loans made at the terminal are settled with tickets");
    QStringList loanIds;
    {
        QSqlQuery q(t.db());
        q.exec("SELECT id FROM ticket_loans WHERE sale_id IS NOT NULL AND settled_amount_kg = 0 ORDER BY loaned_at LIMIT 3");
        while (q.next()) {
            loanIds << q.value(0).toString();
        }
    }
    check(loanIds.size() == 3, "three open loans of the terminal (" + QString::number(loanIds.size()) + ")");
    for (int n = 0; n < loanIds.size(); ++n) {
        const qlonglong amount = t.one("SELECT amount_kg FROM ticket_loans WHERE id = '" + loanIds.at(n) + "'").toLongLong(); // kg x 100
        const int fives = int((amount + 499) / 500);
        const int want = (n == 2) ? qMax(1, fives / 2) : fives; // the third loan is settled only in part
        const QList<qlonglong> sold = sellTickets(t, "D", 0, want, customers.at(n % customers.size()), "sale", &why);
        const LoanSettlement::Result r = LoanSettlement::create(t.db(), loanIds.at(n), sold, t.userId, QDateTime::currentDateTime());
        if (n < 2) {
            check(!sold.isEmpty() && r.ok && r.balanceX100 <= 0, "loan " + QString::number(n + 1) + " (" + Decimal::format(amount, 2) + " kg) is settled with " + QString::number(want) + " tickets of 5 kg (balance " + Decimal::format(r.balanceX100, 2) + ", " + r.error + ")");
        } else {
            check(!sold.isEmpty() && r.ok && r.balanceX100 > 0, "loan 3 (" + Decimal::format(amount, 2) + " kg) is settled in part: " + Decimal::format(r.balanceX100, 2) + " kg are still owed (" + r.error + ")");
        }
    }
    if (!loanIds.isEmpty()) {
        const QList<qlonglong> reuse = QList<qlonglong>() << t.one("SELECT ticket_barcode FROM ticket_loan_settlements ORDER BY rowid LIMIT 1").toLongLong();
        check(!LoanSettlement::create(t.db(), loanIds.at(2), reuse, t.userId, QDateTime::currentDateTime()).ok, "a ticket that settled a loan cannot settle another");
    }

    // ---- upload
    step("upload everything");
    const int waiting = Uploader::pendingTotal(t.db());
    out << "    waiting for upload: " << waiting << "\n";
    int rejected = 0;
    int saved = 0;
    int totalSaved = 0;
    int totalRejected = 0;
    for (int round = 0; round < 8 && Uploader::pendingTotal(t.db()) > 0; ++round) {
        const bool ok = t.upload(QString(), &message, &rejected, &saved);
        totalSaved += saved;
        totalRejected += rejected;
        out << "    upload round " << (round + 1) << ": " << message << (ok ? "" : " (failed)") << "\n";
    }
    check(Uploader::pendingTotal(t.db()) == 0, "nothing waits any more (" + QString::number(totalSaved) + " records sent, " + QString::number(totalRejected) + " refused)");
    check(totalRejected == 0, "the web app refused nothing");
    check(t.one("SELECT COUNT(*) FROM upload_errors").toInt() == 0, "no refusals are kept");

    QJsonObject extra;
    extra.insert("ticketSales", ticketSales);
    extra.insert("ticketsSold", ticketsSold);
    extra.insert("ticketKgX100", double(ticketKgX100));
    extra.insert("spotSales", spotSales);
    extra.insert("spotKgX100", double(spotKgX100));
    extra.insert("spotTotalX100", double(spotTotalX100));
    extra.insert("supplies", plant.recorded);
    extra.insert("dispensedX100", double(dispensedX100));
    extra.insert("seconds", started.secsTo(QDateTime::currentDateTime()));
    writeFacts(t, factsFile, extra);
    out << "    the terminal part took " << started.secsTo(QDateTime::currentDateTime()) << " s\n";

    return 0;
}
