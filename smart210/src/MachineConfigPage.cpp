#include "MachineConfigPage.h"

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

#include <QComboBox>
#include <QHeaderView>
#include <QIntValidator>
#include <QLineEdit>
#include <QTabWidget>
#include <QVariant>
#include <QLabel>
#include <QListWidget>
#include <QPushButton>
#include <QTableWidget>

namespace {

const int LogLines = 12;

// the columns of the nozzle table
enum { ColNozzle, ColStatus, ColMode, ColFuel, ColDensity, ColPulses, ColTank, Columns };

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

    if (what == "upload mode") {
        return QObject::tr("Upload");
    }

    return what == "server" ? QObject::tr("Server address") : what;
}

}

MachineConfigPage::MachineConfigPage(SetupFlow *flow, MachineConfig *config, Session *session, Settings *settings, PrintService *printer,
                                     NozzleConfigSync *nozzles, QWidget *parent)
    : QWidget(parent), ui_(new Ui::MachineConfigPage), config_(config), session_(session), settings_(settings), printer_(printer), nozzles_(nozzles),
      checking_(false), selectedNumber_(0)
{
    ui_->setupUi(this);

    // the server form needs the flow, so it is made here and put where the Server tab has room for it
    server_ = new ServerForm(flow);
    ui_->serverFormLayout->addWidget(server_);

    // the nozzles: what each board reported, and a form to send a change to the selected board
    table_ = ui_->nozzleTable;
    table_->setColumnCount(Columns);
    table_->setHorizontalHeaderLabels(QStringList() << tr("Nozzle") << tr("Status") << tr("Mode") << tr("Fuel type") << tr("Density")
                                                    << tr("Pulses per litre") << tr("Tank"));
    table_->horizontalHeader()->setStretchLastSection(true);
    table_->setColumnWidth(ColNozzle, 84);
    table_->setColumnWidth(ColStatus, 140);
    table_->setColumnWidth(ColMode, 90);
    table_->setColumnWidth(ColFuel, 90);
    table_->setColumnWidth(ColDensity, 100);
    table_->setColumnWidth(ColPulses, 140);
    table_->setHorizontalScrollBarPolicy(Qt::ScrollBarAlwaysOff);
    table_->setFixedWidth(510);
    table_->verticalHeader()->setVisible(false);
    table_->setEditTriggers(QAbstractItemView::NoEditTriggers);
    table_->setSelectionBehavior(QAbstractItemView::SelectRows);
    table_->setSelectionMode(QAbstractItemView::SingleSelection);
    table_->setColumnHidden(ColDensity, true); // the form shows them; the Dispensers screen lists them all
    table_->setColumnHidden(ColPulses, true);
    table_->setFocusPolicy(Qt::NoFocus);
    table_->setVerticalScrollBarPolicy(Qt::ScrollBarAlwaysOff);
    table_->setMinimumHeight(6 * 36 + 46);

    selected_ = ui_->selectedNozzle;
    mode_ = ui_->nozzleMode;
    mode_->addItem(tr("Auto"), BoardProtocol::ModeAuto);
    mode_->addItem(tr("Manual"), BoardProtocol::ModeManual);
    fuel_ = ui_->nozzleFuel;
    density_ = ui_->nozzleDensity;
    pulses_ = ui_->nozzlePulses;
    tank_ = ui_->nozzleTank;

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
    connect(ui_->sendNozzleButton, SIGNAL(clicked()), this, SLOT(sendNozzle()));
    connect(ui_->readNozzlesButton, SIGNAL(clicked()), this, SLOT(readNozzles()));
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
    connect(nozzles_, SIGNAL(updateDone(int)), this, SLOT(updateDone(int)));
    connect(nozzles_, SIGNAL(updateFailed(int,QString)), this, SLOT(updateFailed(int,QString)));
}

MachineConfigPage::~MachineConfigPage()
{
    delete ui_;
}

void MachineConfigPage::reload()
{
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

    nozzles_->refresh(); // the boards are asked again for their config
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
        } else if (!nozzles_->isOnline(nozzle.number)) {
            status = tr("No answer");
        } else if (!nozzle.seen()) {
            status = tr("Reading...");
        } else {
            status = tr("Online");
        }
        if (!nozzles_->lastError(nozzle.number).isEmpty() && nozzles_->isOnline(nozzle.number)) {
            status = nozzles_->lastError(nozzle.number);
        }

        QStringList cells;
        cells << QString::number(nozzle.number) << status;
        if (nozzle.seen()) {
            cells << (nozzle.mode == BoardProtocol::ModeManual ? tr("Manual") : tr("Auto")) << nozzle.fuelType
                  << (nozzle.density < 0 ? QString() : Decimal::format(nozzle.density, 4))
                  << (nozzle.pulsesPerLiter < 0 ? QString() : Decimal::format(nozzle.pulsesPerLiter, 2))
                  << (nozzle.tank.isEmpty() ? tr("(pick a tank)") : nozzle.tank);
        } else {
            cells << QString() << QString() << QString() << QString() << QString();
        }
        for (int column = 0; column < Columns; ++column) {
            table_->setItem(i, column, new QTableWidgetItem(cells.at(column)));
        }
    }

    if (selectedNumber_ > 0) {
        table_->blockSignals(true);
        table_->selectRow(selectedNumber_ - 1);
        table_->blockSignals(false);
    }
}

// The tanks that hold this fuel type, with the given one picked (or the first).
void MachineConfigPage::fillTanks(const QString &fuelType, int selectTankId)
{
    tank_->clear();
    tank_->addItem(tr("(none)"), 0);
    const QList<MachineConfig::Tank> tanks = config_->tanks(fuelType);
    for (int i = 0; i < tanks.size(); ++i) {
        tank_->addItem(tanks.at(i).name, tanks.at(i).id);
    }
    const int at = tank_->findData(selectTankId);
    tank_->setCurrentIndex(at >= 0 ? at : (tanks.size() == 1 ? 1 : 0));
}

// The form shows the nozzle's config as the board reported it (or a start for a board that has none yet).
void MachineConfigPage::fillForm(int number)
{
    selectedNumber_ = number;
    if (number < 1) {
        selected_->setText(tr("Pick a nozzle in the table to change its config."));
        density_->clear();
        pulses_->clear();
        fillTanks(QString(), 0);

        return;
    }

    const MachineConfig::Nozzle nozzle = config_->nozzle(number);
    selected_->setText(tr("Nozzle %1").arg(number));
    mode_->setCurrentIndex(mode_->findData(nozzle.mode));
    int at = nozzle.seen() ? fuel_->findData(nozzle.fuelType) : 0;
    if (nozzle.seen() && at < 0 && !nozzle.fuelType.isEmpty()) {
        fuel_->addItem(nozzle.fuelType, nozzle.fuelType); // a fuel type the web app does not have: kept as the board has it
        at = fuel_->count() - 1;
    }
    fuel_->setCurrentIndex(at);
    density_->setText(nozzle.density < 0 ? QString() : Decimal::format(nozzle.density, 4));
    pulses_->setText(nozzle.pulsesPerLiter < 0 ? QString() : Decimal::format(nozzle.pulsesPerLiter, 2));
    fillTanks(fuel_->itemData(fuel_->currentIndex()).toString(), nozzle.tankId);
    if (density_->text().isEmpty()) {
        fuelPicked();
    }
}

void MachineConfigPage::rowSelected()
{
    const int row = table_->currentRow();
    if (row < 0) {
        return;
    }
    fillForm(row + 1);
}

// Another fuel type: its tanks, and its density from the web app when the field is empty.
void MachineConfigPage::fuelPicked()
{
    const QString code = fuel_->itemData(fuel_->currentIndex()).toString();
    fillTanks(code, 0);

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

void MachineConfigPage::readNozzles()
{
    nozzles_->refresh();
    showStatus(nozzles_->isLinked() ? tr("Reading the boards...") : tr("There is no connection to the boards."), nozzles_->isLinked());
}

// What is typed is checked, then sent to the board; the board's answer decides what is kept.
void MachineConfigPage::sendNozzle()
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

    if (!nozzles_->isOnline(selectedNumber_)) {
        showStatus(tr("The board of nozzle %1 does not answer.").arg(selectedNumber_), false);

        return;
    }
    if (!nozzles_->update(selectedNumber_, wanted, tank_->itemData(tank_->currentIndex()).toInt(), session_->userId())) {
        showStatus(tr("The config could not be sent. Try again."), false);

        return;
    }

    showStatus(tr("Sent to the board of nozzle %1. Waiting for its answer...").arg(selectedNumber_), true);
}

void MachineConfigPage::updateDone(int number)
{
    fillTable();
    if (number == selectedNumber_) {
        fillForm(number);
    }
    fillLog();
    showStatus(tr("The board of nozzle %1 confirmed the config.").arg(number), true);
}

void MachineConfigPage::updateFailed(int number, const QString &message)
{
    fillTable();
    showStatus(tr("Nozzle %1: %2").arg(number).arg(message), false);
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
