#ifndef KEYBOARD_H
#define KEYBOARD_H

/*
 * The driver of the keyboard board (keyboard.c). It is called from the main loop only, never from an interrupt, and it BLOCKS for the time given below.
 *
 * The keyboard has 17 keys and an LED display of its own, 5 seven-segment digits (UI_KEYPAD_DIGITS) that the board refreshes while it scans the keys: they show the
 * number of the amount that is set or typed (the `value`).
 */

#include <stdint.h>

/* The key codes. The three bytes that the board clocks out (column 0 first) are joined into a 32 bit word as byte0 << 24 | byte1 << 16 | byte2 << 8, and a pressed
   key is a 1: the 17 keys use the 5 low bits of the first byte, all 8 bits of the second and the 4 high bits of the third, and the code of a key is its bit. The driver
   returns the code of the key that was pressed (KEY_NONE when there is none); ui.c says what each key does. */
#define KEY_NONE                   0x00000000
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

/* The digit of a digit key (KEY_0 to KEY_9, which are consecutive bits), -1 for any other code. */
static inline int key_digit(int key)
{
    int n;

    if (key < KEY_1 || key > KEY_0 || (key & (key - 1)) != 0) {
        return -1;
    }
    for (n = 0; (KEY_1 << n) != key; ++n) {
    }

    return (n + 1) % 10;
}

#define UI_KEYPAD_DIGITS 5          /* the digits of the display of the keyboard board */

/* Scans the keyboard and, in the same time, refreshes the display of the keyboard board. Takes about 20 ms (the waits of the scan are 17 ms of HAL_Delay, which
   each wait a tick longer, and about 1 ms of the link: 876 us of waits and the pin writes). Called at every pass of the main loop with what the display has to show
   now:
     value  the digits as a text, right aligned: UI_KEYPAD_DIGITS digits ('0'..'9', ' ', '-'; a '.' after a digit lights the point of that digit and takes no digit),
            for example " 15.00" or "  12.5" (the amount being typed); all blanks when there is nothing to show. A text longer than the digits loses its left end.
   Returns the code (KEY_*) of one key for each new press and KEY_NONE (0) otherwise. A key counts as pressed when three scans in a row (60 ms) found it so, and as
   released when three scans in a row found it up, so a bounce of a contact gives nothing and a key that is held gives its code once. Keys that went down together
   are given one by one at the next scans, the lowest bit first. */
int ScanKeyboardAndRefreshDisplay(const char *value);

/* The link of the keyboard board (kept public for the tests; the main loop only calls ScanKeyboardAndRefreshDisplay): strobe (KEY_SEL), clock (KEY_CLK) and one
   data line (KEY_DATA) that is an output while the commands and the display go out and an input while the keys are read. */
void SendLcdDataToKeyboard(void);
void SendCommand1ToKeyboard(void);
void SendCommand2ToKeyboard(void);
uint8_t ScanKeyColumn(uint8_t col);
void WriteByteToKeyboard(uint8_t data);

#endif
