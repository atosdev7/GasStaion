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

// The "cardsmany" scenario: five cards, each topped up and filled three times on the terminal (round by round, so that the ledger of the five cards is
// mixed), a fill that asks for more than the card holds (refused), upload; then the web app tops two cards up and deletes one of the terminal's top-ups
// (cards_step.php); the terminal downloads. After every step the balance of every card on the terminal is the one worked out here, and at the end the web
// app (verify.php) has the same ledger, balances and records for every card.
static int cardsManyScenario(Terminal &t, const QString &factsFile, const QString &php, const QString &dir)
{
    const int CardCount = 5;
    const int Rounds = 3;
    QString message;

    step("the first download");
    check(t.download(&message), "the first download finishes (" + message + ")");
    t.userId = t.one("SELECT id FROM users WHERE username = 'cash'").toInt();
    check(t.userId > 0, "the cashier came down");

    FakeBoards boards;
    Plant::addBoards(&boards);
    Plant plant(&t, &boards);
    check(plant.login(), "the cashier logs in on the device");
    check(plant.start(), "both nozzle boards answer behind the converter");

    step("five cards are registered");
    QStringList ids;
    QStringList uids;
    QList<qlonglong> expected;
    for (int i = 0; i < CardCount; ++i) {
        const QString uid = QString("E2EMANY%1").arg(i + 1, 2, 10, QChar('0'));
        const Cards::Registration reg = Cards::registerCard(t.db(), uid, QString("Holder %1").arg(i + 1), "D", "1234", QString("139 0000 %1").arg(1000 + i));
        check(reg.ok, "card " + uid + " is registered (" + reg.error + ")");
        ids << reg.cardId;
        uids << uid;
        expected << 0;
    }

    // one fill on nozzle 1 (an automatic nozzle) with a card: select the nozzle (when it is not selected), start, let the board dispense, wait for the record
    QString whyNot;
    auto fill = [&](const QString &cardId, qlonglong kgX100) -> bool {
        QString why;
        if (!(plant.selection.current() == 1 && plant.selection.isSelected(1))) {
            const int beeps = boards.board(1).beeps;
            if (!plant.selection.select(1, &why)) {
                whyNot = "select: " + why;

                return false;
            }
            if (!waitFor([&]() { return plant.selection.current() == 1 && plant.selection.isSelected(1) && boards.board(1).beeps > beeps; })) {
                whyNot = "the board did not beep";

                return false;
            }
            pause(200);
        }
        const int before = plant.recorded;
        if (!plant.supply.startCard(1, cardId, kgX100, &why)) {
            whyNot = why;

            return false;
        }
        if (!waitFor([&]() { return boards.board(1).state == BoardProtocol::StateDispensing; })) {
            whyNot = "the board did not start";

            return false;
        }
        boards.simulator().tick(1000000);
        if (!waitFor([&]() { return plant.recorded > before && boards.board(1).state == BoardProtocol::StateIdle; })) {
            whyNot = "the fill was not recorded (" + plant.lastMessage + ")";

            return false;
        }
        // the terminal has seen the board idle again (its poll runs a few times a second): only then may the next fill start
        if (!waitFor([&]() { return plant.bus.lastStatus(1).state == BoardProtocol::StateIdle; })) {
            whyNot = "the poll still shows the board busy";

            return false;
        }
        pause(300);

        return true;
    };

    step("top-ups and fills, round by round");
    const QDateTime base = QDateTime::currentDateTime().addSecs(-3600);
    int counter = 0;
    QStringList firstTopUpSale;
    for (int round = 0; round < Rounds; ++round) {
        for (int i = 0; i < CardCount; ++i) {
            const qlonglong topX100 = 3000 + 500 * i + 250 * round;       // 30.00 + 5 kg per card + 2.50 kg per round
            const qlonglong priceX100 = 700 + 25 * i;                       // 7.00 to 8.00
            const qlonglong fillX100 = topX100 * 3 / 5;                     // 60% of what was topped up
            const Cards::TopUpResult up = Cards::topUp(t.db(), ids.at(i), topX100, priceX100, t.userId, base.addSecs(20 * ++counter));
            expected[i] += topX100;
            check(up.ok && up.balanceX100 == expected.at(i) && up.totalX100 == (topX100 * priceX100 + 50) / 100, uids.at(i) + " round " + QString::number(round + 1) + ": top-up of "
                  + QString::number(topX100 / 100.0, 'f', 2) + " kg, balance " + QString::number(expected.at(i) / 100.0, 'f', 2) + " (" + up.error + ")");
            if (round == 0) {
                firstTopUpSale << up.saleId;
            }

            if (round == 0 && i == 0) {                                     // a card cannot be filled with more than it holds
                const bool tooMuch = fill(ids.at(i), expected.at(i) + 100);
                check(!tooMuch && whyNot.contains("holds only"), "a fill of more than the card holds is refused (" + whyNot + ")");
                check(Cards::findById(t.db(), ids.at(i)).balanceX100 == expected.at(i), "the refused fill changed nothing");
            }

            const bool filled = fill(ids.at(i), fillX100);
            expected[i] -= fillX100;
            check(filled, uids.at(i) + " round " + QString::number(round + 1) + ": a fill of " + QString::number(fillX100 / 100.0, 'f', 2) + " kg is recorded (" + whyNot + ")");
            check(Cards::findById(t.db(), ids.at(i)).balanceX100 == expected.at(i), uids.at(i) + " holds " + QString::number(expected.at(i) / 100.0, 'f', 2) + " kg on the terminal");
        }
    }
    check(t.one("SELECT COUNT(*) FROM supplements WHERE card_id IS NOT NULL").toInt() == CardCount * Rounds, "fifteen fills are recorded as supplements with a card");
    check(t.one("SELECT COUNT(*) FROM sales WHERE sale_type = 'card'").toInt() == CardCount * Rounds, "fifteen top-ups are recorded as card sales");

    step("Fill Manual: the supplier filled at the manual nozzle 2, the cashier types what came out");
    const qlonglong density = plant.config.nozzle(2).density;
    check(density > 0, "the density of nozzle 2 is known (" + QString::number(density) + ")");
    const qlonglong manualKg = 500;                                            // 5.00 kg from card 1
    const qlonglong manualLiters = density > 0 ? (manualKg * 10000 + density / 2) / density : 0;
    QString manualWhy;
    check(plant.supply.recordManualCard(2, ids.at(0), manualKg, manualLiters, QDateTime::currentDateTime().addSecs(-30), &manualWhy), "a fill of 5.00 kg is typed in for card 1 (" + manualWhy + ")");
    expected[0] -= manualKg;
    check(Cards::findById(t.db(), ids.at(0)).balanceX100 == expected.at(0), uids.at(0) + " holds " + QString::number(expected.at(0) / 100.0, 'f', 2) + " kg on the terminal");
    check(!plant.supply.recordManualCard(1, ids.at(0), 100, 120, QDateTime::currentDateTime().addSecs(-30), &manualWhy) && manualWhy.contains("auto mode"), "nozzle 1 (auto) is refused for a manual fill (" + manualWhy + ")");

    step("Upload data");
    int rejected = 0;
    int saved = 0;
    check(t.upload(QString(), &message, &rejected, &saved), "the upload finishes (" + message + ")");
    check(rejected == 0, "the web app refused nothing (" + QString::number(rejected) + ")");
    check(Uploader::pendingTotal(t.db()) == 0, "nothing waits any more");

    step("the web app tops two cards up and deletes a top-up of the terminal");
    bool ok = false;
    const QMap<QString, QString> web2 = cardsStep(php, dir, QStringList() << "webtopup" << ids.at(2) << "12", &ok);
    check(ok && !web2.value("sale").isEmpty(), "12.00 kg are topped up on the web on card 3");
    expected[2] += 1200;
    const QMap<QString, QString> web4 = cardsStep(php, dir, QStringList() << "webtopup" << ids.at(4) << "8", &ok);
    check(ok && !web4.value("sale").isEmpty(), "8.00 kg are topped up on the web on card 5");
    expected[4] += 800;
    const QMap<QString, QString> deleted = cardsStep(php, dir, QStringList() << "delete" << firstTopUpSale.at(1), &ok);
    expected[1] -= 3500;                                                 // the first top-up of card 2: 30.00 + 5.00
    check(ok && deleted.value("balance") == QString::number(expected.at(1) / 100.0, 'f', 2), "the first top-up of card 2 (35.00 kg) is deleted on the web: its balance is "
          + QString::number(expected.at(1) / 100.0, 'f', 2) + " (" + deleted.value("balance") + ")");

    step("the terminal downloads: the same balances and ledgers");
    check(t.download(&message), "the download finishes (" + message + ")");
    const int entryCounts[] = {7, 7, 7, 6, 7};                           // 3 top-ups and 3 fills (card 1: and the fill typed in); the web top-ups and the reversal on top
    for (int i = 0; i < CardCount; ++i) {
        check(Cards::findById(t.db(), ids.at(i)).balanceX100 == expected.at(i), uids.at(i) + " holds " + QString::number(expected.at(i) / 100.0, 'f', 2) + " kg on the terminal, as on the web app");
        check(t.one("SELECT COUNT(*) FROM card_transactions WHERE card_id = '" + ids.at(i) + "'").toInt() == entryCounts[i], uids.at(i) + " has " + QString::number(entryCounts[i]) + " ledger entries");
        const QList<CardLedger::Entry> entries = CardLedger::entries(t.db(), ids.at(i), 50);
        bool noneProvisional = true;
        for (int e = 0; e < entries.size(); ++e) {
            noneProvisional = noneProvisional && !entries.at(e).provisional;
        }
        check(entries.size() == entryCounts[i] && noneProvisional, uids.at(i) + ": the History list shows the web app's entries, none marked as not confirmed");
    }
    check(Uploader::pendingTotal(t.db()) == 0, "the download changed nothing that waits");

    QJsonObject extra;
    QJsonArray cards;
    for (int i = 0; i < CardCount; ++i) {
        QJsonObject card;
        card.insert("id", ids.at(i));
        card.insert("uid", uids.at(i));
        card.insert("expected_x100", expected.at(i));
        card.insert("entries", entryCounts[i]);
        cards.append(card);
    }
    extra.insert("cards", cards);
    extra.insert("ledger", dump(t, "SELECT id, card_id, type, kg, balance_kg, price, total_amount, source_id, occurred_at, seq FROM card_transactions ORDER BY card_id, occurred_at, seq"));
    extra.insert("deleted_sale", firstTopUpSale.at(1));
    writeFacts(t, factsFile, extra);

    return 0;
}
