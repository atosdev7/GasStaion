#ifndef CONFIRMDIALOG_H
#define CONFIRMDIALOG_H

#include <QDialog>
#include <QString>

namespace Ui {
class ConfirmDialog;
}

// "Do you really want to do this?" with OK and Cancel: asked before an action that changes money or fuel (the Cards screen: Top Up, Start fill, Save fill).
// OK is the default button, so Enter confirms and Esc cancels. `ask` shows it and returns true when the cashier pressed OK.
//
// For the tests: `setAutoAnswer(Yes / No)` makes `ask` answer at once without opening a window; `lastQuestion()` is the text of the latest question.
class ConfirmDialog : public QDialog
{
    Q_OBJECT

public:
    enum AutoAnswer { Show = 0, Yes = 1, No = 2 };

    ConfirmDialog(const QString &title, const QString &question, QWidget *parent = 0);
    ~ConfirmDialog();

    static bool ask(QWidget *parent, const QString &title, const QString &question);
    static void setAutoAnswer(int answer);
    static QString lastQuestion();

private:
    Ui::ConfirmDialog *ui_;
};

#endif
