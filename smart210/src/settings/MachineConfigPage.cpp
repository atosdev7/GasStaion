#include "MachineConfigPage.h"

#include "AppReset.h"
#include "AutoUpload.h"
#include "BoardProtocol.h"
#include "CardReader.h"
#include "Decimal.h"
#include "MachineConfig.h"
#include "NozzleConfigSync.h"
#include "PrintService.h"
#include "ServerForm.h"
#include "Session.h"
#include "Settings.h"
#include "SetupFlow.h"
#include "ui_MachineConfigPage.h"
#include "Theme.h"
#include "Uploader.h"

#include <QComboBox>
#include <QHeaderView>
#include <QIntValidator>
#include <QLineEdit>
#include <QTabWidget>
#include <QVariant>
#include <QLabel>
#include <QListWidget>
#include <QMessageBox>
#include <QPushButton>
#include <QTableWidget>
#include <QTimer>

namespace {

const int LogLines = 12;

// the columns of the nozzle table
enum { ColNozzle, ColName, ColStatus, ColMode, ColFuel, ColDensity, ColPulses, ColTank, ColRemove, Columns };

QString subject(const QString &what)
{
    if (what.startsWith("nozzle:")) {
        return QObject::tr("Nozzle %1").arg(what.mid(7));
    }

    if (what == "converter") {
        return QObject::tr("Converter port");
    }

    if (what == "printer") {
        return QObject::tr("Printer address");
    }

    if (what == "card reader") {
        return QObject::tr("Card reader");
    }

    if (what == "reset") {
        return QObject::tr("Reset");
    }

    if (what == "upload mode") {
        return QObject::tr("Upload");
    }

    if (what == "show sales without receipt") {
        return QObject::tr("Sales without a receipt");
    }

    return what == "server" ? QObject::tr("Server address") : what;
}

}

MachineConfigPage::MachineConfigPage(SetupFlow *flow, MachineConfig *config, Session *session, Settings *settings, PrintService *printer,
                                     NozzleConfigSync *nozzles, QWidget *parent)
    : QWidget(parent), ui_(new Ui::MachineConfigPage), config_(config), session_(session), settings_(settings), printer_(printer), nozzles_(nozzles),
      checking_(false), armedReset_(0), resetTimer_(0), selectedNumber_(0)
{
    ui_->setupUi(this);

    // the server form needs the flow, so it is made here and put where the Server tab has room for it
    server_ = new ServerForm(flow);
    ui_->serverFormLayout->addWidget(server_);

    // the nozzles: what each board reported, and a form to send a change to the selected board
    table_ = ui_->nozzleTable;
    table_->setColumnCount(Columns);
    table_->setHorizontalHeaderLabels(QStringList() << tr("Nozzle") << tr("Name") << tr("Status") << tr("Mode") << tr("Fuel type") << tr("Density")
                                                    << tr("Pulses per litre") << tr("Tank") << QString());
    table_->horizontalHeader()->setStretchLastSection(false);
    table_->horizontalHeader()->setSectionResizeMode(ColTank, QHeaderView::Stretch);
    table_->setColumnWidth(ColNozzle, 84);
    table_->setColumnWidth(ColName, 150);
    table_->setColumnWidth(ColStatus, 130);
    table_->setColumnWidth(ColMode, 84);
    table_->setColumnWidth(ColFuel, 116);
    table_->setColumnWidth(ColDensity, 100);
    table_->setColumnWidth(ColPulses, 140);
    table_->setColumnWidth(ColRemove, 90);
    table_->setHorizontalScrollBarPolicy(Qt::ScrollBarAlwaysOff);
    table_->verticalHeader()->setDefaultSectionSize(40); // the Remove button of a row fits in it
    table_->verticalHeader()->setVisible(false);
    table_->setEditTriggers(QAbstractItemView::NoEditTriggers);
    table_->setSelectionBehavior(QAbstractItemView::SelectRows);
    table_->setSelectionMode(QAbstractItemView::SingleSelection);
    table_->setColumnHidden(ColDensity, true); // the form shows them; the Dispensers screen lists them all
    table_->setColumnHidden(ColPulses, true);
    table_->setFocusPolicy(Qt::NoFocus);
    table_->setVerticalScrollBarPolicy(Qt::ScrollBarAlwaysOff);
    table_->setMinimumHeight(6 * 40 + 44); // six rows (the most there can be) and the header: nothing is cut off or runs over the Add group

    selected_ = ui_->selectedNozzle;
    // its text changes with the pick (a long hint, then "Nozzle 2"): it wraps and does not size the form column, or the table would change its width
    selected_->setWordWrap(true);
    selected_->setSizePolicy(QSizePolicy::Ignored, QSizePolicy::Preferred);
    selected_->setMinimumHeight(selected_->fontMetrics().lineSpacing() * 2);
    mode_ = ui_->nozzleMode;
    mode_->addItem(tr("Auto"), BoardProtocol::ModeAuto);
    mode_->addItem(tr("Manual"), BoardProtocol::ModeManual);
    fuel_ = ui_->nozzleFuel;
    density_ = ui_->nozzleDensity;
    pulses_ = ui_->nozzlePulses;
    nozzleName_ = ui_->nozzleName;
    // (the fields of the config use the width of their group: the form column shares the tab with the table, see the .ui)

    converterPort_ = ui_->converterPort;
    converterPort_->setValidator(new QIntValidator(1, 65535, converterPort_));

    status_ = ui_->machineStatus;
    log_ = ui_->changeLog;

    // the printer: its address, saved, and a check that asks the printer for its status
    printerHost_ = ui_->printerHost;
    printerPort_ = ui_->printerPort;
    printerPort_->setValidator(new QIntValidator(1, 65535, printerPort_));
    printerStatus_ = ui_->printerStatus;

    // the RFID card reader: its serial port, speed and how the id is framed and read
    cardPort_ = ui_->cardReaderPort;
    cardPort_->setPlaceholderText(CardReader::defaultPort());
    cardBaud_ = ui_->cardReaderBaud;
    const int bauds[] = {1200, 2400, 4800, 9600, 19200, 38400, 57600, 115200};
    for (int i = 0; i < int(sizeof(bauds) / sizeof(bauds[0])); ++i) {
        cardBaud_->addItem(QString::number(bauds[i]), bauds[i]);
    }
    cardFormat_ = ui_->cardReaderFormat;
    cardFormat_->addItem(tr("The id ends with CR or LF"), "line");
    cardFormat_->addItem(tr("The id has a fixed length"), "fixed");
    cardLength_ = ui_->cardReaderLength;
    cardLength_->setValidator(new QIntValidator(1, 64, cardLength_));
    cardPrefix_ = ui_->cardReaderPrefix;
    cardEncoding_ = ui_->cardReaderEncoding;
    cardEncoding_->addItem(tr("Text (ASCII)"), "ascii");
    cardEncoding_->addItem(tr("Hex digits of the bytes"), "hex");

    // when the terminal uploads by itself (the download is always every minute)
    uploadMode_ = ui_->uploadModeCombo;
    uploadMode_->addItem(tr("Manual"), AutoUpload::textOf(AutoUpload::Manual));
    uploadMode_->addItem(tr("At close of day"), AutoUpload::textOf(AutoUpload::CloseOfDay));
    uploadMode_->addItem(tr("Continuous"), AutoUpload::textOf(AutoUpload::Continuous));

    connect(uploadMode_, SIGNAL(activated(int)), this, SLOT(uploadModePicked()));
    connect(ui_->saveUploadModeButton, SIGNAL(clicked()), this, SLOT(saveUploadMode()));
    connect(ui_->saveReviewOptionsButton, SIGNAL(clicked()), this, SLOT(saveReviewOptions()));
    connect(ui_->saveManualSupplyOptionsButton, SIGNAL(clicked()), this, SLOT(saveManualSupplyOptions()));
    connect(ui_->addNozzleButton, SIGNAL(clicked()), this, SLOT(addNozzlePressed()));
    connect(ui_->addNozzleFuel, SIGNAL(activated(int)), this, SLOT(addFuelPicked()));
    connect(ui_->cleanDataButton, SIGNAL(clicked()), this, SLOT(cleanDataPressed()));
    connect(ui_->clearConfigButton, SIGNAL(clicked()), this, SLOT(clearConfigPressed()));
    resetTimer_ = new QTimer(this);
    resetTimer_->setSingleShot(true);
    resetTimer_->setInterval(6000);
    connect(resetTimer_, SIGNAL(timeout()), this, SLOT(disarmReset()));
    connect(ui_->updateNozzleButton, SIGNAL(clicked()), this, SLOT(updateNozzle()));
    connect(ui_->readNozzlesButton, SIGNAL(clicked()), this, SLOT(readNozzles()));
    connect(ui_->saveNozzleNameButton, SIGNAL(clicked()), this, SLOT(saveNozzleName()));
    connect(table_, SIGNAL(itemSelectionChanged()), this, SLOT(rowSelected()));
    connect(fuel_, SIGNAL(activated(int)), this, SLOT(fuelPicked()));
    connect(ui_->saveConverterPortButton, SIGNAL(clicked()), this, SLOT(saveConverterPort()));
    connect(ui_->saveCardReaderButton, SIGNAL(clicked()), this, SLOT(saveCardReader()));
    connect(cardFormat_, SIGNAL(activated(int)), this, SLOT(formatPicked()));
    connect(ui_->machineBackButton, SIGNAL(clicked()), this, SIGNAL(backRequested()));
    connect(server_, SIGNAL(configured()), this, SLOT(serverConfigured()));
    connect(ui_->savePrinterButton, SIGNAL(clicked()), this, SLOT(savePrinter()));
    connect(ui_->checkPrinterButton, SIGNAL(clicked()), this, SLOT(checkPrinter()));
    connect(printer_, SIGNAL(finished(bool,QString)), this, SLOT(printerChecked(bool,QString)));
    connect(nozzles_, SIGNAL(changed()), this, SLOT(nozzlesChanged()));
    connect(nozzles_, SIGNAL(readDone(int)), this, SLOT(readDone(int)));
    connect(nozzles_, SIGNAL(readFailed(int,QString)), this, SLOT(readFailed(int,QString)));
    connect(nozzles_, SIGNAL(updateDone(int)), this, SLOT(updateDone(int)));
    connect(nozzles_, SIGNAL(updateFailed(int,QString)), this, SLOT(updateFailed(int,QString)));
}

MachineConfigPage::~MachineConfigPage()
{
    delete ui_;
}

void MachineConfigPage::reload()
{
    disarmReset();
    showResetBox();
    fillAddForm();
    server_->reload();
    serverBefore_ = settings_->value(SettingKey::ServerHost) + ":" + QString::number(settings_->intValue(SettingKey::ServerPort, 80));

    printerHost_->setText(settings_->value(SettingKey::PrinterHost));
    printerPort_->setText(QString::number(settings_->intValue(SettingKey::PrinterPort, PrintService::DefaultPort)));
    printerStatus_->clear();
    printerBefore_ = settings_->value(SettingKey::PrinterHost) + ":" + QString::number(settings_->intValue(SettingKey::PrinterPort, PrintService::DefaultPort));

    converterPort_->setText(QString::number(settings_->intValue(SettingKey::ConverterPort, MachineConfig::DefaultConverterPort)));
    converterBefore_ = converterPort_->text();

    uploadModeBefore_ = AutoUpload::textOf(AutoUpload::modeOf(settings_->value(SettingKey::UploadMode)));
    uploadMode_->setCurrentIndex(qMax(0, uploadMode_->findData(uploadModeBefore_)));
    uploadModePicked();

    showNoReceiptBefore_ = settings_->boolValue(SettingKey::ShowSalesWithoutReceipt, true);
    ui_->showNoReceiptCheck->setChecked(showNoReceiptBefore_);

    manualSupplyDaysBefore_ = settings_->intValue(SettingKey::ManualSupplyDays, 3);
    ui_->manualSupplyDaysSpin->setValue(manualSupplyDaysBefore_);

    cardPort_->setText(settings_->value(SettingKey::CardReaderPort, CardReader::defaultPort()));
    cardBaud_->setCurrentIndex(qMax(0, cardBaud_->findData(settings_->intValue(SettingKey::CardReaderBaud, CardReader::DefaultBaud))));
    cardFormat_->setCurrentIndex(qMax(0, cardFormat_->findData(settings_->value(SettingKey::CardReaderFormat, "line"))));
    cardLength_->setText(settings_->value(SettingKey::CardReaderLength));
    cardPrefix_->setText(settings_->value(SettingKey::CardReaderPrefix, CardReader::defaultPrefix()));
    cardEncoding_->setCurrentIndex(qMax(0, cardEncoding_->findData(settings_->value(SettingKey::CardReaderEncoding, "ascii"))));
    formatPicked();
    cardBefore_ = (cardPort_->text() + " " + cardBaud_->currentText() + " " + cardFormat_->itemData(cardFormat_->currentIndex()).toString() + " "
        + cardLength_->text() + " " + cardEncoding_->itemData(cardEncoding_->currentIndex()).toString() + " " + cardPrefix_->text().trimmed()).trimmed();

    fuel_->clear();
    const QList<MachineConfig::Fuel> fuels = config_->fuels();
    for (int i = 0; i < fuels.size(); ++i) {
        fuel_->addItem(fuels.at(i).code + "  " + fuels.at(i).name, fuels.at(i).code);
    }

    Theme::setState(status_, "");
    status_->setText(QString());
    selectedNumber_ = 0;
    table_->clearSelection();
    fillForm(0);
    fillTable();
    fillLog();

    // a nozzle must be connected to a tank (a nozzle added before that rule may have none): the technician is told when the page opens
    QStringList withoutTank;
    foreach (const MachineConfig::Nozzle &nozzle, config_->nozzles()) {
        if (nozzle.tankId <= 0) {
            withoutTank << QString::number(nozzle.number);
        }
    }
    if (!withoutTank.isEmpty()) {
        showStatus(tr("Nozzle(s) %1 have no tank: a nozzle must be connected to a tank. Remove it and add it again with its tank.").arg(withoutTank.join(", ")), false);
    }
}

// The nozzle table: one line per board, with its status and what it reported.
void MachineConfigPage::fillTable()
{
    const QList<MachineConfig::Nozzle> nozzles = config_->nozzles();
    table_->setRowCount(nozzles.size());

    for (int i = 0; i < nozzles.size(); ++i) {
        const MachineConfig::Nozzle &nozzle = nozzles.at(i);

        QString status;
        if (!nozzles_->isLinked()) {
            status = tr("No converter");
        } else if (nozzles_->differs(nozzle.number)) {
            status = tr("Board differs"); // the saved config was not confirmed by the board: press Update (or Read)
        } else if (!nozzles_->isPolled(nozzle.number)) {
            status = tr("Not polled"); // a nozzle in manual mode
        } else if (!nozzles_->isOnline(nozzle.number)) {
            status = tr("No answer");
        } else if (!nozzle.seen()) {
            status = tr("Not set"); // its config was not sent to the board or read from it yet
        } else {
            status = tr("Online");
        }
        if (!nozzles_->lastError(nozzle.number).isEmpty() && nozzles_->canTalk(nozzle.number)) {
            status = nozzles_->lastError(nozzle.number);
        }

        const QString refusal = Uploader::nozzleRefusal(settings_->database(), nozzle.number);
        if (!refusal.isEmpty() && nozzles_->isLinked()) {
            status = tr("Web refused"); // the web app did not take the nozzle: the reason is its tooltip
        }

        QStringList cells;
        cells << QString::number(nozzle.number) << nozzle.name << status;
        // what the terminal holds for the nozzle (its own database), whether the board has it or not
        cells << (nozzle.mode == BoardProtocol::ModeManual ? tr("Manual") : tr("Auto")) << nozzle.fuelType
              << (nozzle.density < 0 ? QString() : Decimal::format(nozzle.density, 4))
              << (nozzle.pulsesPerLiter < 0 ? QString() : Decimal::format(nozzle.pulsesPerLiter, 2))
              << (nozzle.tank.isEmpty() ? tr("(no tank)") : nozzle.tank);
        for (int column = 0; column < ColRemove; ++column) {
            table_->setItem(i, column, new QTableWidgetItem(cells.at(column)));
        }
        table_->item(i, 0)->setData(Qt::UserRole, nozzle.number);
        if (!refusal.isEmpty()) {
            table_->item(i, ColStatus)->setToolTip(tr("The web app refused this nozzle: %1").arg(refusal));
        }

        // the Remove button of the row: it knows the nozzle by its number, and a click asks before anything is removed
        QPushButton *remove = new QPushButton(tr("Remove"));
        remove->setObjectName("removeNozzle" + QString::number(nozzle.number));
        remove->setProperty("nozzle", nozzle.number);
        remove->setStyleSheet("font-size: 18px; min-height: 0px; padding: 2px 6px; margin: 3px 4px;");
        connect(remove, SIGNAL(clicked()), this, SLOT(removeNozzlePressed()));
        table_->setCellWidget(i, ColRemove, remove);
    }

    if (selectedNumber_ > 0) {
        table_->blockSignals(true);
        for (int row = 0; row < table_->rowCount(); ++row) {
            if (table_->item(row, 0)->data(Qt::UserRole).toInt() == selectedNumber_) {
                table_->selectRow(row);
            }
        }
        table_->blockSignals(false);
    }
}

// The form shows the nozzle's config as this terminal holds it (the density of the fuel type and no pulses yet for a nozzle just added).
void MachineConfigPage::fillForm(int number)
{
    selectedNumber_ = number;
    if (number < 1) {
        selected_->setText(tr("Pick a nozzle in the table to change its config."));
        density_->clear();
        pulses_->clear();
        nozzleName_->clear();

        return;
    }

    const MachineConfig::Nozzle nozzle = config_->nozzle(number);
    selected_->setText(nozzle.name.isEmpty() ? tr("Nozzle %1").arg(number) : tr("Nozzle %1: %2").arg(number).arg(nozzle.name));
    nozzleName_->setText(nozzle.name);
    mode_->setCurrentIndex(mode_->findData(nozzle.mode));
    int at = fuel_->findData(nozzle.fuelType);
    if (at < 0 && !nozzle.fuelType.isEmpty()) {
        fuel_->addItem(nozzle.fuelType, nozzle.fuelType); // a fuel type the web app does not have: kept as it is
        at = fuel_->count() - 1;
    }
    fuel_->setCurrentIndex(at);
    density_->setText(nozzle.density < 0 ? QString() : Decimal::format(nozzle.density, 4));
    pulses_->setText(nozzle.pulsesPerLiter < 0 ? QString() : Decimal::format(nozzle.pulsesPerLiter, 2));
    if (density_->text().isEmpty()) {
        fuelPicked();
    }
}

void MachineConfigPage::rowSelected()
{
    const int row = table_->currentRow();
    if (row < 0 || !table_->item(row, 0)) {
        return;
    }
    disarmReset(); // another nozzle: a Remove that was pressed once is not for this one
    fillForm(table_->item(row, 0)->data(Qt::UserRole).toInt());
}

// Another fuel type: its density from the web app when the field is empty.
void MachineConfigPage::fuelPicked()
{
    const QString code = fuel_->itemData(fuel_->currentIndex()).toString();

    if (density_->text().trimmed().isEmpty()) {
        foreach (const MachineConfig::Fuel &fuel, config_->fuels()) {
            if (fuel.code == code && fuel.density > 0) {
                density_->setText(Decimal::format(fuel.density, 4));
            }
        }
    }
}

void MachineConfigPage::nozzlesChanged()
{
    fillTable();
}

// Read: the config of the picked nozzle is asked of its board, and what the board holds becomes the nozzle's config (nothing is read by itself).
void MachineConfigPage::readNozzles()
{
    if (selectedNumber_ < 1 || !config_->isConfigured(selectedNumber_)) {
        showStatus(tr("Pick a nozzle in the table first."), false);

        return;
    }
    if (!nozzles_->isLinked()) {
        showStatus(tr("There is no connection to the boards."), false);

        return;
    }
    if (!nozzles_->canTalk(selectedNumber_)) {
        showStatus(tr("The board of nozzle %1 does not answer.").arg(selectedNumber_), false);

        return;
    }
    if (!nozzles_->read(selectedNumber_, session_->userId())) {
        showStatus(tr("The board of nozzle %1 is busy. Try again.").arg(selectedNumber_), false);

        return;
    }
    showStatus(tr("Reading the board of nozzle %1...").arg(selectedNumber_), true);
}

void MachineConfigPage::readDone(int number)
{
    fillTable();
    if (number == selectedNumber_) {
        fillForm(number);
    }
    fillLog();
    showStatus(tr("The config of nozzle %1 was read from its board and saved.").arg(number), true);
    emit nozzlesEdited();
}

void MachineConfigPage::readFailed(int number, const QString &message)
{
    fillTable();
    showStatus(tr("Nozzle %1: %2").arg(number).arg(message), false);
}

// What is typed is checked, then sent to the board; the board's answer decides what is kept.
void MachineConfigPage::updateNozzle()
{
    if (selectedNumber_ < 1) {
        showStatus(tr("Pick a nozzle in the table first."), false);

        return;
    }
    if (fuel_->currentIndex() < 0) {
        showStatus(tr("Pick a fuel type. The fuel types come from the web app with the config download."), false);

        return;
    }

    qlonglong density = 0;
    if (!Decimal::parse(density_->text().trimmed(), 4, &density) || density < BoardProtocol::MinDensity || density > BoardProtocol::MaxDensity) {
        showStatus(tr("Enter the density in kg per litre, from 0.1000 to 2.0000."), false);

        return;
    }
    qlonglong pulses = 0;
    if (!Decimal::parse(pulses_->text().trimmed(), 2, &pulses) || pulses <= 0 || pulses > BoardProtocol::MaxPulsesX100) {
        showStatus(tr("Enter the pulses per litre as a number above 0, for example 1200 or 1234.5."), false);

        return;
    }

    BoardProtocol::BoardConfig wanted;
    wanted.mode = mode_->itemData(mode_->currentIndex()).toInt();
    wanted.fuelType = fuel_->itemData(fuel_->currentIndex()).toString();
    wanted.density = quint16(density);
    wanted.pulsesX100 = quint32(pulses);

    const QString blocker = config_->configBlocker(selectedNumber_);
    if (!blocker.isEmpty()) {
        alert(tr("Update the nozzle"), blocker);

        return;
    }
    if (config_->tankFor(wanted.fuelType, config_->nozzle(selectedNumber_).tankId) == 0) {
        alert(tr("Update the nozzle"), tr("Nozzle %1 must be connected to a tank that holds fuel type %2, and none can be taken for it. Remove the nozzle and add it again with its tank.")
                                           .arg(selectedNumber_).arg(wanted.fuelType));

        return;
    }

    // Update saves the config in this terminal's database first: it is the nozzle's config whether the board takes it or not (the tank belongs to the
    // web app, not to the board: it stays, and follows the fuel type when that changes). Then it tries to send it to the board and to verify it.
    if (!config_->saveConfig(selectedNumber_, wanted, session_->userId())) {
        showStatus(tr("The config could not be saved."), false);

        return;
    }
    nozzles_->setDiffers(selectedNumber_, true); // until the board has confirmed it
    fillTable();
    fillForm(selectedNumber_);
    fillLog();
    emit nozzlesEdited();

    if (!nozzles_->isLinked()) {
        nozzles_->syncBoards();
        showStatus(tr("Saved. There is no connection to the boards, so nozzle %1 was not updated: press Update again later.").arg(selectedNumber_), false);

        return;
    }
    if (!nozzles_->canTalk(selectedNumber_)) {
        nozzles_->syncBoards(); // (the polling follows the mode that was saved)
        showStatus(tr("Saved. The board of nozzle %1 does not answer, so it was not updated: press Update again when it answers.").arg(selectedNumber_), false);

        return;
    }
    if (!nozzles_->send(selectedNumber_, wanted)) {
        nozzles_->syncBoards();
        showStatus(tr("Saved. The board of nozzle %1 is busy: press Update again to send it.").arg(selectedNumber_), false);

        return;
    }

    showStatus(tr("Saved. Sent to the board of nozzle %1. Waiting for it to confirm...").arg(selectedNumber_), true);
}

// Save name: only the name of the selected nozzle (it is not a part of the board's config, so the board is not asked); it goes up with the nozzle.
void MachineConfigPage::saveNozzleName()
{
    if (selectedNumber_ < 1) {
        showStatus(tr("Pick a nozzle in the table first."), false);

        return;
    }

    QString why;
    if (!config_->setNozzleName(selectedNumber_, nozzleName_->text(), session_->userId(), &why)) {
        showStatus(why, false);

        return;
    }
    fillTable();
    fillForm(selectedNumber_);
    fillLog();
    showStatus(tr("Nozzle %1: the name was saved.").arg(selectedNumber_), true);
    emit nozzlesEdited();
}

void MachineConfigPage::updateDone(int number)
{
    fillTable();
    showStatus(tr("Nozzle %1: the board holds the config as saved. Verified.").arg(number), true);
}

void MachineConfigPage::updateFailed(int number, const QString &message)
{
    fillTable();
    showStatus(tr("Nozzle %1: saved here, but the board was not updated: %2").arg(number).arg(message), false);
}

// The length only matters for a fixed frame.
void MachineConfigPage::formatPicked()
{
    const bool fixed = cardFormat_->itemData(cardFormat_->currentIndex()).toString() == "fixed";
    cardLength_->setEnabled(fixed);
    cardPrefix_->setEnabled(!fixed); // the text before the id belongs to lines
}

void MachineConfigPage::saveCardReader()
{
    const QString port = cardPort_->text().trimmed().isEmpty() ? CardReader::defaultPort() : cardPort_->text().trimmed();
    const QString format = cardFormat_->itemData(cardFormat_->currentIndex()).toString();
    const int length = cardLength_->text().toInt();
    if (format == "fixed" && (length < 1 || length > 64)) {
        showStatus(tr("Enter how many characters the id has (1 to 64)."), false);

        return;
    }

    const int baud = cardBaud_->itemData(cardBaud_->currentIndex()).toInt();
    const QString encoding = cardEncoding_->itemData(cardEncoding_->currentIndex()).toString();
    if (!settings_->setValue(SettingKey::CardReaderPort, port) || !settings_->setInt(SettingKey::CardReaderBaud, baud)
        || !settings_->setValue(SettingKey::CardReaderFormat, format) || !settings_->setInt(SettingKey::CardReaderLength, format == "fixed" ? length : 0)
        || !settings_->setValue(SettingKey::CardReaderEncoding, encoding)
        || !settings_->setValue(SettingKey::CardReaderPrefix, format == "fixed" ? QString() : cardPrefix_->text().trimmed())) {
        showStatus(tr("The values could not be saved."), false);

        return;
    }

    const QString now = (port + " " + QString::number(baud) + " " + format + " " + (format == "fixed" ? QString::number(length) : QString()) + " " + encoding
        + " " + (format == "fixed" ? QString() : cardPrefix_->text().trimmed())).trimmed();
    if (now != cardBefore_) {
        config_->logChange("card reader", cardBefore_, now, session_->userId());
        cardBefore_ = now;
        fillLog();
    }
    emit cardReaderChanged();
    showStatus(tr("Saved."), true);
}

// What the picked upload mode does, in words.
void MachineConfigPage::uploadModePicked()
{
    const AutoUpload::Mode mode = AutoUpload::modeOf(uploadMode_->itemData(uploadMode_->currentIndex()).toString());
    QString hint;

    if (mode == AutoUpload::CloseOfDay) {
        hint = tr("The records of the business days that are over (9 AM to 9 AM) go up by themselves. Today's records stay here, so that they can still be "
                  "fixed or deleted, until Upload data is pressed.");
    } else if (mode == AutoUpload::Continuous) {
        hint = tr("Every record goes up by itself %1 minutes after it was made (time to delete a mistaken sale). A record that was uploaded cannot "
                  "be changed on the terminal any more.").arg(AutoUpload::GraceMinutes);
    } else {
        hint = tr("Records go up only when the cashier presses Upload data on the Review screen.");
    }
    ui_->uploadModeHint->setText(hint + " " + tr("The terminal downloads from the server every minute."));
}

void MachineConfigPage::saveUploadMode()
{
    const QString mode = uploadMode_->itemData(uploadMode_->currentIndex()).toString();
    if (!settings_->setValue(SettingKey::UploadMode, mode)) {
        showStatus(tr("The values could not be saved."), false);

        return;
    }
    if (mode != uploadModeBefore_) {
        config_->logChange("upload mode", uploadModeBefore_, mode, session_->userId());
        uploadModeBefore_ = mode;
        fillLog();
    }
    showStatus(tr("Saved."), true);
}

// Whether the Review screen lists the sales that have no receipt (the box on the sale forms).
void MachineConfigPage::saveReviewOptions()
{
    const bool show = ui_->showNoReceiptCheck->isChecked();
    if (!settings_->setBool(SettingKey::ShowSalesWithoutReceipt, show)) {
        showStatus(tr("The values could not be saved."), false);

        return;
    }
    if (show != showNoReceiptBefore_) {
        config_->logChange("show sales without receipt", showNoReceiptBefore_ ? "yes" : "no", show ? "yes" : "no", session_->userId());
        showNoReceiptBefore_ = show;
        fillLog();
    }
    showStatus(tr("Saved."), true);
}

// How far back the cashier may date a supply typed in by hand (the Manual input tab).
void MachineConfigPage::saveManualSupplyOptions()
{
    const int days = ui_->manualSupplyDaysSpin->value();
    if (!settings_->setInt(SettingKey::ManualSupplyDays, days)) {
        showStatus(tr("The values could not be saved."), false);

        return;
    }
    if (days != manualSupplyDaysBefore_) {
        config_->logChange("manual supply days", QString::number(manualSupplyDaysBefore_), QString::number(days), session_->userId());
        manualSupplyDaysBefore_ = days;
        fillLog();
    }
    showStatus(tr("Saved."), true);
}

void MachineConfigPage::saveConverterPort()
{
    const int port = converterPort_->text().toInt();
    if (port < 1 || port > 65535) {
        showStatus(tr("Enter a port from 1 to 65535."), false);

        return;
    }
    if (!settings_->setInt(SettingKey::ConverterPort, port)) {
        showStatus(tr("The values could not be saved."), false);

        return;
    }

    const QString now = QString::number(port);
    if (now != converterBefore_) {
        config_->logChange("converter", converterBefore_, now, session_->userId());
        converterBefore_ = now;
        fillLog();
        if (!nozzles_->restart(port)) {
            showStatus(tr("Saved, but this terminal cannot listen on that port: %1").arg(nozzles_->listenError()), false);

            return;
        }
    }
    showStatus(tr("Saved."), true);
}

// The printer's address is saved (and the change logged). An empty address means no printer.
bool MachineConfigPage::storePrinter()
{
    const QString host = printerHost_->text().trimmed();
    const int port = printerPort_->text().toInt();
    if (port < 1 || port > 65535) {
        Theme::setState(printerStatus_, "error");
        printerStatus_->setText(tr("Enter a port from 1 to 65535."));

        return false;
    }

    const QString now = host + ":" + QString::number(port);
    if (!settings_->setValue(SettingKey::PrinterHost, host) || !settings_->setInt(SettingKey::PrinterPort, port)) {
        Theme::setState(printerStatus_, "error");
        printerStatus_->setText(tr("The values could not be saved."));

        return false;
    }
    if (now != printerBefore_) {
        config_->logChange("printer", printerBefore_, now, session_->userId());
        printerBefore_ = now;
        fillLog();
    }

    return true;
}

void MachineConfigPage::savePrinter()
{
    if (storePrinter()) {
        Theme::setState(printerStatus_, "ok");
        printerStatus_->setText(tr("Saved."));
    }
}

void MachineConfigPage::checkPrinter()
{
    if (!storePrinter()) {
        return;
    }

    checking_ = true;
    Theme::setState(printerStatus_, "");
    printerStatus_->setText(tr("Checking the printer..."));
    printer_->checkPrinter();
}

void MachineConfigPage::printerChecked(bool ok, const QString &message)
{
    if (!checking_) {
        return; // a ticket being printed, not this check
    }

    checking_ = false;
    Theme::setState(printerStatus_, ok ? "ok" : "error");
    printerStatus_->setText(message);
}

void MachineConfigPage::fillLog()
{
    log_->clear();
    foreach (const MachineConfig::Change &change, config_->recentChanges(LogLines)) {
        const QString who = change.userName.isEmpty() ? QString() : "  " + change.userName;
        const QString from = change.oldValue.isEmpty() ? QString("-") : change.oldValue;
        log_->addItem(change.changedAt + who + "  " + subject(change.what) + ": " + from + " -> " + change.newValue);
    }
}

void MachineConfigPage::showStatus(const QString &message, bool ok)
{
    Theme::setState(status_, ok ? "ok" : "error");
    status_->setText(message);
}

// Something that cannot be done now: the reason is shown in a box (and in the status line).
void MachineConfigPage::alert(const QString &title, const QString &reason)
{
    showStatus(reason, false);
    QMessageBox::warning(this, title, reason);
}

// The form of Add nozzle: the addresses not added yet, the fuel types of the last download and the tanks that hold the fuel type.
void MachineConfigPage::fillAddForm()
{
    const int keepNumber = ui_->addNozzleNumber->itemData(ui_->addNozzleNumber->currentIndex()).toInt();
    const QString keepFuel = ui_->addNozzleFuel->itemData(ui_->addNozzleFuel->currentIndex()).toString();

    ui_->addNozzleNumber->clear();
    for (int number = 1; number <= MachineConfig::NozzleCount; ++number) {
        if (!config_->isConfigured(number)) {
            ui_->addNozzleNumber->addItem(QString::number(number), number);
        }
    }
    ui_->addNozzleNumber->setCurrentIndex(qMax(0, ui_->addNozzleNumber->findData(keepNumber)));

    ui_->addNozzleFuel->clear();
    foreach (const MachineConfig::Fuel &fuel, config_->fuels()) {
        ui_->addNozzleFuel->addItem(fuel.code + "  " + fuel.name, fuel.code);
    }
    ui_->addNozzleFuel->setCurrentIndex(qMax(0, ui_->addNozzleFuel->findData(keepFuel)));
    addFuelPicked();

    ui_->addNozzleButton->setEnabled(ui_->addNozzleNumber->count() > 0 && ui_->addNozzleFuel->count() > 0);
}

void MachineConfigPage::addFuelPicked()
{
    ui_->addNozzleTank->clear();
    foreach (const MachineConfig::Tank &tank, config_->tanks(ui_->addNozzleFuel->itemData(ui_->addNozzleFuel->currentIndex()).toString())) {
        ui_->addNozzleTank->addItem(tank.name, tank.id);
    }
    // (the first tank of the fuel type is picked; none: Add nozzle says that a nozzle must be connected to a tank)
}

void MachineConfigPage::addNozzlePressed()
{
    const int number = ui_->addNozzleNumber->itemData(ui_->addNozzleNumber->currentIndex()).toInt();
    const QString fuel = ui_->addNozzleFuel->itemData(ui_->addNozzleFuel->currentIndex()).toString();
    const int tank = ui_->addNozzleTank->itemData(ui_->addNozzleTank->currentIndex()).toInt();

    QString why;
    if (!config_->addNozzle(number, fuel, tank, session_->userId(), &why, ui_->addNozzleName->text())) {
        if (tank <= 0 && !fuel.isEmpty()) {
            alert(tr("Add a nozzle"), why); // no tank holds this fuel type
        } else {
            showStatus(why, false);
        }

        return;
    }
    nozzles_->syncBoards(); // its board is polled from now on (its config is not read: type it and press Update, or press Read)
    ui_->addNozzleName->clear();
    selectedNumber_ = number;
    fillTable();
    fillForm(number);
    fillAddForm();
    fillLog();
    showStatus(tr("Nozzle %1 was added. Enter its config and press Update, or press Read to take it from its board.").arg(number), true);
    emit nozzlesEdited();
}

// The Remove button of a row: the technician is asked first, and the nozzle is removed only when it is confirmed.
void MachineConfigPage::removeNozzlePressed()
{
    QPushButton *button = qobject_cast<QPushButton *>(sender());
    const int number = button ? button->property("nozzle").toInt() : 0;
    if (number < 1 || !config_->isConfigured(number)) {
        return;
    }
    disarmReset();
    const QString blocker = config_->removeBlocker(number);
    if (!blocker.isEmpty()) {
        alert(tr("Remove the nozzle"), blocker);

        return;
    }
    if (QMessageBox::question(this, tr("Remove the nozzle"),
                              tr("Remove nozzle %1? It is not asked, shown or used any more.").arg(number),
                              QMessageBox::Yes | QMessageBox::No, QMessageBox::No) != QMessageBox::Yes) {
        return;
    }

    QString why;
    if (!config_->removeNozzle(number, session_->userId(), &why)) {
        alert(tr("Remove the nozzle"), why);

        return;
    }
    nozzles_->syncBoards();
    if (selectedNumber_ == number) {
        selectedNumber_ = 0;
    }
    fillTable();
    fillForm(selectedNumber_);
    fillAddForm();
    fillLog();
    showStatus(tr("Nozzle %1 was removed.").arg(number), true);
    emit nozzlesEdited();
}

// The records that wait for the upload decide whether Clean Data may be used at all: they exist nowhere else.
void MachineConfigPage::showResetBox()
{
    const int waits = AppReset::waiting(settings_->database());
    ui_->cleanDataButton->setEnabled(waits == 0);
    Theme::setState(ui_->resetInfo, waits > 0 ? "warning" : "ok");
    ui_->resetInfo->setText(waits > 0 ? tr("%1 records wait for the upload: Clean Data is possible after they are uploaded (Review screen).").arg(waits)
                                      : tr("Nothing waits for the upload."));
}

// The first press of a reset button arms it (it asks for the second press, and gives up after a few seconds); the second press does it.
void MachineConfigPage::armReset(int which)
{
    disarmReset();
    armedReset_ = which;
    QPushButton *button = which == 1 ? ui_->cleanDataButton : ui_->clearConfigButton;
    button->setText(tr("Press again to confirm"));
    Theme::setState(button, "error");
    showStatus(which == 1 ? tr("Press Clean Data again to erase all the data of this terminal.") : tr("Press Clear Config again to erase the machine settings."), false);
    resetTimer_->start();
}

void MachineConfigPage::disarmReset()
{
    armedReset_ = 0;
    resetTimer_->stop();
    ui_->cleanDataButton->setText(tr("Clean Data"));
    ui_->clearConfigButton->setText(tr("Clear Config"));
    Theme::setState(ui_->cleanDataButton, "");
    Theme::setState(ui_->clearConfigButton, "");
}

void MachineConfigPage::cleanDataPressed()
{
    if (armedReset_ != 1) {
        armReset(1);

        return;
    }
    disarmReset();

    QString why;
    if (!AppReset::cleanData(settings_->database(), &why)) {
        showStatus(why, false);
        showResetBox();

        return;
    }
    config_->logChange("reset", QString(), "clean data", session_->userId());
    fillLog();
    showStatus(tr("The data was erased. The program starts again."), true);
    finishReset();
}

void MachineConfigPage::clearConfigPressed()
{
    if (armedReset_ != 2) {
        armReset(2);

        return;
    }
    disarmReset();

    QString why;
    if (!AppReset::clearConfig(settings_->database(), &why)) {
        showStatus(why, false);

        return;
    }
    config_->logChange("reset", QString(), "clear config", session_->userId());
    fillLog();
    showStatus(tr("The machine settings were erased. The program starts again."), true);
    finishReset();
}

// Nothing in memory is right after a reset: the buttons are shut, and after a moment (to read the message) the program is asked to start again.
void MachineConfigPage::finishReset()
{
    ui_->cleanDataButton->setEnabled(false);
    ui_->clearConfigButton->setEnabled(false);
    QTimer::singleShot(1500, this, SIGNAL(restartRequested()));
}
// The address of the server was changed and tested: it is saved, and the change is logged.
void MachineConfigPage::serverConfigured()
{
    const QString now = settings_->value(SettingKey::ServerHost) + ":" + QString::number(settings_->intValue(SettingKey::ServerPort, 80));
    if (now != serverBefore_) {
        config_->logChange("server", serverBefore_, now, session_->userId());
        serverBefore_ = now;
    }
    fillLog();
}
