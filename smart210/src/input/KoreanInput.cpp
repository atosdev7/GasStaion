#include "KoreanInput.h"

#include "KoreanKeyMap.h"

#include <QGuiApplication>
#include <QInputMethodEvent>
#include <QInputMethodQueryEvent>
#include <QKeyEvent>

KoreanInput::KoreanInput(QObject *parent)
    : QObject(parent), korean_(false), sending_(false)
{
    addKeys(koreanRow1, KOREAN_KEY_COUNT(koreanRow1));
    addKeys(koreanRow2, KOREAN_KEY_COUNT(koreanRow2));
    addKeys(koreanRow3, KOREAN_KEY_COUNT(koreanRow3));

    qApp->installEventFilter(this);
    connect(qApp, SIGNAL(focusObjectChanged(QObject*)), this, SLOT(focusChanged()));
}

void KoreanInput::addKeys(const KoreanKeyDef *keys, int count)
{
    for (int i = 0; i < count; ++i) {
        const char letter = keys[i].label[0];   // "q" is Qt::Key_Q
        if (letter >= 'a' && letter <= 'z') {
            keyIndex_.insert(Qt::Key_A + (letter - 'a'), keys_.size());
            keys_.append(&keys[i]);
        }
    }
}

void KoreanInput::setKoreanMode(bool on)
{
    if (on == korean_) {
        return;
    }
    if (!on) {
        send(composer_.flush());   // the syllable being composed is kept as typed
    }
    korean_ = on;
    emit koreanModeChanged(on);
}

// The field that lost the focus commits or drops its own preedit; the composer starts again.
void KoreanInput::focusChanged()
{
    composer_.clear();
}

bool KoreanInput::isModifierKey(int key)
{
    return (key >= Qt::Key_Shift && key <= Qt::Key_ScrollLock) || key == Qt::Key_AltGr || key == Qt::Key_Super_L || key == Qt::Key_Super_R;
}

// The Right Alt key (not the left one). Qt reports it as Key_AltGr on a layout that has AltGr, and as Key_Alt on the others; the left and the right Alt are told apart
// by the native codes of the platform: Windows (virtual key VK_RMENU 0xA5, scan code 0x38 with the extended flag 0x100 or 0xE000), X11 (keysym Alt_R 0xFFEA or
// ISO_Level3_Shift 0xFE03, key code 108) and the evdev keyboard of the board (key code 100, KEY_RIGHTALT).
bool KoreanInput::isRightAlt(const QKeyEvent *key)
{
    if (key->key() == Qt::Key_AltGr) {
        return true;
    }
    if (key->key() != Qt::Key_Alt) {
        return false;
    }
    const quint32 virtualKey = key->nativeVirtualKey();
    const quint32 scanCode = key->nativeScanCode();

    return virtualKey == 0xA5 || virtualKey == 0xFFEA || virtualKey == 0xFE03 || scanCode == 0x138 || scanCode == 0xE038 || scanCode == 108 || scanCode == 100;
}

// The focused object takes text, and does not hide it (a PIN or a password is never typed in Korean).
bool KoreanInput::focusAcceptsText()
{
    QObject *focus = QGuiApplication::focusObject();
    if (!focus) {
        return false;
    }
    QInputMethodQueryEvent query(Qt::ImEnabled | Qt::ImHints);
    QGuiApplication::sendEvent(focus, &query);

    return query.value(Qt::ImEnabled).toBool() && !(query.value(Qt::ImHints).toInt() & Qt::ImhHiddenText);
}

bool KoreanInput::eventFilter(QObject *object, QEvent *event)
{
    if (sending_ || (event->type() != QEvent::KeyPress && event->type() != QEvent::KeyRelease) || object != QGuiApplication::focusObject()) {
        return false;
    }

    QKeyEvent *key = static_cast<QKeyEvent *>(event);
    const bool press = event->type() == QEvent::KeyPress;
    const int code = key->key();
    const Qt::KeyboardModifiers modifiers = key->modifiers();

    // the Right Alt key or Ctrl+Space switches between Korean and English
    if (isRightAlt(key) || (code == Qt::Key_Space && (modifiers & Qt::ControlModifier))) {
        if (press && !key->isAutoRepeat()) {
            setKoreanMode(!korean_);
        }

        return true;
    }

    if (!korean_ || isModifierKey(code) || !focusAcceptsText()) {
        return false;
    }

    // a shortcut with Ctrl, Alt or Meta: the syllable is committed, the key goes on
    if (modifiers & (Qt::ControlModifier | Qt::AltModifier | Qt::MetaModifier)) {
        if (press && composer_.composing()) {
            send(composer_.flush());
        }

        return false;
    }

    // a letter key gives its jamo
    QMap<int, int>::const_iterator found = keyIndex_.constFind(code);
    if (found != keyIndex_.constEnd()) {
        if (press) {
            const KoreanKeyDef *def = keys_.at(found.value());
            const char *text = ((modifiers & Qt::ShiftModifier) && *def->shifted) ? def->shifted : def->normal;
            const QString jamo = QString::fromUtf8(text);
            if (!jamo.isEmpty()) {
                send(composer_.input(jamo[0]));
            }
        }

        return true;   // the press and the release are both taken
    }

    // Backspace takes the last jamo off the syllable being composed
    if (code == Qt::Key_Backspace && press && composer_.backspace()) {
        send(QString());

        return true;
    }

    // anything else (digits, Space, Enter, the arrows): the syllable is committed, the key goes on
    if (press && composer_.composing()) {
        send(composer_.flush());
    }

    return false;
}

// Commits what is finished and shows the syllable being composed as the preedit of the focused field.
void KoreanInput::send(const QString &commit)
{
    QObject *focus = QGuiApplication::focusObject();
    if (!focus) {
        return;
    }

    sending_ = true;
    const QString preedit = composer_.preedit();
    QList<QInputMethodEvent::Attribute> attributes;
    attributes << QInputMethodEvent::Attribute(QInputMethodEvent::Cursor, preedit.length(), 1, QVariant());
    QInputMethodEvent event(preedit, attributes);
    event.setCommitString(commit);
    QGuiApplication::sendEvent(focus, &event);
    sending_ = false;
}
