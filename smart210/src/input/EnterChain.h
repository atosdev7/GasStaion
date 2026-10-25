#ifndef ENTERCHAIN_H
#define ENTERCHAIN_H

#include <QList>
#include <QObject>
#include <QPointer>

class QEvent;
class QWidget;

// Keyboard-only entry of a form: Enter in a field moves on to the next one, and Enter on the button at the end presses it.
//
// The widgets are added in the order of the form. Enter in a line edit, a combo box (not while its list is open) or a check box moves the focus to
// the next widget that is shown, enabled and takes focus, so a field that is switched off (the amount of an own-use sale, a customer that is
// fixed) is jumped over. Enter on a push button presses it (Space presses it anyway). A widget added as `manual` keeps its own Enter (for
// example "add the item"): the chain only knows where it stands, so the Enter before it moves to it. After the last widget Enter does nothing.
//
// `jumpFrom(widget, target)` makes the next Enter at `widget` go to `target` instead; any other key typed in `widget` takes the jump back. The
// ticket sale uses it: right after an item was added, one more Enter at the spec means "no more items" and goes on to the receipt.
class EnterChain : public QObject
{
    Q_OBJECT

public:
    explicit EnterChain(QObject *parent = 0);

    void add(QWidget *widget, bool manual = false);

    // The first widget of the chain that can take focus now, or 0.
    QWidget *first() const;
    // The widget after `from` that can take focus now, or 0.
    QWidget *next(QWidget *from) const;

    void focusFirst();
    void focusWidget(QWidget *widget);
    void jumpFrom(QWidget *widget, QWidget *target); // target 0 takes the jump back

protected:
    bool eventFilter(QObject *watched, QEvent *event);

private:
    struct Entry {
        QPointer<QWidget> widget;
        bool manual;
    };

    static bool usable(QWidget *widget);
    int indexOf(QObject *widget) const;

    QList<Entry> entries_;
    QList<QPointer<QWidget> > jumpFrom_;
    QList<QPointer<QWidget> > jumpTo_;
};

#endif
