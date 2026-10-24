#ifndef APPRESET_H
#define APPRESET_H

#include <QSqlDatabase>
#include <QString>

// The two resets of Machine Config (Maintenance tab). Neither touches the web app.
//
//   Clean Data    erases what the terminal keeps in its database (tickets, sales, supplies, remainders, loans, cards, users, the session, the
//                 sync state: every table). The machine settings stay, so after a restart the terminal downloads everything again and is ready.
//                 Refused while any record waits for the upload: those records exist nowhere else.
//   Clear Config  erases the machine settings: the device file (server address, printer, converter port, card reader, upload mode, options, the
//                 tank of each nozzle) and what an older terminal kept of them in the database. The data stays. After a restart the terminal asks
//                 for the server again.
//
// The program is restarted afterwards (MainWindow::restartRequested): nothing it holds in memory is right any more.
class AppReset
{
public:
    // The records that wait for the upload (all of them).
    static int waiting(QSqlDatabase db);

    // False, with the reason, when it was refused or the database failed.
    static bool cleanData(QSqlDatabase db, QString *why);
    static bool clearConfig(QSqlDatabase db, QString *why);
};

#endif
