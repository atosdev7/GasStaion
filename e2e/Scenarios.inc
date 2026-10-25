// The scenarios of e2e.cpp (included there).

static qlonglong press(int serial)
{
    return 6000000000LL + serial; // sign 00000, code 06 (the printing press), serial
}

static SpotSale::Input spot(const QString &usage, int customer, qlonglong kgX100)
{
    SpotSale::Input input;
    input.usage = usage;
    input.fuelType = "D";
    input.customerId = customer;
    input.priceX100 = 750;
    input.kgX100 = kgX100;

    return input;
}

// ---- 9.1: one shift of every kind of business ----

static int basicScenario(Terminal &t, const QString &factsFile)
{
    QString message;

    step("the first download");
    check(t.download(&message), "the first download finishes (" + message + ")");
    check(t.one("SELECT COUNT(*) FROM tickets WHERE status = 0").toInt() == 15, "15 tickets in stock came down");
    check(t.one("SELECT COUNT(*) FROM tickets WHERE status = 1").toInt() == 14, "14 tickets sold on the web came down");
    check(t.one("SELECT COUNT(*) FROM ticket_loans").toInt() == 1, "the loan made on the web came down");
    check(TicketNumbers::available(t.db(), "remainder") && TicketNumbers::available(t.db(), "direct"), "the ticket numbers are known");
    t.userId = t.one("SELECT id FROM users WHERE username = 'cash'").toInt();
    const int acme = t.customer("ACME");
    const int beta = t.customer("Beta Ltd");
    const int self = t.customer("Individual");
    check(t.userId > 0 && acme > 0 && beta > 0 && self > 0, "the cashier and the customers came down");

    FakeBoards boards;
    Plant::addBoards(&boards);
    Plant plant(&t, &boards);
    check(plant.login(), "the cashier logs in on the device (offline password check)");
    check(plant.start(), "both nozzle boards answer behind the converter");

    step("spot sales of every use");
    const QDateTime now = QDateTime::currentDateTime();
    const SpotSale::Result sale = SpotSale::create(t.db(), spot("sale", acme, 1050), t.userId, now);
    check(sale.ok && sale.totalX100 == 7875, "a spot sale 10.50 kg x 7.50 = 78.75");
    check(SpotSale::create(t.db(), spot("self", self, 800), t.userId, now).ok, "own use 8.00 kg");
    check(SpotSale::create(t.db(), spot("other", beta, 600), t.userId, now).ok, "other use 6.00 kg");
    const SpotSale::Result loanSale = SpotSale::create(t.db(), spot("ticket_loan", acme, 2500), t.userId, now);
    check(loanSale.ok, "fuel on ticket loan 25.00 kg");
    const QString deviceLoan = t.one("SELECT id FROM ticket_loans WHERE sale_id = '" + loanSale.saleId + "'").toString();
    check(!deviceLoan.isEmpty(), "the loan of that sale exists");

    step("ticket sales");
    TicketSale::Input ticketSale;
    ticketSale.fuelType = "D";
    ticketSale.customerId = beta;
    ticketSale.usage = "sale";
    ticketSale.priceX100 = 750;
    ticketSale.barcodes << press(1) << press(2) << press(3);
    TicketSale::Line threeFives;                 // the item as the cashier entered it: spec 0 (5 kg), serials 1 to 3
    threeFives.spec = 0;
    threeFives.quantity = 3;
    threeFives.start = 1;
    threeFives.end = 3;
    threeFives.kgX100 = 1500;
    ticketSale.lines << threeFives;
    const TicketSale::Result sold = TicketSale::create(t.db(), ticketSale, t.userId, now);
    check(sold.ok && sold.kgX100 == 1500 && sold.totalX100 == 11250, "three 5 kg tickets sold for 112.50 (" + sold.error + ")");
    TicketSale::Input own;
    own.fuelType = "D";
    own.customerId = self;
    own.usage = "self";
    own.priceX100 = 750;
    own.barcodes << press(101);
    TicketSale::Line oneTen;                     // spec 1 (10 kg), serial 101
    oneTen.spec = 1;
    oneTen.quantity = 1;
    oneTen.start = 101;
    oneTen.end = 101;
    oneTen.kgX100 = 1000;
    own.lines << oneTen;
    check(TicketSale::create(t.db(), own, t.userId, now).ok, "a 10 kg ticket for own use");

    step("ticket loans settled");
    const QString webLoan = t.one("SELECT id FROM ticket_loans WHERE sale_id IS NULL").toString();
    LoanSettlement::Result paid = LoanSettlement::create(t.db(), webLoan, QList<qlonglong>() << press(201) << press(202) << press(203) << press(204), t.userId, now);
    check(paid.ok && paid.balanceX100 == 0, "the loan of the web app (20 kg) is settled with four 5 kg tickets (" + paid.error + ")");
    LoanSettlement::Result paid2 = LoanSettlement::create(t.db(), deviceLoan, QList<qlonglong>() << press(401) << press(402) << press(205), t.userId, now);
    check(paid2.ok && paid2.balanceX100 == 0, "the loan made here (25 kg) is settled with 10 + 10 + 5 kg tickets (" + paid2.error + ")");
    check(!LoanSettlement::create(t.db(), webLoan, QList<qlonglong>() << press(201), t.userId, now).ok, "a ticket cannot settle twice");

    step("supplies on the boards");
    // 1: auto, two 15 kg tickets (30 kg), the supplier stops the fill at 12 kg: 18 kg come back as a remainder ticket
    const bool fill1 = plant.autoFill(1, QList<qlonglong>() << press(301) << press(302), 12000);
    check(fill1, "auto supply 1: two 15 kg tickets, stopped at 12 kg (" + plant.lastMessage + ")");
    check(plant.remainders.size() == 1, "a remainder ticket was made");
    const qlonglong remainderKey = plant.remainders.isEmpty() ? 0 : Barcode::toKey(plant.remainders.first());
    check(t.one("SELECT amount_kg FROM tickets WHERE barcode = " + QString::number(remainderKey)).toInt() == 1800, "the remainder ticket holds 18.00 kg");
    check(t.one("SELECT out_amount_kg FROM supplements ORDER BY supplied_at, id LIMIT 1").toInt() == 1200, "12.00 kg were dispensed");
    check(t.one("SELECT out_amount_lt FROM supplements ORDER BY supplied_at, id LIMIT 1").toInt() == 1446, "= 14.46 litres at 0.83 kg/L");
    // 2: auto again, the remainder ticket and a 5 kg ticket (23 kg), the fill runs to its preset
    const bool fill2 = plant.autoFill(1, QList<qlonglong>() << remainderKey << press(206), 0);
    check(fill2, "auto supply 2: the remainder ticket and a 5 kg ticket, run to the preset (" + plant.lastMessage + ")");
    check(plant.remainders.size() == 1, "no second remainder: the fill reached its preset");
    // 3: manual mode: 10 kg typed at the dispenser; the app acknowledges the result and records nothing; the cashier types the supply in
    const int before = plant.recorded;
    const int suppliesBefore = t.one("SELECT COUNT(*) FROM supplements").toInt();
    check(boards.simulator().typeAmount(2, 10000), "the supplier types 10 kg at the manual dispenser");
    check(waitFor([&]() { return plant.bus.lastStatus(2).presetG == 10000; }), "the poll shows the preset typed at the dispenser");
    check(boards.simulator().liftGun(2), "the gun is taken up: the manual board starts by itself");
    check(waitFor([&]() { return plant.bus.lastStatus(2).state == BoardProtocol::StateDispensing; }), "the poll shows the fill");
    boards.simulator().tick(20000);
    check(waitFor([&]() { return boards.board(2).state == BoardProtocol::StateIdle && plant.bus.lastStatus(2).state == BoardProtocol::StateIdle; }), "the board ends it by itself: nothing is reported, the poll shows IDLE again");
    check(plant.recorded == before && t.one("SELECT COUNT(*) FROM supplements").toInt() == suppliesBefore, "the app only polled: nothing was recorded");
    const int waitingBefore = Uploader::pendingTotal(t.db());
    QString why;
    const bool typedIn = plant.supply.recordManual(2, 1000, 1205, QList<qlonglong>() << press(207) << press(208), now, &why);
    check(typedIn, "the cashier types the 10 kg supply in with two 5 kg tickets (" + why + ")");
    const QString manualSupplement = plant.lastSupplement;
    check(Uploader::pendingTotal(t.db()) == waitingBefore + 3, "it and its two tickets are ready to upload");
    // 4: a supply typed in for another nozzle with its ticket
    check(plant.supply.recordManual(1, 500, 602, QList<qlonglong>() << press(209), now, &why), "5.00 kg / 6.02 litres typed in with a 5 kg ticket (" + why + ")");
    const QString handSupplement = plant.lastSupplement;

    step("the cashier's review, then Upload data");
    const QString firstSale = sale.saleId;
    QList<ReviewData::Ref> refs;
    refs << ReviewData::Ref(ReviewData::Sale, firstSale);
    check(ReviewData::setComment(t.db(), refs, "paid by card") == 1, "a comment on the first sale");
    check(Uploader::pendingTotal(t.db()) > 0, "records wait for the upload");
    int rejected = 0;
    int saved = 0;
    const bool uploaded = t.upload(QString(), &message, &rejected, &saved);
    check(uploaded, "the upload finishes (" + message + ")");
    check(rejected == 0, "the web app refused nothing (" + QString::number(rejected) + ")");
    check(Uploader::pendingTotal(t.db()) == 0, "nothing waits any more");
    check(t.one("SELECT COUNT(*) FROM upload_errors").toInt() == 0, "no refusals are kept");
    check(saved > 30, "records were saved (" + QString::number(saved) + ")");
    check(t.upload(QString(), &message, &rejected, &saved) && saved == 0, "a second upload sends nothing");

    step("edits after the upload go up again, one date at a time");
    const QString today = Checkout::businessDay(QDateTime::currentDateTime());
    const QString yesterday = QDate::fromString(today, "yyyy-MM-dd").addDays(-1).toString("yyyy-MM-dd");
    QList<ReviewData::Ref> moved;
    moved << ReviewData::Ref(ReviewData::Sale, sold.saleId);
    check(ReviewData::setDate(t.db(), moved, yesterday) == 1, "the ticket sale is moved to the day before");
    QList<ReviewData::Ref> commented;
    commented << ReviewData::Ref(ReviewData::Supplement, manualSupplement);
    check(ReviewData::setComment(t.db(), commented, "ticket check done") == 1, "a comment on the manual supply");
    check(Uploader::pendingTotal(t.db()) == 2, "two records wait");
    check(Uploader::pendingTotal(t.db(), yesterday) == 1 && Uploader::pendingTotal(t.db(), today) == 1, "one on each date");
    check(t.upload(yesterday, &message, &rejected, &saved) && saved == 1 && rejected == 0, "Upload of the day before sends only that record");
    check(Uploader::pendingTotal(t.db()) == 1, "the record of today still waits");
    check(t.upload(today, &message, &rejected, &saved) && saved == 1 && rejected == 0, "Upload of today sends the rest");

    step("the next download");
    const bool downloaded = t.download(&message);
    check(downloaded, "the download finishes (" + message + ")");
    check(t.one("SELECT settled_amount_kg FROM ticket_loans WHERE id = '" + webLoan + "'").toInt() == 2000, "the web loan is settled 20.00 kg, as the web app sums it");
    check(t.one("SELECT settled_amount_kg FROM ticket_loans WHERE id = '" + deviceLoan + "'").toInt() == 2500, "the loan made here is settled 25.00 kg");
    check(Uploader::pendingTotal(t.db()) == 0, "the download changed nothing that waits");

    writeFacts(t, factsFile);

    return 0;
}

