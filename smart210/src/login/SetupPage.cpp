#include "SetupPage.h"

#include "ServerForm.h"
#include "SetupFlow.h"
#include "ui_SetupPage.h"


SetupPage::SetupPage(SetupFlow *flow, QWidget *parent)
    : QWidget(parent), ui_(new Ui::SetupPage)
{
    ui_->setupUi(this);

    form_ = new ServerForm(flow); // it needs the flow, so it is made here and put where the form has room for it
    ui_->serverFormLayout->addWidget(form_);

    connect(form_, SIGNAL(configured()), this, SIGNAL(configured()));
}

SetupPage::~SetupPage()
{
    delete ui_;
}
