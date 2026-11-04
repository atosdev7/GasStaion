#ifndef UI_DRIVERS_H
#define UI_DRIVERS_H

/*
 * The display and keyboard drivers of the dispenser. Only the three functions are defined here (the code that drives the display board and the
 * keyboard board is yours). ui_drivers_weak.c holds empty versions so that the project links before yours are in; yours replace them without any
 * other change. They are called from the main loop only, never from an interrupt, and they BLOCK for the time given below.
 *
 * The displays: the display board has 2 x 6 digits (kg, litres) and 1 x 4 digits (density). The keyboard board has an LCD of its own, 8 seven-segment
 * digits (UI_KEYPAD_DIGITS) that it refreshes while it scans the keys: the first digit shows the mode or the status of the dispenser (a number or a letter,
 * the `code`), the other 7 (UI_KEYPAD_VALUE_DIGITS) show the number of the amount that is set (the `value`).
 *
 * The text of a display has one character for each digit, right aligned, so its length is the number of digits (blanks in front), and a '.'
 * after a digit lights the point of that digit (the '.' takes no digit). The characters: '0'..'9', ' ' (blank), '-', and the letters 'E', 'r',
 * 'b', 'A', 'L' (for "Err" and "bAL"). Examples for 6 digits: "  15.00" is 15.00; "   Err"; "----.--"; for the 4 digits of the density "0.830".
 */

#include <stdint.h>

#define UI_KG_DIGITS 6
#define UI_LITRES_DIGITS 6
#define UI_DENSITY_DIGITS 4
#define UI_KEYPAD_DIGITS 8          /* the LCD of the keyboard board: the code + the value */
#define UI_KEYPAD_VALUE_DIGITS 7    /* its digits for the value */

/* Sets what the three displays of the display board show; the texts are copied. It only stores the values (nothing goes out to the board until RefreshDisplay
   runs) and is cheap: it is called when a text changed. (The LCD of the keyboard board is given to ScanKeyboardAndRefreshDisplay.) */
void UpdateDisplay(const char *kg, const char *litres, const char *density);

/* One pass over the display board: the 2 x 6 digits and the 1 x 4 digits are sent to it. Takes 8 to 10 ms. The digits only stay lit while this
   (and the next function) is called again and again. */
void RefreshDisplay(void);

/* Scans the keyboard and, in the same time, refreshes the LCD of the keyboard board. Takes about 20 ms. Called at every pass of the main loop with what the
   LCD has to show now:
     code   the first digit, the mode or the status: '0'..'9' or a letter ('A'..'Z'; show what the 7 segments can, a lower-case letter is the same letter);
            ui.c says which (keypad_code): '0' idle, '2' a preset waits, '3' a fill runs, '4' a result waits, 'E' a fault, 't' the total is shown (auto board);
            'H' idle, 'P' a preset waits, 'F' a fill runs (manual board);
     value  the other 7 digits as a text, right aligned: UI_KEYPAD_VALUE_DIGITS digits ('0'..'9', ' ', '-'; a '.' after a digit lights the point of that digit and
            takes no digit), for example "   15.00" or "    12.5" (the amount being typed); all blanks when there is nothing to show.
   Returns one key code of ui.h (UI_KEY_0 .. UI_KEY_9, UI_KEY_POINT, UI_KEY_NEXT, UI_KEY_PREV, UI_KEY_TOTAL, UI_KEY_CLEAR, UI_KEY_SET, UI_KEY_FUNCTION: 17 keys)
   for each new press and UI_KEY_NONE (0) otherwise: it debounces itself, so a key that is held gives its code once. */
int ScanKeyboardAndRefreshDisplay(char code, const char *value);

#endif
