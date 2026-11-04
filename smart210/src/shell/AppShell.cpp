#include "AppShell.h"

#include "Theme.h"
#include "ui_AppShell.h"
#include "ui_ShellFooter.h"
#include "ui_ShellHeader.h"
#include "ui_SideBar.h"

#include <QAbstractButton>
#include <QButtonGroup>
#include <QFile>
#include <QPixmap>
#include <QPushButton>
#include <QStackedWidget>
#include <QStringList>

namespace {

const int LogoHeight = 48;

// The keys of the sidebar in order; the button of a key is "nav" and the key with a capital first letter (navSales).
const char *const keyList[] = {"sales", "supplies", "remainders", "cards", "dispensers", "debt", "review", "settings"};
const int keyCount = int(sizeof(keyList) / sizeof(keyList[0]));

}

SideBar::SideBar(QWidget *parent)
    : QWidget(parent), ui_(new Ui::SideBar), group_(new QButtonGroup(this))
{
    setObjectName("shellSidebar");
    setAttribute(Qt::WA_StyledBackground, true);
    ui_->setupUi(this);

    group_->setExclusive(true);
    for (int i = 0; i < keyCount; ++i) {
        const QString key = keyList[i];
        QPushButton *button = findChild<QPushButton *>("nav" + key.left(1).toUpper() + key.mid(1));
        group_->addButton(button);
        buttons_.insert(key, button);
    }

    connect(group_, SIGNAL(buttonClicked(QAbstractButton*)), this, SLOT(clicked(QAbstractButton*)));
}

SideBar::~SideBar()
{
    delete ui_;
}

QStringList SideBar::keys()
{
    QStringList list;
    for (int i = 0; i < keyCount; ++i) {
        list << keyList[i];
    }

    return list;
}

void SideBar::setCurrent(const QString &key)
{
    QPushButton *button = buttons_.value(key);
    if (button) {
        button->setChecked(true);
    }
}

void SideBar::clicked(QAbstractButton *button)
{
    emit pageRequested(buttons_.key(qobject_cast<QPushButton *>(button)));
}

QString ShellHeader::companyName()
{
    QFile file(":/brand/company.txt");
    if (file.open(QIODevice::ReadOnly)) {
        const QString name = QString::fromUtf8(file.readLine()).trimmed();
        if (!name.isEmpty()) {
            return name;
        }
    }

    return QObject::tr("Gas Station");
}

ShellHeader::ShellHeader(QWidget *parent)
    : QWidget(parent), ui_(new Ui::ShellHeader)
{
    setObjectName("shellHeader");
    setAttribute(Qt::WA_StyledBackground, true);
    ui_->setupUi(this);

    ui_->logoLabel->setPixmap(QPixmap(":/brand/logo.png").scaledToHeight(LogoHeight, Qt::SmoothTransformation)); // the file can be replaced: its shape is kept
    ui_->companyName->setText(companyName());
}

ShellHeader::~ShellHeader()
{
    delete ui_;
}

void ShellHeader::setCashier(const QString &name)
{
    ui_->cashierLabel->setText(name);
}

void ShellHeader::setClock(const QString &text)
{
    ui_->headerClock->setText(text);
}

ShellFooter::ShellFooter(QWidget *parent)
    : QWidget(parent), ui_(new Ui::ShellFooter)
{
    setObjectName("shellFooter");
    setAttribute(Qt::WA_StyledBackground, true);
    ui_->setupUi(this);
}

ShellFooter::~ShellFooter()
{
    delete ui_;
}

void ShellFooter::setSync(const QString &text, bool problem)
{
    ui_->footerSync->setText(text);
    Theme::setState(ui_->footerSync, problem ? "error" : "");
}

void ShellFooter::setBoards(bool linked, int online, int total, bool enabled)
{
    ui_->footerBoards->setVisible(enabled);
    ui_->footerBoards->setText(linked ? tr("Dispensers: %1 of %2 online").arg(online).arg(total) : tr("Dispensers: no converter"));
    Theme::setState(ui_->footerBoards, linked && online > 0 ? "" : "warning");
}

void ShellFooter::setScanner(bool supported, bool connected)
{
    ui_->footerScanner->setVisible(supported);
    ui_->footerScanner->setText(connected ? tr("Scanner: connected") : tr("Scanner: not found"));
    Theme::setState(ui_->footerScanner, connected ? "" : "warning");
}

void ShellFooter::setInputMode(bool enabled, bool korean)
{
    ui_->footerInput->setVisible(enabled);
    ui_->footerInput->setText(korean ? QString::fromUtf8("가") : QString("A"));
}

void ShellFooter::setWaiting(int records)
{
    ui_->footerWaiting->setText(records > 0 ? tr("Waiting for upload: %1").arg(records) : tr("Everything is uploaded"));
}

void ShellFooter::setClockWarning(const QString &text)
{
    ui_->footerClockWarning->setText(text);
    ui_->footerClockWarning->setVisible(!text.isEmpty());
}

AppShell::AppShell(QWidget *parent)
    : QWidget(parent), ui_(new Ui::AppShell)
{
    ui_->setupUi(this);

    header_ = ui_->shellHeader;
    sidebar_ = ui_->shellSidebar;
    footer_ = ui_->shellFooter;
    content_ = ui_->shellContent;
    content_->setAttribute(Qt::WA_StyledBackground, true);
}

AppShell::~AppShell()
{
    delete ui_;
}
