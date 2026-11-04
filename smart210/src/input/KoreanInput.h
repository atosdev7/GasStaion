#ifndef KOREANINPUT_H
#define KOREANINPUT_H

#include "KoreanComposer.h"

#include <QList>
#include <QMap>
#include <QObject>

struct KoreanKeyDef;

// Turns the key presses of a keyboard (USB or PS/2) into Hangul text for the widget that has the focus: an event filter on the application,
// no screen of its own. The mode starts as English (keys pass through untouched: barcodes, numbers, Latin text keeps working);
// the Hangul key or Ctrl+Space switches Hangul on and off, and the footer shows which is on (MainWindow connects hangulModeChanged). In Hangul
// mode the letter keys give jamo (KoreanKeyMap.h, Dubeolsik) that KoreanComposer builds into syllables; the syllable being composed is shown
// as the preedit of the field, Space, Enter, digits and the arrows commit it, Backspace takes the last jamo off it. Fields that hide what is
// typed (PIN, password) and widgets that take no text are never touched.
class KoreanInput : public QObject
{
    Q_OBJECT

public:
    explicit KoreanInput(QObject *parent = 0);

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
    void addKeys(const KoreanKeyDef *keys, int count);
    static bool isModifierKey(int key);
    static bool focusAcceptsText();
    void send(const QString &commit);

    KoreanComposer composer_;
    bool hangul_;
    bool sending_;                       // our own events: not filtered again
    QList<const KoreanKeyDef *> keys_;
    QMap<int, int> keyIndex_;            // Qt::Key_A... -> index into keys_
};

#endif
