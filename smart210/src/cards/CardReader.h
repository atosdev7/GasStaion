#ifndef CARDREADER_H
#define CARDREADER_H

#include <QByteArray>
#include <QObject>
#include <QString>
#include <QStringList>

class QSocketNotifier;
class QTimer;

// What the card reader sends over its serial line is cut into card ids. Two ways of framing, set on the terminal's settings page:
//   line    the id ends with CR and/or LF (most readers); a STX / ETX around it is dropped. A reader that says more than the id
//           (an Arduino sending "CARD: 04A1B2C3", and "Ready" when it starts) has the text before the id set as the prefix: a line that
//           does not start with it is ignored, and the prefix is cut off the ones that do
//   fixed   the id is exactly `length` characters (a reader that sends no end character); an unfinished frame is dropped after a pause
// and two ways of reading the bytes: as ASCII text (the characters are the id) or as HEX (every byte becomes two hex digits).
// No widgets and no device: it can be tried with plain bytes.
class CardFrameParser
{
public:
    enum Format { Line, Fixed };
    enum Encoding { Ascii, Hex };

    CardFrameParser() : format_(Line), length_(0), encoding_(Ascii) {}

    void setFormat(Format format, int length, Encoding encoding)
    {
        format_ = format;
        length_ = length;
        encoding_ = encoding;
        reset();
    }

    // The text a line starts with, before the id (empty: the whole line is the id). Only for the line format; the case does not matter.
    void setPrefix(const QString &prefix) { prefix_ = prefix.trimmed().toLatin1(); }

    // The ids that the bytes completed (none, one or several).
    QStringList feed(const QByteArray &bytes);

    // An unfinished frame is forgotten.
    void reset() { buffer_.clear(); }

    static Format formatOf(const QString &name) { return name == "fixed" ? Fixed : Line; }
    static Encoding encodingOf(const QString &name) { return name == "hex" ? Hex : Ascii; }

private:
    QString idOf(const QByteArray &frame) const;
    QString lineId(const QByteArray &line) const;

    Format format_;
    int length_;
    Encoding encoding_;
    QByteArray prefix_;
    QByteArray buffer_;
};

// The RFID card reader, on an RS-232 port of the board (default /dev/ttyUSB0). It opens the port (8N1, the given speed), reads what comes and
// emits cardRead() with each id; when the port is not there (the reader is unplugged) it tries again every few seconds. It runs in the main
// thread (a socket notifier on the device) and only does something on Linux; elsewhere (a PC) start() does nothing and the cashier types the
// id on the card screen. feed() gives it bytes as if the device had sent them (tests).
class CardReader : public QObject
{
    Q_OBJECT

public:
    struct Config {
        Config() : baud(9600), length(0) {}

        QString port;       // for example /dev/ttyUSB0
        int baud;
        QString format;     // "line" or "fixed"
        QString prefix;     // the text before the id in a line, for example "CARD:" (empty: none)
        int length;         // the characters of a fixed frame
        QString encoding;   // "ascii" or "hex"
    };

    static const int DefaultBaud = 9600;
    static QString defaultPort() { return "/dev/ttyUSB0"; }
    static QString defaultPrefix() { return "CARD:"; } // what the station's own (Arduino) reader sends before the id

    explicit CardReader(QObject *parent = 0);
    ~CardReader();

    static bool supported();

    // Opens the port with this config (or tries again until it can); the config of a reader that is running is replaced.
    void start(const Config &config);
    void stop();

    bool isOpen() const { return fd_ >= 0; }
    bool isRunning() const { return running_; }
    Config config() const { return config_; }

public slots:
    void feed(const QByteArray &bytes);

signals:
    void cardRead(const QString &id);
    void openChanged(bool open);

private slots:
    void readable();
    void retry();
    void frameTimeout();

private:
    bool openPort();
    void closePort();

    Config config_;
    CardFrameParser parser_;
    QSocketNotifier *notifier_;
    QTimer *retryTimer_;
    QTimer *frameTimer_;
    int fd_;
    bool running_;
};

#endif
