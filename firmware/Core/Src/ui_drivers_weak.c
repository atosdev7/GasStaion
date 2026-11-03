#include "ui_drivers.h"

/* Empty versions of the display and keyboard drivers: yours (without __attribute__((weak))) take their place at link time. */

__attribute__((weak)) void UpdateDisplay(const char *kg, const char *litres, const char *density, const char *keypad)
{
    (void)kg;
    (void)litres;
    (void)density;
    (void)keypad;
}

__attribute__((weak)) void RefreshDisplay(void)
{
}

__attribute__((weak)) int ScanKeyboardAndRefreshDisplay(void)
{
    return 0;
}
