#ifndef MAINWINDOW_H
#define MAINWINDOW_H

#include "BoardSimulator.h"
#include "ConverterClient.h"

#include <QList>
#include <QWidget>

class NozzlePanel;
class QCheckBox;
class QLabel;
class QLineEdit;
class QListWidget;
class QPushButton;
class QTimer;

// The simulator window: the link to the Smart210 at the top, the six nozzles in the middle, the log of what was asked and answered below.
class MainWindow : public QWidget
{
    Q_OBJECT

public:
    explicit MainWindow(QWidget *parent = 0);

    static const int NozzleCount = 6; // the boards 1 to 6, as on the Smart210

protected:
    void closeEvent(QCloseEvent *event);

private slots:
    void connectClicked();
    void connectionChanged(bool connected);
    void request(const BoardProtocol::Frame &frame);
    void boardChanged(int address);
    void exchanged(int address, const QString &request, const QString &reply);
    void tick();
    void save();

private:
    void log(const QString &line);

    BoardSimulator sim_;
    ConverterClient client_;
    QList<NozzlePanel *> panels_;
    QLineEdit *host_;
    QLineEdit *port_;
    QPushButton *connect_;
    QCheckBox *reconnect_;
    QCheckBox *showPolls_;
    QLabel *status_;
    QListWidget *log_;
    QTimer *tick_;
    QTimer *saveTimer_;
};

#endif
