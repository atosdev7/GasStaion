#include "NozzlePanel.h"

#include "BoardSimulator.h"

#include <QCheckBox>
#include <QComboBox>
#include <QGridLayout>
#include <QHBoxLayout>
#include <QLabel>
#include <QFontDatabase>
#include <QProgressBar>
#include <QPushButton>
#include <QSpinBox>
#include <QVBoxLayout>

using namespace BoardProtocol;

namespace {

QString kg(quint32 grams)
{
    return QString::number(grams / 1000.0, 'f', 3);
}

QString litres(quint32 millilitres)
{
    return QString::number(millilitres / 1000.0, 'f', 2);
}

// What a display of the dispenser shows: `digits` digits and `decimals` of them after the point, the leading zeros left blank (a 6-digit display with 2
// decimals shows 15.00 as "  15.00"); a number too big for the display shows dashes.
QString shown(double value, int digits, int decimals)
{
    const QString text = QString::number(value, 'f', decimals);
    if (text.size() - (decimals > 0 ? 1 : 0) > digits) {
        return QString(digits + (decimals > 0 ? 1 : 0), '-');
    }

    return text.rightJustified(digits + (decimals > 0 ? 1 : 0), ' ');
}

// The density has 4 digits: 0.8333 kg/L shows as ".8333", 1.2500 as "1.250"; a board that holds no config shows "----".
QString densityText(quint32 densityX10000)
{
    if (densityX10000 == 0) {
        return "----";
    }
    if (densityX10000 < 10000) {
        return QString::number(densityX10000 / 10000.0, 'f', 4).mid(1);
    }

    return QString::number(densityX10000 / 10000.0, 'f', 3).left(5);
}

QString colorOf(int state)
{
    switch (state) {
    case StateSelected:
        return "#1d4ed8"; // blue
    case StateArmed:
        return "#b45309"; // amber
    case StateDispensing:
        return "#15803d"; // green
    case StateResult:
        return "#7e22ce"; // purple
    case StateFault:
        return "#b91c1c"; // red
    }

    return "#6b7280";     // gray: idle
}

QString reasonText(int reason)
{
    switch (reason) {
    case ReasonPresetReached:
        return "preset reached";
    case ReasonTankFullOrNoFlow:
        return "stopped (tank full or no flow)";
    case ReasonInterrupted:
        return "interrupted (power cut)";
    case ReasonFault:
        return "fault";
    }

    return QString::number(reason);
}

}

NozzlePanel::NozzlePanel(int address, BoardSimulator *simulator, QWidget *parent)
    : QGroupBox(QString("Nozzle %1  (board address %1)").arg(address), parent), address_(address), sim_(simulator), refreshing_(false)
{
    state_ = new QLabel;
    QFont big = state_->font();
    big.setPointSize(big.pointSize() * 3 / 2);
    big.setBold(true);
    state_->setFont(big);
    config_ = new QLabel;
    config_->setWordWrap(true);
    progress_ = new QProgressBar;
    progress_->setTextVisible(false);
    fill_ = new QLabel;
    result_ = new QLabel;
    result_->setWordWrap(true);

    select_ = new QPushButton("Select");
    clear_ = new QPushButton("Clear");
    enter_ = new QPushButton("Enter");
    gunUp_ = new QPushButton("Gun up");
    gunDown_ = new QPushButton("Gun down");
    gunUp_->setToolTip("The supplier takes the gun up: the board reports it (GUN_UP); the Smart210 sends START when it has tickets queued (a manual board starts by itself)");
    gunDown_->setToolTip("The gun goes back on its hook: a fill that runs ends 3 seconds later");
    select_->setToolTip("The select key of the dispenser (auto mode): the board reports it, the Smart210 chooses the nozzle");
    clear_->setToolTip("Auto mode: the clear key of the dispenser (reported to the Smart210). Manual mode: clears the amount that is being typed");
    enter_->setToolTip("Manual mode: the amount typed is the preset; the fill starts when the gun is up");

    // the three displays of the dispenser, like the real one: 6 digits (kg), 6 digits (litres), 4 digits (density)
    QHBoxLayout *displays = new QHBoxLayout;
    const char *captions[] = { "kg", "litres", "density" };
    QLabel **targets[] = { &kgDisplay_, &litresDisplay_, &densityDisplay_ };
    for (int i = 0; i < 3; ++i) {
        QVBoxLayout *one = new QVBoxLayout;
        QLabel *caption = new QLabel(QString("%1 (%2 digits)").arg(captions[i]).arg(i == 2 ? 4 : 6));
        caption->setAlignment(Qt::AlignHCenter);
        one->addWidget(caption);
        one->addWidget(makeDisplay(targets[i]));
        displays->addLayout(one, i == 2 ? 2 : 3);
    }

    // the keypad of the dispenser: the digits and the point type the amount of a manual fill; Select and Clear are the keys of an auto dispenser
    QGridLayout *keypad = new QGridLayout;
    const char *keys[] = { "7", "8", "9", "4", "5", "6", "1", "2", "3", "0", "." };
    for (int i = 0; i < 11; ++i) {
        QPushButton *key = new QPushButton(keys[i]);
        key->setMinimumWidth(34);
        digits_ << key;
        connect(key, SIGNAL(clicked()), this, SLOT(digitKey()));
        if (i < 9) {
            keypad->addWidget(key, i / 3, i % 3);
        } else {
            keypad->addWidget(key, 3, i - 9);
        }
    }
    keypad->addWidget(select_, 0, 3);
    keypad->addWidget(clear_, 1, 3);
    keypad->addWidget(enter_, 2, 3, 2, 1);
    keypad->addWidget(gunUp_, 4, 0, 1, 2);
    keypad->addWidget(gunDown_, 4, 2, 1, 2);    stop_ = new QPushButton("Stop fill");
    flow_ = new QSpinBox;
    flow_->setRange(1, 5000);
    flow_->setSuffix(" g/s");
    flow_->setToolTip("How fast a fill runs");
    fault_ = new QComboBox;
    fault_->addItem("No fault", 0);
    fault_->addItem("Flow sensor fault", 1);
    fault_->addItem("Valve fault", 2);
    fault_->addItem("Memory fault", 3);
    fault_->addItem("Other fault", 4);
    QPushButton *cut = new QPushButton("Power cut (3 s)");
    silent_ = new QCheckBox("Not answering");
    QPushButton *lose = new QPushButton("Lose next 3 answers");
    QPushButton *spoil = new QPushButton("Spoil next 3 answers");
    QPushButton *reset = new QPushButton("Factory reset");
    reset->setToolTip("The board forgets its config (as a new board)");

    QGridLayout *buttons = new QGridLayout;
    buttons->addWidget(stop_, 0, 0, 1, 2);
    buttons->addWidget(new QLabel("Flow"), 2, 0);
    buttons->addWidget(flow_, 2, 1);
    buttons->addWidget(fault_, 3, 0);
    buttons->addWidget(cut, 3, 1);
    buttons->addWidget(silent_, 4, 0);
    buttons->addWidget(reset, 4, 1);
    buttons->addWidget(lose, 5, 0);
    buttons->addWidget(spoil, 5, 1);

    QVBoxLayout *layout = new QVBoxLayout(this);
    layout->addWidget(state_);
    layout->addWidget(config_);
    layout->addWidget(progress_);
    layout->addWidget(fill_);
    layout->addWidget(result_);
    layout->addLayout(displays);
    layout->addLayout(keypad);
    layout->addLayout(buttons);

    connect(select_, SIGNAL(clicked()), this, SLOT(selectKey()));
    connect(enter_, SIGNAL(clicked()), this, SLOT(enterKey()));
    connect(stop_, SIGNAL(clicked()), this, SLOT(stopFill()));
    connect(clear_, SIGNAL(clicked()), this, SLOT(clearKey()));
    connect(gunUp_, SIGNAL(clicked()), this, SLOT(gunUp()));
    connect(gunDown_, SIGNAL(clicked()), this, SLOT(gunDown()));
    connect(fault_, SIGNAL(activated(int)), this, SLOT(faultPicked(int)));
    connect(cut, SIGNAL(clicked()), this, SLOT(powerCut()));
    connect(silent_, SIGNAL(toggled(bool)), this, SLOT(silentToggled(bool)));
    connect(lose, SIGNAL(clicked()), this, SLOT(loseAnswers()));
    connect(spoil, SIGNAL(clicked()), this, SLOT(spoilAnswers()));
    connect(reset, SIGNAL(clicked()), this, SLOT(factoryReset()));
    connect(flow_, SIGNAL(valueChanged(int)), this, SLOT(flowChanged(int)));

    refresh();
}

void NozzlePanel::refresh()
{
    const BoardSimulator::Board &b = sim_->board(address_);
    refreshing_ = true;

    state_->setText(BoardSimulator::stateName(b.state));
    state_->setStyleSheet("color: " + colorOf(b.state) + ";");

    if (b.config.pulsesX100 == 0 && b.config.density == 0) {
        config_->setText("Not configured (a new board)");
    } else {
        config_->setText(QString("%1  |  fuel %2  |  %3 kg/L  |  %4 pulses/L")
                             .arg(b.config.mode == ModeManual ? "MANUAL" : "AUTO")
                             .arg(b.config.fuelType.isEmpty() ? QString("-") : b.config.fuelType)
                             .arg(b.config.density / 10000.0, 0, 'f', 4)
                             .arg(b.config.pulsesX100 / 100.0, 0, 'f', 2));
    }

    const bool fillShown = b.state == StateDispensing || b.state == StateResult;
    progress_->setRange(0, b.presetG > 0 ? int(qMin<quint32>(b.presetG, 2000000000)) : 1);
    progress_->setValue(fillShown ? int(qMin<quint32>(b.dispensedG, 2000000000)) : 0);
    if (b.state == StateSelected || b.state == StateArmed || fillShown) {
        fill_->setText(QString("%1 of %2 kg  (%3 L)").arg(kg(b.dispensedG)).arg(b.presetG > 0 ? kg(b.presetG) : QString("-")).arg(litres(b.dispensedMl)));
    } else if (b.shownG > 0) {
        fill_->setText(QString("Display: balance %1 kg (not a preset)").arg(kg(b.shownG)));   // SHOW_BALANCE: a card's balance, for the customer to confirm
    } else {
        fill_->setText("No fill");
    }

    if (b.state == StateResult) {
        result_->setText(QString("Result #%1 waits for the Smart210: %2 kg, %3").arg(b.resultId).arg(kg(b.dispensedG)).arg(reasonText(b.reason)));
    } else if (b.lastAckedId != 0) {
        result_->setText(QString("Last result #%1 was acknowledged").arg(b.lastAckedId));
    } else {
        result_->setText("No result yet");
    }

    const bool manual = b.config.mode == ModeManual;
    if (!manual || b.state != StateIdle) {
        typed_.clear(); // what was being typed is gone when the board is not waiting for an amount
    }

    // display 1, the kg: what is being typed, a fill's weight (it runs or is held as a result), the preset, or a card's balance; display 2, the litres of the fill
    double kgShown = 0;
    if (!typed_.isEmpty()) {
        kgShown = typed_.toDouble();
    } else if (b.state == StateDispensing || b.state == StateResult) {
        kgShown = b.dispensedG / 1000.0;
    } else if (b.state == StateSelected || b.state == StateArmed) {
        kgShown = b.presetG / 1000.0;
    } else if (b.shownG > 0) {
        kgShown = b.shownG / 1000.0;
    }
    kgDisplay_->setText(typed_.isEmpty() ? shown(kgShown, 6, 2) : typed_.rightJustified(7, ' '));
    litresDisplay_->setText(shown((b.state == StateDispensing || b.state == StateResult) ? b.dispensedMl / 1000.0 : 0, 6, 2));
    densityDisplay_->setText(densityText(b.config.density));

    select_->setEnabled(!manual && (b.state == StateIdle || b.state == StateArmed));
    clear_->setEnabled(manual ? (b.state == StateIdle && !typed_.isEmpty()) : b.state == StateArmed);
    enter_->setEnabled(manual && b.state == StateIdle && !typed_.isEmpty());
    for (int i = 0; i < digits_.size(); ++i) {
        digits_.at(i)->setEnabled(manual && b.state == StateIdle);
    }
    stop_->setEnabled(b.state == StateDispensing);
    gunUp_->setEnabled(!b.gunUp && (b.state == StateIdle || b.state == StateArmed || b.state == StateDispensing));
    gunDown_->setEnabled(b.gunUp || (b.state == StateDispensing && b.hookDownMsLeft == 0));    flow_->setValue(b.flowGramsPerSecond);
    fault_->setCurrentIndex(fault_->findData(b.fault));
    silent_->setChecked(b.silent);

    refreshing_ = false;
}

void NozzlePanel::selectKey()
{
    sim_->pressSelect(address_);
}

void NozzlePanel::clearKey()
{
    if (sim_->board(address_).config.mode == ModeManual) {
        typed_.clear(); // a manual dispenser: the key clears what is being typed
        refresh();

        return;
    }
    sim_->pressClearKey(address_);
}

void NozzlePanel::gunUp()
{
    sim_->liftGun(address_);
}

void NozzlePanel::gunDown()
{
    sim_->putGunDown(address_);
}

// A digit or the point of the keypad: the amount (kg, 6 digits, up to 2 after the point) shows on display 1 as it is typed.
void NozzlePanel::digitKey()
{
    QPushButton *key = qobject_cast<QPushButton *>(sender());
    if (key == 0) {
        return;
    }
    QString next = typed_ + key->text();
    const int point = next.indexOf('.');
    const int digitCount = next.size() - (point >= 0 ? 1 : 0);
    if (next.count('.') > 1 || digitCount > 6 || (point >= 0 && next.size() - point - 1 > 2)) {
        return; // the display has no room for it
    }
    typed_ = next;
    refresh();
}

// Enter: the amount that was typed is the preset (the board is SET); the gun then starts the fill.
void NozzlePanel::enterKey()
{
    bool ok = false;
    const double kilograms = typed_.toDouble(&ok);
    if (ok && kilograms > 0 && sim_->typeAmount(address_, quint32(kilograms * 1000 + 0.5))) {
        typed_.clear();
    }
    refresh();
}
void NozzlePanel::stopFill()
{
    sim_->stopFill(address_);
}

void NozzlePanel::faultPicked(int index)
{
    sim_->setFault(address_, fault_->itemData(index).toInt());
}

void NozzlePanel::powerCut()
{
    sim_->powerCut(address_, 3000);
}

void NozzlePanel::silentToggled(bool on)
{
    if (refreshing_) {
        return;
    }
    BoardSimulator::Board &b = sim_->board(address_);
    b.silent = on;
    b.silentMsLeft = 0;
    sim_->changed(address_);
}

void NozzlePanel::loseAnswers()
{
    sim_->board(address_).dropReplies = 3;
}

void NozzlePanel::spoilAnswers()
{
    sim_->board(address_).corruptReplies = 3;
}

void NozzlePanel::factoryReset()
{
    const int flow = sim_->board(address_).flowGramsPerSecond;
    sim_->addBlank(address_);
    sim_->board(address_).flowGramsPerSecond = flow;
}

void NozzlePanel::flowChanged(int gramsPerSecond)
{
    if (!refreshing_) {
        sim_->board(address_).flowGramsPerSecond = gramsPerSecond;
    }
}

// A display of the dispenser: green digits on black, in a fixed-width font.
QLabel *NozzlePanel::makeDisplay(QLabel **target)
{
    QLabel *label = new QLabel;
    QFont font = QFontDatabase::systemFont(QFontDatabase::FixedFont);
    font.setPointSize(18);
    font.setBold(true);
    label->setFont(font);
    label->setAlignment(Qt::AlignRight | Qt::AlignVCenter);
    label->setStyleSheet("background: #111827; color: #4ade80; padding: 4px 8px; border-radius: 4px;");
    label->setMinimumHeight(40);
    *target = label;

    return label;
}