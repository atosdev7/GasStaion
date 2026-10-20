#include "SettingsPage.h"
#include "ui_SettingsPage.h"

#include <QLabel>
#include <QPushButton>


SettingsPage::SettingsPage(QWidget *parent)
    : QWidget(parent), ui_(new Ui::SettingsPage)
{
    ui_->setupUi(this);

    cashier_ = ui_->settingsCashier;
    about_ = ui_->settingsAbout;

    connect(ui_->logoutButton, SIGNAL(clicked()), this, SIGNAL(logoutRequested()));
}

SettingsPage::~SettingsPage()
{
    delete ui_;
}

void SettingsPage::setCashier(const QString &name)
{
    cashier_->setText(tr("Logged in as %1").arg(name));
}

void SettingsPage::setAbout(const QString &text)
{
    about_->setText(text);
}
