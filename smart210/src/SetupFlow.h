#ifndef SETUPFLOW_H
#define SETUPFLOW_H

#include "ApiClient.h"

#include <QObject>
#include <QString>

class ApiReply;
class Settings;

// The first run, without any screen: the cashier-less setup of the server address. The web app knows this terminal by its
// IP address (the admin registered it on the Devices screen), so all the device has to do is find the server and check
// that its own address is registered there:
//
//   testConnection(host, port) -> connectionTested(ok, message)
//
// On success the address, port and the device's id are saved in the settings table and the device counts as configured.
// The first sync (task 4.3) and the cashier login (4.4) follow.
class SetupFlow : public QObject
{
    Q_OBJECT

public:
    SetupFlow(Settings *settings, QObject *parent = 0);

    bool isConfigured() const;
    QString savedHost() const;
    int savedPort() const;
    void setTimeoutMs(int timeoutMs) { client_.setTimeoutMs(timeoutMs); }

    void testConnection(const QString &host, int port);

    QString serverTime() const { return serverTime_; }

signals:
    void connectionTested(bool ok, const QString &message);

private slots:
    void pingFinished();

private:
    Settings *settings_;
    ApiClient client_;
    QString testedHost_;
    int testedPort_;
    QString serverTime_;
};

#endif
