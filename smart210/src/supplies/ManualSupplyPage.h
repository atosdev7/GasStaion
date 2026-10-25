#ifndef MANUALSUPPLYPAGE_H
#define MANUALSUPPLYPAGE_H

#include <QList>
#include <QSqlDatabase>
#include <QWidget>

class MachineConfig;
class QComboBox;
class QDateTimeEdit;
class QLabel;
class QLineEdit;
class QPushButton;
class QTableWidget;
class SupplySession;

namespace Ui {
class ManualSupplyPage;
}

// The Manual input screen (a tab of Supplies): the supplies of manual-mode nozzles, which the program does not work with at all (the supplier
// types the amount at the dispenser; the board only answers POLL, SET_TIME and the config). Only the manual-mode nozzles can be chosen. The cashier types the
// supply in: the nozzle (its density is shown), when, and the tickets that paid for it, by their barcodes (typed with the keyboard, Enter adds
// one). The tickets must be sold, of the nozzle's fuel type and in no queue. Their total is the preset; the dispensed kg is set to the preset
// by itself (and the remainder is 0), and the litres follow from the kg and the density (they can be edited too). The cashier types the dispensed kg
// or the remainder, whichever is at hand: the other follows (dispensed + remainder = the tickets). The tickets must cover the dispensed kg; what
// is left is a remainder ticket, which is printed (unless it is within 0.05 kg, which the station keeps). An optional comment is kept with the supplement. Save writes the supplement with its tickets for the upload (SupplySession::recordManual).
class ManualSupplyPage : public QWidget
{
    Q_OBJECT

public:
    ManualSupplyPage(QSqlDatabase db, MachineConfig *config, SupplySession *session, QWidget *parent = 0);
    ~ManualSupplyPage();

    void reload(); // called when the tab is opened: an empty form for now

signals:
    void recorded(); // a supply was written: what waits for the upload has changed

private slots:
    void nozzleChanged();
    void addTicket();
    void removeSelected();
    void kgEdited();
    void remainderEdited();
    void save();

private:
    Ui::ManualSupplyPage *ui_;
    int nozzle() const;
    qlonglong density() const;
    qlonglong ticketsX100() const;
    void showTickets();
    void showSummary();
    void calculateLiters();
    void showRemainder();
    void clearForm();

    QSqlDatabase db_;
    MachineConfig *config_;
    SupplySession *session_;
    QComboBox *nozzle_;
    QDateTimeEdit *time_;
    QLineEdit *density_;
    QLineEdit *preset_;
    QLineEdit *kg_;
    QLineEdit *remainder_;
    QLineEdit *liters_;
    QLineEdit *code_;
    QLineEdit *comment_;
    QTableWidget *tickets_;
    QLabel *summary_;
    QLabel *status_;
    QPushButton *save_;
    QList<qlonglong> barcodes_;
    QList<qlonglong> weights_;
    bool kgTyped_;   // the cashier edited the kg: the preset does not set it any more
};

#endif
