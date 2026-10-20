#include "MainWindow.h"

#include "NozzlePanel.h"

#include <QApplication>
#include <QCheckBox>
#include <QCloseEvent>
#include <QDateTime>
#include <QGridLayout>
#include <QHBoxLayout>
#include <QIntValidator>
#include <QLabel>
#include <QLineEdit>
#include <QListWidget>
#include <QPushButton>
#include <QSettings>
#include <QTimer>
#include <QVBoxLayout>

namespace {

const int TickMs = 100;
const int MaxLogLines = 400;

QString iniPath()
{
    return QApplication::applicationDirPath() + "/simulator.ini";
}

}

MainWindow::MainWindow(QWidget *parent)
    : QWidget(parent)
{
    setWindowTitle("Nozzle board simulator");

    for (int number = 1; number <= NozzleCount; ++number) {
        sim_.add(number);
    }
    QSettings settings(iniPath(), QSettings::IniFormat);
    sim_.load(settings); // what the boards kept in their flash: config, result counter, flow

    host_ = new QLineEdit(settings.value("host", "127.0.0.1").toString());
    host_->setMaximumWidth(200);
    port_ = new QLineEdit(settings.value("port", 4001).toString());
    port_->setValidator(new QIntValidator(1, 65535, port_));
    port_->setMaximumWidth(80);
    connect_ = new QPushButton("Connect");
    reconnect_ = new QCheckBox("Connect again by itself");
    reconnect_->setChecked(true);
    showPolls_ = new QCheckBox("Show POLL in the log");
    status_ = new QLabel("Not connected");

    QHBoxLayout *top = new QHBoxLayout;
    top->addWidget(new QLabel("Smart210 address"));
    top->addWidget(host_);
    top->addWidget(new QLabel("Port"));
    top->addWidget(port_);
    top->addWidget(connect_);
    top->addWidget(reconnect_);
    top->addWidget(showPolls_);
    top->addStretch();
    top->addWidget(status_);

    QGridLayout *grid = new QGridLayout;
    for (int number = 1; number <= NozzleCount; ++number) {
        NozzlePanel *panel = new NozzlePanel(number, &sim_);
        panels_.append(panel);
        grid->addWidget(panel, (number - 1) / 3, (number - 1) % 3);
    }

    log_ = new QListWidget;
    log_->setMaximumHeight(170);

    QVBoxLayout *layout = new QVBoxLayout(this);
    layout->addLayout(top);
    layout->addLayout(grid);
    layout->addWidget(log_);

    tick_ = new QTimer(this);
    tick_->start(TickMs);
    saveTimer_ = new QTimer(this);
    saveTimer_->setSingleShot(true);

    connect(connect_, SIGNAL(clicked()), this, SLOT(connectClicked()));
    connect(&client_, SIGNAL(connectionChanged(bool)), this, SLOT(connectionChanged(bool)));
    connect(&client_, SIGNAL(requestReceived(BoardProtocol::Frame)), this, SLOT(request(BoardProtocol::Frame)));
    connect(&sim_, SIGNAL(changed(int)), this, SLOT(boardChanged(int)));
    connect(&sim_, SIGNAL(exchanged(int,QString,QString)), this, SLOT(exchanged(int,QString,QString)));
    connect(tick_, SIGNAL(timeout()), this, SLOT(tick()));
    connect(saveTimer_, SIGNAL(timeout()), this, SLOT(save()));

    resize(1300, 900);
}

void MainWindow::connectClicked()
{
    if (connect_->text() == "Disconnect") {
        client_.disconnectNow();
        connect_->setText("Connect");
        status_->setText("Not connected");

        return;
    }

    client_.setAutoReconnect(reconnect_->isChecked());
    client_.setReconnectMs(1000);
    connect_->setText("Disconnect");
    status_->setText("Connecting...");
    client_.connectTo(host_->text().trimmed(), quint16(port_->text().toInt()));
    save();
}

void MainWindow::connectionChanged(bool connected)
{
    if (connect_->text() != "Disconnect") {
        return; // closed by the button
    }
    status_->setText(connected ? "Connected to the Smart210" : "Connecting...");
    log(connected ? "Connected to the Smart210" : "The link was lost");
}

void MainWindow::request(const BoardProtocol::Frame &frame)
{
    client_.send(sim_.answer(frame));
}

void MainWindow::boardChanged(int address)
{
    if (address >= 1 && address <= panels_.size()) {
        panels_.at(address - 1)->refresh();
    }
    saveTimer_->start(1000); // config and the result counter are what the boards keep
}

void MainWindow::exchanged(int address, const QString &request, const QString &reply)
{
    if (request.startsWith("POLL") && !showPolls_->isChecked()) {
        return;
    }
    log(QString("Nozzle %1  <- %2  -> %3").arg(address).arg(request).arg(reply));
}

void MainWindow::tick()
{
    sim_.tick(TickMs);
}

void MainWindow::save()
{
    QSettings settings(iniPath(), QSettings::IniFormat);
    settings.setValue("host", host_->text().trimmed());
    settings.setValue("port", port_->text().toInt());
    sim_.save(settings);
}

void MainWindow::log(const QString &line)
{
    log_->addItem(QDateTime::currentDateTime().toString("HH:mm:ss.zzz") + "  " + line);
    while (log_->count() > MaxLogLines) {
        delete log_->takeItem(0);
    }
    log_->scrollToBottom();
}

void MainWindow::closeEvent(QCloseEvent *event)
{
    save();
    event->accept();
}
