#ifndef INPUTDEVICES_H
#define INPUTDEVICES_H

#include <QString>

// Finds an input device of the Linux board without udev: /proc/bus/input/devices lists them all with their name, their USB port
// (Phys) and the eventN node, and N is not the same from one start to the next, so a device is found by what it is.
namespace InputDevices {

// The path "/dev/input/eventN" of the keyboard-type device whose name is exactly `exactName` and, when given, whose Phys contains
// `physPattern`; empty when there is none. `devicesText` is the content of /proc/bus/input/devices.
QString findEvent(const QString &devicesText, const QString &exactName, const QString &physPattern = QString(),
                  const QString &deviceDir = QString("/dev/input"));

// The same, reading the file (the default is /proc/bus/input/devices); empty when it cannot be read.
QString findEventIn(const QString &devicesFile, const QString &exactName, const QString &physPattern = QString(),
                    const QString &deviceDir = QString("/dev/input"));

}

#endif
