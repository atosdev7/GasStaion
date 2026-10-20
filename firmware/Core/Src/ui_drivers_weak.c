#include "ui_drivers.h"

/* Empty versions of the LCD and keyboard drivers: yours (without __attribute__((weak))) take their place at link time. */

__attribute__((weak)) void ui_lcd_init(void)
{
}

__attribute__((weak)) void ui_lcd_clear(void)
{
}

__attribute__((weak)) void ui_lcd_print(uint8_t row, uint8_t col, const char *text)
{
    (void)row;
    (void)col;
    (void)text;
}

__attribute__((weak)) int ui_key_read(void)
{
    return 0;
}
