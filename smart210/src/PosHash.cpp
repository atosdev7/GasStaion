#include "PosHash.h"

#include <QByteArray>
#include <QCryptographicHash>

bool PosHash::matches(const QString &salt, const QString &secret, const QString &hash)
{
    if (secret.isEmpty() || hash.isEmpty()) {
        return false;
    }

    const QByteArray data = (salt + secret).toUtf8();
    QByteArray expected;
    if (hash.size() == 64) {
        expected = QCryptographicHash::hash(data, QCryptographicHash::Sha256).toHex();
    } else if (hash.size() == 40) {
        expected = QCryptographicHash::hash(data, QCryptographicHash::Sha1).toHex();
    } else {
        return false;
    }

    return expected == hash.toLower().toLatin1();
}
