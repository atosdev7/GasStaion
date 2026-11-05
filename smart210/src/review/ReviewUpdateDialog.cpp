#include "ReviewUpdateDialog.h"
#include "Theme.h"
#include "ui_ReviewUpdateDialog.h"

#include <QDateEdit>
#include <QLabel>
#include <QLineEdit>
#include <QPushButton>

ReviewUpdateDialog::ReviewUpdateDialog(const QString &record, const QString &comment, const QDate &date, int maxComment, QWidget *parent)
    : QDialog(parent), ui_(new Ui::ReviewUpdateDialog)
{
    ui_->setupUi(this);
    ui_->updateRecord->setText(record);
    ui_->updateComment->setMaxLength(maxComment);
    ui_->updateComment->setText(comment);
    ui_->updateDate->setDate(date);
    ui_->updateOkButton->setDefault(true);
    connect(ui_->updateOkButton, SIGNAL(clicked()), this, SLOT(applyClicked()));
    connect(ui_->updateCancelButton, SIGNAL(clicked()), this, SLOT(reject()));
    ui_->updateComment->setFocus();
}

ReviewUpdateDialog::~ReviewUpdateDialog()
{
    delete ui_;
}

void ReviewUpdateDialog::applyClicked()
{
    ui_->updateMessage->setVisible(false);
    emit applyRequested(ui_->updateComment->text(), ui_->updateDate->date());
}

void ReviewUpdateDialog::showProblem(const QString &text)
{
    Theme::setState(ui_->updateMessage, "error");
    ui_->updateMessage->setText(text);
    ui_->updateMessage->setVisible(true);
}
