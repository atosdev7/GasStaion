#ifndef SUPPLIERSCANNER_H
#define SUPPLIERSCANNER_H

#include <QObject>
#include <QString>

class QThread;
class ScannerReader;

// The supplier's barcode scanner, read in the background: it runs a ScannerReader in a thread of its own and passes each scanned barcode
// on as a signal in the main thread. The scanner is grabbed, so what it scans never types into a screen; the cashier's scanner is a
// plain keyboard and is not involved. Where there is no evdev (a PC) start() does nothing and the scanner is never connected.
class SupplierScanner : public QObject
{
    Q_OBJECT

public:
    explicit SupplierScanner(QObject *parent = 0);
    ~SupplierScanner();

    // The scanner is found by its exact name (the N: Name="..." line of /proc/bus/input/devices) and, when given, its USB port (a part of the
    // Phys line, for example "usb-s5p-ehci-1.1").
    void start(const QString &deviceName, const QString &phys = QString());
    void stop();

    bool isRunning() const { return thread_ != 0; }
    bool isConnected() const { return connected_; }
    static bool isSupported();

signals:
    void barcodeScanned(const QString &code);
    void connectedChanged(bool connected);

private slots:
    void readerConnected(bool connected);

private:
    QThread *thread_;
    ScannerReader *reader_;
    bool connected_;
};

#endif
