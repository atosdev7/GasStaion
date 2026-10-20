#include "NozzleConfigSync.h"

using namespace BoardProtocol;

NozzleConfigSync::NozzleConfigSync(QSqlDatabase db, BoardBus *bus, QObject *parent)
    : QObject(parent), config_(db), bus_(bus)
{
    for (int number = 1; number <= MachineConfig::NozzleCount; ++number) {
        bus_->addBoard(number);
    }

    connect(bus_, SIGNAL(boardOnlineChanged(int,bool)), this, SLOT(boardOnline(int,bool)));
    connect(bus_, SIGNAL(commandDone(int,int,QByteArray)), this, SLOT(commandDone(int,int,QByteArray)));
    connect(bus_, SIGNAL(commandFailed(int,int,int)), this, SLOT(commandFailed(int,int,int)));
    connect(bus_, SIGNAL(connectionChanged(bool)), this, SLOT(linkChanged(bool)));
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

void NozzleConfigSync::refresh()
{
    for (int number = 1; number <= MachineConfig::NozzleCount; ++number) {
        if (bus_->isOnline(number)) {
            reads_ << number;
        }
    }
    work();
}

bool NozzleConfigSync::update(int number, const BoardConfig &config, int tankId, int userId)
{
    if (!config.isValid() || !bus_->isOnline(number) || updates_.contains(number)) {
        return false;
    }

    Update update;
    update.config = config;
    update.tankId = tankId;
    update.userId = userId;
    update.before = config_.nozzle(number);
    updates_.insert(number, update);
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
        const int number = wanted.at(i);
        if (!bus_->isOnline(number)) {
            reads_.remove(number); // asked again when it comes back
        } else if (!updates_.contains(number) && bus_->getConfig(number)) {
            reads_.remove(number);
        }
    }
}

void NozzleConfigSync::boardOnline(int address, bool online)
{
    if (online) {
        reads_ << address; // every board that comes online is asked for its config
        errors_.remove(address);
    } else {
        reads_.remove(address);
        if (updates_.contains(address)) {
            const QString text = errorText(ErrNoAnswer);
            updates_.remove(address);
            errors_.insert(address, text);
            emit updateFailed(address, text);
        }
    }
    emit changed();
    work();
}

void NozzleConfigSync::commandDone(int address, int command, const QByteArray &data)
{
    if (command != CmdGetConfig && command != CmdSetConfig) {
        return;
    }

    BoardConfig reported;
    if (!parseBoardConfig(data, &reported)) {
        errors_.insert(address, errorText(ErrBadLength));
        emit changed();
        work();

        return;
    }

    config_.storeBoardConfig(address, reported);
    errors_.remove(address);

    if (command == CmdSetConfig && updates_.contains(address)) {
        const Update update = updates_.take(address);
        if (update.tankId > 0) {
            config_.setTank(address, update.tankId);
        }
        // the change is logged once the board has confirmed it, with the cashier who asked
        const QString was = MachineConfig::describe(update.before);
        const QString now = MachineConfig::describe(config_.nozzle(address));
        if (was != now) {
            config_.logChange(QString("nozzle:%1").arg(address), was, now, update.userId);
        }
        reads_ << address; // and the board is asked once more: what it reports then is what counts
        emit updateDone(address);
    }

    emit changed();
    work();
}

void NozzleConfigSync::commandFailed(int address, int command, int error)
{
    if (command != CmdGetConfig && command != CmdSetConfig) {
        return;
    }

    const QString text = errorText(error);
    errors_.insert(address, text);
    if (command == CmdSetConfig && updates_.contains(address)) {
        updates_.remove(address);
        emit updateFailed(address, text);
    }
    emit changed();
    work();
}

void NozzleConfigSync::linkChanged(bool connected)
{
    if (!connected) {
        // nothing can be asked now; what waited for the boards is dropped (the boards come back one by one)
        reads_.clear();
        const QList<int> numbers = updates_.keys();
        updates_.clear();
        for (int i = 0; i < numbers.size(); ++i) {
            emit updateFailed(numbers.at(i), errorText(ErrNoConnection));
        }
    }
    emit changed();
}
