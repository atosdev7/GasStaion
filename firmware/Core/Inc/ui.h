#ifndef UI_H
#define UI_H

/*
 * The dispenser's own displays and keyboard: what is shown in each state of the board, the keys (select and clear are reported to the Smart210,
 * the digits, Dot and Set type and take the amount in manual mode, Total shows the total in both modes). No hardware in here: the displays, the keyboard, the beeper and the LEDs come in through
 * UiHal (port.c fills it in; the display and keyboard drivers are display.c and keyboard.c).
 *
 * The four displays (texts as display.h and keyboard.h describe them):
 *   kg (6 digits):       0.00 when idle; the balance of SHOW_BALANCE; the preset (SET); the kg so far (DISPENSING); the kg of the result (DONE); the kg of the last fill stay after it (manual: when it ends, auto: when the result is acknowledged) until the next preset is set;
 *                        "Err" for a moment when a key is refused and while the board is in FAULT
 *   litres (6 digits):   0.00 when idle; "bAL" while a balance is shown; the preset in litres by the density (SET); the litres so far / of the result (they stay in IDLE after the fill until the next preset is set); the fault code in FAULT
 *   density (4 digits):  the density of the config, 0.830 for 0.8300 kg/L; "----" for a board that was never configured
 *   keypad (8 digits):   manual mode, idle: the amount being typed; the preset while SET and DISPENSING; blank otherwise
 */

#include "board.h"
#include "display.h"
#include "keyboard.h"

#include <stdint.h>

#define UI_TEXT_MAX (UI_KEYPAD_VALUE_DIGITS + UI_KEYPAD_VALUE_DIGITS + 1)   /* the characters of a text: a digit each and, at most, a point each */
#define UI_DISPLAYS 4                                           /* kg, litres, density, and the value of the keyboard's LCD */

typedef struct {
    void *ctx;
    void (*update)(void *ctx, const char *kg, const char *litres, const char *density);                      /* UpdateDisplay */
    void (*refresh)(void *ctx);                                                                              /* RefreshDisplay: 8 to 10 ms */
    int (*scan)(void *ctx, char code, const char *value);                                                    /* ScanKeyboardAndRefreshDisplay(code, value): 20 ms; a UI_KEY_* code, UI_KEY_NONE when no new press */
    void (*beep)(void *ctx, uint16_t ms);
    void (*led)(void *ctx, uint8_t index, int on);                              /* 0 heartbeat, 1 preset set, 2 dispensing, 3 result (steady) or fault (blinking) */
} UiHal;

#define UI_ENTRY_MAX 7   /* 999.99 */
#define UI_TOTAL_SHOW_MS 5000   /* how long the Total key shows the total */

typedef struct {
    const UiHal *hal;
    Board *board;
    char shown[UI_DISPLAYS][UI_TEXT_MAX + 1];   /* what the displays were last given */
    int drawn;
    char code;                      /* the first digit of the keyboard's LCD: the mode or the status */
    char entry[UI_ENTRY_MAX + 1];
    uint8_t entry_len;
    uint32_t note_ms;               /* while it runs the kg display says "Err": a key was refused */
    uint32_t total_ms;              /* while it runs the two numeric displays show the total of the board (the Total key) */
    uint8_t last_state;
    uint32_t clock_ms;              /* 0..1999: the clock of the LEDs (the heartbeat and the blink of a fault) */
} Ui;

void ui_init(Ui *ui, Board *board, const UiHal *hal);

/* Scans the keyboard (about 20 ms), reacts, gives the displays what changed and refreshes the display board (8 to 10 ms): one call takes about 30 ms. Call it in
   the main loop with the milliseconds since the last call. */
void ui_tick(Ui *ui, uint32_t elapsed_ms);

/* The amount typed so far in grams (0 when it is not a valid amount); for the tests. */
uint32_t ui_entry_grams(const Ui *ui);

#endif
