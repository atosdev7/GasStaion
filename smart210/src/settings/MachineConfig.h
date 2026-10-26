#ifndef MACHINECONFIG_H
#define MACHINECONFIG_H

#include "BoardProtocol.h"

#include <QList>
#include <QSqlDatabase>
#include <QString>

// What the technician sets on the terminal (Settings > Machine Config): the nozzles, the port the converter connects to, and the server
// address (see ServerForm). The nozzles are added by hand here (Add nozzle): a nozzle is a board address from 1 to NozzleCount that the technician
// gave to a fuel type, and only the nozzles that were added are asked, shown and used; a board that answers at another address is ignored. The number
// of a nozzle is the address of its board. A board holds its own config (mode, fuel type, density, pulses per litre); what is kept here is what the board last reported (see
// NozzleConfigSync), plus the tank, which the board does not know and which is picked here. Every change is written to the change log
// with the cashier who was logged in.
class MachineConfig
{
public:
    struct Nozzle {
        Nozzle() : id(0), number(0), tankId(0), mode(BoardProtocol::ModeAuto), density(-1), pulsesPerLiter(-1) {}
        int id;               // 0 for a nozzle that was not added
        int number;           // 1 to NozzleCount, also the board's address
        QString fuelType;
        QString tank;         // name; empty when none is assigned
        int tankId;           // 0 when none is assigned
        int mode;             // BoardProtocol::ModeAuto or ModeManual
        qlonglong density;    // kg per litre x 10000; -1 when none
        qlonglong pulsesPerLiter; // x100; -1 when none
        QString seenAt;       // when the config was last stored (verified after a send, or read from the board); empty if never
        QString name;         // typed on Machine Config (up to 50 characters); empty when it has none

        // What the lists show for the nozzle: its name, "Nozzle <number>" while it has none.
        QString label() const;
        bool seen() const { return !seenAt.isEmpty(); }
        bool exists() const { return id > 0; } // added on Machine Config
    };

    struct Fuel {
        QString code;
        QString name;
        qlonglong density; // kg per litre x 10000 from the last config download; -1 when the web app has none
    };

    struct Tank {
        int id;
        QString name;
        QString fuelType;
    };

    struct Change {
        QString changedAt;
        int userId;
        QString userName; // empty when unknown
        QString what; // a code, shown translated by the page: "nozzle:<nozzle number>", "converter", "printer" or "server"
        QString oldValue;
        QString newValue;
    };

    static const int NameLength = 50; // the longest name of a nozzle
    static const int NozzleCount = 6; // how many nozzle boards there are: addresses 1 to NozzleCount
    static const int DefaultConverterPort = 4001;

    explicit MachineConfig(QSqlDatabase db);

    // The changes are written as text lines to this file (on the SD card), one for each change: "2026-10-08 06:47:18: nozzle:1: old -> new
    // (Cash One)". Nothing is written while no file is set. They are not kept in the database and never uploaded.
    void setLogFile(const QString &path) { logFile_ = path; }

    // The nozzles that were added, by number; a nozzle whose config was not set yet (sent to its board and verified, or read from it) has seen()
    // false. A number that was not added has no entry.
    QList<Nozzle> nozzles() const;
    Nozzle nozzle(int number) const; // not exists() when it was not added
    bool isConfigured(int number) const { return nozzle(number).exists(); }

    // Adds a nozzle: the board address (1 to NozzleCount, not added yet), the fuel type it dispenses (a fuel type of the last download) and its
    // tank: a nozzle must be connected to a tank that holds its fuel type (0: the tank this address had before, or the only tank of the fuel type;
    // refused when there is none). Its config (mode, density, pulses per litre) is set later
    // on Machine Config: sent to the board, or read from it.
    // False, with the reason, when it is not possible. Logged.
    // `name` is optional (up to NameLength characters).
    bool addNozzle(int number, const QString &fuelType, int tankId, int userId, QString *why, const QString &name = QString());

    // Gives the nozzle its name (empty: none). Logged, and uploaded with the nozzle. False, with the reason, when the nozzle was not added or the name is too long.
    bool setNozzleName(int number, const QString &name, int userId, QString *why);

    // Takes a nozzle away: it is not asked, shown or used any more, and the web app is told. Refused while a fill is open on it or tickets are
    // queued on it (see removeBlocker()). Its records stay.
    bool removeNozzle(int number, int userId, QString *why);

    // Why a nozzle cannot be removed now (a fill is open, or tickets are queued on it), empty when it can.
    QString removeBlocker(int number) const;

    // Why the config of a nozzle cannot be changed now (a fill is open, or tickets are queued on it: they were checked against its fuel type),
    // empty when it can.
    QString configBlocker(int number) const;

    // The tank the nozzle keeps for this fuel type: its own (or the given current one) when it holds the fuel type, else the only tank that does,
    // else none (0). A nozzle must be connected to a tank.
    int tankFor(const QString &fuelType, int currentTankId) const;

    // The fuel types of the last config download.
    QList<Fuel> fuels() const;

    // The tanks of the last config download, to pick from (all, or only those holding this fuel type).
    QList<Tank> tanks(const QString &fuelType = QString()) const;

    // Saves a config as the nozzle's: what the board confirmed after a send, or what it reported when it was read. Only for a nozzle that was added
    // (false for any other address). The tank is kept when it still holds the fuel type; otherwise the only tank with that fuel type is taken, or
    // none. Not logged (see describe()).
    bool storeBoardConfig(int number, const BoardProtocol::BoardConfig &config);

    // Saves a config typed on Machine Config (the Update button): it is the nozzle's config from now on, whether its board has it yet or not, and it
    // is logged with the user. The tank follows the fuel type as in storeBoardConfig().
    bool saveConfig(int number, const BoardProtocol::BoardConfig &config, int userId);

    // Picks the tank of a nozzle that has answered; the tank must hold the nozzle's fuel type. The nozzle waits for the next upload.
    bool setTank(int number, int tankId);

    // The nozzle in a line for the change log, in no language: "auto D 0.8300 1200.00 T1".
    static QString describe(const Nozzle &nozzle);

    void logChange(const QString &what, const QString &oldValue, const QString &newValue, int userId);

    // The last changes in the log file, newest first.
    QList<Change> recentChanges(int limit) const;

private:
    static int rememberedTank(int number);

    QSqlDatabase db_;
    QString logFile_;
};

#endif
