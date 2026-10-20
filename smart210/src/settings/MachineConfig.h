#ifndef MACHINECONFIG_H
#define MACHINECONFIG_H

#include "BoardProtocol.h"

#include <QList>
#include <QSqlDatabase>
#include <QString>

// What the technician sets on the terminal (Settings > Machine Config): the nozzles, the port the converter connects to, and the server
// address (see ServerForm). The nozzles are the boards 1 to NozzleCount: the number of a nozzle is the address of its board. A board
// holds its own config (mode, fuel type, density, pulses per litre); what is kept here is what the board last reported (see
// NozzleConfigSync), plus the tank, which the board does not know and which is picked here. Every change is written to the change log
// with the cashier who was logged in.
class MachineConfig
{
public:
    struct Nozzle {
        Nozzle() : id(0), number(0), tankId(0), mode(BoardProtocol::ModeAuto), density(-1), pulsesPerLiter(-1) {}
        int id;               // 0 while the board has never answered
        int number;           // 1 to NozzleCount, also the board's address
        QString fuelType;     // as the board reported it
        QString tank;         // name; empty when none is assigned
        int tankId;           // 0 when none is assigned
        int mode;             // BoardProtocol::ModeAuto or ModeManual
        qlonglong density;    // kg per litre x 10000; -1 when the board has none
        qlonglong pulsesPerLiter; // x100; -1 when the board has none
        QString seenAt;       // when the board last answered with its config; empty if never

        bool seen() const { return !seenAt.isEmpty(); }
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

    static const int NozzleCount = 6; // how many nozzle boards there are: addresses 1 to NozzleCount
    static const int DefaultConverterPort = 4001;

    explicit MachineConfig(QSqlDatabase db);

    // The changes are written as text lines to this file (on the SD card), one for each change: "2026-10-08 06:47:18: nozzle:1: old -> new
    // (Cash One)". Nothing is written while no file is set. They are not kept in the database and never uploaded.
    void setLogFile(const QString &path) { logFile_ = path; }

    // Always NozzleCount entries, numbers 1 to NozzleCount; a nozzle whose board never answered is blank (seen() is false).
    QList<Nozzle> nozzles() const;
    Nozzle nozzle(int number) const;

    // The fuel types of the last config download.
    QList<Fuel> fuels() const;

    // The tanks of the last config download, to pick from (all, or only those holding this fuel type).
    QList<Tank> tanks(const QString &fuelType = QString()) const;

    // The board answered with its config: it becomes the nozzle's config (the nozzle is made on the first answer). The tank is kept when it
    // still holds the board's fuel type; otherwise the only tank with that fuel type is taken, or none. Not logged (see describe()).
    bool storeBoardConfig(int number, const BoardProtocol::BoardConfig &config);

    // Picks the tank of a nozzle that has answered; the tank must hold the nozzle's fuel type. The nozzle waits for the next upload.
    bool setTank(int number, int tankId);

    // The nozzle in a line for the change log, in no language: "auto D 0.8300 1200.00 T1".
    static QString describe(const Nozzle &nozzle);

    void logChange(const QString &what, const QString &oldValue, const QString &newValue, int userId);

    // The last changes in the log file, newest first.
    QList<Change> recentChanges(int limit) const;

private:
    static int rememberedTank(int number);
    int tankFor(const QString &fuelType, int currentTankId) const;

    QSqlDatabase db_;
    QString logFile_;
};

#endif
