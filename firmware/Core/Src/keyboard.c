#include "keyboard.h"

#include <string.h>

/*
 * The driver of the keyboard board: 17 keys and an LED display of 8 digits behind one three-wire link (strobe, clock, one data line).
 *
 * One scan (ScanKeyboardAndRefreshDisplay) is: the display memory goes out (SendLcdDataToKeyboard), two commands that start the key scan (SendCommand1ToKeyboard,
 * SendCommand2ToKeyboard), and the board clocks out three bytes of keys (ScanKeyColumn). The pins, the commands and the waits are the ones of the old driver that
 * worked with the keyboard; what is new is how the keys are judged (three scans, a code for each new press) and how the display memory is made from a text.
 */

#ifdef KEYBOARD_HOST_TEST

/* the tests on the PC: the pins, the waits and the data line are functions that the test records and feeds */
enum { KB_PIN_SEL = 0, KB_PIN_CLK = 1, KB_PIN_DATA = 2 };
extern void keyboard_pin(int pin, int high);
extern void keyboard_data_mode(int output);
extern int keyboard_data_read(void);
extern void keyboard_wait_us(uint32_t us);
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

/* microseconds by TIM7, which main.c sets up as a free running 16 bit counter of the timer clock (no prescaler) and which nothing else uses (the display uses TIM6;
   port.c leaves both to the display and the keyboard). The longest wait here is 500 us. */
static void keyboard_wait_us(uint32_t us)
{
    const uint32_t ticks = us * (SystemCoreClock / 1000000u);
    uint16_t start;

    if (!(TIM7->CR1 & TIM_CR1_CEN)) {
        TIM7->CR1 |= TIM_CR1_CEN;
    }
    start = (uint16_t)TIM7->CNT;
    while ((uint16_t)((uint16_t)TIM7->CNT - start) < ticks) {
    }
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

/* The display: 8 digits of 7 segments and a point, a byte for each. The wiring of the segments on the byte is the one the old driver had for these digits (the
   table kbcd_map[0] of the old keyboard.c; the second table of the old file, which is the wiring of the lowest digit of the display board, is not used here). */
#define LCD_BYTES UI_KEYPAD_DIGITS

static const uint8_t seg_bit[7] = {0x08, 0x80, 0x20, 0x01, 0x02, 0x04, 0x40};      /* the bits of the segments a to g */
static const uint8_t dot_bit = 0x10;

/* the display memory that SendLcdDataToKeyboard sends */
static uint8_t keylcd_buf[LCD_BYTES];

/* The byte of each digit, counted from the right: digit 0 is the last digit of the value, digit 6 the first one, digit 7 (the left end) the code. */
static const uint8_t digit_byte[UI_KEYPAD_DIGITS] = {0, 1, 2, 3, 4, 5, 6, 7};

/* the digits 0 to 9 as segments a (bit 0) to g (bit 6) */
static const uint8_t digit_segments[10] = {0x3f, 0x06, 0x5b, 0x4f, 0x66, 0x6d, 0x7d, 0x07, 0x7f, 0x6f};

/* the letters A to Z as segments (a lower-case letter is the same letter); 0 where seven segments cannot make it */
static const uint8_t letter_segments[26] = {
    0x77, 0x7c, 0x39, 0x5e, 0x79, 0x71, 0x3d, 0x76, 0x30, 0x1e, 0x00, 0x38, 0x00,     /* A b C d E F G H I J K L M */
    0x54, 0x5c, 0x73, 0x67, 0x50, 0x6d, 0x78, 0x3e, 0x3e, 0x00, 0x00, 0x6e, 0x5b      /* n o P q r S t U V W X y Z */
};

/* a character as segments (a blank, or a character that cannot be shown, is dark) */
static uint8_t character_segments(char c)
{
    if (c >= '0' && c <= '9') {
        return digit_segments[c - '0'];
    }
    if (c >= 'A' && c <= 'Z') {
        return letter_segments[c - 'A'];
    }
    if (c >= 'a' && c <= 'z') {
        return letter_segments[c - 'a'];
    }
    if (c == '-') {
        return 0x40;
    }

    return 0;
}

static uint8_t segments_to_byte(uint8_t segments)
{
    uint8_t out = 0;
    int s;

    for (s = 0; s < 7; ++s) {
        if (segments & (1u << s)) {
            out = (uint8_t)(out | seg_bit[s]);
        }
    }

    return out;
}

/* The code digit and the text of the value into the display memory. The value is right aligned, one character for each digit, and a '.' lights the point of the
   digit before it and takes no digit; a text longer than the digits loses its left end. */
static void render(char code, const char *value)
{
    size_t at = value ? strlen(value) : 0;
    uint8_t digit = 0;
    int point = 0;

    memset(keylcd_buf, 0, sizeof keylcd_buf);
    while (at > 0 && digit < UI_KEYPAD_VALUE_DIGITS) {
        const char c = value[--at];

        if (c == '.') {
            point = 1;
            continue;
        }
        keylcd_buf[digit_byte[digit]] = segments_to_byte(character_segments(c));
        if (point) {
            keylcd_buf[digit_byte[digit]] = (uint8_t)(keylcd_buf[digit_byte[digit]] | dot_bit);
            point = 0;
        }
        ++digit;
    }
    keylcd_buf[digit_byte[UI_KEYPAD_VALUE_DIGITS]] = segments_to_byte(character_segments(code));
}

/* ---------------------------------------------------------------- the link */

/* one byte, the highest bit first: the clock goes low, the data is set, the clock goes high (the board takes the bit then) */
void WriteByteToKeyboard(uint8_t data)
{
    int bit;

    for (bit = 0; bit < 8; ++bit) {
        CLK(0);
        keyboard_wait_us(1);
        DATA(data & 0x80);
        keyboard_wait_us(1);
        CLK(1);
        keyboard_wait_us(1);
        data = (uint8_t)(data << 1);
    }
}

/* a start of a command: the strobe goes high and low again */
static void strobe(uint32_t high_us, uint32_t low_us)
{
    SEL(1);
    keyboard_wait_us(high_us);
    SEL(0);
    if (low_us > 0) {
        keyboard_wait_us(low_us);
    }
}

/* the display memory: the command 0x02, the command 0x03 and the bytes, and the strobe goes high at the end */
void SendLcdDataToKeyboard(void)
{
    int i;

    strobe(2, 0);
    keyboard_data_mode(1);

    WriteByteToKeyboard(0x02);
    keyboard_wait_us(5);

    strobe(2, 3);

    WriteByteToKeyboard(0x03);
    for (i = 0; i < LCD_BYTES; ++i) {
        WriteByteToKeyboard(keylcd_buf[i]);
    }

    keyboard_wait_us(5);
    SEL(1);
}

/* the first command that starts the key scan: 0x3C and 0x69 */
void SendCommand1ToKeyboard(void)
{
    strobe(2, 2);
    WriteByteToKeyboard(0x3C);
    keyboard_wait_us(5);
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
    keyboard_wait_us(10);
    for (bit = 0; bit < 8; ++bit) {
        data = (uint8_t)(data << 1);
        CLK(0);
        keyboard_wait_us(1);
        if (keyboard_data_read()) {
            data |= 0x01;
        }
        keyboard_wait_us(1);
        CLK(1);
        keyboard_wait_us(1);
    }
    (void)col;

    return data;
}

/* ---------------------------------------------------------------- the keys */

#define KEY_BITS (KEY_1 | KEY_2 | KEY_3 | KEY_4 | KEY_5 | KEY_6 | KEY_7 | KEY_8 | KEY_9 | KEY_0 | KEY_DOT | KEY_CLEAR | KEY_NEXT | KEY_PRINT | KEY_START | KEY_STOP | KEY_PREV)
#define DEBOUNCE_SCANS 3

/* what each key of the board is for the program: the order is the order in which keys that went down together are given */
static const struct {
    uint32_t mask;
    int key;
} key_map[] = {
    {KEY_0, UI_KEY_0}, {KEY_1, UI_KEY_1}, {KEY_2, UI_KEY_2}, {KEY_3, UI_KEY_3}, {KEY_4, UI_KEY_4},
    {KEY_5, UI_KEY_5}, {KEY_6, UI_KEY_6}, {KEY_7, UI_KEY_7}, {KEY_8, UI_KEY_8}, {KEY_9, UI_KEY_9},
    {KEY_DOT, UI_KEY_POINT}, {KEY_CLEAR, UI_KEY_CLEAR}, {KEY_NEXT, UI_KEY_NEXT}, {KEY_PREV, UI_KEY_PREV},
    {KEY_PRINT, UI_KEY_TOTAL}, {KEY_START, UI_KEY_SET}, {KEY_STOP, UI_KEY_FUNCTION}
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

int ScanKeyboardAndRefreshDisplay(char code, const char *value)
{
    uint32_t keys = 0;
    unsigned int i;

    render(code, value);

    SendLcdDataToKeyboard();
    keyboard_wait_ms(2);

    SendCommand1ToKeyboard();
    keyboard_wait_us(500);
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
