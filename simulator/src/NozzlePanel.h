#ifndef NOZZLEPANEL_H
#define NOZZLEPANEL_H

#include <QGroupBox>

class BoardSimulator;
class QCheckBox;
class QComboBox;
class QLabel;
class QLineEdit;
class QProgressBar;
class QPushButton;
class QSpinBox;

// One nozzle of the simulator: what its board is doing, and the buttons for what people and the hardware do to it (the select key, the
// amount typed at the dispenser, a fault, a power cut) and for a bad bus (answers lost or spoiled, a silent board).
class NozzlePanel : public QGroupBox
{
    Q_OBJECT

public:
    NozzlePanel(int address, BoardSimulator *simulator, QWidget *parent = 0);

    void refresh(); // shows the board as it is now

private slots:
    void selectKey();
    void clearKey();
    void liftGun();
    void typeAmount();
    void stopFill();
    void faultPicked(int index);
    void powerCut();
    void silentToggled(bool on);
    void loseAnswers();
    void spoilAnswers();
    void factoryReset();
    void flowChanged(int gramsPerSecond);

private:
    int address_;
    BoardSimulator *sim_;
    QLabel *state_;
    QLabel *config_;
    QProgressBar *progress_;
    QLabel *fill_;
    QLabel *result_;
    QPushButton *select_;
    QPushButton *clear_;
    QPushButton *gun_;
    QLineEdit *amount_;
    QPushButton *type_;
    QPushButton *stop_;
    QSpinBox *flow_;
    QComboBox *fault_;
    QCheckBox *silent_;
    bool refreshing_;
};

#endif
