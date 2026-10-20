#ifndef CARDHISTORYDIALOG_H
#define CARDHISTORYDIALOG_H

#include <QDialog>
#include <QSqlDatabase>

namespace Ui {
class CardHistoryDialog;
}

// The history of a card, for the Cards screen: the latest entries of its ledger (CardLedger), newest first: when, top-up / fill / reversal, the kg in
// or out, the balance after it, and for a top-up the price per kg and what was paid. What this terminal made and the web app has not entered yet is
// marked "not confirmed yet". Read only.
class CardHistoryDialog : public QDialog
{
    Q_OBJECT

public:
    CardHistoryDialog(QSqlDatabase db, const QString &cardId, const QString &holder, QWidget *parent = 0);
    ~CardHistoryDialog();

    static const int Latest = 50;

private:
    Ui::CardHistoryDialog *ui_;
};

#endif
