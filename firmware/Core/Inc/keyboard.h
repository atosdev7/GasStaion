#ifndef KEYBOARD_H
#define KEYBOARD_H

/*
 * The driver of the keyboard board (keyboard.c). It is called from the main loop only, never from an interrupt, and it BLOCKS for the time given below.
 *
 * The keyboard has 17 keys and an LED display of its own, 5 seven-segment digits (UI_KEYPAD_DIGITS) that the board refreshes while it scans the keys: they show the
 * number of the amount that is set or typed (the `value`).
 */

#include <stdint.h>

/* The key codes the driver returns (one per press). */
enum {
    UI_KEY_NONE = 0,
    UI_KEY_0 = 1, UI_KEY_1, UI_KEY_2, UI_KEY_3, UI_KEY_4, UI_KEY_5, UI_KEY_6, UI_KEY_7, UI_KEY_8, UI_KEY_9,
    UI_KEY_POINT,
    UI_KEY_NEXT,       /* not used yet */
    UI_KEY_PREV,       /* not used yet */
    UI_KEY_TOTAL,      /* shows the total the board has dispensed */
    UI_KEY_CLEAR,
    UI_KEY_SET,        /* manual mode: takes the typed amount */
    UI_KEY_FUNCTION    /* auto mode: the select key (beeps, reported to the Smart210) */
};

/* The keys as the board reports them: the three bytes that it clocks out (column 0 first) are joined into a 32 bit word as byte0 << 24 | byte1 << 16 | byte2 << 8, and
   a pressed key is a 1. The 17 keys use the 5 low bits of the first byte, all 8 bits of the second and the 4 high bits of the third. (The names are the ones of the
   keyboard board; keyboard.c says which UI_KEY_* each one gives: PRINT is FUNCTION, START is SET, STOP is TOTAL.) */
#define KEY_1                      0x00001000
#define KEY_2                      0x00002000
#define KEY_3                      0x00004000
#define KEY_4                      0x00008000
#define KEY_5                      0x00010000
#define KEY_6                      0x00020000
#define KEY_7                      0x00040000
#define KEY_8                      0x00080000
#define KEY_9                      0x00100000
#define KEY_0                      0x00200000
#define KEY_DOT                    0x00400000
#define KEY_CLEAR                  0x00800000
#define KEY_NEXT                   0x01000000
#define KEY_PRINT                  0x02000000
#define KEY_START                  0x04000000
#define KEY_STOP                   0x08000000
#define KEY_PREV                   0x10000000

#define UI_KEYPAD_DIGITS 5          /* the digits of the display of the keyboard board */

/* Scans the keyboard and, in the same time, refreshes the display of the keyboard board. Takes about 20 ms (the waits of the scan are 17 ms of HAL_Delay, which
   each wait a tick longer, and about 1 ms of the link: 876 us of waits and the pin writes). Called at every pass of the main loop with what the display has to show
   now:
     value  the digits as a text, right aligned: UI_KEYPAD_DIGITS digits ('0'..'9', ' ', '-'; a '.' after a digit lights the point of that digit and takes no digit),
            for example " 15.00" or "  12.5" (the amount being typed); all blanks when there is nothing to show. A text longer than the digits loses its left end.
   Returns one UI_KEY_* code for each new press and UI_KEY_NONE (0) otherwise. A key counts as pressed when three scans in a row (60 ms) found it so, and as
   released when three scans in a row found it up, so a bounce of a contact gives nothing and a key that is held gives its code once. Keys that went down together
   are given one by one at the next scans. */
int ScanKeyboardAndRefreshDisplay(const char *value);

/* The link of the keyboard board (kept public for the tests; the main loop only calls ScanKeyboardAndRefreshDisplay): strobe (KEY_SEL), clock (KEY_CLK) and one
   data line (KEY_DATA) that is an output while the commands and the display go out and an input while the keys are read. */
void SendLcdDataToKeyboard(void);
void SendCommand1ToKeyboard(void);
void SendCommand2ToKeyboard(void);
uint8_t ScanKeyColumn(uint8_t col);
void WriteByteToKeyboard(uint8_t data);

#endif
