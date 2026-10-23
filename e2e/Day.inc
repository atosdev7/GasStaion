// The "day" scenario of e2e.cpp (included there): a business day from one end to the other, with both programs. The terminal does its shift (the
// "basic" scenario) and uploads; verify.php compares the two; then the web staff work through the web app's own pages (webday.php: a credit sale,
// a part payment, a return, a wrong sale deleted, a supply typed in, the daily confirmation, taking a confirmation back, the reports, the periodic
// backup); then the terminal downloads and must hold what the web staff did. Rules from REVIEW_2026-10-20.md that the code does not keep yet are shown
// as "ISSUE" lines and do not fail the run.

static int knownIssues = 0;

static int dayScenario(Terminal &t, const QString &factsFile, const QString &php, const QString &dir, const QString &host, int port)
{
    const int code = basicScenario(t, factsFile);
    if (code != 0) {
        return code;
    }

    step("the web app agrees with the terminal's shift (verify.php)");
    {
        QProcess verify;
        verify.start(php, QStringList() << dir + "/verify.php" << "basic" << factsFile);
        const bool finished = verify.waitForFinished(300000);
        const QStringList lines = QString::fromLocal8Bit(verify.readAllStandardOutput()).split('\n', QString::SkipEmptyParts);
        check(finished && verify.exitCode() == 0, "every record of the shift is on the server with the same values (" + (lines.isEmpty() ? QString() : lines.last().trimmed()) + ")");
        if (!(finished && verify.exitCode() == 0)) {
            foreach (const QString &line, lines) {
                if (line.contains("FAIL")) {
                    out << line << "\n";
                }
            }
        }
    }

    step("the web staff work through the web app's pages (webday.php)");
    QMap<QString, QString> web;
    {
        QProcess p;
        p.start(php, QStringList() << dir + "/webday.php" << host << QString::number(port));
        const bool finished = p.waitForFinished(900000);
        foreach (const QString &raw, QString::fromLocal8Bit(p.readAllStandardOutput()).split('\n')) {
            const QString line = raw.trimmed();
            if (line.isEmpty()) {
                continue;
            }
            if (line.startsWith('@')) {
                web.insert(line.mid(1).section('=', 0, 0), line.section('=', 1));
                continue;
            }
            if (line.startsWith("ok ")) {
                ++checks;
            } else if (line.startsWith("FAIL")) {
                ++checks;
                ++failures;
            } else if (line.startsWith("ISSUE")) {
                ++knownIssues;
            }
            out << (line.startsWith("==") ? "\n" : "  ") << line << "\n";
        }
        const QString errors = QString::fromLocal8Bit(p.readAllStandardError()).trimmed();
        if (!finished || (p.exitCode() != 0 && p.exitCode() != 1) || !errors.isEmpty()) {
            check(false, "the web step ran to its end (" + (finished ? errors.left(600) : QString("it did not finish")) + ")");
        }
    }

    step("the terminal downloads what the web staff did");
    QString message;
    check(t.download(&message), "the download finishes (" + message + ")");
    const qlonglong returned = web.value("returned").toLongLong();
    const qlonglong used = web.value("used").toLongLong();
    const qlonglong restricted = web.value("restricted").toLongLong();
    check(returned > 0 && t.one("SELECT status FROM tickets WHERE barcode = " + QString::number(returned)).toInt() == TicketStatus::Publish,
          "the ticket the customer brought back is in the terminal's stock again");
    const qlonglong returned2 = web.value("returned2").toLongLong();
    check(returned2 > 0 && t.one("SELECT status FROM tickets WHERE barcode = " + QString::number(returned2)).toInt() == TicketStatus::Publish,
          "a ticket returned from a sale the terminal itself made (and the web app had confirmed) is in the terminal's stock again");
    check(used > 0 && t.one("SELECT COUNT(*) FROM tickets WHERE barcode = " + QString::number(used) + " AND status = " + QString::number(TicketStatus::Publish)).toInt() == 0,
          "the ticket used for the supply typed in on the web cannot be sold at the terminal");
    check(restricted > 0 && t.one("SELECT COUNT(*) FROM tickets WHERE barcode = " + QString::number(restricted) + " AND status = " + QString::number(TicketStatus::Publish)).toInt() == 0,
          "the ticket of the sale that was deleted cannot be sold at the terminal");
    check(web.contains("published") && t.one("SELECT COUNT(*) FROM tickets WHERE status = 0").toInt() == web.value("published").toInt(),
          "the terminal's stock (" + t.one("SELECT COUNT(*) FROM tickets WHERE status = 0").toString() + " tickets) is the web app's stock (" + web.value("published") + ")");
    check(Uploader::pendingTotal(t.db()) == 0, "the download left nothing waiting to be uploaded");

    check(t.download(&message), "a second download works after the backup cleared old records (" + message + ")");
    check(t.one("SELECT COUNT(*) FROM tickets WHERE status = 0").toInt() == web.value("published").toInt(), "and changes nothing");

    return 0;
}
