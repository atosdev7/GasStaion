#include "FirstSyncPage.h"
#include "ui_FirstSyncPage.h"
#include "Theme.h"

#include <QLabel>
#include <QPushButton>

FirstSyncPage::FirstSyncPage(QWidget *parent)
    : QWidget(parent), ui_(new Ui::FirstSyncPage)
{
    ui_->setupUi(this);

    status_ = ui_->firstSyncStatus;
    retry_ = ui_->retryButton;

    connect(retry_, SIGNAL(clicked()), this, SIGNAL(retryRequested()));
    showWorking();
}

FirstSyncPage::~FirstSyncPage()
{
    delete ui_;
}

void FirstSyncPage::showWorking()
{
    Theme::setState(status_, "");
    status_->setText(tr("Downloading from the server... please wait."));
    retry_->setVisible(false);
}

void FirstSyncPage::showFailure(const QString &message)
{
    Theme::setState(status_, "error");
    status_->setText(tr("The download did not finish: %1").arg(message));
    retry_->setVisible(true);
}
