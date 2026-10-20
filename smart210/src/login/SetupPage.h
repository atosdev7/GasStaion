#ifndef SETUPPAGE_H
#define SETUPPAGE_H

#include <QWidget>

class ServerForm;
class SetupFlow;

namespace Ui {
class SetupPage;
}

// The first-run screen: the server's address and port with a Test Connection button (see ServerForm). configured() is emitted
// when the server answered and knows this terminal's address.
class SetupPage : public QWidget
{
    Q_OBJECT

public:
    SetupPage(SetupFlow *flow, QWidget *parent = 0);
    ~SetupPage();

signals:
    void configured();

private:
    Ui::SetupPage *ui_;
    ServerForm *form_;
};

#endif
