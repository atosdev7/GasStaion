#ifndef APPSHELL_H
#define APPSHELL_H

#include <QMap>
#include <QWidget>

class QAbstractButton;
class QButtonGroup;
class QLabel;
class QPushButton;
class QStackedWidget;

namespace Ui {
class AppShell;
class ShellFooter;
class ShellHeader;
class SideBar;
}

// The frame of every screen a logged-in cashier uses: a header on top (the company's logo and name from the Qt resources, the cashier and the
// time), the sidebar on the left (one button with an icon for each part of the program), the footer at the bottom (the status of the
// terminal) and the content between them. The logo and the company name are in the resources (brand/logo.png, brand/company.txt) so that
// they can be changed without touching the code. The layouts are the forms ui/AppShell.ui, SideBar.ui, ShellHeader.ui and ShellFooter.ui
// (Qt Designer); the colours and fonts are in resources/theme.qss.

// The sidebar: the parts of the program. Keys: sales, supplies, cards, dispensers, debt, review, settings (the buttons navSales, navSupplies ...).
class SideBar : public QWidget
{
    Q_OBJECT

public:
    explicit SideBar(QWidget *parent = 0);
    ~SideBar();

    void setCurrent(const QString &key); // highlights the part that is open
    static QStringList keys();

signals:
    void pageRequested(const QString &key);

private slots:
    void clicked(QAbstractButton *button);

private:
    Ui::SideBar *ui_;
    QButtonGroup *group_;
    QMap<QString, QPushButton *> buttons_;
};

class ShellHeader : public QWidget
{
    Q_OBJECT

public:
    explicit ShellHeader(QWidget *parent = 0);
    ~ShellHeader();

    void setCashier(const QString &name);
    void setClock(const QString &text);
    static QString companyName(); // from the resources

private:
    Ui::ShellHeader *ui_;
};

class ShellFooter : public QWidget
{
    Q_OBJECT

public:
    explicit ShellFooter(QWidget *parent = 0);
    ~ShellFooter();

    void setSync(const QString &text, bool problem);
    void setBoards(bool linked, int online, int total, bool enabled);
    void setScanner(bool supported, bool connected);
    void setWaiting(int records);
    void setInputMode(bool enabled, bool hangul); // the Hangul input method: shows "A" or the Hangul syllable (see HangulInput); hidden when it is off
    void setClockWarning(const QString &text); // empty: no warning

private:
    Ui::ShellFooter *ui_;
};

class AppShell : public QWidget
{
    Q_OBJECT

public:
    explicit AppShell(QWidget *parent = 0);
    ~AppShell();

    ShellHeader *header() const { return header_; }
    SideBar *sidebar() const { return sidebar_; }
    ShellFooter *footer() const { return footer_; }
    QStackedWidget *content() const { return content_; }

private:
    Ui::AppShell *ui_;
    ShellHeader *header_;
    SideBar *sidebar_;
    ShellFooter *footer_;
    QStackedWidget *content_;
};

#endif
