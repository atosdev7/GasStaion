#ifndef FIRSTSYNCPAGE_H
#define FIRSTSYNCPAGE_H

#include <QWidget>

class QLabel;
class QPushButton;

namespace Ui {
class FirstSyncPage;
}

// The first-sync gate: until the first download has finished the terminal has no users, prices or tickets, so nothing else
// can be used. Shows that it is working, or why it failed with a Retry button.
class FirstSyncPage : public QWidget
{
    Q_OBJECT

public:
    explicit FirstSyncPage(QWidget *parent = 0);
    ~FirstSyncPage();

    void showWorking();
    void showFailure(const QString &message);

signals:
    void retryRequested();

private:
    Ui::FirstSyncPage *ui_;
    QLabel *status_;
    QPushButton *retry_;
};

#endif
