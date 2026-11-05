#include "MainWindow.h"

#include "AppConfig.h"
#include "AppShell.h"
#include "CardPage.h"
#include "CardReader.h"
#include "DispensersPage.h"
#include "ManualSupplyPage.h"
#include "TabbedPage.h"
#include "Theme.h"
#include "AutoUpload.h"
#include "Checkout.h"
#include "Clock.h"
#include "FirstSyncPage.h"
#include "LoanSettlePage.h"
#include "LoginPage.h"
#include "KoreanInput.h"
#include "MachineConfig.h"
#include "BoardBus.h"
#include "BoardClock.h"
#include "MachineConfigPage.h"
#include "NozzleConfigSync.h"
#include "SupplierScanner.h"
#include "SupplyPage.h"
#include "SupplySelection.h"
#include "SupplySession.h"
#include "SupplyRecord.h"
#include "PinPage.h"
#include "PrintService.h"
#include "RemainderTicketsPage.h"
#include "Purger.h"
#include "Session.h"
#include "Settings.h"
#include "SettingsPage.h"
#include "SetupFlow.h"
#include "SetupPage.h"
#include "SpotSalePage.h"
#include "SyncClient.h"
#include "TicketSalePage.h"
#include "ReviewPage.h"
#include "Uploader.h"

#include <QDateTime>
#include <QFontDatabase>
#include <QGridLayout>
#include <QLabel>
#include <QPushButton>
#include <QStackedWidget>
#include <QStyle>
#include <QFileSystemWatcher>
#include <QTabWidget>
#include <QTimer>
#include <QVBoxLayout>

namespace {

const qint64 NothingWaitingEveryMs = 5 * 60 * 1000; // how often the web app is told that no record of a finished business day waits for an upload

}

MainWindow::MainWindow(const AppConfig &config, const QString &fontFamily, const QString &databaseInfo, QSqlDatabase db,
                       QWidget *parent)
    : QWidget(parent), db_(db), settings_(new Settings(db)), session_(new Session(db)), machineConfig_(new MachineConfig(db)), printingRemainder_(false), restartWanted_(false), lastReportMs_(0)
{
    setWindowTitle(tr("Gas Station Cashier"));
    themeFile_ = config.themeFile();
    themeWatcher_ = 0;
    setStyleSheet(Theme::styleSheet(themeFile_));
    if (!themeFile_.isEmpty()) {
        // a look being tried: the file is applied again whenever it is saved (an editor may replace the file, so it is watched again each time)
        themeWatcher_ = new QFileSystemWatcher(QStringList() << themeFile_, this);
        connect(themeWatcher_, SIGNAL(fileChanged(QString)), this, SLOT(themeFileChanged()));
    }
    boardLink_ = config.boardLink();
    Checkout::setStartHour(settings_->intValue(SettingKey::BusinessDayStartHour, Checkout::StartHour));   // the web app's hour for the start of the business day

    setup_ = new SetupFlow(settings_, this);
    sync_ = new SyncClient(db, this);
    uploader_ = new Uploader(db, this);

    // the ticket printing: its options come from config.ini; a font file for the ticket text is loaded first
    PrintOptions printOptions = config.printOptions();
    if (!config.ticketFont().isEmpty()) {
        const int id = QFontDatabase::addApplicationFont(config.ticketFont());
        if (id >= 0 && !QFontDatabase::applicationFontFamilies(id).isEmpty()) {
            printOptions.fontFamily = QFontDatabase::applicationFontFamilies(id).first();
        }
    }
    printer_ = new PrintService(db, printOptions, this);

    // the nozzle boards: the converter connects to this terminal, and the nozzles' config is read from the boards that answer
    boardBus_ = new BoardBus(this);
    nozzleSync_ = new NozzleConfigSync(db, boardBus_, this);
    boardClock_ = new BoardClock(boardBus_, this); // the boards' clocks are set from this terminal's
    machineConfig_->setLogFile(config.changeLogFile());
    nozzleSync_->setLogFile(config.changeLogFile());
    if (config.boardLink()) {
        nozzleSync_->start(settings_->intValue(SettingKey::ConverterPort, MachineConfig::DefaultConverterPort));
    }

    // the supplier's barcode scanner is read in the background (grabbed, so it never types into a screen); the supply session uses it
    supplierScanner_ = new SupplierScanner(this);
    if (config.boardLink()) {
        supplierScanner_->start(config.supplierScannerName(), config.supplierScannerPhys());
    }

    // the RFID card reader, on a serial port: its settings are on Machine Config; the screen of the cards listens to it
    cardReader_ = new CardReader(this);
    sync_->setAutoSetClock(config.setSystemClock());

    setupPage_ = new SetupPage(setup_);
    gatePage_ = new FirstSyncPage;
    loginPage_ = new LoginPage(session_);
    about_ = tr("Version %1").arg(APP_VERSION) + "\n" + tr("Language: %1").arg(AppConfig::languageName(config.language())) + "\n"
        + (fontFamily.isEmpty() ? tr("No font found in the fonts folder, using the system font.") : tr("Font: %1").arg(fontFamily)) + "\n" + databaseInfo;
    settingsPage_ = new SettingsPage(settings_);
    pinPage_ = new PinPage(settings_);
    machinePage_ = new MachineConfigPage(setup_, machineConfig_, session_, settings_, printer_, nozzleSync_);
    reviewPage_ = new ReviewPage(db, uploader_, session_, printer_);
    supplySelection_ = new SupplySelection(boardBus_, machineConfig_, this);
    supplySession_ = new SupplySession(db, boardBus_, machineConfig_, supplySelection_, session_, this);
    spotPage_ = new SpotSalePage(db, session_, printer_, boardBus_, machineConfig_, supplySelection_, supplySession_, settings_);
    supplyPage_ = new SupplyPage(db, boardBus_, machineConfig_, supplySelection_, supplySession_);
    cardPage_ = new CardPage(db, session_, printer_, cardReader_, boardBus_, machineConfig_, supplySelection_, supplySession_, settings_);
    manualPage_ = new ManualSupplyPage(db, machineConfig_, supplySession_);
    remainderPage_ = new RemainderTicketsPage(db);
    dispensersPage_ = new DispensersPage(db, boardBus_, machineConfig_);
    ticketPage_ = new TicketSalePage(db, session_);
    loanPage_ = new LoanSettlePage(db, session_);

    // the parts of the program that are made of several screens
    salesPage_ = new TabbedPage(tr("Sales"));
    salesPage_->addPage(spotPage_, tr("Spot sale"));
    salesPage_->addPage(ticketPage_, tr("Ticket sale"));

    supplyPart_ = new TabbedPage(tr("Supplies"));
    supplyPart_->addPage(supplyPage_, tr("Sessions"));
    supplyPart_->addPage(manualPage_, tr("Manual input"));

    shell_ = new AppShell;
    // the Korean input method: off until the Right Alt key or Ctrl+Space; the footer shows the mode
    koreanInput_ = config.koreanInput() ? new KoreanInput(this) : 0;
    shell_->footer()->setInputMode(koreanInput_ != 0, false);
    if (koreanInput_) {
        connect(koreanInput_, SIGNAL(koreanModeChanged(bool)), this, SLOT(koreanModeChanged(bool)));
    }
    QStackedWidget *content = shell_->content();
    content->addWidget(salesPage_);
    content->addWidget(supplyPart_);
    content->addWidget(remainderPage_);
    content->addWidget(cardPage_);
    content->addWidget(dispensersPage_);
    content->addWidget(loanPage_);
    content->addWidget(reviewPage_);
    content->addWidget(settingsPage_);

    // The PIN and Machine Config are not part of the cashier's program: they have the whole window, no sidebar, no header, no footer, and are reached from
    // the login screen only (nobody is logged in then).
    pages_ = new QStackedWidget(this);
    pages_->addWidget(setupPage_);
    pages_->addWidget(gatePage_);
    pages_->addWidget(loginPage_);
    pages_->addWidget(shell_);
    pages_->addWidget(pinPage_);
    pages_->addWidget(machinePage_);

    QVBoxLayout *layout = new QVBoxLayout(this);
    layout->setContentsMargins(0, 0, 0, 0);
    layout->addWidget(pages_);

    connect(setupPage_, SIGNAL(configured()), this, SLOT(startFirstDownload()));
    connect(gatePage_, SIGNAL(retryRequested()), this, SLOT(startFirstDownload()));
    connect(loginPage_, SIGNAL(loggedIn()), this, SLOT(showStart()));
    connect(settingsPage_, SIGNAL(logoutRequested()), this, SLOT(logout()));
    connect(loginPage_, SIGNAL(settingsRequested()), this, SLOT(askForPin()));
    connect(pinPage_, SIGNAL(accepted()), this, SLOT(showMachineConfig()));
    connect(pinPage_, SIGNAL(cancelled()), this, SLOT(showSettingsAgain()));
    connect(machinePage_, SIGNAL(backRequested()), this, SLOT(showSettingsAgain()));
    connect(machinePage_, SIGNAL(restartRequested()), this, SLOT(machineWasReset()));
    connect(machinePage_, SIGNAL(nozzlesEdited()), this, SLOT(sendNozzles()));
    connect(shell_->sidebar(), SIGNAL(pageRequested(QString)), this, SLOT(sidebarClicked(QString)));
    connect(salesPage_->tabs(), SIGNAL(currentChanged(int)), this, SLOT(tabChanged()));
    connect(supplyPart_->tabs(), SIGNAL(currentChanged(int)), this, SLOT(tabChanged()));
    connect(supplierScanner_, SIGNAL(barcodeScanned(QString)), supplySession_, SLOT(scanned(QString)));
    connect(cardPage_, SIGNAL(saved()), this, SLOT(refreshUploadButton()));
    connect(machinePage_, SIGNAL(cardReaderChanged()), this, SLOT(startCardReader()));
    connect(supplySession_, SIGNAL(recorded(int,QString)), this, SLOT(refreshUploadButton()));
    connect(supplySession_, SIGNAL(remainderMade(QString)), this, SLOT(printRemainder(QString)));
    connect(printer_, SIGNAL(finished(bool,QString)), this, SLOT(printerFinished(bool,QString)));
    connect(remainderPage_, SIGNAL(printRequested(QString)), this, SLOT(printRemainder(QString)));
    connect(remainderPage_, SIGNAL(saved()), this, SLOT(refreshUploadButton()));
    connect(manualPage_, SIGNAL(recorded()), this, SLOT(refreshUploadButton()));
    connect(reviewPage_, SIGNAL(uploaded()), this, SLOT(refreshUploadButton()));
    connect(reviewPage_, SIGNAL(edited()), this, SLOT(refreshUploadButton()));
    connect(reviewPage_, SIGNAL(showNoReceiptChanged(bool)), this, SLOT(noReceiptListingChanged(bool)));
    connect(boardBus_, SIGNAL(connectionChanged(bool)), this, SLOT(refreshStatus()));
    connect(boardBus_, SIGNAL(boardOnlineChanged(int,bool)), this, SLOT(refreshStatus()));
    connect(supplierScanner_, SIGNAL(connectedChanged(bool)), this, SLOT(refreshStatus()));
    connect(spotPage_, SIGNAL(saved()), this, SLOT(refreshUploadButton()));
    connect(ticketPage_, SIGNAL(saved()), this, SLOT(refreshUploadButton()));
    connect(cardReader_, SIGNAL(cardRead(QString)), this, SLOT(cardRead(QString)));
    connect(loanPage_, SIGNAL(settled()), this, SLOT(refreshUploadButton()));
    connect(sync_, SIGNAL(downloadFinished(bool,QString)), this, SLOT(downloadFinished(bool,QString)));

    timer_ = new QTimer(this);
    connect(timer_, SIGNAL(timeout()), this, SLOT(updateClock()));
    timer_->start(1000);
    updateClock();

    // the app downloads by itself every minute; whether it also uploads by itself is the upload mode of Machine Config (AutoUpload)
    downloadTimer_ = new QTimer(this);
    downloadTimer_->setInterval(config.downloadMinutes() * 60 * 1000);
    connect(downloadTimer_, SIGNAL(timeout()), this, SLOT(autoDownload()));
    downloadTimer_->start();

    uploadTimer_ = new QTimer(this);
    uploadTimer_->setInterval(60 * 1000);
    connect(uploadTimer_, SIGNAL(timeout()), this, SLOT(autoUpload()));
    uploadTimer_->start();

    startCardReader();

    if (!setup_->isConfigured()) {
        pages_->setCurrentWidget(setupPage_);
    } else if (!settings_->boolValue(SettingKey::InitialDone)) {
        startFirstDownload();
    } else {
        Purger::purge(db_, settings_->intValue(SettingKey::PurgeDays, 3), QDateTime::currentDateTime());
        autoCheckout(); // the catch-up: records made before the date was filled in at once get their business day
        showStartOrLogin(); // a cashier who logged in earlier is still logged in, unless the web app disabled them
        sync_->download();
    }
}

MainWindow::~MainWindow()
{
    delete machineConfig_;
    delete session_;
    delete settings_;
}

QString MainWindow::currentPage() const
{
    QWidget *page = pages_->currentWidget();

    if (page == setupPage_) {
        return "setup";
    }
    if (page == gatePage_) {
        return "gate";
    }
    if (page == loginPage_) {
        return "login";
    }
    if (page == pinPage_) {
        return "pin";
    }
    if (page == machinePage_) {
        return "machine";
    }

    QWidget *part = shell_->content()->currentWidget();
    if (part == salesPage_) {
        return salesTabName();
    }
    if (part == reviewPage_) {
        return "review";
    }
    if (part == supplyPart_) {
        return supplyPart_->tabs()->currentWidget() == manualPage_ ? "manual" : "supply";
    }
    if (part == remainderPage_) {
        return "remainders";
    }
    if (part == cardPage_) {
        return "cards";
    }
    if (part == dispensersPage_) {
        return "dispensers";
    }
    if (part == loanPage_) {
        return "loan";
    }
    if (part == settingsPage_) {
        return "settings";
    }

    return QString();
}

// The card reader is (re)started with the settings of Machine Config; only where the boards are linked (the same as the supplier's scanner).
void MainWindow::themeFileChanged()
{
    setStyleSheet(QString()); // reset first: a changed rule must not leave the old look behind
    setStyleSheet(Theme::styleSheet(themeFile_));
    if (!themeWatcher_->files().contains(themeFile_)) {
        themeWatcher_->addPath(themeFile_);
    }
}

// The screen of the tab of Sales that is open, as showPage() names it.
QString MainWindow::salesTabName() const
{
    QWidget *tab = salesPage_->tabs()->currentWidget();

    return tab == ticketPage_ ? "ticket" : "spot";
}

// A card on the reader opens the Cards screen with the card shown (its information, the top-up and the fill), wherever the cashier is in the
// program. The Cards screen listens to the reader itself, so a card read there stays there; before the login nothing is opened.
void MainWindow::cardRead(const QString &uid)
{
    if (!session_->isLoggedIn() || pages_->currentWidget() != shell_ || shell_->content()->currentWidget() == cardPage_) {
        return;
    }

    showPage("cards"); // it starts empty, so the card is shown again (the screen already took it, before it was opened)
    cardPage_->showCard(uid);
}

void MainWindow::startCardReader()
{
    if (!boardLink_) {
        return;
    }
    CardReader::Config reader;
    reader.port = settings_->value(SettingKey::CardReaderPort, CardReader::defaultPort());
    reader.baud = settings_->intValue(SettingKey::CardReaderBaud, CardReader::DefaultBaud);
    reader.format = settings_->value(SettingKey::CardReaderFormat, "line");
    reader.length = settings_->intValue(SettingKey::CardReaderLength, 0);
    reader.encoding = settings_->value(SettingKey::CardReaderEncoding, "ascii");
    reader.prefix = settings_->value(SettingKey::CardReaderPrefix, CardReader::defaultPrefix());
    cardReader_->start(reader);
}

void MainWindow::setDownloadIntervalMs(int milliseconds)
{
    downloadTimer_->setInterval(milliseconds);
}

void MainWindow::koreanModeChanged(bool on)
{
    shell_->footer()->setInputMode(true, on);
}

void MainWindow::setUploadIntervalMs(int milliseconds)
{
    uploadTimer_->setInterval(milliseconds);
}

void MainWindow::startFirstDownload()
{
    gatePage_->showWorking();
    pages_->setCurrentWidget(gatePage_);
    sync_->download();
}

void MainWindow::autoDownload()
{
    if (setup_->isConfigured() && settings_->boolValue(SettingKey::InitialDone) && !sync_->isRunning()) {
        sync_->download();
    }
}

// The automatic upload (Machine Config, "Upload"): in the modes "close" and "continuous" the records that are old enough go up by themselves
// (see AutoUpload); a failed upload loses nothing, it is tried again at the next beat. Never while a download or another upload runs.
void MainWindow::autoUpload()
{
    if (!setup_->isConfigured() || !settings_->boolValue(SettingKey::InitialDone) || sync_->isRunning() || uploader_->isRunning()) {
        return;
    }

    // the nozzles go up whatever the mode is: the web app should know the nozzles of the station (and the supplements need theirs there); the ones
    // it refused are not counted, so they cannot hold the records back
    if (Uploader::nozzlesWaiting(db_) > 0) {
        uploader_->uploadNozzles();

        return;
    }

    reportNothingWaiting();

    const QString cutoff = AutoUpload::cutoff(AutoUpload::modeOf(settings_->value(SettingKey::UploadMode)), QDateTime::currentDateTime());
    if (cutoff.isEmpty() || Uploader::pendingTotal(db_, QString(), cutoff) == 0) {
        return;
    }

    uploader_->upload(QString(), cutoff);
}

// Every few minutes, while no record of a business day that is over waits for an upload (the upload mode does not matter: the running day is the cashier's), the web app
// is told so. Its backup waits for this call: a push of records says only that a batch arrived, and an upload can stop between two tables.
void MainWindow::reportNothingWaiting()
{
    const qint64 now = QDateTime::currentMSecsSinceEpoch();
    if (now - lastReportMs_ < NothingWaitingEveryMs) {
        return;
    }
    const QString cutoff = AutoUpload::cutoff(AutoUpload::CloseOfDay, QDateTime::currentDateTime());   // the start of the running business day
    if (Uploader::unsentBefore(db_, cutoff) > 0) {
        return;
    }

    lastReportMs_ = now;
    uploader_->reportNothingWaiting();
}

// A nozzle was added, removed or changed on Machine Config: the web app is told now, not with the next upload of the records.
void MainWindow::sendNozzles()
{
    if (!setup_->isConfigured() || !settings_->boolValue(SettingKey::InitialDone) || sync_->isRunning() || uploader_->isRunning()) {
        return; // the next beat of autoUpload() sends them
    }
    Uploader::forgetNozzleRefusals(db_); // something changed on Machine Config: what the web app refused is tried once more
    if (Uploader::nozzlesWaiting(db_) > 0) {
        uploader_->uploadNozzles(true);
    }
}

void MainWindow::downloadFinished(bool ok, const QString &message)
{
    lastDownloadError_ = ok ? QString() : message;

    if (ok) {
        Purger::purge(db_, settings_->intValue(SettingKey::PurgeDays, 3), QDateTime::currentDateTime());
        boardClock_->syncAll(); // the terminal's clock may just have been corrected from the server's: the boards get it again
    }

    if (pages_->currentWidget() == gatePage_) {
        if (ok) {
            showStartOrLogin();
        } else {
            gatePage_->showFailure(message);
        }

        return;
    }

    if (pages_->currentWidget() == loginPage_) {
        loginPage_->reload(); // a cashier added on the web arrives here
    }
    if (ok && pages_->currentWidget() == shell_ && currentPage() == "review") {
        reviewPage_->reload(); // records the accountant confirmed on the web app have gone from the list
    }
    refreshStatus();
}

// The first screen (Sales) for a cashier who is logged in (and still allowed), the login screen for everybody else.
void MainWindow::showStartOrLogin()
{
    if (session_->validate()) {
        showStart();
    } else {
        showLogin();
    }
}

void MainWindow::showLogin()
{
    loginPage_->reload();
    pages_->setCurrentWidget(loginPage_);
}

// There is no home page: after the login the program opens on Sales, at the tab that was open last.
void MainWindow::showStart()
{
    showPage(salesTabName());
}

// The catch-up checkout, once when the app starts: records made by an older version of the program have no date yet; they get the business
// day of their time. (A record made now gets its date at once, see Checkout::businessDay.)
void MainWindow::autoCheckout()
{
    if (!setup_->isConfigured() || !settings_->boolValue(SettingKey::InitialDone)) {
        return;
    }

    if (Checkout::run(db_, QDateTime::currentDateTime(), "auto", 0, false).records > 0) {
        refreshUploadButton();
        if (currentPage() == "review") {
            reviewPage_->reload();
        }
    }
}

void MainWindow::showLoanSettlement()
{
    showPage("loan");
}

void MainWindow::showTicketSale()
{
    showPage("ticket");
}

void MainWindow::showSpotSale()
{
    showPage("spot");
}

void MainWindow::showSupply()
{
    showPage("supply");
}

void MainWindow::showUpload()
{
    showPage("review");
}

// Opens a screen inside the shell, makes it up to date and shows which part of the program it belongs to in the sidebar.
void MainWindow::showPage(const QString &name)
{
    QWidget *part = 0;
    QString key;

    if (name == "review" || name == "upload" || name == "checkout") {
        part = reviewPage_;
        key = "review";
        reviewPage_->reload();
    } else if (name == "supply" || name == "manual") {
        part = supplyPart_;
        key = "supplies";
        supplyPart_->tabs()->setCurrentWidget(name == "manual" ? static_cast<QWidget *>(manualPage_) : static_cast<QWidget *>(supplyPage_));
        reloadTab(supplyPart_);
    } else if (name == "remainders") {
        part = remainderPage_;
        key = "remainders";
        remainderPage_->reload();
        updateRemainderCount();
    } else if (name == "cards") {
        part = cardPage_;
        key = "cards";
        cardPage_->reload();
    } else if (name == "dispensers") {
        part = dispensersPage_;
        key = "dispensers";
        dispensersPage_->reload();
    } else if (name == "loan") {
        part = loanPage_;
        key = "debt";
        loanPage_->reload();
    } else if (name == "settings") {
        part = settingsPage_;
        key = "settings";
        settingsPage_->setCashier(session_->userName());
        settingsPage_->reload();
        settingsPage_->setAbout(about_ + "\n" + tr("Server %1:%2, this terminal is %3")
                                             .arg(settings_->value(SettingKey::ServerHost))
                                             .arg(settings_->intValue(SettingKey::ServerPort))
                                             .arg(settings_->value(SettingKey::DeviceName))
                                             + "\n" + (Clock::zoneText(*settings_).isEmpty() ? tr("Time zone: not known yet") : tr("Time zone: %1").arg(Clock::zoneText(*settings_))));
    } else if (name == "pin") {
        pinPage_->reset();
        pages_->setCurrentWidget(pinPage_); // outside the shell: the whole window

        return;
    } else if (name == "machine") {
        machinePage_->reload();
        pages_->setCurrentWidget(machinePage_);

        return;
    } else {
        // Sales: the tab named, or the one that was open last
        part = salesPage_;
        key = "sales";
        QWidget *tab = name == "ticket" ? static_cast<QWidget *>(ticketPage_) : (name == "spot" ? static_cast<QWidget *>(spotPage_) : salesPage_->tabs()->currentWidget());
        salesPage_->tabs()->setCurrentWidget(tab);
        reloadTab(salesPage_);
    }

    shell_->header()->setCashier(session_->isLoggedIn() ? tr("Cashier: %1").arg(session_->userName()) : QString());
    shell_->sidebar()->setEnabled(session_->isLoggedIn());
    shell_->content()->setCurrentWidget(part);
    shell_->sidebar()->setCurrent(key);
    pages_->setCurrentWidget(shell_);
    refreshStatus();
}

// A button of the sidebar: the part of the program opens at the screen that was open in it last.
void MainWindow::sidebarClicked(const QString &key)
{
    if (key == "sales") {
        showPage(salesTabName());
    } else if (key == "supplies") {
        showPage(supplyPart_->tabs()->currentWidget() == manualPage_ ? "manual" : "supply");
    } else if (key == "debt") {
        showPage("loan");
    } else {
        showPage(key);
    }
}

// Another tab of Sales or Review was picked: its screen is made up to date.
void MainWindow::tabChanged()
{
    if (shell_->content()->currentWidget() == salesPage_) {
        reloadTab(salesPage_);
    } else if (shell_->content()->currentWidget() == supplyPart_) {
        reloadTab(supplyPart_);
    }
    refreshStatus();
}

void MainWindow::reloadTab(TabbedPage *page)
{
    QWidget *tab = page->tabs()->currentWidget();

    if (tab == spotPage_) {
        spotPage_->reload();
    } else if (tab == ticketPage_) {
        ticketPage_->reload();
    } else if (tab == supplyPage_) {
        supplyPage_->reload();
    } else if (tab == manualPage_) {
        manualPage_->reload();
    }
}

// The button of the sidebar is marked (property alert, the colour is in the theme) while remainder tickets are not printed; its tip says how many.
void MainWindow::updateRemainderCount()
{
    const int unprinted = SupplyRecord::unprintedRemainders(db_);
    QPushButton *button = shell_->sidebar()->findChild<QPushButton *>("navRemainders");
    if (button && button->property("alert").toBool() != (unprinted > 0)) {
        button->setProperty("alert", unprinted > 0);
        button->style()->unpolish(button);
        button->style()->polish(button);
    }
    if (button) {
        button->setToolTip(unprinted > 0 ? tr("%1 not printed").arg(unprinted) : QString());
    }
}

// A remainder ticket was made by a supply: it is printed, one after the other.
void MainWindow::printRemainder(const QString &barcode)
{
    remaindersToPrint_ << barcode;
    printNextRemainder();
}

void MainWindow::printNextRemainder()
{
    if (printingRemainder_ || remaindersToPrint_.isEmpty() || printer_->isBusy()) {
        return;
    }
    printingRemainder_ = true;
    printingBarcode_ = remaindersToPrint_.takeFirst();
    printer_->printRemainderTicket(printingBarcode_);
}

void MainWindow::printerFinished(bool ok, const QString &message)
{
    if (!printingRemainder_) {
        return; // the print of a sale's ticket
    }
    printingRemainder_ = false;
    if (ok) {
        SupplyRecord::markRemainderPrinted(db_, printingBarcode_, QDateTime::currentDateTime());
        emit supplySession_->message(tr("The remainder ticket was sent to the printer."), true);
        remainderPage_->showResult(tr("The ticket %1 was sent to the printer.").arg(printingBarcode_), true);
    } else {
        emit supplySession_->message(tr("The remainder ticket %1 was not printed: %2 Print it on the Remainders screen.").arg(printingBarcode_).arg(message), false);
        remainderPage_->showResult(tr("The ticket %1 was not printed: %2").arg(printingBarcode_).arg(message), false);
    }
    updateRemainderCount();
    printNextRemainder();
}

// The footer says how many records wait for the upload.
void MainWindow::refreshUploadButton()
{
    shell_->footer()->setWaiting(Uploader::pendingTotal(db_));
    updateRemainderCount();
}

void MainWindow::showSettings()
{
    showPage("settings");
}

// Machine Config is behind the device PIN, asked every time. It is reached from the Settings button of the login screen only: nobody who has
// logged in can open it (the Settings page after the login has no button for it).
void MainWindow::askForPin()
{
    showPage("pin");
}

void MainWindow::showMachineConfig()
{
    showPage("machine");
}

// Back from the PIN or from Machine Config: to Settings, or to the login when Machine Config was opened from there.
void MainWindow::noReceiptListingChanged(bool show)
{
    machineConfig_->logChange("show sales without receipt", show ? "no" : "yes", show ? "yes" : "no", session_->userId());
}

void MainWindow::machineWasReset()
{
    restartWanted_ = true;
    emit restartRequested();
}

void MainWindow::showSettingsAgain()
{
    if (session_->isLoggedIn()) {
        showSettings();
    } else {
        showLogin();
    }
}

void MainWindow::logout()
{
    session_->logout();
    showLogin();
}

// What the footer says: the last download, the clock, the dispensers, the scanner and what waits for the upload.
void MainWindow::refreshStatus()
{
    ShellFooter *footer = shell_->footer();

    const QString last = settings_->value(SettingKey::LastDownloadAt);
    if (!lastDownloadError_.isEmpty()) {
        footer->setSync(tr("The last download failed, it will be tried again: %1").arg(lastDownloadError_), true);
    } else {
        footer->setSync(last.isEmpty() ? tr("No download yet.") : tr("Last download: %1").arg(last), false);
    }

    const qint64 offset = settings_->value(SettingKey::ClockOffsetSeconds).toLongLong();
    footer->setClockWarning(Clock::isOff(offset) ? tr("The clock differs from the server's by %1 s.").arg(offset < 0 ? -offset : offset) : QString());

    int online = 0;
    const QList<MachineConfig::Nozzle> nozzles = machineConfig_->nozzles();
    foreach (const MachineConfig::Nozzle &nozzle, nozzles) {
        online += boardBus_->isOnline(nozzle.number) ? 1 : 0;
    }
    footer->setBoards(boardBus_->isConnected(), online, nozzles.size(), boardLink_);
    footer->setScanner(boardLink_ && SupplierScanner::isSupported(), supplierScanner_->isConnected());
    footer->setWaiting(Uploader::pendingTotal(db_));
}

void MainWindow::updateClock()
{
    shell_->header()->setClock(QDateTime::currentDateTime().toString("yyyy-MM-dd HH:mm:ss"));
}
