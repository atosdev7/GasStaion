#include "MachineConfig.h"

#include "Decimal.h"
#include "Settings.h"
#include "SupplyQueue.h"

#include <QDateTime>
#include <QDir>
#include <QFile>
#include <QFileInfo>
#include <QMap>
#include <QSqlQuery>
#include <QVariant>

using namespace BoardProtocol;

// A constant used by reference (QCOMPARE does) needs a definition: the old compiler of the board links only with it.
const int MachineConfig::NozzleCount;

MachineConfig::MachineConfig(QSqlDatabase db)
    : db_(db)
{
}

QList<MachineConfig::Nozzle> MachineConfig::nozzles() const
{
    QMap<int, Nozzle> known;

    QSqlQuery query(db_);
    query.exec("SELECT n.id, n.number, n.fuel_type, COALESCE(t.name, ''), c.pulses_per_liter, n.tank_id, n.mode, n.density, COALESCE(n.seen_at, ''), COALESCE(n.name, '') "
               "FROM nozzles n LEFT JOIN tanks t ON t.id = n.tank_id LEFT JOIN nozzle_config c ON c.nozzle_id = n.id "
               "WHERE n.deleted = 0 ORDER BY n.number, n.id");
    while (query.next()) {
        Nozzle nozzle;
        nozzle.id = query.value(0).toInt();
        nozzle.number = query.value(1).toInt();
        nozzle.fuelType = query.value(2).toString();
        nozzle.tank = query.value(3).toString();
        nozzle.pulsesPerLiter = query.value(4).isNull() ? -1 : query.value(4).toLongLong();
        nozzle.tankId = query.value(5).toInt();
        nozzle.mode = query.value(6).toInt();
        nozzle.density = query.value(7).isNull() ? -1 : query.value(7).toLongLong();
        nozzle.seenAt = query.value(8).toString();
        nozzle.name = query.value(9).toString();
        known.insert(nozzle.number, nozzle);
    }

    return known.values(); // by number
}

QString MachineConfig::Nozzle::label() const
{
    return name.isEmpty() ? QObject::tr("Nozzle %1").arg(number) : name;
}

MachineConfig::Nozzle MachineConfig::nozzle(int number) const
{
    foreach (const Nozzle &nozzle, nozzles()) {
        if (nozzle.number == number) {
            return nozzle;
        }
    }

    return Nozzle();
}

bool MachineConfig::addNozzle(int number, const QString &fuelType, int tankId, int userId, QString *why, const QString &name)
{
    QString reason;
    bool known = false;
    foreach (const Fuel &fuel, fuels()) {
        known = known || fuel.code == fuelType;
    }
    if (number < 1 || number > NozzleCount) {
        reason = QObject::tr("The nozzle number is from 1 to %1.").arg(NozzleCount);
    } else if (isConfigured(number)) {
        reason = QObject::tr("Nozzle %1 is added already.").arg(number);
    } else if (!known) {
        reason = QObject::tr("Choose the fuel type of the nozzle.");
    } else if (name.trimmed().size() > NameLength) {
        reason = QObject::tr("The name of a nozzle is up to %1 characters.").arg(NameLength);
    }

    int tank = 0;
    if (reason.isEmpty() && tankId > 0) {
        foreach (const Tank &t, tanks(fuelType)) {
            if (t.id == tankId) {
                tank = tankId;
            }
        }
        if (tank == 0) {
            reason = QObject::tr("That tank does not hold this fuel type.");
        }
    }
    if (reason.isEmpty() && tank == 0) {
        tank = tankFor(fuelType, rememberedTank(number)); // the tank this address had before, or the only tank of the fuel type
    }
    if (reason.isEmpty() && tank == 0) {
        reason = QObject::tr("A nozzle must be connected to a tank: choose the tank of the nozzle (there is no tank for this fuel type to take).");
    }

    if (!reason.isEmpty()) {
        if (why) {
            *why = reason;
        }

        return false;
    }

    // the config starts as auto with the density of the fuel type (from the web app); the pulses per litre are typed on Machine Config, and the
    // config counts (seen()) once it was sent to the board and verified, or read from it
    QVariant density(QVariant::Int);
    foreach (const Fuel &fuel, fuels()) {
        if (fuel.code == fuelType && fuel.density > 0) {
            density = int(fuel.density);
        }
    }

    QSqlQuery insert(db_);
    insert.prepare("INSERT INTO nozzles (number, tank_id, fuel_type, board_address, mode, density, seen_at, synced, deleted, name) VALUES (?, ?, ?, ?, 0, ?, NULL, 0, 0, ?)");
    insert.addBindValue(number);
    insert.addBindValue(tank);
    insert.addBindValue(fuelType);
    insert.addBindValue(number);
    insert.addBindValue(density);
    insert.addBindValue(name.trimmed().isEmpty() ? QVariant(QVariant::String) : QVariant(name.trimmed()));
    if (!insert.exec()) {
        if (why) {
            *why = QObject::tr("The nozzle could not be saved.");
        }

        return false;
    }
    if (tank > 0) {
        Settings::setDeviceValue("nozzle_tank_" + QString::number(number), QString::number(tank));
    }
    logChange(QString("nozzle:%1").arg(number), QString(), QString("added %1").arg(fuelType), userId);

    return true;
}

bool MachineConfig::setNozzleName(int number, const QString &name, int userId, QString *why)
{
    const Nozzle current = nozzle(number);
    const QString wanted = name.trimmed();
    QString reason;
    if (!current.exists()) {
        reason = QObject::tr("Nozzle %1 was not added.").arg(number);
    } else if (wanted.size() > NameLength) {
        reason = QObject::tr("The name of a nozzle is up to %1 characters.").arg(NameLength);
    }

    if (reason.isEmpty()) {
        // (a nozzle without a tank never went up: there is nothing to tell the web app yet)
        QSqlQuery update(db_);
        update.prepare("UPDATE nozzles SET name = ?, synced = CASE WHEN tank_id > 0 THEN 0 ELSE synced END WHERE id = ?");
        update.addBindValue(wanted.isEmpty() ? QVariant(QVariant::String) : QVariant(wanted));
        update.addBindValue(current.id);
        if (!update.exec()) {
            reason = QObject::tr("The name could not be saved.");
        }
    }
    if (!reason.isEmpty()) {
        if (why) {
            *why = reason;
        }

        return false;
    }
    if (wanted != current.name) {
        logChange(QString("nozzle:%1").arg(number), QString("name %1").arg(current.name.isEmpty() ? QString("-") : current.name), QString("name %1").arg(wanted.isEmpty() ? QString("-") : wanted), userId);
    }

    return true;
}

bool MachineConfig::removeNozzle(int number, int userId, QString *why)
{
    const Nozzle current = nozzle(number);
    if (!current.exists()) {
        if (why) {
            *why = QObject::tr("Nozzle %1 was not added.").arg(number);
        }

        return false;
    }
    const QString blocker = removeBlocker(number);
    if (!blocker.isEmpty()) {
        if (why) {
            *why = blocker;
        }

        return false;
    }

    QSqlQuery remove(db_);
    // (a nozzle without a tank never went up: there is nothing to tell the web app)
    remove.prepare("UPDATE nozzles SET deleted = 1, synced = CASE WHEN tank_id > 0 THEN 0 ELSE 1 END WHERE id = ?");
    remove.addBindValue(current.id);
    QSqlQuery config(db_);
    config.prepare("DELETE FROM nozzle_config WHERE nozzle_id = ?");
    config.addBindValue(current.id);
    if (!remove.exec() || !config.exec()) {
        if (why) {
            *why = QObject::tr("The nozzle could not be removed.");
        }

        return false;
    }
    logChange(QString("nozzle:%1").arg(number), QString("added %1").arg(current.fuelType), QString("removed"), userId);

    return true;
}

QString MachineConfig::removeBlocker(int number) const
{
    if (SupplyQueue::session(db_, number).exists) {
        return QObject::tr("A fill is open on nozzle %1. Finish or clear it first.").arg(number);
    }
    if (!SupplyQueue::entries(db_, number).isEmpty()) {
        return QObject::tr("Tickets are queued on nozzle %1. Remove them from its queue first.").arg(number);
    }

    return QString();
}

QString MachineConfig::configBlocker(int number) const
{
    if (SupplyQueue::session(db_, number).exists) {
        return QObject::tr("A fill is open on nozzle %1. Its config can be changed when the fill is over.").arg(number);
    }
    if (!SupplyQueue::entries(db_, number).isEmpty()) {
        return QObject::tr("Tickets are queued on nozzle %1. Remove them from its queue before changing its config.").arg(number);
    }

    return QString();
}

QList<MachineConfig::Fuel> MachineConfig::fuels() const
{
    QList<Fuel> list;

    QSqlQuery query(db_);
    query.exec("SELECT fuel_type, name, density FROM fuel_types ORDER BY fuel_type");
    while (query.next()) {
        Fuel fuel;
        fuel.code = query.value(0).toString();
        fuel.name = query.value(1).toString();
        fuel.density = query.value(2).isNull() ? -1 : query.value(2).toLongLong();
        list.append(fuel);
    }

    return list;
}

QList<MachineConfig::Tank> MachineConfig::tanks(const QString &fuelType) const
{
    QList<Tank> list;

    QSqlQuery query(db_);
    query.exec("SELECT id, name, fuel_type FROM tanks ORDER BY name, id");
    while (query.next()) {
        Tank tank;
        tank.id = query.value(0).toInt();
        tank.name = query.value(1).toString();
        tank.fuelType = query.value(2).toString();
        if (fuelType.isEmpty() || tank.fuelType == fuelType) {
            list.append(tank);
        }
    }

    return list;
}

// The tank the nozzle keeps: its own when it holds this fuel type, else the only tank that does, else none (0).
int MachineConfig::tankFor(const QString &fuelType, int currentTankId) const
{
    if (fuelType.isEmpty()) {
        return 0; // a board that has no fuel type yet holds no tank
    }

    const QList<Tank> holding = tanks(fuelType);
    for (int i = 0; i < holding.size(); ++i) {
        if (holding.at(i).id == currentTankId) {
            return currentTankId;
        }
    }

    return holding.size() == 1 ? holding.at(0).id : 0;
}

bool MachineConfig::storeBoardConfig(int number, const BoardConfig &config)
{
    if (number < 1 || number > NozzleCount || !isConfigured(number)) {
        return false; // only a nozzle that was added has a config
    }

    const QString fuelText = config.fuelType.isNull() ? QString("") : config.fuelType; // a null string would bind as NULL
    const QVariant density = config.density > 0 ? QVariant(int(config.density)) : QVariant(QVariant::Int);
    const QString now = QDateTime::currentDateTime().toString("yyyy-MM-dd HH:mm:ss");

    QSqlQuery find(db_);
    find.prepare("SELECT id, tank_id, fuel_type FROM nozzles WHERE number = ? AND deleted = 0");
    find.addBindValue(number);
    find.exec();

    int id = 0;
    QSqlQuery save(db_);
    if (find.next()) { // always: the nozzle was added (checked above)
        id = find.value(0).toInt();
        const int tankId = tankFor(config.fuelType, find.value(1).toInt() > 0 ? find.value(1).toInt() : rememberedTank(number));
        const QString fuel = fuelText;
        // only the tank and the fuel type go to the web app: a change of the rest does not need an upload
        const bool forWeb = tankId != find.value(1).toInt() || fuel != find.value(2).toString();
        save.prepare(QString("UPDATE nozzles SET tank_id = ?, fuel_type = ?, board_address = ?, mode = ?, density = ?, seen_at = ?%1 WHERE id = ?")
                         .arg(forWeb ? ", synced = 0" : ""));
        save.addBindValue(tankId);
        save.addBindValue(fuel);
        save.addBindValue(number);
        save.addBindValue(config.mode);
        save.addBindValue(density);
        save.addBindValue(now);
        save.addBindValue(id);
    } else {
        return false;
    }
    if (!save.exec()) {
        return false;
    }

    QSqlQuery pulses(db_);
    if (config.pulsesX100 > 0) {
        pulses.prepare("INSERT OR REPLACE INTO nozzle_config (nozzle_id, pulses_per_liter) VALUES (?, ?)");
        pulses.addBindValue(id);
        pulses.addBindValue(qlonglong(config.pulsesX100));
    } else {
        pulses.prepare("DELETE FROM nozzle_config WHERE nozzle_id = ?");
        pulses.addBindValue(id);
    }

    return pulses.exec();
}

bool MachineConfig::saveConfig(int number, const BoardConfig &config, int userId)
{
    const QString was = describe(nozzle(number));
    if (!storeBoardConfig(number, config)) {
        return false;
    }
    const QString now = describe(nozzle(number));
    if (was != now) {
        logChange(QString("nozzle:%1").arg(number), was, now, userId);
    }

    return true;
}

bool MachineConfig::setTank(int number, int tankId)
{
    const Nozzle current = nozzle(number);
    if (!current.seen()) {
        return false;
    }

    QSqlQuery tank(db_);
    tank.prepare("SELECT fuel_type FROM tanks WHERE id = ?");
    tank.addBindValue(tankId);
    if (!tank.exec() || !tank.next() || tank.value(0).toString() != current.fuelType) {
        return false; // no such tank, or it holds another fuel
    }
    if (current.tankId == tankId) {
        return true;
    }

    QSqlQuery save(db_);
    save.prepare("UPDATE nozzles SET tank_id = ?, synced = 0 WHERE id = ?");
    save.addBindValue(tankId);
    save.addBindValue(current.id);
    if (!save.exec()) {
        return false;
    }
    Settings::setDeviceValue("nozzle_tank_" + QString::number(number), QString::number(tankId));

    return true;
}

// The tank the admin gave this nozzle, kept apart from the SD card (0 when none): a new card gets the same tanks back.
int MachineConfig::rememberedTank(int number)
{
    return Settings::deviceValue("nozzle_tank_" + QString::number(number)).toInt();
}

QString MachineConfig::describe(const Nozzle &nozzle)
{
    if (!nozzle.seen()) {
        return QString();
    }

    return QString("%1 %2 %3 %4%5")
        .arg(nozzle.mode == ModeManual ? "manual" : "auto")
        .arg(nozzle.fuelType.isEmpty() ? QString("-") : nozzle.fuelType)
        .arg(nozzle.density < 0 ? QString("-") : Decimal::format(nozzle.density, 4))
        .arg(nozzle.pulsesPerLiter < 0 ? QString("-") : Decimal::format(nozzle.pulsesPerLiter, 2))
        .arg(nozzle.tank.isEmpty() ? QString() : " " + nozzle.tank);
}

void MachineConfig::logChange(const QString &what, const QString &oldValue, const QString &newValue, int userId)
{
    if (logFile_.isEmpty()) {
        return;
    }

    QString user;
    if (userId > 0) {
        QSqlQuery query(db_);
        query.prepare("SELECT name FROM users WHERE id = ?");
        query.addBindValue(userId);
        if (query.exec() && query.next()) {
            user = query.value(0).toString();
        }
    }

    QDir().mkpath(QFileInfo(logFile_).absolutePath());
    QFile file(logFile_);
    if (!file.open(QIODevice::Append | QIODevice::Text)) {
        return;
    }
    // one line: DATE TIME: what: old -> new (cashier)
    QString line = QDateTime::currentDateTime().toString("yyyy-MM-dd HH:mm:ss") + ": " + what + ": " + (oldValue.isEmpty() ? QString("-") : oldValue)
        + " -> " + newValue;
    if (!user.isEmpty()) {
        line += " (" + user + ")";
    }
    file.write(line.toUtf8() + "\n");
}

QList<MachineConfig::Change> MachineConfig::recentChanges(int limit) const
{
    QList<Change> list;

    QFile file(logFile_);
    if (logFile_.isEmpty() || !file.open(QIODevice::ReadOnly | QIODevice::Text)) {
        return list;
    }
    const QStringList lines = QString::fromUtf8(file.readAll()).split('\n', QString::SkipEmptyParts);

    for (int i = lines.size() - 1; i >= 0 && list.size() < limit; --i) {
        QString line = lines.at(i).trimmed();
        Change change;
        change.userId = 0;

        // DATE TIME: what: old -> new (cashier)
        change.changedAt = line.left(19);
        line = line.mid(21);
        const int whatEnd = line.indexOf(": ");
        if (change.changedAt.size() < 19 || whatEnd < 0) {
            continue; // not one of ours
        }
        change.what = line.left(whatEnd);
        line = line.mid(whatEnd + 2);
        if (line.endsWith(')') && line.lastIndexOf(" (") > 0) {
            const int open = line.lastIndexOf(" (");
            change.userName = line.mid(open + 2, line.size() - open - 3);
            line = line.left(open);
        }
        const int arrow = line.indexOf(" -> ");
        if (arrow < 0) {
            continue;
        }
        change.oldValue = line.left(arrow) == "-" ? QString() : line.left(arrow);
        change.newValue = line.mid(arrow + 4);
        list.append(change);
    }

    return list;
}
