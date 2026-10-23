#ifndef FONTS_H
#define FONTS_H

#include <QString>

// The board has no Korean font of its own (the Hangul input needs one), so one is shipped in the fonts folder (any .ttf, .otf or .ttc). Every font
// found there is registered, and the first family that covers Korean becomes the application font, so Latin text and
// digits use it too.
class Fonts
{
public:
    // Returns the family chosen, or an empty string when the folder has no usable font (the system default stays).
    static QString install(const QString &dir, int pointSize);
};

#endif
