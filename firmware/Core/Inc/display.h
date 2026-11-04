#ifndef DISPLAY_H
#define DISPLAY_H

/*
 * The driver of the display board (display.c). The functions are called from the main loop only, never from an interrupt, and they BLOCK for the time given below.
 *
 * The display board has 2 x 6 digits (kg, litres) and 1 x 4 digits (density). The text of a display has one character for each digit, right aligned, so its
 * length is the number of digits (blanks in front), and a '.' after a digit lights the point of that digit (the '.' takes no digit). The characters:
 * '0'..'9', ' ' (blank), '-', and the letters 'E', 'r', 'b', 'A', 'L' (for "Err" and "bAL"); anything else is dark. Examples for 6 digits: "  15.00" is 15.00;
 * "   Err"; "----.--". The board has one decimal point on each display, after the second digit from the right, so the kg and the litres have two decimals and the
 * density, which the board shows as kg per litre with two decimals, is its text "0.830" rounded to hundredths: " 0.83". (The LCD of the keyboard board is in keyboard.h.)
 */

#include <stdint.h>

#define UI_KG_DIGITS 6
#define UI_LITRES_DIGITS 6
#define UI_DENSITY_DIGITS 4

/* Sets what the three displays of the display board show; the texts are copied. It only stores the values (nothing goes out to the board until RefreshDisplay
   runs) and is cheap: it is called when a text changed. */
void UpdateDisplay(const char *kg, const char *litres, const char *density);

/* One pass over the display board: the display memory (the 2 x 6 digits and the 1 x 4 digits) is sent to it. Takes about 4 ms (the waits of the link are 3.7 ms,
   and the pin writes come on top). The digits only stay lit while this is called again and again. */
void RefreshDisplay(void);

/* The same displays from a number, two decimals, rounded: ShowAmountKg(15) is "15.00". A value below zero (or not a number) shows dashes, one that does not fit
   (10000 kg or litres, 100 for the density) shows Err. Like UpdateDisplay they only store; RefreshDisplay sends. */
void ShowAmountKg(float amount);
void ShowAmountL(float amount);
void ShowDensity(float val);

/* The serial link of the board (kept public for the tests; the main loop only calls RefreshDisplay). WriteBlockToLcd sends the command 0x40 and `length`
   bytes of display memory (16 for the board) and then the five check words; WriteByteToLcd and WriteChkToLcd send a byte (8 bits) and a word of 12 bits, the
   highest bit first. */
void WriteBlockToLcd(const uint8_t *block, uint8_t length);
void WriteByteToLcd(uint8_t data);
void WriteChkToLcd(uint16_t chk);

#endif
