#ifndef REMAINDERTICKETSPAGE_H
#define REMAINDERTICKETSPAGE_H

#include <QSqlDatabase>
#include <QStringList>
#include <QWidget>

class QLabel;
class QLineEdit;
class QPushButton;
class QTableWidget;

namespace Ui {
class RemainderTicketsPage;
}

// The remainder tickets the supplies have made (a fill that gave less than its tickets covered): when, at which nozzle, how much, whether the
// ticket was printed and whether it was used already. A ticket that was not printed (the printer was off, the app stopped) or got lost can be
// printed again here, as often as needed. The cashier can write a comment on a remainder (Save comment): it goes up with the next upload.
class RemainderTicketsPage : public QWidget
{
    Q_OBJECT

public:
    explicit RemainderTicketsPage(QSqlDatabase db, QWidget *parent = 0);
    ~RemainderTicketsPage();

    void reload();

    // What happened to the last print, from the printer (shown under the list).
    void showResult(const QString &text, bool good);

signals:
    void printRequested(const QString &barcode); // 16 digits
    void saved();                                // a comment was saved: the remainder waits for the upload again

private slots:
    void selectionChanged();
    void printClicked();
    void saveCommentClicked();

private:
    Ui::RemainderTicketsPage *ui_;
    QSqlDatabase db_;
    QTableWidget *table_;
    QPushButton *print_;
    QLineEdit *comment_;
    QPushButton *saveComment_;
    QStringList ids_;   // the id of each row of the table
    QLabel *status_;
};

#endif
