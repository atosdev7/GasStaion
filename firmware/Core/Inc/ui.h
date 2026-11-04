#ifndef UI_H
#define UI_H

/*
 * The dispenser's own displays and keyboard: what is shown in each state of the board, the keys (select and clear are reported to the Smart210,
 * the digits, Dot and Set type and take the amount in manual mode, Total shows the total in both modes). No hardware in here: the displays, the keyboard, the beeper and the LEDs come in through
 * UiHal (port.c fills it in; the display and keyboard drivers are the three functions of ui_drivers.h).
 *
 * The four displays (texts as ui_drivers.h describes them):
 *   kg (6 digits):       0.00 when idle; the balance of SHOW_BALANCE; the preset (SET); the kg so far (DISPENSING); the kg of the result (DONE);
 *                        "Err" for a moment when a key is refused and while the board is in FAULT
 *   litres (6 digits):   0.00 when idle; "bAL" while a balance is shown; the litres so far / of the result; the fault code in FAULT
 *   density (4 digits):  the density of the config, 0.830 for 0.8300 kg/L; "----" for a board that was never configured
 *   keypad (8 digits):   manual mode, idle: the amount being typed; the preset while SET and DISPENSING; blank otherwise
 */

#include "board.h"
#include "ui_drivers.h"

#include <stdint.h>

/* The key codes the keyboard driver returns (one per press). */
enum {
    UI_KEY_NONE = 0,
    UI_KEY_0 = 1, UI_KEY_1, UI_KEY_2, UI_KEY_3, UI_KEY_4, UI_KEY_5, UI_KEY_6, UI_KEY_7, UI_KEY_8, UI_KEY_9,
    UI_KEY_POINT,
    UI_KEY_NEXT,       /* not used yet */
    UI_KEY_PREV,       /* not used yet */
    UI_KEY_TOTAL,      /* manual mode: shows the total the board has dispensed */
    UI_KEY_CLEAR,
    UI_KEY_SET,        /* manual mode: takes the typed amount */
    UI_KEY_FUNCTION    /* auto mode: the select key (beeps, reported to the Smart210) */
};

#define UI_TEXT_MAX (UI_KEYPAD_DIGITS + UI_KEYPAD_DIGITS + 1)   /* the characters of a text: a digit each and, at most, a point each */
#define UI_DISPLAYS 4                                           /* kg, litres, density, keypad */

typedef struct {
    void *ctx;
    void (*update)(void *ctx, const char *kg, const char *litres, const char *density, const char *keypad);   /* UpdateDisplay */
    void (*refresh)(void *ctx);                                                                              /* RefreshDisplay: 8 to 10 ms */
    int (*scan)(void *ctx);                                                                                  /* ScanKeyboardAndRefreshDisplay: 20 ms; a UI_KEY_* code, UI_KEY_NONE when no new press */
    void (*beep)(void *ctx, uint16_t ms);
    void (*led)(void *ctx, uint8_t index, int on);                              /* 0 selected/armed, 1 dispensing, 2 result, 3 fault */
} UiHal;

#define UI_ENTRY_MAX 7   /* 999.99 */
#define UI_TOTAL_SHOW_MS 5000   /* how long the Total key shows the total */

typedef struct {
    const UiHal *hal;
    Board *board;
    char shown[UI_DISPLAYS][UI_TEXT_MAX + 1];   /* what the displays were last given */
    int drawn;
    char entry[UI_ENTRY_MAX + 1];
    uint8_t entry_len;
    uint32_t note_ms;               /* while it runs the kg display says "Err": a key was refused */
    uint32_t total_ms;              /* while it runs the two numeric displays show the total of the board (the Total key) */
    uint8_t last_state;
} Ui;

void ui_init(Ui *ui, Board *board, const UiHal *hal);

/* Scans the keyboard (about 20 ms), reacts, gives the displays what changed and refreshes the display board (8 to 10 ms): one call takes about 30 ms. Call it in
   the main loop with the milliseconds since the last call. */
void ui_tick(Ui *ui, uint32_t elapsed_ms);

/* The amount typed so far in grams (0 when it is not a valid amount); for the tests. */
uint32_t ui_entry_grams(const Ui *ui);

#endif
