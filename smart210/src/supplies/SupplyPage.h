#ifndef SUPPLYPAGE_H
#define SUPPLYPAGE_H

#include "SupplyQueue.h"

#include <QDateTime>
#include <QList>
#include <QSqlDatabase>
#include <QWidget>

class BoardBus;
class MachineConfig;
class QLabel;
class QLineEdit;
class QTableWidget;
class QTimer;
class SupplySelection;
class SupplySession;

namespace Ui {
class SupplyPage;
}

// The Sessions tab of Supplies: only a view of what the supply does in the background. The six nozzles with their fuel type, mode, the status of
// the session (Queued: tickets scanned, nothing sent; Set: the preset is on the board and the dispenser shows it; Working: the gun is up and the
// fill runs; Done: the fill ended and was recorded, shown for 10 seconds; else the live state of the board: free, selected, no answer, ...), the
// tickets (kg - count) and the weight dispensed so far. Clicking a nozzle shows its queued tickets in the table below (barcode, fuel type,
// amount); it does not choose the nozzle at the dispenser: the supplier does that with the select key and scans the tickets (SupplySession),
// and clears the queue with the clear key of the dispenser. Without a click the table shows the nozzle the supplier chose last. A ticket whose barcode
// cannot be scanned is typed in the **Enter ticket** box (11 or 16 digits, then Enter): it goes through the same checks to the queue of the nozzle the table shows
// (the one that was clicked, else the chosen one), which is chosen first when it is not the chosen one yet.
class SupplyPage : public QWidget
{
    Q_OBJECT

public:
    SupplyPage(QSqlDatabase db, BoardBus *bus, MachineConfig *config, SupplySelection *selection, SupplySession *session, QWidget *parent = 0);
    ~SupplyPage();

    void reload(); // called when the page is opened

    // How long a finished fill stays as Done (10 seconds); tests shorten it.
    void setDoneMs(int milliseconds) { doneMs_ = milliseconds; }

private slots:
    void rowClicked(int row);
    void ticketEntered();
    void sessionMessage(const QString &text, bool good);
    void supplyRecorded(int nozzle, const QString &supplementId);
    void refresh();

private:
    struct DoneFill {
        DoneFill() : until(0), nozzle(0), dispensedX100(0) {}

        qint64 until;                         // ms since epoch: it goes after this
        int nozzle;
        qlonglong dispensedX100;
        QList<SupplyQueue::Entry> tickets;
        QString card;                         // the customer of a card fill
    };

    int viewedNozzle() const;
    const DoneFill *doneOf(int nozzle) const;
    void showQueue();
    void showMessage(const QString &text, bool good);

    Ui::SupplyPage *ui_;
    QSqlDatabase db_;
    BoardBus *bus_;
    MachineConfig *config_;
    SupplySelection *selection_;
    SupplySession *session_;
    QTableWidget *table_;
    QTableWidget *queue_;
    QLabel *queueTotal_;
    QLabel *current_;
    QLabel *message_;
    QLineEdit *ticketField_;
    QTimer *timer_;
    QList<DoneFill> done_;
    QString shown_;   // what the tickets table shows now (drawn again only when it changed)
    int viewed_;      // the nozzle that was clicked; 0: the one the supplier chose
    int followed_;    // the nozzle the supplier chose last (its tickets stay on the screen while its fill runs)
    int doneMs_;
};

#endif
