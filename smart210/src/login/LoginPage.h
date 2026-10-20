#ifndef LOGINPAGE_H
#define LOGINPAGE_H

#include <QWidget>

class QLabel;
class QLineEdit;
class QComboBox;
class QPushButton;
class Session;

namespace Ui {
class LoginPage;
}

// The login screen: the cashier chosen in a combo box (by name), the password and a Settings button (bottom right) that opens Machine Config
// behind the device PIN without a login. loggedIn() is emitted when the password was right.
class LoginPage : public QWidget
{
    Q_OBJECT

public:
    LoginPage(Session *session, QWidget *parent = 0);
    ~LoginPage();

    // Fills the combo box again from the users the device has (after a download brought new ones).
    void reload();

signals:
    void loggedIn();
    void settingsRequested();

private slots:
    void login();
    void selectionChanged();

private:
    Ui::LoginPage *ui_;
    Session *session_;
    QComboBox *cashier_;
    QLineEdit *password_;
    QPushButton *loginButton_;
    QLabel *status_;
};

#endif
