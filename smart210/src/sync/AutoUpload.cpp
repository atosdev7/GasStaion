#include "AutoUpload.h"

#include "Checkout.h"

#include <QDate>
#include <QTime>

AutoUpload::Mode AutoUpload::modeOf(const QString &text)
{
    if (text == "close") {
        return CloseOfDay;
    }

    return text == "continuous" ? Continuous : Manual;
}

QString AutoUpload::textOf(Mode mode)
{
    switch (mode) {
    case CloseOfDay:
        return "close";
    case Continuous:
        return "continuous";
    case Manual:
        break;
    }

    return "manual";
}

QString AutoUpload::cutoff(Mode mode, const QDateTime &now)
{
    if (mode == CloseOfDay) {
        // the running business day began at 9 AM of the day that Checkout::businessDay names: everything before that is over
        const QDate day = QDate::fromString(Checkout::businessDay(now), "yyyy-MM-dd");

        return QDateTime(day, QTime(Checkout::startHour(), 0)).toString("yyyy-MM-dd HH:mm:ss");
    }
    if (mode == Continuous) {
        return now.addSecs(-GraceMinutes * 60).toString("yyyy-MM-dd HH:mm:ss");
    }

    return QString();
}
