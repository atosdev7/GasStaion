#include "ManualSupplyPage.h"

#include "Barcode.h"
#include "BoardProtocol.h"
#include "Decimal.h"
#include "MachineConfig.h"
#include "SupplyQueue.h"
#include "SupplyRecord.h"
#include "SupplySession.h"
#include "Settings.h"
#include "ui_ManualSupplyPage.h"
#include "Theme.h"

#include <QComboBox>
#include <QDateTime>
#include <QDateTimeEdit>
#include <QHeaderView>
#include <QLabel>
#include <QLineEdit>
#include <QPushButton>
#include <QRegExpValidator>
#include <QTableWidget>

namespace {

QString kg(qlonglong x100)
{
    return Decimal::format(x100, 2);
}

}

ManualSupplyPage::ManualSupplyPage(QSqlDatabase db, MachineConfig *config, SupplySession *session, QWidget *parent)
    : QWidget(parent), ui_(new Ui::ManualSupplyPage), db_(db), config_(config), session_(session), kgTyped_(false)
{
    ui_->setupUi(this);

    nozzle_ = ui_->msNozzleCombo;
    time_ = ui_->msTimeEdit;
    density_ = ui_->msDensityField;
    preset_ = ui_->msPresetField;
    QRegExpValidator *decimal = new QRegExpValidator(QRegExp("[0-9]{0,9}([.][0-9]{0,2})?"), this);
    kg_ = ui_->msKgField;
    kg_->setValidator(decimal);
    remainder_ = ui_->msRemainderField;
    remainder_->setValidator(decimal);
    liters_ = ui_->msLitersField;   // worked out from the kg and the density: it is never typed
    liters_->setReadOnly(true);
    liters_->setFocusPolicy(Qt::NoFocus);
    code_ = ui_->msBarcode;
    summary_ = ui_->msSummary;
    status_ = ui_->msStatus;
    comment_ = ui_->msCommentField;
    save_ = ui_->msSaveButton;

    tickets_ = ui_->msTickets;
    tickets_->setColumnCount(2);
    tickets_->setHorizontalHeaderLabels(QStringList() << tr("Ticket") << tr("kg"));
    tickets_->horizontalHeader()->setStretchLastSection(true);
    tickets_->setColumnWidth(0, 280);
    tickets_->verticalHeader()->setVisible(false);
    tickets_->setEditTriggers(QAbstractItemView::NoEditTriggers);
    tickets_->setSelectionBehavior(QAbstractItemView::SelectRows);
    tickets_->setSelectionMode(QAbstractItemView::SingleSelection);
    tickets_->setFocusPolicy(Qt::NoFocus);

    connect(nozzle_, SIGNAL(currentIndexChanged(int)), this, SLOT(nozzleChanged()));
    connect(kg_, SIGNAL(textEdited(QString)), this, SLOT(kgEdited()));
    connect(remainder_, SIGNAL(textEdited(QString)), this, SLOT(remainderEdited()));
    connect(code_, SIGNAL(returnPressed()), this, SLOT(addTicket()));
    connect(ui_->msAddButton, SIGNAL(clicked()), this, SLOT(addTicket()));
    connect(ui_->msRemoveButton, SIGNAL(clicked()), this, SLOT(removeSelected()));
    connect(save_, SIGNAL(clicked()), this, SLOT(save()));

    reload();
}

ManualSupplyPage::~ManualSupplyPage()
{
    delete ui_;
}

int ManualSupplyPage::nozzle() const
{
    return nozzle_->itemData(nozzle_->currentIndex()).toInt();
}

// kg per litre x10000 of the chosen nozzle's board; -1 when it has none.
qlonglong ManualSupplyPage::density() const
{
    return nozzle() > 0 ? config_->nozzle(nozzle()).density : -1;
}

qlonglong ManualSupplyPage::ticketsX100() const
{
    qlonglong total = 0;
    foreach (qlonglong weight, weights_) {
        total += weight;
    }

    return total;
}

// An empty form: the nozzles that work in manual mode, the time of now.
void ManualSupplyPage::reload()
{
    const int keep = nozzle();

    nozzle_->blockSignals(true);
    nozzle_->clear();
    foreach (const MachineConfig::Nozzle &n, config_->nozzles()) {
        if (n.seen() && n.mode == BoardProtocol::ModeManual) {
            nozzle_->addItem(tr("Nozzle %1 (%2)").arg(n.number).arg(n.fuelType), n.number);
        }
    }
    const int at = nozzle_->findData(keep);
    nozzle_->setCurrentIndex(at >= 0 ? at : (nozzle_->count() > 0 ? 0 : -1));
    nozzle_->blockSignals(false);

    clearForm();
}

void ManualSupplyPage::clearForm()
{
    comment_->clear();
    // the time may be as far back as Machine Config says (the chosen day starts at midnight)
    const QDateTime now = QDateTime::currentDateTime();
    time_->setMinimumDateTime(QDateTime(now.date().addDays(-Settings(db_).intValue(SettingKey::ManualSupplyDays, 3)), QTime(0, 0, 0)));
    time_->setDateTime(now);
    kg_->clear();
    remainder_->clear();
    liters_->clear();
    preset_->clear();
    density_->setText(density() > 0 ? Decimal::format(density(), 4) : QString());
    barcodes_.clear();
    weights_.clear();
    kgTyped_ = false;
    code_->clear();
    status_->clear();
    showTickets();
}

void ManualSupplyPage::nozzleChanged()
{
    barcodes_.clear(); // the tickets must be of the fuel of the nozzle
    weights_.clear();
    kg_->clear();
    remainder_->clear();
    liters_->clear();
    kgTyped_ = false;
    density_->setText(density() > 0 ? Decimal::format(density(), 4) : QString());
    status_->clear();
    showTickets();
}

// The litres of the kg and the density of the nozzle: litres = kg / density.
void ManualSupplyPage::calculateLiters()
{
    qlonglong weight = 0;
    if (density() > 0 && Decimal::parse(kg_->text().trimmed(), 2, &weight) && weight > 0) {
        liters_->setText(kg((weight * 10000 + density() / 2) / density()));
    } else if (kg_->text().trimmed().isEmpty()) {
        liters_->clear();
    }
}

// The remainder that goes with the dispensed kg: what is left of the tickets. Empty while there are no tickets or the kg is not a figure or
// is more than the tickets cover (the summary says so).
void ManualSupplyPage::showRemainder()
{
    qlonglong dispensed = 0;
    if (!barcodes_.isEmpty() && Decimal::parse(kg_->text().trimmed(), 2, &dispensed) && dispensed <= ticketsX100()) {
        remainder_->setText(kg(ticketsX100() - dispensed));
    } else {
        remainder_->clear();
    }
}

void ManualSupplyPage::kgEdited()
{
    kgTyped_ = true; // the cashier decides: the preset does not set it any more
    status_->clear();
    calculateLiters();
    showRemainder();
    showSummary();
}

// The remainder typed: the dispensed kg is what is left of the tickets (and the litres follow).
void ManualSupplyPage::remainderEdited()
{
    kgTyped_ = true;
    status_->clear();
    qlonglong left = 0;
    if (!barcodes_.isEmpty() && Decimal::parse(remainder_->text().trimmed(), 2, &left) && left <= ticketsX100()) {
        kg_->setText(kg(ticketsX100() - left));
    } else {
        kg_->clear();
    }
    calculateLiters();
    showSummary();
}

void ManualSupplyPage::addTicket()
{
    const QString text = code_->text().trimmed();
    if (text.isEmpty()) {
        return;
    }
    Theme::setState(status_, "error");
    if (nozzle() <= 0) {
        status_->setText(tr("Choose the nozzle first."));

        return;
    }

    const SupplyQueue::Scan scan = SupplyQueue::check(db_, text, config_->nozzle(nozzle()).fuelType);
    if (!scan.ok) {
        status_->setText(scan.error);
        code_->selectAll();

        return;
    }
    if (barcodes_.contains(scan.barcode)) {
        status_->setText(tr("This ticket is in the list already."));
        code_->selectAll();

        return;
    }

    barcodes_ << scan.barcode;
    weights_ << scan.kgX100;
    status_->clear();
    code_->clear();
    showTickets();
}

void ManualSupplyPage::removeSelected()
{
    const int row = tickets_->currentRow();
    if (row >= 0 && row < barcodes_.size() && !tickets_->selectedItems().isEmpty()) {
        barcodes_.removeAt(row);
        weights_.removeAt(row);
        showTickets();
    }
}

// The tickets typed so far: their total is the preset, and the dispensed kg follows it (the remainder 0) until the cashier types another figure.
void ManualSupplyPage::showTickets()
{
    tickets_->setRowCount(barcodes_.size());
    for (int i = 0; i < barcodes_.size(); ++i) {
        QTableWidgetItem *weight = new QTableWidgetItem(kg(weights_.at(i)));
        weight->setTextAlignment(Qt::AlignRight | Qt::AlignVCenter);
        tickets_->setItem(i, 0, new QTableWidgetItem(Barcode::fromKey(barcodes_.at(i))));
        tickets_->setItem(i, 1, weight);
    }

    preset_->setText(barcodes_.isEmpty() ? QString() : kg(ticketsX100()));
    if (!kgTyped_) {
        kg_->setText(barcodes_.isEmpty() ? QString() : kg(ticketsX100()));
        calculateLiters();
    }
    showRemainder();
    showSummary();
}

// How the tickets compare with the dispensed kg; Save is possible when they fit and the litres are there.
void ManualSupplyPage::showSummary()
{
    const qlonglong total = ticketsX100();
    qlonglong dispensed = 0;
    qlonglong liters = 0;
    const bool typed = Decimal::parse(kg_->text().trimmed(), 2, &dispensed) && dispensed > 0 && Decimal::parse(liters_->text().trimmed(), 2, &liters) && liters > 0;
    bool matches = false;
    if (nozzle_->count() == 0) {
        Theme::setState(summary_, "");
        summary_->setText(tr("No nozzle works in manual mode."));
    } else if (density() <= 0) {
        Theme::setState(summary_, "warning");
        summary_->setText(tr("The density of this nozzle is not known yet, so the litres cannot be worked out: read its config first."));
    } else if (!typed) {
        Theme::setState(summary_, "");
        summary_->setText(barcodes_.isEmpty() ? tr("Type the ticket barcodes, one after the other.") : tr("Type the kg that were dispensed."));
    } else {
        const qlonglong rest = total - dispensed;
        matches = !barcodes_.isEmpty() && rest >= 0;
        Theme::setState(summary_, matches ? "ok" : "warning");
        if (barcodes_.isEmpty() || rest < 0) {
            summary_->setText(tr("Tickets: %1 kg, dispensed: %2 kg. The tickets must cover everything that was dispensed.").arg(kg(total)).arg(kg(dispensed)));
        } else if (rest == 0 || rest <= SupplyRecord::ToleranceX100) {
            summary_->setText(tr("Tickets: %1 kg, dispensed: %2 kg. No remainder (up to %3 kg the station keeps).").arg(kg(total)).arg(kg(dispensed)).arg(kg(SupplyRecord::ToleranceX100)));
        } else {
            summary_->setText(tr("Tickets: %1 kg, dispensed: %2 kg. A remainder ticket of %3 kg is made and printed.").arg(kg(total)).arg(kg(dispensed)).arg(kg(rest)));
        }
    }
    save_->setEnabled(matches);
}

void ManualSupplyPage::save()
{
    qlonglong dispensed = 0;
    qlonglong liters = 0;
    Theme::setState(status_, "error");
    if (!Decimal::parse(kg_->text().trimmed(), 2, &dispensed) || !Decimal::parse(liters_->text().trimmed(), 2, &liters)) {
        status_->setText(tr("Type the weight and the litres as numbers, for example 15.00."));

        return;
    }

    QString why;
    const int tickets = barcodes_.size();
    const qlonglong rest = ticketsX100() - dispensed;
    if (!session_->recordManual(nozzle(), dispensed, liters, barcodes_, time_->dateTime(), &why, comment_->text())) {
        status_->setText(why);

        return;
    }

    clearForm();
    Theme::setState(status_, "ok");
    status_->setText(rest > SupplyRecord::ToleranceX100
                         ? tr("The supply was recorded with %1 tickets, and a remainder ticket of %2 kg was made. It will be uploaded.").arg(tickets).arg(kg(rest))
                         : tr("The supply was recorded with %1 tickets. It will be uploaded.").arg(tickets));
    emit recorded();
}
