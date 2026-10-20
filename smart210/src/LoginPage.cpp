#include "LoginPage.h"

#include "Session.h"
#include "ui_LoginPage.h"
#include "Theme.h"

#include <QLabel>
#include <QLineEdit>
#include <QComboBox>
#include <QPushButton>

LoginPage::LoginPage(Session *session, QWidget *parent)
    : QWidget(parent), ui_(new Ui::LoginPage), session_(session)
{
    ui_->setupUi(this);

    cashier_ = ui_->cashierCombo;
    password_ = ui_->password;
    loginButton_ = ui_->loginButton;
    status_ = ui_->loginStatus;

    connect(loginButton_, SIGNAL(clicked()), this, SLOT(login()));
    connect(password_, SIGNAL(returnPressed()), this, SLOT(login()));
    connect(cashier_, SIGNAL(activated(int)), this, SLOT(selectionChanged()));
    connect(ui_->loginSettingsButton, SIGNAL(clicked()), this, SIGNAL(settingsRequested()));

    reload();
}

LoginPage::~LoginPage()
{
    delete ui_;
}

void LoginPage::reload()
{
    const int selected = cashier_->itemData(cashier_->currentIndex()).toInt();

    cashier_->clear();
    foreach (const Session::Cashier &cashier, session_->cashiers()) {
        cashier_->addItem(cashier.name.isEmpty() ? cashier.username : cashier.name, cashier.id);
    }
    const int at = cashier_->findData(selected);
    cashier_->setCurrentIndex(at >= 0 ? at : (cashier_->count() > 0 ? 0 : -1));

    loginButton_->setEnabled(cashier_->count() > 0);
    password_->setEnabled(cashier_->count() > 0);
    cashier_->setEnabled(cashier_->count() > 0);
    if (cashier_->count() == 0) {
        Theme::setState(status_, "error");
        status_->setText(tr("There are no cashier accounts on this terminal. Add one in the web app; it arrives with the next download."));
    } else {
        status_->clear();
    }
}

void LoginPage::selectionChanged()
{
    status_->clear();
    password_->clear();
    password_->setFocus();
}

void LoginPage::login()
{
    if (cashier_->currentIndex() < 0) {
        Theme::setState(status_, "error");
        status_->setText(tr("Choose your name first."));

        return;
    }

    const bool ok = session_->login(cashier_->itemData(cashier_->currentIndex()).toInt(), password_->text());
    password_->clear(); // never kept

    if (!ok) {
        Theme::setState(status_, "error");
        status_->setText(tr("Wrong password."));
        password_->setFocus();

        return;
    }

    status_->clear();
    emit loggedIn();
}
