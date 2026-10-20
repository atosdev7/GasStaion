#include "CardReader.h"

#include <QSocketNotifier>
#include <QTimer>

// A serial port exists on Linux (the board); elsewhere the reader does nothing.
#if defined(Q_OS_LINUX)
#define HAVE_TERMIOS 1
#include <cerrno>
#include <fcntl.h>
#include <termios.h>
#include <unistd.h>
#endif

const int CardReader::DefaultBaud;

namespace {

const int RetryMs = 3000;       // the port is tried again this often while it cannot be opened
const int FrameTimeoutMs = 300;  // a fixed frame that stops short is forgotten after this pause
const int MaxFrame = 128;        // a line longer than this is not a card id

}

QStringList CardFrameParser::feed(const QByteArray &bytes)
{
    QStringList ids;

    for (int i = 0; i < bytes.size(); ++i) {
        const char c = bytes.at(i);

        if (format_ == Fixed && length_ > 0) {
            buffer_.append(c);
            if (buffer_.size() >= length_) {
                const QString id = idOf(buffer_);
                buffer_.clear();
                if (!id.isEmpty()) {
                    ids << id;
                }
            }
            continue;
        }

        if (c == '\r' || c == '\n') {
            if (!buffer_.isEmpty()) {
                const QString id = lineId(buffer_);
                buffer_.clear();
                if (!id.isEmpty()) {
                    ids << id;
                }
            }
        } else if (buffer_.size() < MaxFrame) {
            buffer_.append(c);
        } else {
            buffer_.clear(); // noise, not a card
        }
    }

    return ids;
}

// A line of the line format: it has to start with the prefix, when there is one (a line of another kind is not a card).
QString CardFrameParser::lineId(const QByteArray &line) const
{
    if (prefix_.isEmpty()) {
        return idOf(line);
    }

    const QByteArray text = line.trimmed();
    if (!text.toUpper().startsWith(prefix_.toUpper())) {
        return QString();
    }

    return idOf(text.mid(prefix_.size()));
}

QString CardFrameParser::idOf(const QByteArray &frame) const
{
    if (encoding_ == Hex) {
        return QString::fromLatin1(frame.toHex()).toUpper();
    }

    QString id;
    for (int i = 0; i < frame.size(); ++i) {
        const unsigned char c = static_cast<unsigned char>(frame.at(i));
        if (c > 0x20 && c < 0x7F) { // printable and not a space: STX, ETX and other control characters are dropped
            id.append(QChar(c));
        }
    }

    return id.toUpper();
}

CardReader::CardReader(QObject *parent)
    : QObject(parent), notifier_(0), retryTimer_(0), frameTimer_(0), fd_(-1), running_(false)
{
    retryTimer_ = new QTimer(this);
    retryTimer_->setSingleShot(true);
    connect(retryTimer_, SIGNAL(timeout()), this, SLOT(retry()));

    frameTimer_ = new QTimer(this);
    frameTimer_->setSingleShot(true);
    frameTimer_->setInterval(FrameTimeoutMs);
    connect(frameTimer_, SIGNAL(timeout()), this, SLOT(frameTimeout()));
}

CardReader::~CardReader()
{
    closePort();
}

bool CardReader::supported()
{
#ifdef HAVE_TERMIOS
    return true;
#else
    return false;
#endif
}

void CardReader::start(const Config &config)
{
    stop();

    config_ = config;
    if (config_.port.isEmpty()) {
        config_.port = defaultPort();
    }
    if (config_.baud <= 0) {
        config_.baud = DefaultBaud;
    }
    parser_.setFormat(CardFrameParser::formatOf(config_.format), config_.length, CardFrameParser::encodingOf(config_.encoding));
    parser_.setPrefix(config_.prefix);

    running_ = true;
    retry();
}

void CardReader::stop()
{
    running_ = false;
    retryTimer_->stop();
    frameTimer_->stop();
    if (fd_ >= 0) {
        closePort();
        emit openChanged(false);
    }
}

void CardReader::feed(const QByteArray &bytes)
{
    if (bytes.isEmpty()) {
        return;
    }
    const QStringList ids = parser_.feed(bytes);
    foreach (const QString &id, ids) {
        emit cardRead(id);
    }
    if (config_.format == "fixed") {
        frameTimer_->start(); // the next bytes of an unfinished frame must come soon
    }
}

void CardReader::frameTimeout()
{
    parser_.reset();
}

void CardReader::retry()
{
    if (!running_ || fd_ >= 0) {
        return;
    }
    if (openPort()) {
        parser_.reset();
        emit openChanged(true);
    } else if (supported()) {
        retryTimer_->start(RetryMs);
    }
}

bool CardReader::openPort()
{
#ifdef HAVE_TERMIOS
    const int fd = ::open(config_.port.toLocal8Bit().constData(), O_RDWR | O_NOCTTY | O_NONBLOCK);
    if (fd < 0) {
        return false; // not plugged in
    }

    struct termios tio;
    if (::tcgetattr(fd, &tio) != 0) {
        ::close(fd);

        return false;
    }
    ::cfmakeraw(&tio);
    tio.c_cflag |= (CLOCAL | CREAD);
    tio.c_cflag &= ~(PARENB | CSTOPB | CSIZE);
    tio.c_cflag |= CS8;                         // 8N1
    tio.c_cc[VMIN] = 0;
    tio.c_cc[VTIME] = 0;

    speed_t speed = B9600;
    switch (config_.baud) {
    case 1200: speed = B1200; break;
    case 2400: speed = B2400; break;
    case 4800: speed = B4800; break;
    case 9600: speed = B9600; break;
    case 19200: speed = B19200; break;
    case 38400: speed = B38400; break;
    case 57600: speed = B57600; break;
    case 115200: speed = B115200; break;
    default: break;
    }
    ::cfsetispeed(&tio, speed);
    ::cfsetospeed(&tio, speed);
    if (::tcsetattr(fd, TCSANOW, &tio) != 0) {
        ::close(fd);

        return false;
    }
    ::tcflush(fd, TCIOFLUSH);

    fd_ = fd;
    notifier_ = new QSocketNotifier(fd_, QSocketNotifier::Read, this);
    connect(notifier_, SIGNAL(activated(int)), this, SLOT(readable()));

    return true;
#else
    return false;
#endif
}

void CardReader::closePort()
{
#ifdef HAVE_TERMIOS
    if (notifier_) {
        notifier_->setEnabled(false);
        notifier_->deleteLater(); // this can run inside the notifier's own slot
        notifier_ = 0;
    }
    if (fd_ >= 0) {
        ::close(fd_);
        fd_ = -1;
    }
#endif
}

void CardReader::readable()
{
#ifdef HAVE_TERMIOS
    char buffer[256];

    for (;;) {
        const ssize_t n = ::read(fd_, buffer, sizeof(buffer));
        if (n > 0) {
            feed(QByteArray(buffer, int(n)));
            continue;
        }
        if (n < 0 && (errno == EAGAIN || errno == EINTR)) {
            return; // nothing more for now
        }
        // 0 (closed) or an error such as ENODEV: the reader was unplugged
        closePort();
        emit openChanged(false);
        if (running_) {
            retryTimer_->start(RetryMs);
        }

        return;
    }
#endif
}
