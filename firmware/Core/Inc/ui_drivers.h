#ifndef UI_DRIVERS_H
#define UI_DRIVERS_H

/*
 * The LCD and keyboard drivers of the board. Define these four functions in your driver code (the pins are LCD_SEL, LCD_CLK, LCD_DATA and
 * KEY_SEL, KEY_CLK, KEY_DATA in main.h). ui_drivers_weak.c holds empty versions so that the project links before yours are in; yours
 * replace them without any other change.
 *
 * The functions are called from the main loop only, never from an interrupt.
 */

#include <stdint.h>

/* Sets the LCD up. Called once at start. */
void ui_lcd_init(void);

/* Clears the screen. */
void ui_lcd_clear(void);

/* Writes the zero-terminated ASCII text with its first character at the row (0 is the top line) and the column (0 is the left). The text is
   never longer than the line: UI_LCD_COLS characters in ui.h (16 for now; change that define to the real size of the display). */
void ui_lcd_print(uint8_t row, uint8_t col, const char *text);

/* The keyboard: returns one key code of ui.h (UI_KEY_0 .. UI_KEY_9, UI_KEY_POINT, UI_KEY_ENTER, UI_KEY_CLEAR, UI_KEY_SELECT) for each new
   press and UI_KEY_NONE otherwise. It debounces itself and does not wait: it is called at every pass of the main loop. */
int ui_key_read(void);

#endif
