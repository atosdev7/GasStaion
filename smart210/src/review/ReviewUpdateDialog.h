#ifndef REVIEWUPDATEDIALOG_H
#define REVIEWUPDATEDIALOG_H

#include <QDate>
#include <QDialog>
#include <QString>

namespace Ui {
class ReviewUpdateDialog;
}

// The window of the Update button of the Review screen: the fields that can be changed on the selected record (its comment and its date), filled in with what the record
// has now. Update asks the screen to apply them (applyRequested); the screen closes the window when it did, or says why it could not (showProblem) and the window stays open
// for the cashier to correct the date. Cancel closes it without a change. The window is shown, not run (the screen goes on, the rest of the program is blocked while it is open).
class ReviewUpdateDialog : public QDialog
{
    Q_OBJECT

public:
    ReviewUpdateDialog(const QString &record, const QString &comment, const QDate &date, int maxComment, QWidget *parent = 0);
    ~ReviewUpdateDialog();

    void showProblem(const QString &text);

signals:
    void applyRequested(const QString &comment, const QDate &date);

private slots:
    void applyClicked();

private:
    Ui::ReviewUpdateDialog *ui_;
};

#endif
