#include "Theme.h"

#include <QFile>
#include <QStyle>
#include <QWidget>
#include <QTextStream>

namespace Theme {

QString readFile(const QString &path)
{
    QFile file(path);
    if (!file.open(QIODevice::ReadOnly | QIODevice::Text)) {
        return QString();
    }
    QTextStream in(&file);
    in.setCodec("UTF-8");

    return in.readAll();
}

// The text of resources/theme.qss, or the file the config names when it can be read (to try a look without a build).
QString styleSheet(const QString &file)
{
    if (!file.isEmpty()) {
        const QString text = readFile(file);
        if (!text.isEmpty()) {
            return text;
        }
    }

    return readFile(":/theme/theme.qss");
}

// The state of a message: a style sheet rule that uses a property is applied again only after the style has polished the widget again.
void setState(QWidget *widget, const QString &state)
{
    if (widget->property("state").toString() == state) {
        return;
    }
    widget->setProperty("state", state);
    widget->style()->unpolish(widget);
    widget->style()->polish(widget);
    widget->update();
}

}
