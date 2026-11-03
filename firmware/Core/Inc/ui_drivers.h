#ifndef UI_DRIVERS_H
#define UI_DRIVERS_H

/*
 * The display and keyboard drivers of the dispenser. Only the three functions are defined here (the code that drives the display board and the
 * keyboard board is yours). ui_drivers_weak.c holds empty versions so that the project links before yours are in; yours replace them without any
 * other change. They are called from the main loop only, never from an interrupt, and they BLOCK for the time given below.
 *
 * The displays: the display board has 2 x 6 digits (kg, litres) and 1 x 4 digits (density); the keyboard board has one display of its own
 * (UI_KEYPAD_DIGITS digits, 8 now: 6 to 8 on your board) that it refreshes while it scans the keys.
 *
 * The text of a display has one character for each digit, right aligned, so its length is the number of digits (blanks in front), and a '.'
 * after a digit lights the point of that digit (the '.' takes no digit). The characters: '0'..'9', ' ' (blank), '-', and the letters 'E', 'r',
 * 'b', 'A', 'L' (for "Err" and "bAL"). Examples for 6 digits: "  15.00" is 15.00; "   Err"; "----.--"; for the 4 digits of the density "0.830".
 */

#include <stdint.h>

#define UI_KG_DIGITS 6
#define UI_LITRES_DIGITS 6
#define UI_DENSITY_DIGITS 4
#define UI_KEYPAD_DIGITS 8

/* Sets what the four displays show; the texts are copied. It only stores the values (nothing goes out to the boards until RefreshDisplay or
   ScanKeyboardAndRefreshDisplay runs) and is cheap: it is called when a text changed. */
void UpdateDisplay(const char *kg, const char *litres, const char *density, const char *keypad);

/* One pass over the display board: the 2 x 6 digits and the 1 x 4 digits are sent to it. Takes 8 to 10 ms. The digits only stay lit while this
   (and the next function) is called again and again. */
void RefreshDisplay(void);

/* Scans the keyboard and, in the same time, refreshes the display of the keyboard board (6 to 8 digits). Takes about 20 ms. Returns one key code of
   ui.h (UI_KEY_0 .. UI_KEY_9, UI_KEY_POINT, UI_KEY_ENTER, UI_KEY_CLEAR, UI_KEY_SELECT) for each new press and UI_KEY_NONE (0) otherwise: it
   debounces itself, so a key that is held gives its code once. */
int ScanKeyboardAndRefreshDisplay(void);

#endif
