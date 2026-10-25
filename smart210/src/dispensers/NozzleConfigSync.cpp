#include "NozzleConfigSync.h"

#include "Decimal.h"
#include "Settings.h"

using namespace BoardProtocol;

NozzleConfigSync::NozzleConfigSync(QSqlDatabase db, BoardBus *bus, QObject *parent)
    : QObject(parent), db_(db), config_(db), bus_(bus)
{
    for (int number = 1; number <= MachineConfig::NozzleCount; ++number) {
        bus_->addBoard(number);
    }
    syncBoards();

    connect(bus_, SIGNAL(boardOnlineChanged(int,bool)), this, SLOT(boardOnline(int,bool)));
    connect(bus_, SIGNAL(commandDone(int,int,QByteArray)), this, SLOT(commandDone(int,int,QByteArray)));
    connect(bus_, SIGNAL(commandFailed(int,int,int)), this, SLOT(commandFailed(int,int,int)));
    connect(bus_, SIGNAL(connectionChanged(bool)), this, SLOT(linkChanged(bool)));
}

// Only the nozzles that were added on Machine Config are polled: the others are switched off on the bus, and what a board there says is ignored.
void NozzleConfigSync::syncBoards()
{
    for (int number = 1; number <= MachineConfig::NozzleCount; ++number) {
        bus_->setBoardEnabled(number, config_.isConfigured(number));
        bus_->setBoardPolled(number, !(config_.isConfigured(number) && config_.nozzle(number).mode == ModeManual));
        if (!config_.isConfigured(number)) {
            reads_.remove(number);
            reading_.remove(number);
            updates_.remove(number);
            errors_.remove(number);
            if (differs(number)) {
                setDiffers(number, false);
            }
        }
    }
    emit changed();
}

bool NozzleConfigSync::differs(int number) const
{
    return Settings(db_).value(SettingKey::NozzlesNotOnBoard).split(',', QString::SkipEmptyParts).contains(QString::number(number));
}

void NozzleConfigSync::setDiffers(int number, bool differs)
{
    Settings settings(db_);
    QStringList numbers = settings.value(SettingKey::NozzlesNotOnBoard).split(',', QString::SkipEmptyParts);
    numbers.removeAll(QString::number(number));
    if (differs) {
        numbers << QString::number(number);
    }
    numbers.sort();
    if (numbers.isEmpty()) {
        settings.remove(SettingKey::NozzlesNotOnBoard); // (an empty text cannot be stored)
    } else {
        settings.setValue(SettingKey::NozzlesNotOnBoard, numbers.join(","));
    }
}

bool NozzleConfigSync::start(int port)
{
    bus_->setListenPort(port);

    return bus_->start();
}

bool NozzleConfigSync::restart(int port)
{
    bus_->stop();

    return start(port);
}

bool NozzleConfigSync::send(int number, const BoardConfig &config)
{
    if (!config.isValid() || !config_.isConfigured(number) || !bus_->canTalk(number) || updates_.contains(number) || reading_.contains(number)) {
        return false;
    }

    Update update;
    update.config = config;
    updates_.insert(number, update);
    errors_.remove(number);
    work();

    return true;
}

bool NozzleConfigSync::read(int number, int userId)
{
    if (!config_.isConfigured(number) || !bus_->canTalk(number) || updates_.contains(number) || reading_.contains(number)) {
        return false;
    }

    reading_.insert(number, userId);
    reads_ << number;
    errors_.remove(number);
    work();

    return true;
}

// A board that is free gets what waits for it. The bus holds one command per board, so a busy board is tried again when it answers.
void NozzleConfigSync::work()
{
    for (QHash<int, Update>::iterator it = updates_.begin(); it != updates_.end(); ++it) {
        if (!it.value().sent && bus_->setConfig(it.key(), it.value().config)) {
            it.value().sent = true;
        }
    }

    const QList<int> wanted = reads_.values();
    for (int i = 0; i < wanted.size(); ++i) {
        if (bus_->getConfig(wanted.at(i))) {
            reads_.remove(wanted.at(i));
        }
    }
}

void NozzleConfigSync::failUpdate(int number, const QString &text)
{
    updates_.remove(number);
    errors_.insert(number, text);
    emit updateFailed(number, text);
    syncBoards(); // the polling follows the mode that was saved, whether the board took it or not
}

void NozzleConfigSync::failRead(int number, const QString &text)
{
    reading_.remove(number);
    reads_.remove(number);
    errors_.insert(number, text);
    emit readFailed(number, text);
}

void NozzleConfigSync::boardOnline(int address, bool online)
{
    if (online) {
        errors_.remove(address);
    } else {
        if (updates_.contains(address)) {
            failUpdate(address, errorText(ErrNoAnswer));
        }
        if (reading_.contains(address)) {
            failRead(address, errorText(ErrNoAnswer));
        }
    }
    emit changed();
    work();
}

void NozzleConfigSync::commandDone(int address, int command, const QByteArray &data)
{
    const bool isRead = command == CmdGetConfig && reading_.contains(address);
    const bool isUpdate = command == CmdSetConfig && updates_.contains(address);
    if (!isRead && !isUpdate) {
        return; // nothing was asked of this board here: what it says is not kept
    }

    BoardConfig reported;
    if (!parseBoardConfig(data, &reported)) {
        if (isRead) {
            failRead(address, errorText(ErrBadLength));
        } else {
            failUpdate(address, errorText(ErrBadLength));
        }
        emit changed();
        work();

        return;
    }

    if (isRead) {
        const int userId = reading_.take(address);
        if (!reported.isValid()) {
            failRead(address, tr("The board holds no config yet. Press Update to send one."));
        } else if (config_.tankFor(reported.fuelType, config_.nozzle(address).tankId) == 0) {
            failRead(address, tr("The board holds fuel type %1, and no tank can be taken for it: a nozzle must be connected to a tank.").arg(reported.fuelType));
        } else {
            config_.saveConfig(address, reported, userId);
            errors_.remove(address);
            setDiffers(address, false); // this terminal holds what the board holds
            emit readDone(address);
            syncBoards(); // the board may be in another mode now
        }
        emit changed();
        work();

        return;
    }

    // the board answers a SET_CONFIG with what it stored: it is verified when that is what was sent
    const Update update = updates_.value(address);
    if (reported != update.config) {
        failUpdate(address, tr("The board did not keep the config as it was sent (it holds %1 %2 %3 %4).")
                                .arg(reported.mode == ModeManual ? tr("manual") : tr("auto"))
                                .arg(reported.fuelType.isEmpty() ? QString("-") : reported.fuelType)
                                .arg(Decimal::format(reported.density, 4))
                                .arg(Decimal::format(qlonglong(reported.pulsesX100), 2)));
        emit changed();
        work();

        return;
    }

    updates_.remove(address);
    errors_.remove(address);
    setDiffers(address, false); // verified
    emit updateDone(address);
    syncBoards();
    emit changed();
    work();
}

void NozzleConfigSync::commandFailed(int address, int command, int error)
{
    const QString text = errorText(error);
    if (command == CmdSetConfig && updates_.contains(address)) {
        failUpdate(address, text);
    } else if (command == CmdGetConfig && reading_.contains(address)) {
        failRead(address, text);
    } else {
        return;
    }
    emit changed();
    work();
}

void NozzleConfigSync::linkChanged(bool connected)
{
    if (!connected) {
        // nothing can be asked now; what waited for the boards fails (the boards come back one by one)
        const QList<int> updating = updates_.keys();
        for (int i = 0; i < updating.size(); ++i) {
            failUpdate(updating.at(i), errorText(ErrNoConnection));
        }
        const QList<int> reading = reading_.keys();
        for (int i = 0; i < reading.size(); ++i) {
            failRead(reading.at(i), errorText(ErrNoConnection));
        }
    }
    emit changed();
}
