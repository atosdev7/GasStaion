#ifndef KOREANKEYMAP_H
#define KOREANKEYMAP_H

// The layout of the Korean input: which letter key gives which jamo (Dubeolsik). Edit this table to change the layout; the composer
// (KoreanComposer) only sees jamo. Include it only from KoreanInput.cpp.
struct KoreanKeyDef {
    const char *label;   // the letter key (lower case)
    const char *normal;  // the jamo
    const char *shifted; // the jamo with Shift ("" = the same as normal)
};

static const KoreanKeyDef koreanRow1[] = {
    {"q", "ㅂ", "ㅃ"}, {"w", "ㅈ", "ㅉ"}, {"e", "ㄷ", "ㄸ"}, {"r", "ㄱ", "ㄲ"}, {"t", "ㅅ", "ㅆ"},
    {"y", "ㅛ", ""}, {"u", "ㅕ", ""}, {"i", "ㅑ", ""}, {"o", "ㅐ", "ㅒ"}, {"p", "ㅔ", "ㅖ"},
};

static const KoreanKeyDef koreanRow2[] = {
    {"a", "ㅁ", ""}, {"s", "ㄴ", ""}, {"d", "ㅇ", ""}, {"f", "ㄹ", ""}, {"g", "ㅎ", ""},
    {"h", "ㅗ", ""}, {"j", "ㅓ", ""}, {"k", "ㅏ", ""}, {"l", "ㅣ", ""},
};

static const KoreanKeyDef koreanRow3[] = {
    {"z", "ㅋ", ""}, {"x", "ㅌ", ""}, {"c", "ㅊ", ""}, {"v", "ㅍ", ""},
    {"b", "ㅠ", ""}, {"n", "ㅜ", ""}, {"m", "ㅡ", ""},
};

#define KOREAN_KEY_COUNT(a) (int(sizeof(a) / sizeof((a)[0])))

#endif
