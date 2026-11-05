#include "keyboard.h"

#include "delay.h"

#include <string.h>

/*
 * The driver of the keyboard board: 17 keys and an LED display of 5 digits behind one three-wire link (strobe, clock, one data line).
 *
 * One scan (ScanKeyboardAndRefreshDisplay) is: the display memory goes out (SendLcdDataToKeyboard: 5 bytes), two commands that start the key scan
 * (SendCommand1ToKeyboard, SendCommand2ToKeyboard), and the board clocks out three bytes of keys (ScanKeyColumn). The pins, the commands and the waits are the ones of the old driver that
 * worked with the keyboard; what is new is how the keys are judged (three scans, a code for each new press) and how the display memory is made from a text.
 */

#ifdef KEYBOARD_HOST_TEST

/* the tests on the PC: the pins, the data line and the milliseconds are functions that the test records and feeds (the microseconds are delay_us, which the test
   provides too) */
enum { KB_PIN_SEL = 0, KB_PIN_CLK = 1, KB_PIN_DATA = 2 };
extern void keyboard_pin(int pin, int high);
extern void keyboard_data_mode(int output);
extern int keyboard_data_read(void);
extern void keyboard_wait_ms(uint32_t ms);

#else

#include "main.h"

enum { KB_PIN_SEL = 0, KB_PIN_CLK = 1, KB_PIN_DATA = 2 };

static void keyboard_pin(int pin, int high)
{
    const GPIO_PinState level = high ? GPIO_PIN_SET : GPIO_PIN_RESET;

    switch (pin) {
    case KB_PIN_SEL:
        HAL_GPIO_WritePin(KEY_SEL_GPIO_Port, KEY_SEL_Pin, level);
        break;
    case KB_PIN_CLK:
        HAL_GPIO_WritePin(KEY_CLK_GPIO_Port, KEY_CLK_Pin, level);
        break;
    default:
        HAL_GPIO_WritePin(KEY_DATA_GPIO_Port, KEY_DATA_Pin, level);
        break;
    }
}

/* the data line is an output (push pull) while the board is written to and a floating input while it is read */
static void keyboard_data_mode(int output)
{
    GPIO_InitTypeDef init = {0};

    init.Pin = KEY_DATA_Pin;
    init.Mode = output ? GPIO_MODE_OUTPUT_PP : GPIO_MODE_INPUT;
    init.Pull = GPIO_NOPULL;
    init.Speed = GPIO_SPEED_FREQ_LOW;
    HAL_GPIO_Init(KEY_DATA_GPIO_Port, &init);
}

static int keyboard_data_read(void)
{
    return HAL_GPIO_ReadPin(KEY_DATA_GPIO_Port, KEY_DATA_Pin) == GPIO_PIN_SET;
}

static void keyboard_wait_ms(uint32_t ms)
{
    HAL_Delay(ms);
}

#endif

#define SEL(high) keyboard_pin(KB_PIN_SEL, (high))
#define CLK(high) keyboard_pin(KB_PIN_CLK, (high))
#define DATA(high) keyboard_pin(KB_PIN_DATA, (high))

/* ---------------------------------------------------------------- the display memory */

/* The display: 5 digits of 7 segments and a point, in a memory of 5 bytes. Digits 2, 3 and 4 have a byte each; digits 0 and 1 (the two on the right) share bytes 0 and
   1 by their nibbles: the low half of the glyph of digit 0 is in byte 0 and its high half in byte 1, the high half of the glyph of digit 1 is in byte 0 and its low half
   in byte 1 (each half stays where it is in the glyph). The segments are not wired alike on every digit: digit 1 has its own wiring, the others have the first one
   (these are the two tables kbcd_map of the old keyboard.c, as segments). */
#define LCD_BYTES UI_KEYPAD_DIGITS

enum { WIRING_A = 0, WIRING_B = 1 };

static const uint8_t seg_bit[2][7] = {                                 /* the bits of the segments a to g on each wiring */
    {0x08, 0x80, 0x20, 0x01, 0x02, 0x04, 0x40},
    {0x80, 0x08, 0x02, 0x10, 0x20, 0x40, 0x04}
};
static const uint8_t dot_bit[2] = {0x10, 0x01};                        /* the point on each wiring */

/* for each digit, counted from the right: its wiring and the bytes of the low and of the high half of its glyph */
static const struct {
    uint8_t wiring;
    uint8_t low_byte;
    uint8_t high_byte;
} digit_layout[UI_KEYPAD_DIGITS] = {
    {WIRING_A, 0, 1}, {WIRING_B, 1, 0}, {WIRING_A, 2, 2}, {WIRING_A, 3, 3}, {WIRING_A, 4, 4}
};

/* the display memory that SendLcdDataToKeyboard sends */
static uint8_t keylcd_buf[LCD_BYTES];

/* the digits 0 to 9 as segments a (bit 0) to g (bit 6) */
static const uint8_t digit_segments[10] = {0x3f, 0x06, 0x5b, 0x4f, 0x66, 0x6d, 0x7d, 0x07, 0x7f, 0x6f};

/* a character as segments (a blank, or a character that cannot be shown, is dark) */
static uint8_t character_segments(char c)
{
    if (c >= '0' && c <= '9') {
        return digit_segments[c - '0'];
    }
    if (c == '-') {
        return 0x40;
    }

    return 0;
}

static uint8_t segments_to_byte(uint8_t wiring, uint8_t segments)
{
    uint8_t out = 0;
    int s;

    for (s = 0; s < 7; ++s) {
        if (segments & (1u << s)) {
            out = (uint8_t)(out | seg_bit[wiring][s]);
        }
    }

    return out;
}

/* a glyph (its 8 bits) of a digit into the display memory */
static void put_glyph(uint8_t digit, uint8_t glyph)
{
    keylcd_buf[digit_layout[digit].low_byte] = (uint8_t)(keylcd_buf[digit_layout[digit].low_byte] | (glyph & 0x0f));
    keylcd_buf[digit_layout[digit].high_byte] = (uint8_t)(keylcd_buf[digit_layout[digit].high_byte] | (glyph & 0xf0));
}

/* The text of the value into the display memory: right aligned, one character for each digit; a '.' lights the point of the digit before it and takes no digit; a text
   longer than the digits loses its left end. */
static void render(const char *value)
{
    size_t at = value ? strlen(value) : 0;
    uint8_t digit = 0;
    int point = 0;

    memset(keylcd_buf, 0, sizeof keylcd_buf);
    while (at > 0 && digit < UI_KEYPAD_DIGITS) {
        const char c = value[--at];
        const uint8_t wiring = digit_layout[digit].wiring;
        uint8_t glyph;

        if (c == '.') {
            point = 1;
            continue;
        }
        glyph = segments_to_byte(wiring, character_segments(c));
        if (point) {
            glyph = (uint8_t)(glyph | dot_bit[wiring]);
            point = 0;
        }
        put_glyph(digit, glyph);
        ++digit;
    }
}

/* ---------------------------------------------------------------- the link */

/* one byte, the highest bit first: the clock goes low, the data is set, the clock goes high (the board takes the bit then) */
void WriteByteToKeyboard(uint8_t data)
{
    int bit;

    for (bit = 0; bit < 8; ++bit) {
        CLK(0);
        delay_us(1);
        DATA(data & 0x80);
        delay_us(1);
        CLK(1);
        delay_us(1);
        data = (uint8_t)(data << 1);
    }
}

/* a start of a command: the strobe goes high and low again */
static void strobe(uint32_t high_us, uint32_t low_us)
{
    SEL(1);
    delay_us(high_us);
    SEL(0);
    if (low_us > 0) {
        delay_us(low_us);
    }
}

/* the display memory: the command 0x02, the command 0x03 and the bytes, and the strobe goes high at the end */
void SendLcdDataToKeyboard(void)
{
    int i;

    strobe(2, 0);
    keyboard_data_mode(1);

    WriteByteToKeyboard(0x02);
    delay_us(5);

    strobe(2, 3);

    WriteByteToKeyboard(0x03);
    for (i = 0; i < LCD_BYTES; ++i) {
        WriteByteToKeyboard(keylcd_buf[i]);
    }

    delay_us(5);
    SEL(1);
}

/* the first command that starts the key scan: 0x3C and 0x69 */
void SendCommand1ToKeyboard(void)
{
    strobe(2, 2);
    WriteByteToKeyboard(0x3C);
    delay_us(5);
    strobe(2, 2);
    WriteByteToKeyboard(0x69);
}

/* the second one: 0x42, after which the board clocks out the keys */
void SendCommand2ToKeyboard(void)
{
    strobe(2, 2);
    WriteByteToKeyboard(0x42);
}

/* one byte of keys from the board, the highest bit first: the data line is an input, the clock goes low, the bit is read, the clock goes high */
uint8_t ScanKeyColumn(uint8_t col)
{
    uint8_t data = 0;
    int bit;

    keyboard_data_mode(0);
    delay_us(10);
    for (bit = 0; bit < 8; ++bit) {
        data = (uint8_t)(data << 1);
        CLK(0);
        delay_us(1);
        if (keyboard_data_read()) {
            data |= 0x01;
        }
        delay_us(1);
        CLK(1);
        delay_us(1);
    }
    (void)col;

    return data;
}

/* ---------------------------------------------------------------- the keys */

#define KEY_BITS (KEY_1 | KEY_2 | KEY_3 | KEY_4 | KEY_5 | KEY_6 | KEY_7 | KEY_8 | KEY_9 | KEY_0 | KEY_DOT | KEY_CLEAR | KEY_NEXT | KEY_PRINT | KEY_START | KEY_STOP | KEY_PREV)
#define DEBOUNCE_SCANS 3

/* the code of each key of the board: the order is the order in which keys that went down together are given */
static const struct {
    uint32_t mask;
    int key;
} key_map[] = {
    {KEY_0, UI_KEY_0}, {KEY_1, UI_KEY_1}, {KEY_2, UI_KEY_2}, {KEY_3, UI_KEY_3}, {KEY_4, UI_KEY_4},
    {KEY_5, UI_KEY_5}, {KEY_6, UI_KEY_6}, {KEY_7, UI_KEY_7}, {KEY_8, UI_KEY_8}, {KEY_9, UI_KEY_9},
    {KEY_DOT, UI_KEY_DOT}, {KEY_CLEAR, UI_KEY_CLEAR}, {KEY_NEXT, UI_KEY_NEXT}, {KEY_PREV, UI_KEY_PREV},
    {KEY_PRINT, UI_KEY_PRINT}, {KEY_START, UI_KEY_START}, {KEY_STOP, UI_KEY_STOP}
};

static uint32_t scan_window[DEBOUNCE_SCANS];     /* the keys found by the last scans (the 17 key bits) */
static uint8_t window_at;
static uint32_t key_state;                       /* the keys that count as pressed */
static uint32_t key_pending;                     /* pressed since the last code that was given and not given yet */

/* The keys of a new scan. A key goes down when the last three scans all had it and goes up when none of them has it. Returns the keys that went down. */
static uint32_t judge(uint32_t keys)
{
    uint32_t all = KEY_BITS;
    uint32_t any = 0;
    uint32_t before = key_state;
    int i;

    scan_window[window_at] = keys & KEY_BITS;
    window_at = (uint8_t)((window_at + 1) % DEBOUNCE_SCANS);
    for (i = 0; i < DEBOUNCE_SCANS; ++i) {
        all &= scan_window[i];
        any |= scan_window[i];
    }
    key_state = all | (key_state & any);

    return key_state & ~before;
}

int ScanKeyboardAndRefreshDisplay(const char *value)
{
    uint32_t keys = 0;
    unsigned int i;

    render(value);

    SendLcdDataToKeyboard();
    keyboard_wait_ms(2);

    SendCommand1ToKeyboard();
    delay_us(500);
    SendCommand2ToKeyboard();

    keyboard_wait_ms(5);
    keys |= (uint32_t)ScanKeyColumn(0) << 24;
    keyboard_wait_ms(5);
    keys |= (uint32_t)ScanKeyColumn(1) << 16;
    keyboard_wait_ms(5);
    keys |= (uint32_t)ScanKeyColumn(2) << 8;

    key_pending |= judge(keys);
    key_pending &= key_state;          /* a key that was let go before its code was given is forgotten */
    for (i = 0; i < sizeof key_map / sizeof key_map[0]; ++i) {
        if (key_pending & key_map[i].mask) {
            key_pending &= ~key_map[i].mask;

            return key_map[i].key;
        }
    }

    return UI_KEY_NONE;
}
