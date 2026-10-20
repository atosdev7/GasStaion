#ifndef SETTINGSPAGE_H
#define SETTINGSPAGE_H

#include <QWidget>

class QLabel;

namespace Ui {
class SettingsPage;
}

// The Settings page, open to the cashier: who is logged in, the Machine Config button (which asks for the PIN first) and the
// Log out button. Log out is here and not on the main screen on purpose.
class SettingsPage : public QWidget
{
    Q_OBJECT

public:
    explicit SettingsPage(QWidget *parent = 0);
    ~SettingsPage();

    void setCashier(const QString &name);
    void setAbout(const QString &text); // version, language, font, database and server: small print at the bottom

signals:
    void logoutRequested();
    void machineConfigRequested();

private:
    Ui::SettingsPage *ui_;
    QLabel *cashier_;
    QLabel *about_;
};

#endif
