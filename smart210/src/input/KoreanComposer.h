#ifndef KOREANCOMPOSER_H
#define KOREANCOMPOSER_H

#include <QChar>
#include <QString>
#include <QStringList>

// Dubeolsik-style Korean syllable composer. Feed it compatibility jamo (U+3131..U+3163): it builds the precomposed syllables
// (U+AC00..U+D7A3). The jamo are fed by KoreanInput, which turns the keys into jamo with the table of KoreanKeyMap.h; the composer itself knows
// nothing about keys. Header only, C++03.
class KoreanComposer
{
public:
    KoreanComposer() : l_(-1), v_(-1), t_(0) {}

    // Feeds one jamo. Returns the text that has to be committed now; the syllable still being composed is preedit().
    QString input(QChar c)
    {
        QString out;

        if (V().contains(c)) {
            if (l_ < 0) {
                return QString(c);                           // a vowel on its own
            }
            if (v_ < 0) {
                v_ = V().indexOf(c);                         // initial + vowel
                return out;
            }
            if (t_ == 0) {
                const QChar m = combine(vowelPairs(), V()[v_], c);   // ㅗ+ㅏ -> ㅘ ...
                if (!m.isNull()) {
                    v_ = V().indexOf(m);
                    return out;
                }
                out = syllable();
                reset();
                out += c;

                return out;
            }
            // the final consonant becomes the initial of the next syllable
            const QChar tc = T()[t_];
            QChar first;
            QChar second;
            if (split(finalPairs(), tc, &first, &second)) {  // ㄳ -> ㄱ | ㅅ
                t_ = T().indexOf(first);
                out = syllable();
                reset();
                l_ = L().indexOf(second);
            } else {
                t_ = 0;
                out = syllable();
                reset();
                l_ = L().indexOf(tc);
            }
            v_ = V().indexOf(c);

            return out;
        }

        const int li = L().indexOf(c);
        if (li < 0) {
            return flush() + c;                              // not a jamo
        }
        if (l_ < 0) {
            l_ = li;
            return out;
        }
        if (v_ < 0) {
            out = QString(L()[l_]);
            l_ = li;
            return out;
        }
        if (t_ == 0) {
            const int ti = T().indexOf(c);
            if (ti > 0) {
                t_ = ti;                                     // ㄸ ㅃ ㅉ cannot be final: they start the next syllable
                return out;
            }
            out = syllable();
            reset();
            l_ = li;

            return out;
        }
        const QChar m = combine(finalPairs(), T()[t_], c);   // ㄹ+ㄱ -> ㄺ ...
        if (!m.isNull()) {
            t_ = T().indexOf(m);
            return out;
        }
        out = syllable();
        reset();
        l_ = li;

        return out;
    }

    // Undoes one step of the syllable being composed. False when nothing is being composed.
    bool backspace()
    {
        if (l_ < 0) {
            return false;
        }
        QChar first;
        QChar second;
        if (t_ > 0) {
            if (split(finalPairs(), T()[t_], &first, &second)) {
                t_ = T().indexOf(first);
            } else {
                t_ = 0;
            }
        } else if (v_ >= 0) {
            if (split(vowelPairs(), V()[v_], &first, &second)) {
                v_ = V().indexOf(first);
            } else {
                v_ = -1;
            }
        } else {
            l_ = -1;
        }

        return true;
    }

    QString preedit() const { return syllable(); }
    bool composing() const { return l_ >= 0; }
    QString flush() { const QString s = syllable(); reset(); return s; }
    void clear() { reset(); }

private:
    int l_; // initial 0..18, -1 = none yet
    int v_; // vowel 0..20, -1 = none yet
    int t_; // final 0..27 (0 = none)

    void reset() { l_ = -1; v_ = -1; t_ = 0; }

    QString syllable() const
    {
        if (l_ < 0) {
            return QString();
        }
        if (v_ < 0) {
            return QString(L()[l_]);
        }

        return QString(QChar(0xAC00 + (l_ * 21 + v_) * 28 + t_));
    }

    static const QString &L()
    {
        static QString s = QString::fromUtf8("ㄱㄲㄴㄷㄸㄹㅁㅂㅃㅅㅆㅇㅈㅉㅊㅋㅌㅍㅎ");
        return s;
    }
    static const QString &V()
    {
        static QString s = QString::fromUtf8("ㅏㅐㅑㅒㅓㅔㅕㅖㅗㅘㅙㅚㅛㅜㅝㅞㅟㅠㅡㅢㅣ");
        return s;
    }
    static const QString &T()
    {
        static QString s = QString::fromUtf8(" ㄱㄲㄳㄴㄵㄶㄷㄹㄺㄻㄼㄽㄾㄿㅀㅁㅂㅄㅅㅆㅇㅈㅊㅋㅌㅍㅎ");
        return s;
    }
    static const QStringList &vowelPairs()
    {
        static QStringList p = QString::fromUtf8("ㅗㅏㅘ,ㅗㅐㅙ,ㅗㅣㅚ,ㅜㅓㅝ,ㅜㅔㅞ,ㅜㅣㅟ,ㅡㅣㅢ").split(',');
        return p;
    }
    static const QStringList &finalPairs()
    {
        static QStringList p = QString::fromUtf8("ㄱㅅㄳ,ㄴㅈㄵ,ㄴㅎㄶ,ㄹㄱㄺ,ㄹㅁㄻ,ㄹㅂㄼ,ㄹㅅㄽ,ㄹㅌㄾ,ㄹㅍㄿ,ㄹㅎㅀ,ㅂㅅㅄ").split(',');
        return p;
    }
    static QChar combine(const QStringList &table, QChar a, QChar b)
    {
        foreach (const QString &s, table) {
            if (s[0] == a && s[1] == b) {
                return s[2];
            }
        }

        return QChar();
    }
    static bool split(const QStringList &table, QChar c, QChar *a, QChar *b)
    {
        foreach (const QString &s, table) {
            if (s[2] == c) {
                *a = s[0];
                *b = s[1];

                return true;
            }
        }

        return false;
    }
};

#endif
