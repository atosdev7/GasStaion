#ifndef NOZZLECONFIGSYNC_H
#define NOZZLECONFIGSYNC_H

#include "BoardBus.h"
#include "MachineConfig.h"

#include <QHash>
#include <QObject>
#include <QSet>
#include <QSqlDatabase>
#include <QString>

// Moves a nozzle's config between this terminal and its board. The config of a nozzle (mode, fuel type, density, pulses per litre) is kept in
// the database of this terminal, and nothing reads it from a board by itself. The nozzles are the ones added on Machine Config (a board address
// from 1 to MachineConfig::NozzleCount each).
//
//   send()    sends the config to the board (SET_CONFIG; the page saved it in the database first, see MachineConfig::saveConfig). The board answers
//             with what it stored; when that is exactly what was sent, the board is verified (updateDone()), otherwise updateFailed() says what it holds.
//   read()    asks the board for its config (GET_CONFIG) and saves what it reports as the nozzle's config (a board that holds none is not saved).
//
// The converter connects to this terminal (BoardBus listens).
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
    bool isPolled(int number) const { return bus_->isPolled(number); } // false for a nozzle in manual mode: its board is not polled
    bool canTalk(int number) const { return bus_->canTalk(number); }  // a config can be sent to or read from its board now

    // After a nozzle was added, removed or its config saved on Machine Config: the boards of the nozzles that exist are asked, the others are not,
    // and a nozzle in manual mode is out of the polling (only its config is sent or read when asked).
    void syncBoards();

    // Sends the config to the board. False when the board is not answering, a send or a read is already on its way, or the config is not one a
    // board accepts. The result comes as updateDone() or updateFailed().
    bool send(int number, const BoardProtocol::BoardConfig &config);

    // Reads the config of the board and saves it as the nozzle's. False when the board is not answering or a read or an update is already on its
    // way. The result comes as readDone() or readFailed().
    bool read(int number, int userId = 0);

    // The total of everything the board has dispensed (GET_TOTAL) and its reset (RESET_TOTAL, logged with the user and the value it had). False when
    // the board is not answering, a total command is already on its way for it, or the bus is busy with another command for it (try again). The result
    // comes as totalRead() / totalWasReset() or totalFailed(). A board that does not know the commands (older firmware) fails with BAD_COMMAND.
    bool readTotal(int number);
    bool resetTotal(int number, int userId);

    // A nozzle whose saved config the board has not confirmed: an Update was saved here but could not be sent, or the board did not keep it. It is
    // kept across restarts (a setting) and cleared by a verified Update, a Read, or removing the nozzle.
    bool differs(int number) const;
    void setDiffers(int number, bool differs);

    // The reason the last read or update of this board failed; empty when it went well.
    QString lastError(int number) const { return errors_.value(number); }

signals:
    void changed();                                  // a link, a board or a stored config changed: the page may show it again
    void updateDone(int number);                     // the board holds the config as it was sent
    void updateFailed(int number, const QString &message);
    void readDone(int number);                       // the board's config is now the nozzle's
    void readFailed(int number, const QString &message);
    void totalRead(int number, quint64 grams);       // GET_TOTAL answered
    void totalWasReset(int number, quint64 was);     // RESET_TOTAL answered: the total is 0 now, was is what it held (logged)
    void totalFailed(int number, const QString &message);

private slots:
    void boardOnline(int address, bool online);
    void commandDone(int address, int command, const QByteArray &data);
    void commandFailed(int address, int command, int error);
    void linkChanged(bool connected);

private:
    struct Update {
        Update() : sent(false) {}
        BoardProtocol::BoardConfig config;
        bool sent;
    };

    void work(); // sends what waits for a board that is free: updates first, then reads
    void failUpdate(int number, const QString &text);
    void failRead(int number, const QString &text);

    QSqlDatabase db_;
    MachineConfig config_;
    BoardBus *bus_;
    QHash<int, int> reading_;       // boards a read was asked of and has not answered yet: nozzle number => the user who asked
    QSet<int> reads_;               // the ones of them whose command is not sent yet
    QHash<int, Update> updates_;    // by nozzle number
    QHash<int, QString> errors_;
    QHash<int, int> totals_;        // boards a GET_TOTAL or RESET_TOTAL was sent to and has not answered yet: nozzle number => the user who asked (-1: a read)
};

#endif
