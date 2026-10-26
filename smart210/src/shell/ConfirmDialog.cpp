#include "ConfirmDialog.h"
#include "ui_ConfirmDialog.h"

namespace {

int autoAnswer = ConfirmDialog::Show;
QString latestQuestion;

}

ConfirmDialog::ConfirmDialog(const QString &title, const QString &question, QWidget *parent)
    : QDialog(parent), ui_(new Ui::ConfirmDialog)
{
    ui_->setupUi(this);
    ui_->confirmTitle->setText(title);
    ui_->confirmQuestion->setText(question);
    ui_->confirmOkButton->setDefault(true);
    ui_->confirmOkButton->setFocus();
    connect(ui_->confirmOkButton, SIGNAL(clicked()), this, SLOT(accept()));
    connect(ui_->confirmCancelButton, SIGNAL(clicked()), this, SLOT(reject()));
}

ConfirmDialog::~ConfirmDialog()
{
    delete ui_;
}

bool ConfirmDialog::ask(QWidget *parent, const QString &title, const QString &question)
{
    latestQuestion = question;
    if (autoAnswer != Show) {
        return autoAnswer == Yes;
    }

    ConfirmDialog dialog(title, question, parent);

    return dialog.exec() == QDialog::Accepted;
}

void ConfirmDialog::setAutoAnswer(int answer)
{
    autoAnswer = answer;
}

QString ConfirmDialog::lastQuestion()
{
    return latestQuestion;
}
