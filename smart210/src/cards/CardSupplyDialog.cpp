#include "CardSupplyDialog.h"

#include "Cards.h"
#include "Decimal.h"
#include "MachineConfig.h"
#include "Session.h"
#include "SupplySession.h"
#include "Theme.h"

#include <QComboBox>
#include <QFormLayout>
#include <QLabel>
#include <QLineEdit>
#include <QPushButton>
#include <QVBoxLayout>

namespace {

QString kg(qlonglong x100)
{
    return Decimal::format(x100, 2);
}

}

CardSupplyDialog::CardSupplyDialog(QSqlDatabase db, Session *session, MachineConfig *config, SupplySession *supply, const QString &cardId, QWidget *parent)
    : QDialog(parent), db_(db), session_(session), config_(config), supply_(supply), cardId_(cardId), pinOk_(false), shownOn_(0)
{
    setObjectName("cardSupplyDialog");
    setWindowTitle(tr("Supply with a card"));
    setModal(true);

    info_ = new QLabel;
    info_->setObjectName("cardSupplyInfo");
    info_->setWordWrap(true);

    nozzles_ = new QComboBox;
    nozzles_->setObjectName("cardSupplyNozzle");
    pin_ = new QLineEdit;
    pin_->setObjectName("cardSupplyPin");
    pin_->setEchoMode(QLineEdit::Password);
    pin_->setMaxLength(Cards::PinMaxLength);
    show_ = new QPushButton(tr("Show balance"));
    show_->setObjectName("cardSupplyShow");
    kg_ = new QLineEdit;
    kg_->setObjectName("cardSupplyKg");
    kg_->setEnabled(false);
    set_ = new QPushButton(tr("Set amount"));
    set_->setObjectName("cardSupplySet");
    set_->setEnabled(false);
    cancelOpen_ = new QPushButton(tr("Cancel the open fill"));
    cancelOpen_->setObjectName("cardSupplyCancelOpen");
    QPushButton *close = new QPushButton(tr("Close"));
    close->setObjectName("cardSupplyClose");
    status_ = new QLabel;
    status_->setObjectName("cardSupplyStatus");
    status_->setWordWrap(true);

    QFormLayout *form = new QFormLayout;
    form->addRow(tr("Nozzle"), nozzles_);
    form->addRow(tr("PIN"), pin_);
    form->addRow(QString(), show_);
    form->addRow(tr("kg to supply"), kg_);
    form->addRow(QString(), set_);

    QVBoxLayout *layout = new QVBoxLayout(this);
    layout->addWidget(info_);
    layout->addLayout(form);
    layout->addWidget(cancelOpen_);
    layout->addWidget(status_);
    layout->addWidget(close);

    connect(show_, SIGNAL(clicked()), this, SLOT(showBalance()));
    connect(pin_, SIGNAL(returnPressed()), this, SLOT(showBalance()));
    connect(set_, SIGNAL(clicked()), this, SLOT(setAmount()));
    connect(kg_, SIGNAL(returnPressed()), this, SLOT(setAmount()));
    connect(cancelOpen_, SIGNAL(clicked()), this, SLOT(cancelOpenFill()));
    connect(close, SIGNAL(clicked()), this, SLOT(reject()));
    connect(nozzles_, SIGNAL(currentIndexChanged(int)), this, SLOT(nozzleChanged()));

    // the automatic nozzles of the card's fuel type
    const Cards::Card card = Cards::findById(db_, cardId_);
    foreach (const MachineConfig::Nozzle &nozzle, config_->nozzles()) {
        if (nozzle.seen() && nozzle.mode == BoardProtocol::ModeAuto && nozzle.fuelType == card.fuelType) {
            nozzles_->addItem(nozzle.label(), nozzle.number);
        }
    }
    refresh();
}

int CardSupplyDialog::nozzle() const
{
    return nozzles_->currentIndex() >= 0 ? nozzles_->itemData(nozzles_->currentIndex()).toInt() : 0;
}

void CardSupplyDialog::say(const QString &text, bool good)
{
    Theme::setState(status_, good ? "ok" : "error");
    status_->setText(text);
}

// What the card holds, what is held for an open fill, and which controls make sense now.
void CardSupplyDialog::refresh()
{
    const Cards::Card card = Cards::findById(db_, cardId_);
    const Cards::Hold open = Cards::holdOfCard(db_, cardId_);
    const qlonglong available = Cards::availableX100(db_, cardId_);

    QString text = tr("Card of %1: %2 kg, %3 kg can be used.").arg(card.holderName).arg(kg(card.balanceX100)).arg(kg(available));
    if (open.exists) {
        text += "\n" + tr("A fill of %1 kg is open on nozzle %2: the supplier has not started it.").arg(kg(open.presetX100)).arg(open.nozzle);
    }
    info_->setText(text);

    const bool free = !open.exists && nozzles_->count() > 0;
    nozzles_->setEnabled(free);
    pin_->setEnabled(free && !pinOk_);
    show_->setEnabled(free);
    kg_->setEnabled(free && pinOk_ && shownOn_ == nozzle());
    set_->setEnabled(free && pinOk_ && shownOn_ == nozzle());
    cancelOpen_->setVisible(open.exists);
    if (nozzles_->count() == 0 && !open.exists) {
        say(tr("No automatic nozzle of this fuel type is set up."), false);
    }
}

void CardSupplyDialog::nozzleChanged()
{
    // the balance is shown on one dispenser: another nozzle needs it shown there
    refresh();
}

// The PIN is checked (the cashier types what the customer said), then the balance goes to the dispenser for the customer to confirm.
void CardSupplyDialog::showBalance()
{
    if (nozzle() <= 0) {
        say(tr("Choose the nozzle first."), false);

        return;
    }
    if (!pinOk_) {
        const Cards::PinCheck check = Cards::checkPin(db_, cardId_, pin_->text());
        if (check.result != Cards::PinRight) {
            pin_->clear();
            say(check.error, false);
            refresh();

            return;
        }
        pinOk_ = true;
    }

    const qlonglong available = Cards::availableX100(db_, cardId_);
    shownOn_ = nozzle();
    if (supply_->showCardBalance(shownOn_, available)) {
        say(tr("The balance of %1 kg is shown on the dispenser of nozzle %2. When the customer has confirmed it, type the kg.").arg(kg(available)).arg(shownOn_), true);
    } else {
        say(tr("The balance is %1 kg. The dispenser of nozzle %2 could not show it (it is busy or does not answer): the customer can read it here.").arg(kg(available)).arg(shownOn_), false);
    }
    refresh();
    kg_->setFocus();
}

void CardSupplyDialog::setAmount()
{
    qlonglong weight = 0;
    if (!pinOk_ || shownOn_ != nozzle()) {
        say(tr("Show the balance first."), false);

        return;
    }
    if (!Decimal::parse(kg_->text().trimmed(), 2, &weight) || weight <= 0) {
        say(tr("Enter the weight to supply."), false);

        return;
    }
    const qlonglong available = Cards::availableX100(db_, cardId_);
    if (weight > available) {
        say(tr("The card holds only %1 kg.").arg(kg(available)), false);

        return;
    }

    QString why;
    if (!supply_->startCardFill(nozzle(), cardId_, weight, &why)) {
        say(why, false);
        refresh();

        return;
    }

    result_ = tr("Nozzle %1: %2 kg are held on the card of %3 and set on the dispenser. Take the gun up to start the fill.")
                  .arg(nozzle()).arg(kg(weight)).arg(Cards::findById(db_, cardId_).holderName);
    shownOn_ = 0; // the dispenser shows the preset now
    accept();
}

void CardSupplyDialog::cancelOpenFill()
{
    const Cards::Hold open = Cards::holdOfCard(db_, cardId_);
    QString why;
    if (!open.exists) {
        refresh();

        return;
    }
    if (!supply_->cancelCardFill(open.nozzle, &why)) {
        say(why, false);

        return;
    }
    result_ = tr("The fill of nozzle %1 with the card was cancelled.").arg(open.nozzle);
    say(result_, true);
    refresh();
}

// Closed without a fill set: the balance goes from the dispenser.
void CardSupplyDialog::reject()
{
    if (shownOn_ > 0 && !supply_->isHeld(shownOn_)) {
        supply_->showCardBalance(shownOn_, 0);
    }
    QDialog::reject();
}
