#include "PinPage.h"

#include "PosHash.h"
#include "Settings.h"
#include "ui_PinPage.h"
#include "Theme.h"

#include <QDateTime>
#include <QLabel>
#include <QLineEdit>
#include <QPushButton>

PinPage::PinPage(Settings *settings, QWidget *parent)
    : QWidget(parent), ui_(new Ui::PinPage), settings_(settings)
{
    ui_->setupUi(this);

    pin_ = ui_->pinField;
    status_ = ui_->pinStatus;

    connect(ui_->pinOkButton, SIGNAL(clicked()), this, SLOT(check()));
    connect(pin_, SIGNAL(returnPressed()), this, SLOT(check()));
    connect(ui_->pinCancelButton, SIGNAL(clicked()), this, SIGNAL(cancelled()));
}

PinPage::~PinPage()
{
    delete ui_;
}

void PinPage::reset()
{
    pin_->clear();
    status_->clear();
    pin_->setFocus();
}

void PinPage::check()
{
    const QString entered = pin_->text();
    pin_->clear(); // never kept

    const QString hash = settings_->value(SettingKey::PinHash);
    const QDateTime now = QDateTime::currentDateTime();
    const QDateTime lockedUntil = QDateTime::fromString(settings_->value(SettingKey::PinLockedUntil), "yyyy-MM-dd HH:mm:ss");
    if (lockedUntil.isValid() && now < lockedUntil) {
        Theme::setState(status_, "error");
        status_->setText(tr("Too many wrong PINs. Try again in %1 min.").arg((now.secsTo(lockedUntil) + 59) / 60));

        return;
    }
    if (hash.isEmpty()) {
        Theme::setState(status_, "error");
        status_->setText(tr("No PIN is set for this terminal. Set one on the Devices screen of the web app."));

        return;
    }

    if (!PosHash::matches(settings_->value(SettingKey::PinSalt), entered, hash)) {
        Theme::setState(status_, "error");
        const int failures = settings_->intValue(SettingKey::PinFailures) + 1;
        if (failures >= MaxWrong) {
            settings_->setInt(SettingKey::PinFailures, 0);
            settings_->setValue(SettingKey::PinLockedUntil, now.addSecs(LockMinutes * 60).toString("yyyy-MM-dd HH:mm:ss"));
            status_->setText(tr("Too many wrong PINs. Try again in %1 min.").arg(LockMinutes));
        } else {
            settings_->setInt(SettingKey::PinFailures, failures);
            status_->setText(tr("Wrong PIN. %1 more tries before the PIN is locked.").arg(MaxWrong - failures));
        }
        pin_->setFocus();

        return;
    }

    settings_->setInt(SettingKey::PinFailures, 0);
    status_->clear();
    emit accepted();
}
