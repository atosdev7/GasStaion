#ifndef TABBEDPAGE_H
#define TABBEDPAGE_H

#include <QWidget>

class QTabWidget;

namespace Ui {
class TabbedPage;
}

// A part of the program made of several screens: the page title (28 px) and a tab for each screen. The screens' own titles are hidden,
// the tab says what they are.
class TabbedPage : public QWidget
{
    Q_OBJECT

public:
    explicit TabbedPage(const QString &title, QWidget *parent = 0);
    ~TabbedPage();

    void addPage(QWidget *page, const QString &tabText);
    QTabWidget *tabs() const { return tabs_; }
    int indexOf(QWidget *page) const;

private:
    Ui::TabbedPage *ui_;
    QTabWidget *tabs_;
};

#endif
