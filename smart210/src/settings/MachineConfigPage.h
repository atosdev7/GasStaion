#ifndef MACHINECONFIGPAGE_H
#define MACHINECONFIGPAGE_H

#include <QWidget>

class CardReader;
class MachineConfig;
class NozzleConfigSync;
class PrintService;
class QComboBox;
class QLineEdit;
class QPushButton;
class QTabWidget;
class QTimer;
class QLabel;
class QListWidget;
class QTableWidget;
class ServerForm;
class Session;
class Settings;
class SetupFlow;

namespace Ui {
class MachineConfigPage;
}

// Machine Config: the server address, the printer, the nozzles, the port the converter connects to, and the log of what was changed.
// The nozzles are the boards 1 to 6 (the number of a nozzle is the address of its board). Each board holds its own config (mode, fuel
// type, density, pulses per litre): the page shows what the boards reported and their status, and a change is sent to the board, which
// confirms it. Only the tank of a nozzle is picked here. Reached from the Settings page after the PIN; leaving it means the PIN is
// asked again next time.
class MachineConfigPage : public QWidget
{
    Q_OBJECT

public:
    MachineConfigPage(SetupFlow *flow, MachineConfig *config, Session *session, Settings *settings, PrintService *printer,
                      NozzleConfigSync *nozzles, QWidget *parent = 0);
    ~MachineConfigPage();

    // Called whenever the page is opened: shows the current values and the latest changes, and asks the boards for their config.
    void reload();

signals:
    void backRequested();
    void cardReaderChanged();   // the card reader's settings were saved: the reader is started again with them
    void restartRequested();    // Clean Data or Clear Config was done: the program has to start again
    void nozzlesEdited();       // a nozzle was added, removed or changed: the web app should be told

private slots:
    void updateNozzle();
    void saveNozzleName();
    void readNozzles();
    void readDone(int number);
    void readFailed(int number, const QString &message);
    void rowSelected();
    void fuelPicked();
    void nozzlesChanged();
    void updateDone(int number);
    void updateFailed(int number, const QString &message);
    void saveConverterPort();
    void saveCardReader();
    void formatPicked();
    void uploadModePicked();
    void saveUploadMode();
    void saveReviewOptions();
    void saveManualSupplyOptions();
    void serverConfigured();
    void savePrinter();
    void checkPrinter();
    void printerChecked(bool ok, const QString &message);
    void addNozzlePressed();
    void removeNozzlePressed();
    void addFuelPicked();
    void cleanDataPressed();
    void clearConfigPressed();
    void disarmReset();
    void finishReset();

private:
    Ui::MachineConfigPage *ui_;
    void fillTable();
    void fillForm(int number);
    void fillLog();
    bool storePrinter();
    void showStatus(const QString &message, bool ok);
    void showResetBox();
    void fillAddForm();
    void alert(const QString &title, const QString &reason);
    void armReset(int which);

    MachineConfig *config_;
    Session *session_;
    Settings *settings_;
    PrintService *printer_;
    NozzleConfigSync *nozzles_;
    QLineEdit *printerHost_;
    QLineEdit *printerPort_;
    QLabel *printerStatus_;
    bool checking_;
    QString printerBefore_;
    ServerForm *server_;
    QTableWidget *table_;
    QLabel *selected_;
    QComboBox *mode_;
    QComboBox *fuel_;
    QLineEdit *density_;
    QLineEdit *pulses_;
    QLineEdit *nozzleName_;
    QLineEdit *converterPort_;
    QLineEdit *cardPort_;
    QComboBox *cardBaud_;
    QComboBox *cardFormat_;
    QLineEdit *cardLength_;
    QLineEdit *cardPrefix_;
    QComboBox *cardEncoding_;
    QString cardBefore_;
    QString converterBefore_;
    QComboBox *uploadMode_;
    QString uploadModeBefore_;
    int armedReset_;        // 0: none, 1: Clean Data, 2: Clear Config; the first press arms, the second one (within seconds) does it
    QTimer *resetTimer_;
    bool showNoReceiptBefore_;
    int manualSupplyDaysBefore_;
    QListWidget *log_;
    QLabel *status_;
    QString serverBefore_;
    int selectedNumber_; // the nozzle in the form, 0 when none
};

#endif
