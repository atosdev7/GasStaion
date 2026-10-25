#include "EnterChain.h"

#include <QAbstractButton>
#include <QEvent>
#include <QKeyEvent>
#include <QLineEdit>
#include <QPushButton>
#include <QWidget>

EnterChain::EnterChain(QObject *parent)
    : QObject(parent)
{
}

void EnterChain::add(QWidget *widget, bool manual)
{
    Entry entry;
    entry.widget = widget;
    entry.manual = manual;
    entries_ << entry;
    widget->installEventFilter(this);
}

// Shown (it is not hidden itself or by a parent, the window does not have to be on screen), enabled and able to take focus.
bool EnterChain::usable(QWidget *widget)
{
    return widget && widget->isEnabled() && widget->focusPolicy() != Qt::NoFocus && widget->isVisibleTo(widget->window());
}

int EnterChain::indexOf(QObject *widget) const
{
    for (int i = 0; i < entries_.size(); ++i) {
        if (entries_.at(i).widget.data() == widget) {
            return i;
        }
    }

    return -1;
}

QWidget *EnterChain::first() const
{
    for (int i = 0; i < entries_.size(); ++i) {
        if (usable(entries_.at(i).widget)) {
            return entries_.at(i).widget;
        }
    }

    return 0;
}

QWidget *EnterChain::next(QWidget *from) const
{
    const int at = indexOf(from);
    for (int i = at + 1; at >= 0 && i < entries_.size(); ++i) {
        if (usable(entries_.at(i).widget)) {
            return entries_.at(i).widget;
        }
    }

    return 0;
}

void EnterChain::focusWidget(QWidget *widget)
{
    if (widget) {
        widget->setFocus(Qt::TabFocusReason); // a line edit selects its text, so what is typed replaces it
    }
}

void EnterChain::focusFirst()
{
    focusWidget(first());
}

void EnterChain::jumpFrom(QWidget *widget, QWidget *target)
{
    for (int i = jumpFrom_.size() - 1; i >= 0; --i) {
        if (jumpFrom_.at(i) == widget) {
            jumpFrom_.removeAt(i);
            jumpTo_.removeAt(i);
        }
    }
    if (target) {
        jumpFrom_ << QPointer<QWidget>(widget);
        jumpTo_ << QPointer<QWidget>(target);
    }
}

bool EnterChain::eventFilter(QObject *watched, QEvent *event)
{
    if (event->type() != QEvent::KeyPress) {
        return QObject::eventFilter(watched, event);
    }

    const int at = indexOf(watched);
    QWidget *widget = qobject_cast<QWidget *>(watched);
    if (at < 0 || !widget) {
        return QObject::eventFilter(watched, event);
    }

    const QKeyEvent *key = static_cast<QKeyEvent *>(event);
    const bool enter = (key->key() == Qt::Key_Return || key->key() == Qt::Key_Enter);
    if (!enter) {
        const int k = key->key();
        if (k != Qt::Key_Shift && k != Qt::Key_Control && k != Qt::Key_Alt && k != Qt::Key_Meta) {
            jumpFrom(widget, 0); // something else was typed: the Enter that was to jump is a plain Enter again
        }

        return false;
    }
    if (entries_.at(at).manual) {
        return false;
    }

    if (QPushButton *button = qobject_cast<QPushButton *>(widget)) {
        if (button->isEnabled()) {
            button->click();
        }

        return true;
    }

    QWidget *target = 0;
    for (int i = 0; i < jumpFrom_.size(); ++i) {
        if (jumpFrom_.at(i) == widget && usable(jumpTo_.at(i))) {
            target = jumpTo_.at(i);
        }
    }
    jumpFrom(widget, 0);
    if (!target) {
        target = next(widget);
    }
    focusWidget(target);

    return true;
}
