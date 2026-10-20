#ifndef THEME_H
#define THEME_H

#include <QString>

class QWidget;

// The look of the whole program is the style sheet resources/theme.qss (colours, fonts, spacing: edit it in Qt Creator), together with the
// .ui files of the screens (layouts). Font sizes are in pixels (not points) and follow three rules:
//   BodyPx  20   every text by default
//   SubPx   24   menu items, sub titles and buttons
//   TitlePx 28   page titles (a label with the object name "pageTitle") and the company name in the header
// A page that needs a title gives its label the object name "pageTitle" and a sub title "subTitle". The numbers in theme.qss are these three;
// BodyPx is also the size the Chinese font is loaded at (Fonts::install), so change it there too when the body size changes.
namespace Theme {

enum { BodyPx = 20, SubPx = 24, TitlePx = 28 };

// The style sheet of the program: set it on the main window (every page inside gets it). `file` is a theme.qss on disk to use instead of the
// built-in one (config.ini: themeFile); empty or unreadable: the built-in one.
QString styleSheet(const QString &file = QString());

// The state of a message (colour in theme.qss: QLabel[state="error"] ...): "error", "warning", "ok", "muted" or empty for the normal look.
void setState(QWidget *widget, const QString &state);

}

#endif
