#include "SupplierScanner.h"

#include "ScannerReader.h"

#include <QMetaObject>
#include <QThread>

SupplierScanner::SupplierScanner(QObject *parent)
    : QObject(parent), thread_(0), reader_(0), connected_(false)
{
}

SupplierScanner::~SupplierScanner()
{
    stop();
}

bool SupplierScanner::isSupported()
{
    return ScannerReader::supported();
}

void SupplierScanner::start(const QString &deviceName, const QString &phys)
{
    if (thread_ || deviceName.isEmpty() || !ScannerReader::supported()) {
        return;
    }

    thread_ = new QThread(this);
    reader_ = new ScannerReader(deviceName, phys); // no parent: it moves to the thread
    reader_->moveToThread(thread_);

    // signals from the thread arrive here in the main thread (queued by Qt)
    connect(reader_, SIGNAL(barcodeScanned(QString)), this, SIGNAL(barcodeScanned(QString)));
    connect(reader_, SIGNAL(connectedChanged(bool)), this, SLOT(readerConnected(bool)));
    connect(thread_, SIGNAL(started()), reader_, SLOT(start()));

    thread_->start();
}

void SupplierScanner::stop()
{
    if (!thread_) {
        return;
    }

    // the reader lets the device go in its own thread, then the thread ends
    QMetaObject::invokeMethod(reader_, "stop", Qt::BlockingQueuedConnection);
    thread_->quit();
    thread_->wait();
    delete reader_;
    reader_ = 0;
    delete thread_;
    thread_ = 0;

    if (connected_) {
        connected_ = false;
        emit connectedChanged(false);
    }
}

void SupplierScanner::readerConnected(bool connected)
{
    if (connected != connected_) {
        connected_ = connected;
        emit connectedChanged(connected);
    }
}
