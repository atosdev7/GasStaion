#ifndef BOARDPROTOCOL_H
#define BOARDPROTOCOL_H

#include <QByteArray>
#include <QDateTime>
#include <QMetaType>
#include <QString>

// Nozzle board protocol v0.2 (see PROTOCOL.md at the project root): the frame, its CRC and the data of each command.
namespace BoardProtocol {

enum { Stx = 0x02, MaxData = 64, HeaderSize = 5, CrcSize = 2, NakCommand = 0xFF, AnswerFlag = 0x80 };

enum Command {
    CmdInfo = 0x01,
    CmdPoll = 0x02,
    CmdSelect = 0x03,
    CmdSetPreset = 0x05,
    CmdStart = 0x06,
    CmdGetResult = 0x08,
    CmdAckResult = 0x09,
    CmdGetConfig = 0x0A,
    CmdSetConfig = 0x0B,
    CmdSetTime = 0x0C,
    CmdGetTime = 0x0D,
    CmdShowBalance = 0x0E,  // show the balance of a card on the dispenser (IDLE only; the data are the grams, like a preset; 0 clears it)
    CmdGetTotal = 0x0F,     // everything the board has dispensed, in grams (8 bytes); any state
    CmdResetTotal = 0x10    // the total goes back to 0; the answer is what it was (refused while a fill runs)
};

enum State { StateIdle = 0, StateSelected = 1, StateArmed = 2, StateDispensing = 3, StateResult = 4, StateFault = 5 };

enum PollFlag { FlagSelectKey = 0x01, FlagDeselectKey = 0x02, FlagResult = 0x04, FlagFault = 0x08, FlagClearKey = 0x10, FlagGunUp = 0x20 };

// What a NAK says (1 to 8, from the board) and what the Smart210 adds itself (100 and up) when a command gets no answer at all.
enum Error {
    ErrBadCommand = 1,
    ErrBadLength = 2,
    ErrWrongState = 3,
    ErrBadValue = 4,
    ErrNotConfigured = 5,
    ErrNoResult = 6,
    ErrIdMismatch = 7,
    ErrFault = 8,
    ErrNoAnswer = 100,     // the board stopped answering while the command waited
    ErrNoConnection = 101  // the link to the converter is down
};

enum ResultReason { ReasonPresetReached = 0, ReasonTankFullOrNoFlow = 1, ReasonInterrupted = 2, ReasonFault = 3 };

enum Mode { ModeAuto = 0, ModeManual = 1 };

// What a board holds as its config, and the limits it accepts (see PROTOCOL.md).
enum { TimeSize = 7, ConfigSize = 11, MinDensity = 1000, MaxDensity = 20000, MaxPulsesX100 = 100000000 };

struct Frame {
    Frame() : address(0), seq(0), command(0) {}
    int address;
    int seq;
    int command; // with AnswerFlag in an answer, NakCommand in a NAK
    QByteArray data;
};

// The answer to POLL: the status of the dispenser (state, gun and keys in flags, the preset, what was dispensed).
struct Status {
    Status() : state(StateIdle), flags(0), fault(0), sessionId(0), dispensedG(0), dispensedMl(0), presetG(0) {}
    int state;
    int flags;
    int fault;
    quint32 sessionId;
    quint32 dispensedG;
    quint32 dispensedMl;
    quint32 presetG;      // the preset of the board (typed at a manual dispenser, or set by the Smart210); 0 when none
};

// The answer to GET_RESULT.
struct Result {
    Result() : sessionId(0), presetG(0), dispensedG(0), dispensedMl(0), reason(0) {}
    quint32 sessionId;
    quint32 presetG;
    quint32 dispensedG;
    quint32 dispensedMl;
    int reason;
};

// The config of a nozzle board (GET_CONFIG / SET_CONFIG).
struct BoardConfig {
    BoardConfig() : mode(ModeAuto), density(0), pulsesX100(0) {}
    int mode;             // ModeAuto or ModeManual
    QString fuelType;     // up to 4 characters, as in the web app's fuel types
    quint16 density;      // kg per litre x 10000
    quint32 pulsesX100;   // pulses per litre x 100
    bool operator==(const BoardConfig &o) const { return mode == o.mode && fuelType == o.fuelType && density == o.density && pulsesX100 == o.pulsesX100; }
    bool operator!=(const BoardConfig &o) const { return !(*this == o); }
    bool isValid() const; // inside the limits a board accepts
};

// The answer to INFO.
struct Info {
    Info() : protocol(0), versionMajor(0), versionMinor(0), serial(0) {}
    int protocol;
    int versionMajor;
    int versionMinor;
    quint32 serial;
};

quint16 crc16(const QByteArray &bytes); // CRC-16/Modbus
QByteArray build(int address, int seq, int command, const QByteArray &data = QByteArray());

// Data of the requests.
QByteArray presetData(quint32 presetG);
QByteArray sessionIdData(quint32 sessionId);
QByteArray configData(const BoardConfig &config);
QByteArray timeData(const QDateTime &time); // SET_TIME: year (2 bytes), month, day, hour, minute, second of the station's local time

// Data of the answers; false when the length is wrong.
bool parseStatus(const QByteArray &data, Status *status);
bool parseResult(const QByteArray &data, Result *result);
bool parseInfo(const QByteArray &data, Info *info);
bool parseBoardConfig(const QByteArray &data, BoardConfig *config);
bool parseSessionId(const QByteArray &data, quint32 *sessionId);
bool parseTotal(const QByteArray &data, quint64 *totalG); // GET_TOTAL / RESET_TOTAL: the grams, 8 bytes little endian
QByteArray totalData(quint64 totalG);                      // the same, for a board that answers
bool parseTime(const QByteArray &data, QDateTime *time); // false for a wrong length or a date or time that does not exist

QString errorText(int error); // for the screen

// Cuts frames out of the bytes that arrive, in any pieces. A frame with a wrong CRC is dropped by skipping its STX only, so a good frame
// inside the garbage (or right behind it) is still found.
class FrameParser
{
public:
    FrameParser() {}

    void feed(const QByteArray &bytes) { buffer_.append(bytes); }
    void clear() { buffer_.clear(); }
    bool next(Frame *frame); // the next good frame, or false when the bytes so far hold none
    int pending() const { return buffer_.size(); }

private:
    QByteArray buffer_;
};

} // namespace BoardProtocol

Q_DECLARE_METATYPE(BoardProtocol::Status)
Q_DECLARE_METATYPE(BoardProtocol::BoardConfig)

#endif
