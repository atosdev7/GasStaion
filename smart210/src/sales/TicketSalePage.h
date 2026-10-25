#ifndef TICKETSALEPAGE_H
#define TICKETSALEPAGE_H

#include "TicketSale.h"

#include <QList>
#include <QSqlDatabase>
#include <QWidget>

class QButtonGroup;
class EnterChain;
class QEvent;
class QCheckBox;
class QComboBox;
class QLabel;
class QLineEdit;
class QPushButton;
class QTableWidget;
class Session;

namespace Ui {
class TicketSalePage;
}

// The ticket sale screen, arranged like the ticket sale form of the web app: the customer, the fuel type and the price, then the tickets
// to sell as items (an "add item" area at the left: the spec, the start serial that is filled in with the first one in stock, the count and the
// end serial that follows from them, with a Check button; the table of the items at the right with the first and last serial of each), then the
// total, the receipt, the use and the comment. An item without a start serial takes the lowest serials in stock. See TicketSale.
//
// The screen is used with the keyboard alone (EnterChain): the cursor starts at the customer, Enter moves through the fields in the order of the form,
// Enter in the count adds the item and the cursor goes back to the spec for the next one; one more Enter there (nothing typed) is "no more items"
// and goes on to the receipt; Enter or Space on Save sale saves, and the cursor is at the customer again.
class TicketSalePage : public QWidget
{
    Q_OBJECT

public:
    TicketSalePage(QSqlDatabase db, Session *session, QWidget *parent = 0);
    ~TicketSalePage();

    // Called whenever the page is opened: customers and fuel types as downloaded, no items.
    void reload();

    // Takes one item out of the list (the others are worked out anew). A double click on an item asks first, then calls this.
    void removeItem(int row);

signals:
    void saved(); // a sale was written: what waits for the upload has changed

protected:
    // The columns of the items table keep the ratio 1 : 1 : 2 : 2 : 1 (spec, count, start serial, end serial, kg) at any width.
    bool eventFilter(QObject *watched, QEvent *event);

private slots:
    void itemDoubleClicked(int row);
    void fuelChanged();
    void specChanged();
    void showEnd();
    void check();
    void addItem();
    void clearItems();
    void usageChanged();
    void priceEdited();
    void save();

private:
    Ui::TicketSalePage *ui_;
    QString usage() const;
    QString fuelType() const;
    TicketSale::Item formItem() const;   // what the add area says now (quantity 0 when the count is not a number)
    void fillSpecs();
    void fillStart();                    // the first serial in stock, after the items already in the table
    void showItems();
    void showTotals();
    void showResult(const QString &message, bool ok);
    void showStatus(const QString &message, bool ok);

    QSqlDatabase db_;
    Session *session_;
    QComboBox *usage_;
    QComboBox *customer_;
    QComboBox *fuel_;
    QLineEdit *price_;
    QComboBox *spec_;
    QLineEdit *start_;
    QLineEdit *count_;
    QLineEdit *end_;
    QLabel *result_;
    QTableWidget *items_;
    QCheckBox *receipt_;
    QLineEdit *comment_;
    QLabel *totals_;
    QLabel *status_;
    EnterChain *chain_;                  // Enter moves on to the next field; Enter on Save sale saves
    QList<TicketSale::Item> itemList_;
    qlonglong kgX100_;
    int tickets_;
};

#endif
