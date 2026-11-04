#ifndef DISPLAY_H
#define DISPLAY_H

/*
 * The driver of the display board (display.c is yours: the file in the project only holds empty functions so that it builds before yours is in). The
 * functions are called from the main loop only, never from an interrupt, and they BLOCK for the time given below.
 *
 * The display board has 2 x 6 digits (kg, litres) and 1 x 4 digits (density). The text of a display has one character for each digit, right aligned, so its
 * length is the number of digits (blanks in front), and a '.' after a digit lights the point of that digit (the '.' takes no digit). The characters:
 * '0'..'9', ' ' (blank), '-', and the letters 'E', 'r', 'b', 'A', 'L' (for "Err" and "bAL"). Examples for 6 digits: "  15.00" is 15.00; "   Err";
 * "----.--"; for the 4 digits of the density "0.830". (The LCD of the keyboard board is in keyboard.h.)
 */

#include <stdint.h>

#define UI_KG_DIGITS 6
#define UI_LITRES_DIGITS 6
#define UI_DENSITY_DIGITS 4

/* Sets what the three displays of the display board show; the texts are copied. It only stores the values (nothing goes out to the board until RefreshDisplay
   runs) and is cheap: it is called when a text changed. */
void UpdateDisplay(const char *kg, const char *litres, const char *density);

/* One pass over the display board: the 2 x 6 digits and the 1 x 4 digits are sent to it. Takes 8 to 10 ms. The digits only stay lit while this is called again
   and again. */
void RefreshDisplay(void);

#endif