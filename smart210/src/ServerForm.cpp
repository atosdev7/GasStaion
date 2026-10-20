#include "ServerForm.h"

#include "SetupFlow.h"
#include "ui_ServerForm.h"
#include "Theme.h"

#include <QIntValidator>
#include <QLabel>
#include <QLineEdit>
#include <QPushButton>


ServerForm::ServerForm(SetupFlow *flow, QWidget *parent)
    : QGroupBox(parent), ui_(new Ui::ServerForm), flow_(flow), waiting_(false)
{
    ui_->setupUi(this);

    host_ = ui_->serverHost;
    port_ = ui_->serverPort;
    port_->setValidator(new QIntValidator(1, 65535, port_));
    testButton_ = ui_->testConnectionButton;
    status_ = ui_->serverStatus;

    reload();

    connect(testButton_, SIGNAL(clicked()), this, SLOT(testConnection()));
    connect(host_, SIGNAL(returnPressed()), this, SLOT(testConnection()));
    connect(port_, SIGNAL(returnPressed()), this, SLOT(testConnection()));
    connect(flow_, SIGNAL(connectionTested(bool,QString)), this, SLOT(connectionTested(bool,QString)));
}

ServerForm::~ServerForm()
{
    delete ui_;
}

void ServerForm::reload()
{
    host_->setText(flow_->savedHost());
    port_->setText(QString::number(flow_->savedPort()));
    status_->clear();
}

void ServerForm::testConnection()
{
    waiting_ = true;
    testButton_->setEnabled(false);
    Theme::setState(status_, "");
    status_->setText(tr("Connecting..."));
    flow_->testConnection(host_->text(), port_->text().toInt());
}

void ServerForm::connectionTested(bool ok, const QString &message)
{
    if (!waiting_) {
        return; // another form's test: the setup page and Machine Config share one SetupFlow
    }
    waiting_ = false;

    testButton_->setEnabled(true);
    Theme::setState(status_, ok ? "ok" : "error");
    status_->setText(message);
    if (ok) {
        emit configured();
    }
}
