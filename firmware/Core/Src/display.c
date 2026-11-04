#include "display.h"

#include <string.h>

/*
 * The driver of the display board (see display.h for what the three texts are).
 *
 * The board is a 16-byte display memory behind a three-wire serial link (select, clock, data):
 *
 *   bytes  0 ..  3   the density, 4 digits         (byte 0 is its last digit)
 *   bytes  4 ..  9   the kg, 6 digits              (bytes 7, 8, 9 are its last three digits, bytes 6, 5, 4 the first ones: 4 is the leftmost)
 *   bytes 10 .. 15   the litres, 6 digits          (13, 14, 15 the last three, 12, 11, 10 the first ones)
 *
 * The segments are not wired alike on every digit: there are four wirings (the rows of bcd_map), and lcd_bcd says which row a byte uses. All of this is the
 * wiring of the board and is kept exactly as it was proven on the glass; only the way the bytes are made changed (a text, not a float).
 */

#ifdef DISPLAY_HOST_TEST

/* the tests on the PC: the pins and the waits are two functions that the test records */
enum { DISPLAY_PIN_SEL = 0, DISPLAY_PIN_CLK = 1, DISPLAY_PIN_DATA = 2 };
extern void display_pin(int pin, int high);
extern void display_wait_us(uint32_t us);

#else

#include "main.h"

enum { DISPLAY_PIN_SEL = 0, DISPLAY_PIN_CLK = 1, DISPLAY_PIN_DATA = 2 };

static void display_pin(int pin, int high)
{
    const GPIO_PinState level = high ? GPIO_PIN_SET : GPIO_PIN_RESET;

    switch (pin) {
    case DISPLAY_PIN_SEL:
        HAL_GPIO_WritePin(LCD_SEL_GPIO_Port, LCD_SEL_Pin, level);
        break;
    case DISPLAY_PIN_CLK:
        HAL_GPIO_WritePin(LCD_CLK_GPIO_Port, LCD_CLK_Pin, level);
        break;
    default:
        HAL_GPIO_WritePin(LCD_DATA_GPIO_Port, LCD_DATA_Pin, level);
        break;
    }
}

/* microseconds by TIM6, which main.c sets up as a free running 16 bit counter of the timer clock (no prescaler) and which nothing else uses (port.c leaves TIM6
   and TIM7 to the display and the keyboard). The longest wait here is 150 us, far below the 4 ms the counter takes to turn round. */
static void display_wait_us(uint32_t us)
{
    const uint32_t ticks = us * (SystemCoreClock / 1000000u);
    uint16_t start;

    if (!(TIM6->CR1 & TIM_CR1_CEN)) {
        TIM6->CR1 |= TIM_CR1_CEN;
    }
    start = (uint16_t)TIM6->CNT;
    while ((uint16_t)((uint16_t)TIM6->CNT - start) < ticks) {
    }
}

#endif

#define SEL(high) display_pin(DISPLAY_PIN_SEL, (high))
#define CLK(high) display_pin(DISPLAY_PIN_CLK, (high))
#define DATA(high) display_pin(DISPLAY_PIN_DATA, (high))

/* ---------------------------------------------------------------- the segments */

/* the segments of the digits 0 to 9 on the four wirings, and the bit of the decimal point of each wiring */
static const uint8_t bcd_map[4][10] = {
    {0xf5, 0x05, 0xd3, 0x97, 0x27, 0xb6, 0xf6, 0x15, 0xf7, 0xb7},
    {0x5f, 0x50, 0x3d, 0x79, 0x72, 0x6b, 0x6f, 0x51, 0x7f, 0x7b},
    {0xaf, 0xa0, 0xcb, 0xe9, 0xe4, 0x6d, 0x6f, 0xa8, 0xef, 0xed},
    {0xfa, 0x0a, 0xbc, 0x9e, 0x4e, 0xd6, 0xf6, 0x8a, 0xfe, 0xde}   /* the 5 of this row was 0x6d, the 5 of the row before; the wiring of the row makes it 0xd6 */
};
static const uint8_t bcd_dot[4] = {0x08, 0x80, 0x10, 0x01};

/* the bit of each segment a, b, c, d, e, f, g on each wiring (what the rows of bcd_map are made of: the tests check that they give the digits again) */
static const uint8_t seg_bit[4][7] = {
    {0x10, 0x01, 0x04, 0x80, 0x40, 0x20, 0x02},
    {0x01, 0x10, 0x40, 0x08, 0x04, 0x02, 0x20},
    {0x08, 0x80, 0x20, 0x01, 0x02, 0x04, 0x40},
    {0x80, 0x08, 0x02, 0x10, 0x20, 0x40, 0x04}
};

/* the wiring of each of the 16 bytes */
static const uint8_t lcd_bcd[16] = {3, 2, 2, 2, 0, 0, 0, 1, 1, 1, 0, 0, 0, 1, 1, 1};

/* the display memory that RefreshDisplay sends */
static uint8_t lcd_buf[16];

/* the byte of each digit of a display, the last digit first */
static const uint8_t kg_byte[UI_KG_DIGITS] = {7, 8, 9, 6, 5, 4};
static const uint8_t litres_byte[UI_LITRES_DIGITS] = {13, 14, 15, 12, 11, 10};
static const uint8_t density_byte[UI_DENSITY_DIGITS] = {0, 1, 2, 3};

/* the digit (counted from the right, from 0) that carries the decimal point of every display: two decimals */
#define POINT_DIGIT 2

/* a letter or a dash in segments a (bit 0) to g (bit 6); 0xff when the character is not one of them */
static uint8_t letter_segments(char c)
{
    switch (c) {
    case '-':
        return 0x40;               /* g */
    case 'E':
        return 0x79;               /* a d e f g */
    case 'r':
        return 0x50;               /* e g */
    case 'b':
        return 0x7c;               /* c d e f g */
    case 'A':
        return 0x77;               /* a b c e f g */
    case 'L':
        return 0x38;               /* d e f */
    default:
        return 0xff;
    }
}

/* the byte of a character on a wiring (a blank, or a character that the display cannot show, is dark) */
static uint8_t glyph(uint8_t wiring, char c)
{
    uint8_t segments;
    uint8_t out = 0;
    int s;

    if (c >= '0' && c <= '9') {
        return bcd_map[wiring][c - '0'];
    }
    segments = letter_segments(c);
    if (segments == 0xff) {
        return 0;
    }
    for (s = 0; s < 7; ++s) {
        if (segments & (1u << s)) {
            out = (uint8_t)(out | seg_bit[wiring][s]);
        }
    }

    return out;
}

/* A text on one display: right aligned, one character for each digit, a '.' after a digit lights the point of that digit and takes no digit. A text longer than the
   display loses its left end; the digits that the text does not reach stay dark. */
static void render(const uint8_t *byte_of_digit, uint8_t digits, const char *text)
{
    uint8_t digit;
    size_t at = strlen(text);
    int point = 0;

    for (digit = 0; digit < digits; ++digit) {
        lcd_buf[byte_of_digit[digit]] = 0;
    }
    digit = 0;
    while (at > 0 && digit < digits) {
        const char c = text[--at];
        const uint8_t index = byte_of_digit[digit];

        if (c == '.') {
            point = 1;      /* the digit to the left of it carries the point */
            continue;
        }
        lcd_buf[index] = glyph(lcd_bcd[index], c);
        if (point) {
            lcd_buf[index] = (uint8_t)(lcd_buf[index] | bcd_dot[lcd_bcd[index]]);
            point = 0;
        }
        ++digit;
    }
}

/* an amount in hundredths as a text: "0.00", "15.00", "9999.99" (no zeros in front but the three digits of the units and the two decimals) */
static void hundredths_text(char *out, uint32_t x100)
{
    char digits[12];
    int n = 0;
    uint32_t whole = x100 / 100u;
    int i;

    do {
        digits[n++] = (char)('0' + whole % 10u);
        whole /= 10u;
    } while (whole > 0);
    for (i = n - 1; i >= 0; --i) {
        *out++ = digits[i];
    }
    *out++ = '.';
    *out++ = (char)('0' + (x100 / 10u) % 10u);
    *out++ = (char)('0' + x100 % 10u);
    *out = 0;
}

/* The number of a float on a display of `digits` digits, two decimals, rounded (not cut: 0.29 * 100 is 28.999999 in a float and must show 0.29). A value that is not
   a number or is below zero shows dashes, one that does not fit shows Err. */
static void show_float(const uint8_t *byte_of_digit, uint8_t digits, float value)
{
    char text[16];
    float limit = 1.0f;       /* the hundredths that no longer fit: 10 to the number of digits */
    uint8_t d;

    for (d = 0; d < digits; ++d) {
        limit *= 10.0f;
    }
    if (!(value >= 0.0f)) {
        render(byte_of_digit, digits, "----.--");   /* (a '.' needs no digit; the text is cut to the digits the display has) */

        return;
    }
    if (value * 100.0f + 0.5f >= limit) {
        render(byte_of_digit, digits, "Err");

        return;
    }
    hundredths_text(text, (uint32_t)(value * 100.0f + 0.5f));
    render(byte_of_digit, digits, text);
}

void ShowAmountKg(float amount)
{
    show_float(kg_byte, UI_KG_DIGITS, amount);
}

void ShowAmountL(float amount)
{
    show_float(litres_byte, UI_LITRES_DIGITS, amount);
}

void ShowDensity(float val)
{
    show_float(density_byte, UI_DENSITY_DIGITS, val);
}

/* The density as the board shows it. The board has one point on the density, after its second digit: kg per litre with two decimals (0.83), so "0.830" is
   rounded to hundredths. Anything that is not a number with a point ("----", "Err") goes to the display as it is. */
static void show_density_text(const char *text)
{
    uint32_t whole = 0;
    uint32_t thousandths = 0;
    int decimals = 0;
    int points = 0;
    int others = 0;
    const char *p;
    char out[16];
    uint32_t hundredths;

    for (p = text; *p; ++p) {
        if (*p == '.') {
            ++points;
        } else if (*p >= '0' && *p <= '9') {
            if (points == 0) {
                whole = whole * 10u + (uint32_t)(*p - '0');
            } else if (decimals < 3) {
                thousandths = thousandths * 10u + (uint32_t)(*p - '0');
                ++decimals;
            }
        } else if (*p != ' ') {
            ++others;
        }
    }
    if (points != 1 || others != 0 || decimals == 0) {
        render(density_byte, UI_DENSITY_DIGITS, text);

        return;
    }
    while (decimals < 3) {
        thousandths *= 10u;
        ++decimals;
    }
    hundredths = (whole * 1000u + thousandths + 5u) / 10u;
    if (hundredths > 9999u) {
        render(density_byte, UI_DENSITY_DIGITS, "Err");

        return;
    }
    hundredths_text(out, hundredths);
    render(density_byte, UI_DENSITY_DIGITS, out);
}

void UpdateDisplay(const char *kg, const char *litres, const char *density)
{
    render(kg_byte, UI_KG_DIGITS, kg ? kg : "");
    render(litres_byte, UI_LITRES_DIGITS, litres ? litres : "");
    show_density_text(density ? density : "");
}

/* ---------------------------------------------------------------- the serial link */

/* one byte, the highest bit first: the data is set, then the clock goes low and high again */
void WriteByteToLcd(uint8_t data)
{
    int bit;

    display_wait_us(5);
    for (bit = 0; bit < 8; ++bit) {
        DATA(data & 0x80);
        display_wait_us(5);
        CLK(0);
        display_wait_us(1);
        CLK(1);
        display_wait_us(5);
        data = (uint8_t)(data << 1);
    }
}

/* a word of 12 bits, the highest bit first, with a slower clock */
void WriteChkToLcd(uint16_t chk)
{
    int bit;

    for (bit = 0; bit < 12; ++bit) {
        DATA(chk & 0x800);
        display_wait_us(10);
        CLK(0);
        display_wait_us(10);
        CLK(1);
        display_wait_us(10);
        chk = (uint16_t)(chk << 1);
    }
}

/* The display memory to the board: select low, a start edge of the clock with the data high, the command 0x40, the bytes, select high; then the five words that
   the board wants after the memory (select low for each). 3.7 ms of waits (3727 us) and the time of the pin writes. */
void WriteBlockToLcd(const uint8_t *block, uint8_t length)
{
    static const uint16_t chk_buf[5] = {0x9B6, 0x802, 0x830, 0x806, 0x852};
    uint8_t i;

    SEL(0);
    display_wait_us(50);

    DATA(1);
    display_wait_us(10);
    CLK(0);
    display_wait_us(1);
    CLK(1);
    display_wait_us(10);

    WriteByteToLcd(0x40);
    for (i = 0; i < length; ++i) {
        WriteByteToLcd(block[i]);
    }

    display_wait_us(10);
    SEL(1);
    display_wait_us(150);
    for (i = 0; i < 5; ++i) {
        SEL(0);
        display_wait_us(10);
        WriteChkToLcd(chk_buf[i]);
        display_wait_us(10);
        SEL(1);
        display_wait_us(3);
    }
}

void RefreshDisplay(void)
{
    WriteBlockToLcd(lcd_buf, (uint8_t)sizeof lcd_buf);
}
