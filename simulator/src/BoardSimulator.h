#ifndef BOARDSIMULATOR_H
#define BOARDSIMULATOR_H

#include "BoardProtocol.h"

#include <QByteArray>
#include <QDateTime>
#include <QList>
#include <QMap>
#include <QObject>
#include <QString>

class QSettings;

// The nozzle boards of protocol v0.2 (see PROTOCOL.md) without any network: what the firmware of the STM32 boards should do. It answers
// requests with the state machine of the protocol (the same answer again for a repeated ADDR + SEQ, a NAK for what the state does not
// allow, the two modes), runs fills over time (tick), and offers the actions of the people and of the hardware (the select key, the amount
// typed at the dispenser, a fault, a power cut) and of a bad bus (answers lost or spoiled). The simulator program and the tests share it.
class BoardSimulator : public QObject
{
    Q_OBJECT

public:
    struct Board {
        Board()
            : state(0), flags(0), fault(0), gunUp(false), beeps(0), resultId(0), nextId(100), presetG(0), dispensedG(0), dispensedMl(0), reason(0), lastAckedId(0),
              haveSeq(false), lastSeq(0), executed(0), dropReplies(0), corruptReplies(0), dropCommandOnce(0), corruptCommandOnce(0),
              silent(false), keepsNoConfig(false), silentMsLeft(0), flowGramsPerSecond(500), accumulated(0), supportsTime(true),
              clockOffsetSecs(QDateTime::currentDateTime().secsTo(QDateTime(QDate(2000, 1, 1), QTime(0, 0, 0))))
        {
        }

        int state, flags, fault;
        bool gunUp;             // the gun is up (reported in POLL as GUN_UP, as long as it is)
        int beeps;              // how many times the board beeped (it is chosen by a SELECT for its address)
        quint32 resultId, nextId, presetG, dispensedG, dispensedMl;
        int reason;
        BoardProtocol::BoardConfig config; // what the board holds (a board never configured has density and pulses 0)
        quint32 lastAckedId;
        bool haveSeq;
        int lastSeq;
        QByteArray lastAnswer;
        int executed;           // commands run (not counting a repeated ADDR + SEQ, and not POLL)
        int dropReplies;        // the next n answers are not sent
        int corruptReplies;     // the next n answers go out with a wrong CRC
        int dropCommandOnce;    // the answer to this command (first time it is sent) is not sent
        int corruptCommandOnce; // the answer to this command (first time it is sent) goes out with a wrong CRC
        bool silent;            // never answers
        bool keepsNoConfig;     // a faulty board: SET_CONFIG is answered but the new config is not kept (the answer holds the old one)
        int silentMsLeft;       // when above 0, silent ends after this long (a power cut)
        int flowGramsPerSecond; // how fast a fill runs
        double accumulated;     // grams so far in the fill, with the part of a gram
        bool supportsTime;      // false: an older board that does not know SET_TIME and GET_TIME (it answers BAD_COMMAND)
        qint64 clockOffsetSecs; // the board's clock is this far from the computer's (a new board starts at 2000-01-01 00:00:00)
        QList<int> commands;    // every command run, in order (not POLL)
        QList<int> requestSeqs; // SEQ of every request that reached the board
    };

    explicit BoardSimulator(QObject *parent = 0);

    // A board that is already configured (auto, fuel D, density 0.8300, 1000.00 pulses per litre), or one straight from the factory.
    void add(int address);
    void addBlank(int address);
    bool has(int address) const { return boards_.contains(address); }
    QList<int> addresses() const { return boards_.keys(); }
    Board &board(int address) { return boards_[address]; }
    const Board &board(int address) const
    {
        QMap<int, Board>::const_iterator it = boards_.constFind(address);

        return it == boards_.constEnd() ? blank_ : it.value();
    }

    // The answer to a request, as the bytes to put on the bus; empty when the board stays silent (no such board, silent, answer dropped).
    QByteArray answer(const BoardProtocol::Frame &request);

    // The time of the board's own clock (what the Smart210 set with SET_TIME, running on).
    QDateTime clockOf(int address) const { return QDateTime::currentDateTime().addSecs(board(address).clockOffsetSecs); }

    int requests() const { return requests_; }
    int pollsOf(int address) const { return polls_.value(address); }

    // ---- what people and the hardware do ----
    void pressSelect(int address);                      // the select key: the board only reports it (SELECT_KEY); the Smart210 chooses the nozzle with SELECT
    void pressClearKey(int address);                    // the clear key of the dispenser: only reported (CLEAR_KEY); the Smart210 answers with SELECT, which drops the preset
    bool liftGun(int address);                          // the supplier takes the gun up: the board only reports it (flag GUN_UP, a level); the Smart210 sends START
    void putGunDown(int address);                       // the gun goes back (it does by itself when the fill ends)
    bool typeAmount(int address, quint32 grams);        // manual mode: the supplier types the preset at the dispenser (the board is SET); the gun starts the fill
    void finish(int address, quint32 dispensedG, quint32 dispensedMl, int reason); // a fill ends at once: the result is held
    void stopFill(int address);                         // the supplier stops a fill (tank full or no flow): the result is held
    void setFault(int address, int code);               // a fault (1 flow sensor, 2 valve, 3 memory, 4 other); 0 clears it
    void powerCut(int address, int milliseconds = 3000); // the board is dark for a while; a fill that ran ends as interrupted

    // Lets time pass: the fills run, power cuts end.
    void tick(int milliseconds);

    // The config and the result counter are what a board keeps in flash.
    void save(QSettings &settings) const;
    void load(QSettings &settings);

    static QString stateName(int state);
    static QString commandName(int command);

signals:
    void changed(int address);                                                  // something about the board changed (state, fill, config)
    void exchanged(int address, const QString &request, const QString &reply);  // one request and what was answered, in words

private:
    QByteArray execute(Board &b, const BoardProtocol::Frame &f);
    QByteArray nak(const BoardProtocol::Frame &f, int error) const;
    QByteArray ok(const BoardProtocol::Frame &f, const QByteArray &data = QByteArray()) const;
    void startFill(Board &b);
    void endFill(Board &b, int reason);
    quint32 mlOf(const Board &b, quint32 grams) const;

    QMap<int, Board> boards_;
    Board blank_; // what board() gives for an address nobody has
    QMap<int, int> polls_;
    int requests_;
};

#endif
