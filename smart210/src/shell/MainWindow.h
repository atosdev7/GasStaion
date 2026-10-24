#ifndef MAINWINDOW_H
#define MAINWINDOW_H

#include <QSqlDatabase>
#include <QStringList>
#include <QWidget>

class HangulInput;
class AppConfig;
class AppShell;
class CardPage;
class QFileSystemWatcher;
class CardReader;
class DispensersPage;
class ManualSupplyPage;
class TabbedPage;
class ReviewPage;
class FirstSyncPage;
class LoanSettlePage;
class LoginPage;
class MachineConfig;
class MachineConfigPage;
class BoardBus;
class BoardClock;
class NozzleConfigSync;
class SupplierScanner;
class SupplyPage;
class SupplySelection;
class SupplySession;
class PinPage;
class PrintService;
class QLabel;
class RemainderTicketsPage;
class QStackedWidget;
class QTimer;
class Session;
class Settings;
class SettingsPage;
class SpotSalePage;
class TicketSalePage;
class SetupFlow;
class SetupPage;
class SyncClient;
class Uploader;
class QPushButton;

// The one window of the app. The first screens fill the window: setup (until the server is set and knows this terminal), gate (until the
// first download has finished) and login (until a cashier has logged in; the login stays over restarts until Log out). After that every
// screen is inside the shell (AppShell): a header with the company, the cashier and the time, a sidebar with one button for each part,
// a footer with the status, and the content. The parts and their screens (the names are what currentPage() and showPage() use):
//   Home        home        what waits for the upload, and the Upload data button
//   Sales       spot, ticket   the spot sale and the ticket sale
//   Supplies    supply, manual   the nozzles with their live status and the queues of all nozzles with their status (tap or select
//                           key, the supplier's scanner, Start); the supplies of manual-mode nozzles typed in with their tickets
//   Remainders  remainders  the remainder tickets of the supplies (print again)
//   Dispensers  dispensers  the boards at a glance, read only
//   Ticket Debt loan        ticket loan settlement (tickets surrendered to pay a loan)
//   Review      review      the records of a date (sales, supplies, ticket debt), their comments and dates, and Upload data
//   Settings    settings, pin, machine     Settings, the PIN question and Machine Config
// While the app runs it downloads from the server by itself, every few minutes, and cleans up old records after each
// download. It never uploads by itself.
class MainWindow : public QWidget
{
    Q_OBJECT

public:
    MainWindow(const AppConfig &config, const QString &fontFamily, const QString &databaseInfo, QSqlDatabase db,
               QWidget *parent = 0);
    ~MainWindow();

    // "setup", "gate", "login", "spot", "ticket", "supply", "manual", "remainders", "dispensers", "loan", "review", "settings", "pin" or "machine".
    QString currentPage() const;

    // Opens one of the screens inside the shell (what the sidebar does); the names are those of currentPage().
    void showPage(const QString &name);
    void setDownloadIntervalMs(int milliseconds);
    void setUploadIntervalMs(int milliseconds);

    bool restartWanted() const { return restartWanted_; } // main() starts the program again when Machine Config reset it

signals:
    // Clean Data or Clear Config was done on Machine Config: what the program holds in memory is not right any more, main() starts it again.
    void restartRequested();

private slots:
    void machineWasReset();
    void updateClock();
    void startFirstDownload();
    void autoDownload();
    void autoUpload();
    void hangulModeChanged(bool on);
    void downloadFinished(bool ok, const QString &message);
    void showStartOrLogin();
    void sidebarClicked(const QString &key);
    void tabChanged();
    void showSettings();
    void logout();
    void showStart();
    void askForPin();
    void showMachineConfig();
    void showSettingsAgain();
    void showUpload();
    void showSpotSale();
    void showSupply();
    void showTicketSale();
    void showLoanSettlement();
    void autoCheckout();
    void refreshUploadButton();
    void startCardReader();
    void cardRead(const QString &uid);
    void themeFileChanged();
    void refreshStatus();
    void printRemainder(const QString &barcode);
    void printerFinished(bool ok, const QString &message);

private:
    void showLogin();
    QString salesTabName() const;
    void reloadTab(TabbedPage *page);
    void updateRemainderCount();
    void printNextRemainder();

    QSqlDatabase db_;
    Settings *settings_;
    Session *session_;
    MachineConfig *machineConfig_;
    BoardBus *boardBus_;
    BoardClock *boardClock_;
    SupplierScanner *supplierScanner_;
    SupplySelection *supplySelection_;
    CardReader *cardReader_;
    QString themeFile_;
    QFileSystemWatcher *themeWatcher_;
    CardPage *cardPage_;
    SupplySession *supplySession_;
    NozzleConfigSync *nozzleSync_;
    PrintService *printer_;
    SetupFlow *setup_;
    SyncClient *sync_;
    HangulInput *hangulInput_; // the Hangul input method; null when config.ini says hangul=false
    Uploader *uploader_;
    QStackedWidget *pages_;
    SetupPage *setupPage_;
    FirstSyncPage *gatePage_;
    LoginPage *loginPage_;
    AppShell *shell_;
    TabbedPage *salesPage_;
    ReviewPage *reviewPage_;
    DispensersPage *dispensersPage_;
    SettingsPage *settingsPage_;
    PinPage *pinPage_;
    MachineConfigPage *machinePage_;
    SpotSalePage *spotPage_;
    SupplyPage *supplyPage_;
    ManualSupplyPage *manualPage_;
    TabbedPage *supplyPart_;
    QStringList remaindersToPrint_; // remainder tickets waiting for the printer
    bool printingRemainder_;
    QString printingBarcode_;
    RemainderTicketsPage *remainderPage_;
    TicketSalePage *ticketPage_;
    LoanSettlePage *loanPage_;
    bool restartWanted_;
    QString about_;      // version, language, font, database: shown on the Settings page
    bool boardLink_;     // the converter and the scanner are listened to
    QTimer *timer_;
    QTimer *downloadTimer_;
    QTimer *uploadTimer_;
    QString lastDownloadError_;
};

#endif
