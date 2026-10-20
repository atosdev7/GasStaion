#include "TabbedPage.h"
#include "ui_TabbedPage.h"

#include <QLabel>
#include <QTabWidget>

TabbedPage::TabbedPage(const QString &title, QWidget *parent)
    : QWidget(parent), ui_(new Ui::TabbedPage)
{
    ui_->setupUi(this);

    ui_->pageTitle->setText(title);
    tabs_ = ui_->pageTabs; // the tabs of a part of the program are 24 px (the property pageTabs, see theme.qss)
}

TabbedPage::~TabbedPage()
{
    delete ui_;
}

void TabbedPage::addPage(QWidget *page, const QString &tabText)
{
    // the tab names the screen: its own title would say it twice
    foreach (QLabel *title, page->findChildren<QLabel *>("pageTitle")) {
        title->setVisible(false);
    }
    tabs_->addTab(page, tabText);
}

int TabbedPage::indexOf(QWidget *page) const
{
    return tabs_->indexOf(page);
}
