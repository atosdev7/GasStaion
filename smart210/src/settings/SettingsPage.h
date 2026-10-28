#ifndef SETTINGSPAGE_H
#define SETTINGSPAGE_H

#include <QWidget>

class QComboBox;
class QLabel;
class Settings;

namespace Ui {
class SettingsPage;
}

// The Settings page, open to the cashier: who is logged in, what a spot sale and a card fill give (the ticket on paper, or the weight set straight on a
// nozzle: SettingKey::SpotSaleResult and CardFillResult, saved when the choice changes), the Machine Config button (which asks for the PIN first) and the
// Log out button. Log out is here and not on the main screen on purpose.
class SettingsPage : public QWidget
{
    Q_OBJECT

public:
    explicit SettingsPage(Settings *settings, QWidget *parent = 0);
    ~SettingsPage();

    void setCashier(const QString &name);
    void setAbout(const QString &text); // version, language, font, database and server: small print at the bottom
    void reload();                      // the two choices as they are saved (called when the page is opened)

signals:
    void logoutRequested();
    void machineConfigRequested();

private slots:
    void resultChanged();

private:
    Ui::SettingsPage *ui_;
    Settings *settings_;
    QLabel *cashier_;
    QComboBox *spotResult_;
    QComboBox *cardResult_;
    QLabel *about_;
};

#endif
