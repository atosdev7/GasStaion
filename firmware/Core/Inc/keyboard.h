#ifndef KEYBOARD_H
#define KEYBOARD_H

/*
 * The driver of the keyboard board (keyboard.c is yours: the file in the project only holds an empty function so that it builds before yours is in). It is
 * called from the main loop only, never from an interrupt, and it BLOCKS for the time given below.
 *
 * The keyboard has 17 keys and an LCD of its own, 8 seven-segment digits (UI_KEYPAD_DIGITS) that the board refreshes while it scans the keys: the first digit
 * shows the mode or the status of the dispenser (a number or a letter, the `code`), the other 7 (UI_KEYPAD_VALUE_DIGITS) show the number of the amount that is
 * set (the `value`).
 */

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

#define UI_KEYPAD_DIGITS 8          /* the LCD of the keyboard board: the code + the value */
#define UI_KEYPAD_VALUE_DIGITS 7    /* its digits for the value */

/* Scans the keyboard and, in the same time, refreshes the LCD of the keyboard board. Takes about 20 ms. Called at every pass of the main loop with what the
   LCD has to show now:
     code   the first digit, the mode or the status: '0'..'9' or a letter ('A'..'Z'; show what the 7 segments can, a lower-case letter is the same letter);
            ui.c says which (keypad_code): '0' idle, '2' a preset waits, '3' a fill runs, '4' a result waits, 'E' a fault, 't' the total is shown (auto board);
            'H' idle, 'P' a preset waits, 'F' a fill runs (manual board);
     value  the other 7 digits as a text, right aligned: UI_KEYPAD_VALUE_DIGITS digits ('0'..'9', ' ', '-'; a '.' after a digit lights the point of that digit and
            takes no digit), for example "   15.00" or "    12.5" (the amount being typed); all blanks when there is nothing to show.
   Returns one UI_KEY_* code for each new press and UI_KEY_NONE (0) otherwise: it debounces itself, so a key that is held gives its code once. */
int ScanKeyboardAndRefreshDisplay(char code, const char *value);

#endif