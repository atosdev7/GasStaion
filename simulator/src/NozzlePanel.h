#ifndef NOZZLEPANEL_H
#define NOZZLEPANEL_H

#include <QGroupBox>
#include <QList>
#include <QString>

class BoardSimulator;
class QCheckBox;
class QComboBox;
class QLabel;
class QProgressBar;
class QPushButton;
class QSpinBox;

// One nozzle of the simulator: what its board is doing, the three displays of the dispenser (kg, litres, density), its keypad (digits, point, Select,
// Clear, Enter), the gun (up and down), and the buttons for what the hardware does to it (a fault, a power cut) and for a bad bus (answers lost or spoiled,
// a silent board).
class NozzlePanel : public QGroupBox
{
    Q_OBJECT

public:
    NozzlePanel(int address, BoardSimulator *simulator, QWidget *parent = 0);

    void refresh(); // shows the board as it is now

private slots:
    void selectKey();
    void clearKey();
    void gunUp();
    void gunDown();
    void digitKey();
    void enterKey();
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
    QLabel *kgDisplay_;      // display 1: 6 digits, the kg (the preset, what is dispensed, a card's balance, what is being typed)
    QLabel *litresDisplay_;  // display 2: 6 digits, the litres
    QLabel *densityDisplay_; // display 3: 4 digits, the density
    QLabel *config_;
    QProgressBar *progress_;
    QLabel *fill_;
    QLabel *result_;
    QPushButton *select_;
    QPushButton *clear_;
    QPushButton *gunUp_;
    QPushButton *gunDown_;
    QPushButton *enter_;
    QList<QPushButton *> digits_; // 0 to 9 and the point
    QString typed_;               // the amount being typed on the keypad (manual mode)
    QPushButton *stop_;
    QSpinBox *flow_;
    QComboBox *fault_;
    QCheckBox *silent_;
    bool refreshing_;

    QLabel *makeDisplay(QLabel **target);
};

#endif
