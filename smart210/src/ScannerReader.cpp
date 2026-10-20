#include "ScannerReader.h"

#include "InputDevices.h"

#include <QDateTime>
#include <QSocketNotifier>
#include <QTimer>

// evdev exists on Linux (the board); SCANNER_EVDEV makes the same code compile elsewhere with stand-in headers (a build check).
#if defined(Q_OS_LINUX) || defined(SCANNER_EVDEV)
#define HAVE_EVDEV 1
#include <cerrno>
#include <fcntl.h>
#include <linux/input.h>
#include <sys/ioctl.h>
#include <unistd.h>
#endif

ScannerReader::ScannerReader(const QString &deviceName, const QString &phys)
    : deviceName_(deviceName), phys_(phys), devicesFile_("/proc/bus/input/devices"), retryMs_(1000), fd_(-1), notifier_(0), timer_(0),
      stopped_(true)
{
}

ScannerReader::~ScannerReader()
{
    closeDevice();
}

bool ScannerReader::supported()
{
#ifdef HAVE_EVDEV
    return true;
#else
    return false;
#endif
}

// In the reader's thread: look for the scanner now, and again every retryMs until it is there.
void ScannerReader::start()
{
    stopped_ = false;
    if (!timer_) {
        timer_ = new QTimer(this);
        timer_->setSingleShot(true);
        connect(timer_, SIGNAL(timeout()), this, SLOT(retry()));
    }
    retry();
}

// In the reader's thread: lets the scanner go (the grab ends with the close).
void ScannerReader::stop()
{
    stopped_ = true;
    if (timer_) {
        timer_->stop();
    }
    if (fd_ >= 0) {
        closeDevice();
        emit connectedChanged(false);
    }
}

void ScannerReader::retry()
{
    if (stopped_ || fd_ >= 0) {
        return;
    }
    if (openDevice()) {
        decoder_.reset();
        emit connectedChanged(true);
    } else if (supported()) {
        timer_->start(retryMs_);
    }
}

bool ScannerReader::openDevice()
{
#ifdef HAVE_EVDEV
    const QString path = InputDevices::findEventIn(devicesFile_, deviceName_, phys_);
    if (path.isEmpty()) {
        return false; // not plugged in (or not yet listed)
    }

    const int fd = ::open(path.toLocal8Bit().constData(), O_RDONLY | O_NONBLOCK);
    if (fd < 0) {
        return false;
    }
    // Exclusive: if the grab fails the keystrokes would also type into the screen, so the device is not used.
    if (::ioctl(fd, EVIOCGRAB, 1) < 0) {
        ::close(fd);

        return false;
    }

    fd_ = fd;
    notifier_ = new QSocketNotifier(fd_, QSocketNotifier::Read, this);
    connect(notifier_, SIGNAL(activated(int)), this, SLOT(readable()));

    return true;
#else
    return false;
#endif
}

void ScannerReader::closeDevice()
{
#ifdef HAVE_EVDEV
    if (notifier_) {
        notifier_->setEnabled(false);
        notifier_->deleteLater(); // this can run inside the notifier's own slot
        notifier_ = 0;
    }
    if (fd_ >= 0) {
        ::ioctl(fd_, EVIOCGRAB, 0);
        ::close(fd_);
        fd_ = -1;
    }
#endif
}

// The scanner went away (unplugged): the device is closed and looked for again.
void ScannerReader::lost()
{
    closeDevice();
    emit connectedChanged(false);
    if (!stopped_) {
        timer_->start(retryMs_);
    }
}

void ScannerReader::readable()
{
#ifdef HAVE_EVDEV
    struct input_event event;

    for (;;) {
        const ssize_t n = ::read(fd_, &event, sizeof(event));
        if (n == ssize_t(sizeof(event))) {
            QString barcode;
            if (decoder_.event(event.type, event.code, event.value, QDateTime::currentMSecsSinceEpoch(), &barcode)) {
                emit barcodeScanned(barcode);
            }
            continue;
        }
        if (n < 0 && (errno == EAGAIN || errno == EINTR)) {
            return; // nothing more for now
        }
        if (n > 0) {
            return; // a piece of an event: the rest comes with the next notification
        }
        lost(); // 0 (closed) or an error such as ENODEV: the scanner was unplugged

        return;
    }
#endif
}
