#ifndef HANGULKEYMAP_H
#define HANGULKEYMAP_H

// The layout of the Hangul input: which letter key gives which jamo (Dubeolsik). Edit this table to change the layout; the composer
// (HangulComposer) only sees jamo. Include it only from HangulInput.cpp.
struct HangulKeyDef {
    const char *label;   // the letter key (lower case)
    const char *normal;  // the jamo
    const char *shifted; // the jamo with Shift ("" = the same as normal)
};

static const HangulKeyDef hangulRow1[] = {
    {"q", "ㅂ", "ㅃ"}, {"w", "ㅈ", "ㅉ"}, {"e", "ㄷ", "ㄸ"}, {"r", "ㄱ", "ㄲ"}, {"t", "ㅅ", "ㅆ"},
    {"y", "ㅛ", ""}, {"u", "ㅕ", ""}, {"i", "ㅑ", ""}, {"o", "ㅐ", "ㅒ"}, {"p", "ㅔ", "ㅖ"},
};

static const HangulKeyDef hangulRow2[] = {
    {"a", "ㅁ", ""}, {"s", "ㄴ", ""}, {"d", "ㅇ", ""}, {"f", "ㄹ", ""}, {"g", "ㅎ", ""},
    {"h", "ㅗ", ""}, {"j", "ㅓ", ""}, {"k", "ㅏ", ""}, {"l", "ㅣ", ""},
};

static const HangulKeyDef hangulRow3[] = {
    {"z", "ㅋ", ""}, {"x", "ㅌ", ""}, {"c", "ㅊ", ""}, {"v", "ㅍ", ""},
    {"b", "ㅠ", ""}, {"n", "ㅜ", ""}, {"m", "ㅡ", ""},
};

#define HANGUL_KEY_COUNT(a) (int(sizeof(a) / sizeof((a)[0])))

#endif
