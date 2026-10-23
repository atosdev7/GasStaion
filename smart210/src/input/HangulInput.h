#ifndef HANGULINPUT_H
#define HANGULINPUT_H

#include "HangulComposer.h"

#include <QList>
#include <QMap>
#include <QObject>

struct HangulKeyDef;

// Turns the key presses of a keyboard (USB or PS/2) into Hangul text for the widget that has the focus: an event filter on the application,
// no screen of its own. The mode starts as English (keys pass through untouched: barcodes, numbers, Latin text keeps working);
// the Hangul key or Ctrl+Space switches Hangul on and off, and the footer shows which is on (MainWindow connects hangulModeChanged). In Hangul
// mode the letter keys give jamo (HangulKeyMap.h, Dubeolsik) that HangulComposer builds into syllables; the syllable being composed is shown
// as the preedit of the field, Space, Enter, digits and the arrows commit it, Backspace takes the last jamo off it. Fields that hide what is
// typed (PIN, password) and widgets that take no text are never touched.
class HangulInput : public QObject
{
    Q_OBJECT

public:
    explicit HangulInput(QObject *parent = 0);

    bool hangulMode() const { return hangul_; }

public slots:
    void setHangulMode(bool on);

signals:
    void hangulModeChanged(bool on);

protected:
    bool eventFilter(QObject *object, QEvent *event);

private slots:
    void focusChanged();

private:
    void addKeys(const HangulKeyDef *keys, int count);
    static bool isModifierKey(int key);
    static bool focusAcceptsText();
    void send(const QString &commit);

    HangulComposer composer_;
    bool hangul_;
    bool sending_;                       // our own events: not filtered again
    QList<const HangulKeyDef *> keys_;
    QMap<int, int> keyIndex_;            // Qt::Key_A... -> index into keys_
};

#endif
