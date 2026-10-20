#ifndef SCANNERREADER_H
#define SCANNERREADER_H

#include "ScanDecoder.h"

#include <QObject>
#include <QString>

class QSocketNotifier;
class QTimer;

// Reads one barcode scanner that works as a USB keyboard straight from its Linux input device (evdev), with the device grabbed
// (EVIOCGRAB), so its keystrokes never reach the focused widget; the other scanner (the cashier's) is not touched and types as a keyboard.
// The device is found by its exact name (and, when given, its USB port) in /proc/bus/input/devices every time, because the eventN number
// changes. When the scanner is not there, or is unplugged, the reader tries again every second and takes it up when it comes back.
//
// A ScannerReader lives in its own thread (see SupplierScanner): start() and stop() must run there, because the socket notifier belongs
// to the thread that makes it. On a system without evdev (a PC for trying the screens) it does nothing.
class ScannerReader : public QObject
{
    Q_OBJECT

public:
    ScannerReader(const QString &deviceName, const QString &phys = QString());
    ~ScannerReader();

    void setDevicesFile(const QString &path) { devicesFile_ = path; }
    void setRetryMs(int ms) { retryMs_ = ms; }

    static bool supported(); // false where there is no evdev: the reader then never connects

public slots:
    void start();
    void stop();

signals:
    void barcodeScanned(const QString &code);
    void connectedChanged(bool connected);

private slots:
    void readable();
    void retry();

private:
    bool openDevice();
    void closeDevice();
    void lost();

    QString deviceName_;
    QString phys_;
    QString devicesFile_;
    int retryMs_;
    int fd_;
    QSocketNotifier *notifier_;
    QTimer *timer_;
    ScanDecoder decoder_;
    bool stopped_;
};

#endif
