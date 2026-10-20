#include "SetupFlow.h"

#include "ApiReply.h"
#include "Settings.h"

SetupFlow::SetupFlow(Settings *settings, QObject *parent)
    : QObject(parent), settings_(settings), client_(this), testedPort_(0)
{
}

bool SetupFlow::isConfigured() const
{
    return !settings_->value(SettingKey::ServerHost).isEmpty() && settings_->intValue(SettingKey::DeviceId) > 0;
}

QString SetupFlow::savedHost() const
{
    return settings_->value(SettingKey::ServerHost);
}

int SetupFlow::savedPort() const
{
    return settings_->intValue(SettingKey::ServerPort, 80);
}

void SetupFlow::testConnection(const QString &host, int port)
{
    if (host.trimmed().isEmpty()) {
        emit connectionTested(false, tr("Enter the server address."));

        return;
    }
    if (port < 1 || port > 65535) {
        emit connectionTested(false, tr("Enter a port from 1 to 65535."));

        return;
    }

    client_.setServer(host, port);
    testedHost_ = host.trimmed();
    testedPort_ = port;

    ApiReply *reply = client_.ping();
    connect(reply, SIGNAL(finished()), this, SLOT(pingFinished()));
}

void SetupFlow::pingFinished()
{
    ApiReply *reply = qobject_cast<ApiReply *>(sender());
    reply->deleteLater();

    if (!reply->ok()) {
        emit connectionTested(false, reply->message());

        return;
    }

    serverTime_ = reply->data().value("server_time").toString();

    // The server answers anyone's ping; it only lets in the addresses the admin registered.
    if (!reply->data().value("registered").toBool()) {
        emit connectionTested(false, tr("The server is reachable, but this terminal's address (%1) is not registered. "
                                        "Ask the admin to add it on the Devices screen.")
                                         .arg(reply->data().value("ip").toString()));

        return;
    }

    const bool saved = settings_->setValue(SettingKey::ServerHost, testedHost_)
        && settings_->setInt(SettingKey::ServerPort, testedPort_)
        && settings_->setInt(SettingKey::DeviceId, reply->data().value("device_id").toInt())
        && settings_->setValue(SettingKey::DeviceName, reply->data().value("device_name").toString());
    if (!saved) {
        emit connectionTested(false, tr("The server address could not be saved on this device."));

        return;
    }

    emit connectionTested(true, tr("Connected as %1. Server time %2.")
                                    .arg(reply->data().value("device_name").toString()).arg(serverTime_));
}
