#include "InputDevices.h"

#include <QFile>
#include <QStringList>

namespace InputDevices {

QString findEvent(const QString &devicesText, const QString &exactName, const QString &physPattern, const QString &deviceDir)
{
    // devices are separated by an empty line
    const QStringList lines = devicesText.split('\n');
    QString name;
    QString phys;
    QString event;
    bool keyboard = false;

    for (int i = 0; i <= lines.size(); ++i) {
        const QString line = i < lines.size() ? lines.at(i).trimmed() : QString();

        if (line.isEmpty()) {
            // the end of one device: is it the one?
            if (!name.isEmpty() && name == exactName && keyboard && !event.isEmpty()
                && (physPattern.isEmpty() || phys.contains(physPattern))) {
                return deviceDir + "/" + event;
            }
            name.clear();
            phys.clear();
            event.clear();
            keyboard = false;
            continue;
        }

        if (line.startsWith("N: Name=")) {
            name = line.mid(8);
            if (name.startsWith('"') && name.endsWith('"') && name.size() >= 2) {
                name = name.mid(1, name.size() - 2);
            }
        } else if (line.startsWith("P: Phys=")) {
            phys = line.mid(8);
        } else if (line.startsWith("H: Handlers=")) {
            const QStringList handlers = line.mid(12).split(' ', QString::SkipEmptyParts);
            for (int h = 0; h < handlers.size(); ++h) {
                if (handlers.at(h) == "kbd") {
                    keyboard = true;
                } else if (handlers.at(h).startsWith("event") && handlers.at(h).mid(5).toInt() >= 0 && handlers.at(h).size() > 5) {
                    event = handlers.at(h);
                }
            }
        }
    }

    return QString();
}

QString findEventIn(const QString &devicesFile, const QString &exactName, const QString &physPattern, const QString &deviceDir)
{
    QFile file(devicesFile);
    if (!file.open(QIODevice::ReadOnly | QIODevice::Text)) {
        return QString();
    }

    return findEvent(QString::fromLocal8Bit(file.readAll()), exactName, physPattern, deviceDir);
}

}
