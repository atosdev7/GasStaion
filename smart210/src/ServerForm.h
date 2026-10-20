#ifndef SERVERFORM_H
#define SERVERFORM_H

#include <QGroupBox>

class QLabel;
class QLineEdit;
class QPushButton;
class SetupFlow;

namespace Ui {
class ServerForm;
}

// The server's address and port with a Test Connection button, and what came of it. Used by the first-run Server Setting page
// and, behind the PIN, by Machine Config. configured() is emitted when the server answered and knows this terminal's address;
// the address is saved by then (see SetupFlow).
class ServerForm : public QGroupBox
{
    Q_OBJECT

public:
    ServerForm(SetupFlow *flow, QWidget *parent = 0);
    ~ServerForm();

    // Shows the saved address again and clears the last message.
    void reload();

signals:
    void configured();

private slots:
    void testConnection();
    void connectionTested(bool ok, const QString &message);

private:
    Ui::ServerForm *ui_;
    SetupFlow *flow_;
    QLineEdit *host_;
    QLineEdit *port_;
    QPushButton *testButton_;
    QLabel *status_;
    bool waiting_; // a test started here is running
};

#endif
