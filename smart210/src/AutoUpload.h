#ifndef AUTOUPLOAD_H
#define AUTOUPLOAD_H

#include <QDateTime>
#include <QString>

// When the terminal uploads by itself (Machine Config, "Upload"). The download always runs every minute; the upload has three modes:
//
//   manual       only when the cashier presses Upload data on the Review screen (the default)
//   close        at the close of the day: the records of the business days that are over (9 AM to 9 AM) go up by themselves, the running day
//                stays here, so that the cashier can still fix or delete during the day; Upload data still sends everything when pressed
//   continuous   every record goes up once it is older than GraceMinutes (time to delete a mistaken sale); records made since stay here
//
// A record that has been uploaded is final on the terminal (the web app confirms the day).
class AutoUpload
{
public:
    enum Mode { Manual, CloseOfDay, Continuous };

    static const int GraceMinutes = 30;

    // The text kept in the setting ("manual", "close", "continuous"); anything else is manual.
    static Mode modeOf(const QString &text);
    static QString textOf(Mode mode);

    // The moment before which records go up by themselves, "yyyy-MM-dd HH:mm:ss"; empty for manual.
    static QString cutoff(Mode mode, const QDateTime &now);
};

#endif
