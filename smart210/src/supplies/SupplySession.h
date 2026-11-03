#ifndef SUPPLYSESSION_H
#define SUPPLYSESSION_H

#include "BoardBus.h"
#include "SupplyRecord.h"

#include <QDateTime>
#include <QList>
#include <QMap>
#include <QObject>
#include <QSqlDatabase>
#include <QString>

class MachineConfig;
class Session;
class SupplySelection;

// The supply itself, on the nozzles.
//
// Auto mode, the way it works in the background (protocol v0.4): the supplier chooses the nozzle with its select key at the dispenser and scans
// ticket barcodes with the supplier's scanner; each goes to the queue of the nozzle that is chosen (see SupplySelection) after the checks (sold,
// the nozzle's fuel type, in no other queue). Every change of the queue sends its total to the board as the preset (SET_PRESET, the dispenser
// shows it). The supplier takes the gun up and the board starts the fill by itself; the app sees the fill in POLL (state DISPENSING), saves the
// session (the queue and the result id) and from then on the queue is locked. The clear key of the dispenser is only reported (flag CLEAR_KEY):
// the app drops the queue and sends SET_PRESET with zero, which makes the board forget its preset (IDLE). The board runs the fill to the preset. The app never sends START for tickets; a fill paid with a card is a ticket too (Cards::issueTicket),
// scanned like the others.
//
// Auto mode: a board that has a result waiting (state RESULT) is read, the supplement is saved (see SupplyRecord) and only then
// the board is told it can forget the result. A result is recorded once even when the answer to ACK_RESULT is lost. After a restart the saved
// session is still there, so a fill that ended meanwhile is recorded with its tickets.
//
// Manual mode: the board only answers POLL (status, gun, preset, dispensed amount), SET_TIME and the config: the program does not work with its
// dispensing at all (no result is read or acknowledged, nothing is recorded or kept). The cashier types the supply in on the Manual input
// screen (recordManual) with the tickets that pay for it.
class SupplySession : public QObject
{
    Q_OBJECT

public:
    SupplySession(QSqlDatabase db, BoardBus *bus, MachineConfig *config, SupplySelection *selection, Session *session, QObject *parent = 0);

    // A fill was started on this nozzle and its result is not recorded yet: the queue cannot change.
    bool isLocked(int nozzle) const;

    // The queue of a nozzle. False with the reason in `why` when it cannot be done.
    bool add(int nozzle, const QString &barcode, QString *why = 0);
    bool remove(int nozzle, qlonglong barcode, QString *why = 0);
    bool clear(int nozzle, QString *why = 0);

    // The dispenser of a nozzle shows the balance of a card (SHOW_BALANCE) while the nozzle is idle, has no fill started and its board answers. False when it cannot.
    bool showCardBalance(int nozzle, qlonglong balanceX100);

    // The cashier typed a supply in (Manual input): what came out (kg x100, litres x100), when, and the tickets that pay for it (barcodes as keys;
    // they must cover the kg; what is left of them is a remainder, see SupplyRecord). Refused while a fill runs on the nozzle or a result waits. When a fill was started on the nozzle
    // from the app and its board died, the tickets (or the card) of that fill are used and `tickets` is ignored: the reading must not be more than
    // they cover, and what is left of the tickets is a remainder, as when the result comes from the board.
    bool recordManual(int nozzle, qlonglong dispensedX100, qlonglong litersX100, const QList<qlonglong> &tickets, const QDateTime &when, QString *why = 0, const QString &comment = QString());

    // A ticket number typed in (a barcode that cannot be scanned: smudged, torn): the 11 digits after the station sign (the code and the serial) or all
    // 16 digits, spaces and dashes ignored. It goes to the queue of the chosen nozzle through the same checks as a scan. True when it was added; a refusal
    // is also said by `message`.
    bool typed(const QString &text);

    // A started session whose START did not get through is taken up again: while the board is still SET with the gun up, START is sent again (after `startAgainMs`);
    // when the board has been IDLE for `idleCancelMs` the fill is not running and no result will come, so the session is cancelled and its tickets are free. Tests shorten both.
    void setRecoveryMs(int startAgainMs, int idleCancelMs) { startAgainMs_ = startAgainMs; idleCancelMs_ = idleCancelMs; }

public slots:
    // A barcode from the supplier's scanner: it goes to the queue of the chosen nozzle.
    void scanned(const QString &barcode);

signals:
    void queueChanged(int nozzle);
    void message(const QString &text, bool good);
    void recorded(int nozzle, const QString &supplementId); // a supplement was saved
    void remainderMade(const QString &barcode);                                // its ticket is to be printed

private slots:
    void choiceChanged(int number);
    void polled(int address, const BoardProtocol::Status &status);
    void commandDone(int address, int command, const QByteArray &data);
    void commandFailed(int address, int command, int error);
    void boardOnline(int address, bool online);

private:
    enum StartStep { Starting = 2 };
    static const int SignLength = 5;   // the digits of the station sign at the front of a ticket number
    enum ResultStep { GettingResult = 1, Acknowledging = 2 };

    void saved(int nozzle, const SupplyRecord::Outcome &outcome);
    void followTheSupplier(int address, const BoardProtocol::Status &status);
    void recoverStart(int address, const BoardProtocol::Status &status);
    qint64 nowMs() const;

    QSqlDatabase db_;
    BoardBus *bus_;
    MachineConfig *config_;
    SupplySelection *selection_;
    Session *session_;
    QMap<int, int> startStep_;     // nozzle -> Starting
    QMap<int, int> resultStep_;    // nozzle -> GettingResult or Acknowledging
    QMap<int, qlonglong> presetSent_;     // nozzle -> the total (kg x100) the board was last given as its preset
    int chosen_;                          // the nozzle that was the chosen one until now
    QList<int> forgetPreset_;             // nozzles whose board is to forget its preset (SET_PRESET zero) after the clear key
    QMap<int, qlonglong> presetPending_;  // nozzle -> the total of a SET_PRESET that waits for its answer
    QMap<int, qint64> retryAfter_; // nozzle -> time (ms since epoch) before which a result is not read again after a failure
    QMap<int, qint64> lastStartMs_; // nozzle -> when START was last sent for its session
    QMap<int, qint64> idleSince_;   // nozzle -> when its board began to report IDLE while a session is started
    QList<int> startAgain_;         // nozzles whose START in flight is a repeat (a refusal because the fill runs already is not a failure)
    int startAgainMs_;
    int idleCancelMs_;
};

#endif
