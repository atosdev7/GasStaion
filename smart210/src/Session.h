#ifndef SESSION_H
#define SESSION_H

#include <QList>
#include <QSqlDatabase>
#include <QString>

// Who is using the terminal. A cashier logs in once (password checked on the device against the users downloaded from the
// web app, so it works offline) and stays logged in over restarts, until Log out on the Settings page. The session is just the
// user's id in the settings table; every record made meanwhile carries it as user_id.
class Session
{
public:
    struct Cashier {
        int id;
        QString username;
        QString name;
        QString role;
    };

    explicit Session(QSqlDatabase db);

    // Who may log in: the users the web app marked for the terminal, active and not deleted, by name. Only the cashier and the assistant: the
    // administrator and the manager do not work at the terminal (the roles are named in the SQL of cashiers(), login() and validate()).
    QList<Cashier> cashiers() const;

    // True and logged in when the password is right for that user; false otherwise (nobody is logged in then).
    bool login(int userId, const QString &password);
    void logout();

    bool isLoggedIn() const;
    int userId() const;
    QString userName() const;

    // For the start: the user of the session must still exist, be active and be allowed on the terminal (the web app may
    // have changed that since the last start). Otherwise the session is ended. Returns whether someone is logged in.
    bool validate();

private:
    QSqlDatabase db_;
};

#endif
