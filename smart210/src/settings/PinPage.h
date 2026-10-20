#ifndef PINPAGE_H
#define PINPAGE_H

#include <QWidget>

class QLabel;
class QLineEdit;
class Settings;

namespace Ui {
class PinPage;
}

// Asks for the device PIN before Machine Config opens. The PIN is the one the admin sets on the web Devices screen; its salt and
// hash came with the config download and are checked here, so it works offline. accepted() when it was right.
// Five wrong PINs in a row shut the door for five minutes (a right PIN does not open it meanwhile); a right PIN counts the wrong ones afresh.
// The count and the time are kept in the settings, so restarting the program does not undo the lock.
class PinPage : public QWidget
{
    Q_OBJECT

public:
    PinPage(Settings *settings, QWidget *parent = 0);
    ~PinPage();

    // Ready for the next entry: empty field, no message, focus in the field.
    void reset();

signals:
    void accepted();
    void cancelled();

private slots:
    void check();

public:
    static const int MaxWrong = 5;
    static const int LockMinutes = 5;

private:
    Ui::PinPage *ui_;
    Settings *settings_;
    QLineEdit *pin_;
    QLabel *status_;
};

#endif
