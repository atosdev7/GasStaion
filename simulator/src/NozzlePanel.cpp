#include "NozzlePanel.h"

#include "BoardSimulator.h"

#include <QCheckBox>
#include <QComboBox>
#include <QGridLayout>
#include <QHBoxLayout>
#include <QLabel>
#include <QLineEdit>
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

    select_ = new QPushButton("Select key");
    clear_ = new QPushButton("Clear key");
    gun_ = new QPushButton("Lift the gun");
    gun_->setToolTip("The board reports the gun (GUN_UP); the Smart210 sends START when it has tickets queued");
    select_->setToolTip("The cashier presses the nozzle's select key (auto mode)");
    amount_ = new QLineEdit;
    amount_->setPlaceholderText("kg");
    amount_->setMaximumWidth(70);
    type_ = new QPushButton("Type amount + start");
    type_->setToolTip("Manual mode: the supplier types the amount on the dispenser's keyboard and the fill starts");
    stop_ = new QPushButton("Stop fill");
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

    QHBoxLayout *manual = new QHBoxLayout;
    manual->addWidget(amount_);
    manual->addWidget(type_, 1);

    QGridLayout *buttons = new QGridLayout;
    buttons->addWidget(select_, 0, 0);
    buttons->addWidget(stop_, 0, 1);
    buttons->addLayout(manual, 1, 0, 1, 2);
    buttons->addWidget(new QLabel("Flow"), 2, 0);
    buttons->addWidget(flow_, 2, 1);
    buttons->addWidget(fault_, 3, 0);
    buttons->addWidget(cut, 3, 1);
    buttons->addWidget(silent_, 4, 0);
    buttons->addWidget(reset, 4, 1);
    buttons->addWidget(lose, 5, 0);
    buttons->addWidget(spoil, 5, 1);
    buttons->addWidget(clear_, 6, 0);
    buttons->addWidget(gun_, 6, 1);

    QVBoxLayout *layout = new QVBoxLayout(this);
    layout->addWidget(state_);
    layout->addWidget(config_);
    layout->addWidget(progress_);
    layout->addWidget(fill_);
    layout->addWidget(result_);
    layout->addLayout(buttons);

    connect(select_, SIGNAL(clicked()), this, SLOT(selectKey()));
    connect(type_, SIGNAL(clicked()), this, SLOT(typeAmount()));
    connect(amount_, SIGNAL(returnPressed()), this, SLOT(typeAmount()));
    connect(stop_, SIGNAL(clicked()), this, SLOT(stopFill()));
    connect(clear_, SIGNAL(clicked()), this, SLOT(clearKey()));
    connect(gun_, SIGNAL(clicked()), this, SLOT(liftGun()));
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
    select_->setEnabled(!manual && (b.state == StateIdle || b.state == StateArmed));
    type_->setEnabled(manual && b.state == StateIdle);
    amount_->setEnabled(manual && b.state == StateIdle);
    stop_->setEnabled(b.state == StateDispensing);
    clear_->setEnabled(!manual && b.state == StateArmed);
    gun_->setEnabled(b.state == StateIdle || b.state == StateArmed);
    flow_->setValue(b.flowGramsPerSecond);
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
    sim_->pressClearKey(address_);
}

void NozzlePanel::liftGun()
{
    sim_->liftGun(address_);
}

void NozzlePanel::typeAmount()
{
    bool ok = false;
    const double kilograms = amount_->text().trimmed().replace(',', '.').toDouble(&ok);
    if (!ok || kilograms <= 0 || kilograms > 4000000) {
        amount_->setStyleSheet("background: #fecaca;");

        return;
    }
    amount_->setStyleSheet(QString());
    if (sim_->typeAmount(address_, quint32(kilograms * 1000 + 0.5))) {
        amount_->clear();
    }
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
