#include "SettingsPage.h"
#include "ui_SettingsPage.h"

#include "Settings.h"

#include <QComboBox>
#include <QLabel>
#include <QPushButton>

namespace {

enum { ResultPrint = 0, ResultDirect = 1 };

// "Print ticket" and "Set directly", in the order of the enum (the data of the item is the value that is saved)
void fillResults(QComboBox *combo)
{
    combo->addItem(QObject::tr("Print ticket"), "print");
    combo->addItem(QObject::tr("Set directly"), "direct");
}

}

SettingsPage::SettingsPage(Settings *settings, QWidget *parent)
    : QWidget(parent), ui_(new Ui::SettingsPage), settings_(settings)
{
    ui_->setupUi(this);

    cashier_ = ui_->settingsCashier;
    about_ = ui_->settingsAbout;
    spotResult_ = ui_->spotResultCombo;
    cardResult_ = ui_->cardResultCombo;
    fillResults(spotResult_);
    fillResults(cardResult_);
    reload();

    connect(ui_->logoutButton, SIGNAL(clicked()), this, SIGNAL(logoutRequested()));
    connect(spotResult_, SIGNAL(currentIndexChanged(int)), this, SLOT(resultChanged()));
    connect(cardResult_, SIGNAL(currentIndexChanged(int)), this, SLOT(resultChanged()));
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

// The two choices as they are saved (a terminal that never chose prints).
void SettingsPage::reload()
{
    spotResult_->blockSignals(true);
    cardResult_->blockSignals(true);
    spotResult_->setCurrentIndex(settings_->resultIsDirect(SettingKey::SpotSaleResult) ? ResultDirect : ResultPrint);
    cardResult_->setCurrentIndex(settings_->resultIsDirect(SettingKey::CardFillResult) ? ResultDirect : ResultPrint);
    spotResult_->blockSignals(false);
    cardResult_->blockSignals(false);
}

// A choice is saved at once: the sale screens read it when they save.
void SettingsPage::resultChanged()
{
    settings_->setValue(SettingKey::SpotSaleResult, spotResult_->itemData(spotResult_->currentIndex()).toString());
    settings_->setValue(SettingKey::CardFillResult, cardResult_->itemData(cardResult_->currentIndex()).toString());
}