#ifndef SPOTSALEPAGE_H
#define SPOTSALEPAGE_H

#include "BoardProtocol.h"

#include <QSqlDatabase>
#include <QWidget>

class QButtonGroup;
class BoardBus;
class EnterChain;
class MachineConfig;
class QCheckBox;
class QComboBox;
class QLabel;
class QLineEdit;
class QPushButton;
class PrintService;
class QTimer;
class Session;
class Settings;
class SupplySelection;
class SupplySession;

namespace Ui {
class SpotSalePage;
}

// The spot sale screen, arranged like the spot sale form of the web app: the customer, the fuel type and the price; the weight and
// the amount (type one and the other follows from the price; whichever was typed last is the one that counts) and the payment (always
// cash, paid in full); what the fuel is for; the receipt and the comment. Save writes the sale (see SpotSale); the screen then clears
// for the next one.
//
// The result of the sale is the cashier's setting (Settings page, SettingKey::SpotSaleResult): "Print ticket" prints the sale's ticket at once (Print again prints
// it once more), "Set directly" prints nothing: a question asks for the nozzle (of the sale's fuel type, see DirectSet), the nozzle is chosen on its board
// and the ticket of the sale goes to its queue, so the weight is the preset of the board (the dispenser shows it) and the supplier starts the
// fill by taking the gun up, exactly as when the supplier scans the ticket (SupplySession::add). A sale that could not be sent keeps its ticket, which Print again prints.
//
// The screen is used with the keyboard alone (EnterChain): the cursor starts at the weight, Enter moves through the fields in the order of the form
// (the ones that are switched off are jumped over) and Enter or Space on Save sale saves; after a sale the cursor is at the weight again. The customer
// starts as "Individual"; own use and other use are always Individual.
class SpotSalePage : public QWidget
{
    Q_OBJECT

public:
    SpotSalePage(QSqlDatabase db, Session *session, PrintService *printer, BoardBus *bus, MachineConfig *config, SupplySelection *selection,
                 SupplySession *supply, Settings *settings, QWidget *parent = 0);
    ~SpotSalePage();

    // Called whenever the page is opened: fuel types and customers as downloaded, an empty form.
    void reload();

signals:
    void saved(); // a sale was written: what waits for the upload has changed

private slots:
    void usageChanged();
    void fuelChanged();
    void priceEdited();
    void kgEdited();
    void amountEdited();
    void save();
    void printAgain();
    void printFinished(bool ok, const QString &message);

private:
    Ui::SpotSalePage *ui_;
    QString usage() const;
    void clearEntry();
    void recalculate();
    void showStatus(const QString &message, bool ok);
    void startPrint();
    void setDirectly(const QString &fuelType, qlonglong kgX100, const QString &barcode);

    QSqlDatabase db_;
    Session *session_;
    PrintService *printer_;
    BoardBus *bus_;
    MachineConfig *config_;
    SupplySelection *selection_;
    SupplySession *supply_;
    Settings *settings_;
    QComboBox *usage_;
    QComboBox *fuel_;
    QComboBox *customer_;
    QLineEdit *price_;
    QLineEdit *kg_;
    QLineEdit *amount_;
    QLineEdit *comment_;
    QCheckBox *receipt_;
    QLabel *status_;
    QPushButton *printAgain_;
    EnterChain *chain_;    // Enter moves on to the next field; Enter on Save sale saves
    QString lastSaleId_;   // the sale whose ticket Print again prints
    QString savedMessage_;
    bool printing_;
    bool amountTypedLast_;
};

#endif
