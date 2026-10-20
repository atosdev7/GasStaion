// The "cards" scenario of e2e.cpp (included there): the ledger of a card from one end to the other. The terminal registers a card, tops it up twice and fills
// with it on a simulated board, uploads; the web app turns that into ledger entries with their running balances; the terminal downloads them (its History
// list, first as "not confirmed yet" entries, then as the web app's entries); the web app tops the card up and deletes one of the terminal's top-ups
// (cards_step.php); the terminal downloads again and must show the same ledger and the same balance as the web app.

// "key=value" lines of a PHP step (cards_step.php)
static QMap<QString, QString> cardsStep(const QString &php, const QString &dir, const QStringList &arguments, bool *ok)
{
    QProcess p;
    p.start(php, QStringList() << dir + "/cards_step.php" << arguments);
    *ok = p.waitForFinished(300000) && p.exitCode() == 0;
    QMap<QString, QString> values;
    foreach (const QString &line, QString::fromLocal8Bit(p.readAllStandardOutput()).split('\n', QString::SkipEmptyParts)) {
        values.insert(line.section('=', 0, 0).trimmed(), line.section('=', 1).trimmed());
    }
    if (!*ok) {
        out << "    step " << arguments.join(" ") << ": " << QString::fromLocal8Bit(p.readAllStandardError()) << "\n";
    }

    return values;
}

static int cardsScenario(Terminal &t, const QString &factsFile, const QString &php, const QString &dir)
{
    QString message;

    step("the first download");
    const bool firstDownload = t.download(&message);
    check(firstDownload, "the first download finishes (" + message + ")");
    t.userId = t.one("SELECT id FROM users WHERE username = 'cash'").toInt();
    check(t.userId > 0, "the cashier came down");
    check(t.one("SELECT COUNT(*) FROM card_transactions").toInt() == 0, "the ledger is empty at first");

    FakeBoards boards;
    Plant::addBoards(&boards);
    Plant plant(&t, &boards);
    check(plant.login(), "the cashier logs in on the device");
    check(plant.start(), "both nozzle boards answer behind the converter");

    step("a card is registered and topped up on the terminal");
    const Cards::Registration registered = Cards::registerCard(t.db(), "E2ECARD01", "Li Wei", "D", "1234", "138 0000 1111");
    check(registered.ok, "the card is registered (" + registered.error + ")");
    const QString cardId = registered.cardId;
    const QDateTime now = QDateTime::currentDateTime();
    const Cards::TopUpResult first = Cards::topUp(t.db(), cardId, 4000, 750, t.userId, now.addSecs(-20));
    check(first.ok && first.balanceX100 == 4000 && first.totalX100 == 30000, "a top-up of 40.00 kg at 7.50 = 300.00 (" + first.error + ")");
    const Cards::TopUpResult second = Cards::topUp(t.db(), cardId, 1000, 800, t.userId, now.addSecs(-10));
    check(second.ok && second.balanceX100 == 5000 && second.totalX100 == 8000, "a top-up of 10.00 kg at 8.00 = 80.00 (" + second.error + ")");

    step("a fill with the card");
    QString why;
    const bool chosen = plant.selection.select(1, &why);
    check(chosen, "the supplier chooses nozzle 1 (" + why + ")");
    check(waitFor([&]() { return plant.selection.current() == 1 && plant.selection.isSelected(1); }), "the choice is kept");
    check(waitFor([&]() { return boards.board(1).beeps == 1; }), "the board has beeped");
    pause(200);                                                                // and its answer came: the command slot is free
    const int recordedBefore = plant.recorded;
    const bool started = plant.supply.startCard(1, cardId, 2000, &why);
    check(started, "the fill of 20.00 kg is started (" + why + ")");
    check(waitFor([&]() { return boards.board(1).state == BoardProtocol::StateDispensing; }), "the board dispenses");
    boards.simulator().tick(1000000);
    check(waitFor([&]() { return plant.recorded > recordedBefore && boards.board(1).state == BoardProtocol::StateIdle; }), "the fill is recorded and acknowledged (" + plant.lastMessage + ")");
    check(Cards::findById(t.db(), cardId).balanceX100 == 3000, "the card holds 30.00 kg on the terminal");

    step("the History list before the web app has entered anything");
    QList<CardLedger::Entry> entries = CardLedger::entries(t.db(), cardId, 50);
    check(entries.size() == 3, "three entries, all made on the terminal (" + QString::number(entries.size()) + ")");
    bool allProvisional = true;
    for (int i = 0; i < entries.size(); ++i) {
        allProvisional = allProvisional && entries.at(i).provisional;
    }
    check(allProvisional, "all of them are marked as not confirmed yet");
    check(!entries.isEmpty() && entries.first().type == "fill" && entries.first().balanceX100 == 3000, "the newest is the fill and the balance after it is 30.00");

    step("Upload data");
    int rejected = 0;
    int saved = 0;
    const bool uploaded = t.upload(QString(), &message, &rejected, &saved);
    check(uploaded, "the upload finishes (" + message + ")");
    check(rejected == 0, "the web app refused nothing (" + QString::number(rejected) + ")");
    check(Uploader::pendingTotal(t.db()) == 0, "nothing waits any more");

    step("the web app works on the card, the terminal downloads");
    const bool secondDownload = t.download(&message);
    check(secondDownload, "the download finishes (" + message + ")");
    check(t.one("SELECT COUNT(*) FROM card_transactions WHERE card_id = '" + cardId + "'").toInt() == 3, "the three ledger entries came down");
    entries = CardLedger::entries(t.db(), cardId, 50);
    bool noneProvisional = true;
    for (int i = 0; i < entries.size(); ++i) {
        noneProvisional = noneProvisional && !entries.at(i).provisional;
    }
    check(entries.size() == 3 && noneProvisional, "the History list shows the web app's entries, none marked any more");
    QList<qlonglong> balances;
    for (int i = entries.size() - 1; i >= 0; --i) {
        balances << entries.at(i).balanceX100;
    }
    check(balances == (QList<qlonglong>() << 4000 << 5000 << 3000), "with the balances 40.00, 50.00 and 30.00 after each");

    bool ok = false;
    const QMap<QString, QString> web = cardsStep(php, dir, QStringList() << "webtopup" << cardId << "5", &ok);
    check(ok && !web.value("sale").isEmpty(), "a top-up of 5.00 kg is made on the web");
    const QMap<QString, QString> deleted = cardsStep(php, dir, QStringList() << "delete" << second.saleId, &ok);
    check(ok && deleted.value("balance") == "25.00", "the 10.00 kg top-up of the terminal is deleted on the web: the web balance is 25.00 (" + deleted.value("balance") + ")");

    const bool thirdDownload = t.download(&message);
    check(thirdDownload, "the next download finishes (" + message + ")");
    check(t.one("SELECT COUNT(*) FROM card_transactions WHERE card_id = '" + cardId + "'").toInt() == 5, "five entries now: the web top-up and the reversal came down");
    entries = CardLedger::entries(t.db(), cardId, 50);
    check(entries.size() == 5 && entries.first().type == "reversal" && entries.first().kgX100 == -1000 && entries.first().balanceX100 == 2500,
          "the newest is the reversal of the deleted top-up and the balance after it is 25.00");
    check(Cards::findById(t.db(), cardId).balanceX100 == 2500, "the card holds 25.00 kg on the terminal, as on the web app");
    check(Uploader::pendingTotal(t.db()) == 0, "the download changed nothing that waits");

    QJsonObject extra;
    extra.insert("card_id", cardId);
    extra.insert("ledger", dump(t, "SELECT id, card_id, type, kg, balance_kg, price, total_amount, source_id, occurred_at, seq FROM card_transactions ORDER BY occurred_at, seq"));
    extra.insert("card_balance", Cards::findById(t.db(), cardId).balanceX100);
    extra.insert("topup_sale", second.saleId);
    extra.insert("web_sale", web.value("sale"));
    writeFacts(t, factsFile, extra);

    return 0;
}
