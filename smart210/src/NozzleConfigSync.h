#ifndef NOZZLECONFIGSYNC_H
#define NOZZLECONFIGSYNC_H

#include "BoardBus.h"
#include "MachineConfig.h"

#include <QHash>
#include <QObject>
#include <QSet>
#include <QSqlDatabase>
#include <QString>

// Keeps the nozzles' config in step with the boards. The nozzles are the boards 1 to MachineConfig::NozzleCount: every board that answers
// is asked for its config (GET_CONFIG), and what it reports is saved as the nozzle's config. A change made on the Machine Config page is
// sent to the board (SET_CONFIG); the board answers with what it stored, a read follows, and that is what is kept. The terminal never
// keeps a config the board did not confirm. The converter connects to this terminal (BoardBus listens).
class NozzleConfigSync : public QObject
{
    Q_OBJECT

public:
    NozzleConfigSync(QSqlDatabase db, BoardBus *bus, QObject *parent = 0);

    void setLogFile(const QString &path) { config_.setLogFile(path); } // where a change is written (see MachineConfig)
    bool start(int port);   // listens for the converter; false (see listenError()) when the port cannot be used
    bool restart(int port); // after the port was changed
    QString listenError() const { return bus_->errorText(); }

    bool isLinked() const { return bus_->isConnected(); }
    bool isOnline(int number) const { return bus_->isOnline(number); }

    // Asks every board that answers for its config again.
    void refresh();

    // Sends the config to the board; the nozzle's tank (0: leave it) is applied when the board has confirmed. False when the board is not
    // answering, an update is already on its way, or the config is not one a board accepts. The result comes as updateDone() or updateFailed().
    bool update(int number, const BoardProtocol::BoardConfig &config, int tankId, int userId);

    // The reason the last read or update of this board failed; empty when it went well.
    QString lastError(int number) const { return errors_.value(number); }

signals:
    void changed();                                  // a link, a board or a stored config changed: the page may show it again
    void updateDone(int number);                     // the board confirmed
    void updateFailed(int number, const QString &message);

private slots:
    void boardOnline(int address, bool online);
    void commandDone(int address, int command, const QByteArray &data);
    void commandFailed(int address, int command, int error);
    void linkChanged(bool connected);

private:
    struct Update {
        Update() : tankId(0), userId(0), sent(false) {}
        BoardProtocol::BoardConfig config;
        int tankId;
        int userId;
        bool sent;
        MachineConfig::Nozzle before;
    };

    void work(); // sends what waits for a board that is free: updates first, then reads

    MachineConfig config_;
    BoardBus *bus_;
    QSet<int> reads_;               // boards to ask for their config
    QHash<int, Update> updates_;    // by nozzle number
    QHash<int, QString> errors_;
};

#endif
