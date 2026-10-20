#ifndef POSHASH_H
#define POSHASH_H

#include <QString>

// How the web app stores a cashier's password and the Machine Config PIN for the terminal: a random salt kept in
// pos_salt / pin_salt and the hash of salt + secret in pos_hash / pin_hash. Since 2026-10-05 the hash is SHA-256 (64 hex
// characters); rows nobody has signed in with since still have the older SHA-1 (40 hex characters), so the length tells
// which one a row uses. Checked on the device, so a login works without the server.
class PosHash
{
public:
    // False for an empty secret, an empty hash or a hash of any other length.
    static bool matches(const QString &salt, const QString &secret, const QString &hash);
};

#endif
