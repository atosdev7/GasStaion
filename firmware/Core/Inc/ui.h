#ifndef UI_H
#define UI_H

/*
 * The dispenser's own display and keyboard: what is shown in each state of the board, the select key (auto mode) and the entry of the
 * amount (manual mode). No hardware in here: the LCD, the keyboard, the beeper and the LEDs come in through UiHal (port.c fills it
 * in; the LCD and keyboard drivers are the ones in ui_drivers.h).
 *
 * Auto mode:    IDLE "Press SELECT"; SELECTED "Wait for POS"; ARMED "Preset .. kg / Lift the nozzle"; DISPENSING "Set .. kg / Now .. kg";
 *               RESULT "Done .. kg"; FAULT "FAULT / Code n".
 * Manual mode:  IDLE "Enter amount kg" and the digits typed (0-9, the point, CLEAR deletes the last, ENTER starts the fill); the rest as above.
 */

#include "board.h"

#include <stdint.h>

#ifndef UI_LCD_ROWS
#define UI_LCD_ROWS 2
#endif
#ifndef UI_LCD_COLS
#define UI_LCD_COLS 16
#endif

/* The key codes the keyboard driver returns (one per press). */
enum {
    UI_KEY_NONE = 0,
    UI_KEY_0 = 1, UI_KEY_1, UI_KEY_2, UI_KEY_3, UI_KEY_4, UI_KEY_5, UI_KEY_6, UI_KEY_7, UI_KEY_8, UI_KEY_9,
    UI_KEY_POINT,
    UI_KEY_ENTER,
    UI_KEY_CLEAR,
    UI_KEY_SELECT
};

typedef struct {
    void *ctx;
    void (*lcd_clear)(void *ctx);
    void (*lcd_print)(void *ctx, uint8_t row, uint8_t col, const char *text);   /* writes the text at the position */
    int (*key)(void *ctx);                                                      /* a UI_KEY_* code, UI_KEY_NONE when no new press */
    void (*beep)(void *ctx, uint16_t ms);
    void (*led)(void *ctx, uint8_t index, int on);                              /* 0 selected/armed, 1 dispensing, 2 result, 3 fault */
} UiHal;

#define UI_ENTRY_MAX 7   /* 999.99 */

typedef struct {
    const UiHal *hal;
    Board *board;
    char shown[UI_LCD_ROWS][UI_LCD_COLS + 1];
    int drawn;
    char entry[UI_ENTRY_MAX + 1];
    uint8_t entry_len;
    char note[UI_LCD_COLS + 1];     /* a short message on the second line (for example "Not ready") */
    uint32_t note_ms;
    uint8_t last_state;
} Ui;

void ui_init(Ui *ui, Board *board, const UiHal *hal);

/* Polls the keyboard, reacts, and redraws what changed. Call it in the main loop with the milliseconds since the last call. */
void ui_tick(Ui *ui, uint32_t elapsed_ms);

/* The amount typed so far in grams (0 when it is not a valid amount); for the tests. */
uint32_t ui_entry_grams(const Ui *ui);

#endif
