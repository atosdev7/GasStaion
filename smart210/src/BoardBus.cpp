#include "BoardBus.h"

#include <QDateTime>
#include <QTcpServer>
#include <QTcpSocket>
#include <QTimer>

using namespace BoardProtocol;

BoardBus::BoardBus(QObject *parent)
    : QObject(parent),
      port_(0),
      cycleMs_(100),
      answerTimeoutMs_(100),
      missLimit_(3),
      offlineRetryMs_(2000),
      server_(new QTcpServer(this)),
      socket_(0),
      answerTimer_(new QTimer(this)),
      stepTimer_(new QTimer(this)),
      started_(false),
      connected_(false),
      current_(-1),
      waitingFor_(-1),
      waitingCommand_(0),
      roundStart_(0)
{
    qRegisterMetaType<BoardProtocol::Status>("BoardProtocol::Status");

    answerTimer_->setSingleShot(true);
    stepTimer_->setSingleShot(true);
    stepTimer_->setTimerType(Qt::PreciseTimer); // the coarse default can wait 15 ms on some systems

    connect(server_, SIGNAL(newConnection()), this, SLOT(newConnection()));
    connect(answerTimer_, SIGNAL(timeout()), this, SLOT(answerTimedOut()));
    connect(stepTimer_, SIGNAL(timeout()), this, SLOT(nextExchange()));
}

BoardBus::~BoardBus()
{
    // the socket would report its closing to a bus that is half gone
    if (socket_) {
        socket_->disconnect(this);
        socket_->abort();
    }
}

void BoardBus::addBoard(int address)
{
    if (address < 1 || address > 247 || hasBoard(address)) {
        return;
    }
    Board board;
    board.address = address;
    boards_.append(board);
}

bool BoardBus::hasBoard(int address) const
{
    return indexOf(address) >= 0;
}

bool BoardBus::isOnline(int address) const
{
    const int index = indexOf(address);

    return index >= 0 && boards_.at(index).online;
}

Status BoardBus::lastStatus(int address) const
{
    const int index = indexOf(address);

    return index >= 0 ? boards_.at(index).status : Status();
}

int BoardBus::indexOf(int address) const
{
    for (int i = 0; i < boards_.size(); ++i) {
        if (boards_.at(i).address == address) {
            return i;
        }
    }

    return -1;
}

bool BoardBus::start()
{
    if (started_) {
        return true;
    }
    if (!server_->listen(QHostAddress::Any, quint16(port_))) {
        error_ = server_->errorString();

        return false;
    }
    error_.clear();
    started_ = true;

    return true;
}

void BoardBus::stop()
{
    started_ = false;
    answerTimer_->stop();
    stepTimer_->stop();
    waitingFor_ = -1;
    server_->close();
    dropSocket();
    linkDown();
}

// The converter connected (or connected again): it is the link from now on, and the old one, if any, was dead.
void BoardBus::newConnection()
{
    while (QTcpSocket *socket = server_->nextPendingConnection()) {
        if (!started_) {
            socket->deleteLater();
            continue;
        }
        if (socket_) {
            dropSocket();
            linkDown();
        }
        socket_ = socket;
        socket_->setParent(this);
        socket_->setSocketOption(QAbstractSocket::LowDelayOption, 1); // small requests and answers: send them at once
        connect(socket_, SIGNAL(readyRead()), this, SLOT(readyRead()));
        connect(socket_, SIGNAL(disconnected()), this, SLOT(disconnected()));
        linkUp();
    }
}

void BoardBus::linkUp()
{
    connected_ = true;
    parser_.clear();
    emit connectionChanged(true);
    beginRound();
}

void BoardBus::disconnected()
{
    if (sender() != socket_) {
        return;
    }
    dropSocket();
    linkDown();
}

void BoardBus::dropSocket()
{
    if (!socket_) {
        return;
    }
    QTcpSocket *old = socket_;
    socket_ = 0;
    old->disconnect(this);
    old->abort();
    old->deleteLater();
}

// The link is gone: nothing can be asked, the commands waiting fail and the boards' state is unknown until the converter is back.
void BoardBus::linkDown()
{
    answerTimer_->stop();
    stepTimer_->stop();
    waitingFor_ = -1;
    const bool was = connected_;
    connected_ = false;
    for (int i = 0; i < boards_.size(); ++i) {
        failPending(i, ErrNoConnection);
        if (boards_.at(i).online) {
            boards_[i].online = false;
            boards_[i].missed = 0;
            boards_[i].status = Status();
            emit boardOnlineChanged(boards_.at(i).address, false);
        }
    }
    if (was) {
        emit connectionChanged(false);
    }
}

bool BoardBus::send(int address, int command, const QByteArray &data)
{
    const int index = indexOf(address);
    if (index < 0 || !connected_ || !boards_.at(index).online || boards_.at(index).hasPending || data.size() > MaxData) {
        return false;
    }
    Board &board = boards_[index];
    board.hasPending = true;
    board.pendingCommand = command;
    board.pendingData = data;

    return true;
}

void BoardBus::failPending(int index, int error)
{
    Board &board = boards_[index];
    if (!board.hasPending) {
        return;
    }
    const int command = board.pendingCommand;
    board.hasPending = false;
    board.pendingData.clear();
    // The command is given up, so SEQ moves on: a board that did run it would answer a repeated SEQ with the old answer (of this command)
    // to whatever is asked next, and that answer would never be taken for the answer to a POLL.
    board.seq = (board.seq + 1) & 0xFF;
    emit commandFailed(board.address, command, error);
}

bool BoardBus::due(const Board &board, qint64 now) const
{
    return board.online || now - board.lastTry >= offlineRetryMs_;
}

void BoardBus::beginRound()
{
    current_ = -1;
    roundStart_ = QDateTime::currentMSecsSinceEpoch();
    scheduleNext(0);
}

void BoardBus::scheduleNext(int ms)
{
    stepTimer_->start(ms);
}

// The next board that is due in this round, or the end of the round (then the rest of the cycle time is waited out).
void BoardBus::nextExchange()
{
    if (!started_ || !connected_ || !socket_ || waitingFor_ >= 0) {
        return;
    }
    const qint64 now = QDateTime::currentMSecsSinceEpoch();
    for (int i = current_ + 1; i < boards_.size(); ++i) {
        if (due(boards_.at(i), now)) {
            current_ = i;
            exchange(i);

            return;
        }
    }

    const qint64 spent = now - roundStart_;
    current_ = -1;
    roundStart_ = now + qMax<qint64>(1, cycleMs_ - spent);
    scheduleNext(int(qMax<qint64>(1, cycleMs_ - spent)));
}

void BoardBus::exchange(int index)
{
    Board &board = boards_[index];
    board.lastTry = QDateTime::currentMSecsSinceEpoch();

    const int command = board.hasPending ? board.pendingCommand : int(CmdPoll);
    const QByteArray data = board.hasPending ? board.pendingData : QByteArray();

    parser_.clear(); // whatever is left of an earlier answer is late and no longer wanted
    waitingFor_ = index;
    waitingCommand_ = command;
    socket_->write(build(board.address, board.seq, command, data));
    socket_->flush();
    answerTimer_->start(answerTimeoutMs_);
}

void BoardBus::readyRead()
{
    parser_.feed(socket_->readAll());
    if (waitingFor_ < 0) {
        parser_.clear();

        return;
    }

    Frame frame;
    while (waitingFor_ >= 0 && parser_.next(&frame)) {
        const Board &board = boards_.at(waitingFor_);
        const bool answer = frame.command == (waitingCommand_ | AnswerFlag);
        const bool nak = frame.command == NakCommand && frame.data.size() == 2 && quint8(frame.data.at(1)) == waitingCommand_;
        if (frame.address != board.address || frame.seq != board.seq || (!answer && !nak)) {
            continue; // not the answer to this request: a stray or late frame
        }
        gotAnswer(waitingFor_, frame);
    }
}

void BoardBus::gotAnswer(int index, const Frame &frame)
{
    const int command = waitingCommand_;
    const bool nak = frame.command == NakCommand;

    // A POLL answer of the wrong size is a firmware fault: it counts as no answer.
    Status status;
    if (!nak && command == CmdPoll && !parseStatus(frame.data, &status)) {
        answerTimer_->stop();
        waitingFor_ = -1;
        missed(index);

        return;
    }

    answerTimer_->stop();
    waitingFor_ = -1;

    Board &board = boards_[index];
    board.seq = (board.seq + 1) & 0xFF; // only a good answer moves SEQ on
    board.missed = 0;
    if (!board.online) {
        board.online = true;
        emit boardOnlineChanged(board.address, true);
    }

    const int address = board.address;
    if (command == CmdPoll && !nak) {
        board.status = status;
        emit polled(address, status);
    } else if (board.hasPending && board.pendingCommand == command) {
        board.hasPending = false;
        board.pendingData.clear();
        if (nak) {
            emit commandFailed(address, command, quint8(frame.data.at(0)));
        } else {
            emit commandDone(address, command, frame.data);
        }
    }
    scheduleNext(0);
}

void BoardBus::answerTimedOut()
{
    if (waitingFor_ < 0) {
        return;
    }
    const int index = waitingFor_;
    waitingFor_ = -1;
    missed(index);
}

// No good answer from this board in this round: the next round asks again.
void BoardBus::missed(int index)
{
    Board &board = boards_[index];
    ++board.missed;
    if (board.missed >= missLimit_) {
        const bool wasOnline = board.online;
        board.online = false;
        board.status = Status();
        failPending(index, ErrNoAnswer);
        if (wasOnline) {
            emit boardOnlineChanged(board.address, false);
        }
    }
    scheduleNext(0);
}
