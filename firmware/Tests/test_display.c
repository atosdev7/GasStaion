/*
 * The display driver (Core/Src/display.c) on the PC. The pins and the waits are two functions that record what the driver does, so the serial link can be
 * compared with the one the old, proven functions made, bit by bit and wait by wait, and the digits that are sent to the glass with the old numeric functions.
 *
 * The old functions are copied below as they were (ShowAmountKg, ShowAmountL, ShowDensity, WriteBlockToLcd, WriteByteToLcd, WriteChkToLcd, with their tables), only
 * written for the recorder instead of HAL_GPIO_WritePin / delay_us, and with the missing bracket of WriteBlockToLcd put in.
 */

#define DISPLAY_HOST_TEST
#include "../Core/Src/display.c"

#include <math.h>
#include <stdio.h>
#include <stdlib.h>

static int checks, failures;

#define CHECK_EQ(a, b) do { ++checks; if ((long)(a) != (long)(b)) { ++failures; if (failures < 40) printf("  FAIL test_display.c:%d: %s == %s (%ld vs %ld)\n", __LINE__, #a, #b, (long)(a), (long)(b)); } } while (0)
#define CHECK_STR(a, b) do { ++checks; if (strcmp((a), (b)) != 0) { ++failures; printf("  FAIL test_display.c:%d: \"%s\" vs \"%s\"\n", __LINE__, (a), (b)); } } while (0)

/* ---------------------------------------------------------------- the recorder */

#define EVENTS_MAX 8192
static int events[EVENTS_MAX];
static int event_count;
static long waited_us;

#define PIN_EVENT 100000   /* pin events: PIN_EVENT + pin * 2 + level; wait events: the microseconds */

void display_pin(int pin, int high)
{
    if (event_count < EVENTS_MAX) {
        events[event_count++] = PIN_EVENT + pin * 2 + (high ? 1 : 0);
    }
}

void display_wait_us(uint32_t us)
{
    if (event_count < EVENTS_MAX) {
        events[event_count++] = (int)us;
    }
    waited_us += us;
}

static void record_reset(void)
{
    event_count = 0;
    waited_us = 0;
}

/* ---------------------------------------------------------------- the old functions, as they were */

#define GPIOA 0
#define GPIO_PIN_4 4
#define GPIO_PIN_5 5
#define GPIO_PIN_6 6
#define GPIO_PIN_SET 1
#define GPIO_PIN_RESET 0
#define HAL_GPIO_WritePin(port, pin, state) display_pin((pin) - 4, (state))
#define delay_us(us) display_wait_us(us)

static uint8_t old_bcd_map[4][10] = {
    {0xf5, 0x05, 0xd3, 0x97, 0x27, 0xb6, 0xf6, 0x15, 0xf7, 0xb7},
    {0x5f, 0x50, 0x3d, 0x79, 0x72, 0x6b, 0x6f, 0x51, 0x7f, 0x7b},
    {0xaf, 0xa0, 0xcb, 0xe9, 0xe4, 0x6d, 0x6f, 0xa8, 0xef, 0xed},
    {0xfa, 0x0a, 0xbc, 0x9e, 0x4e, 0x6d, 0xf6, 0x8a, 0xfe, 0xde}
};

static uint8_t old_bcd_dot[4] = {0x08, 0x80, 0x10, 0x01};
static uint8_t old_lcd_bcd[16] = {3, 2, 2, 2, 0, 0, 0, 1, 1, 1, 0, 0, 0, 1, 1, 1};
static uint8_t old_lcd_buf[16];

/* (the old functions took a float and cut it; here they take the number of hundredths that the float was cut to, so that the comparison does not depend on how a
   float is rounded) */
static void old_ShowAmountKg(int intval)
{
    int i = 0;
    unsigned char digit, index;

    for (i = 0; i < 6; i++) {
        digit = intval % 10;
        if (i < 3) {
            index = 7 + i;
            old_lcd_buf[index] = old_bcd_map[old_lcd_bcd[index]][digit];
            if (i == 2) {
                old_lcd_buf[index] = old_lcd_buf[index] | old_bcd_dot[old_lcd_bcd[index]];
            }
        } else {
            index = 4 + (5 - i);
            if (intval > 0) {
                old_lcd_buf[index] = old_bcd_map[old_lcd_bcd[index]][digit];
            } else {
                old_lcd_buf[index] = 0x00;
            }
        }

        intval = intval / 10;
    }
}

static void old_ShowAmountL(int intval)
{
    int i = 0;
    unsigned char digit, index;

    for (i = 0; i < 6; i++) {
        digit = intval % 10;
        if (i < 3) {
            index = 13 + i;
            old_lcd_buf[index] = old_bcd_map[old_lcd_bcd[index]][digit];
            if (i == 2) {
                old_lcd_buf[index] = old_lcd_buf[index] | old_bcd_dot[old_lcd_bcd[index]];
            }
        } else {
            index = 10 + (5 - i);
            if (intval > 0) {
                old_lcd_buf[index] = old_bcd_map[old_lcd_bcd[index]][digit];
            } else {
                old_lcd_buf[index] = 0x00;
            }
        }

        intval = intval / 10;
    }
}

static void old_ShowDensity(int intval)
{
    int i = 0;
    unsigned char digit, index;

    for (i = 0; i < 4; i++) {
        digit = intval % 10;
        index = i;
        old_lcd_buf[index] = old_bcd_map[old_lcd_bcd[index]][digit];

        if (i == 2) {
            old_lcd_buf[index] = old_lcd_buf[index] | old_bcd_dot[old_lcd_bcd[index]];
        }

        if (intval == 0 && i == 3) {
            old_lcd_buf[index] = 0x00;
        }

        intval = intval / 10;
    }
}

static void old_WriteByteToLcd(uint8_t data);
static void old_WriteChkToLcd(uint16_t chk);

static void old_WriteBlockToLcd(const char *block, const int length)
{
    char i;
    uint16_t chk_buf[5] = {0x9B6, 0x802, 0x830, 0x806, 0x852};
    (void)length;
    HAL_GPIO_WritePin(GPIOA, GPIO_PIN_4, GPIO_PIN_RESET);
    delay_us(50);

    HAL_GPIO_WritePin(GPIOA, GPIO_PIN_6, GPIO_PIN_SET);
    delay_us(10);
    HAL_GPIO_WritePin(GPIOA, GPIO_PIN_5, GPIO_PIN_RESET);
    delay_us(1);
    HAL_GPIO_WritePin(GPIOA, GPIO_PIN_5, GPIO_PIN_SET);
    delay_us(10);

    old_WriteByteToLcd(0x40);

    for (i = 0; i < 16; i++) {
        old_WriteByteToLcd((uint8_t)block[(int)i]);
    }

    delay_us(10);
    HAL_GPIO_WritePin(GPIOA, GPIO_PIN_4, GPIO_PIN_SET);
    delay_us(150);
    for (i = 0; i < 5; i++) {
        HAL_GPIO_WritePin(GPIOA, GPIO_PIN_4, GPIO_PIN_RESET);
        delay_us(10);
        old_WriteChkToLcd(chk_buf[(int)i]);
        delay_us(10);
        HAL_GPIO_WritePin(GPIOA, GPIO_PIN_4, GPIO_PIN_SET);
        delay_us(3);
    }
}

static void old_WriteByteToLcd(uint8_t data)
{
    char i;

    delay_us(5);

    for (i = 0; i < 8; i++) {
        if (data & 0x80) {
            HAL_GPIO_WritePin(GPIOA, GPIO_PIN_6, GPIO_PIN_SET);
        } else {
            HAL_GPIO_WritePin(GPIOA, GPIO_PIN_6, GPIO_PIN_RESET);
        }
        delay_us(5);
        HAL_GPIO_WritePin(GPIOA, GPIO_PIN_5, GPIO_PIN_RESET);
        delay_us(1);
        HAL_GPIO_WritePin(GPIOA, GPIO_PIN_5, GPIO_PIN_SET);
        delay_us(5);
        data <<= 1;
    }
}

static void old_WriteChkToLcd(uint16_t chk)
{
    char i;
    for (i = 0; i < 12; i++) {
        if (chk & 0x800) {
            HAL_GPIO_WritePin(GPIOA, GPIO_PIN_6, GPIO_PIN_SET);
        } else {
            HAL_GPIO_WritePin(GPIOA, GPIO_PIN_6, GPIO_PIN_RESET);
        }
        delay_us(10);
        HAL_GPIO_WritePin(GPIOA, GPIO_PIN_5, GPIO_PIN_RESET);
        delay_us(10);
        HAL_GPIO_WritePin(GPIOA, GPIO_PIN_5, GPIO_PIN_SET);
        delay_us(10);
        chk <<= 1;
    }
}

/* ---------------------------------------------------------------- the tests */

/* a row of bcd_map is made of the segments of its wiring: every digit is its standard seven segments */
static void test_the_wirings_make_the_digits(void)
{
    static const char *const segs[10] = {"abcdef", "bc", "abdeg", "abcdg", "bcfg", "acdfg", "acdefg", "abc", "abcdefg", "abcdfg"};
    int wiring, digit;

    for (wiring = 0; wiring < 4; ++wiring) {
        for (digit = 0; digit < 10; ++digit) {
            uint8_t made = 0;
            const char *s;

            for (s = segs[digit]; *s; ++s) {
                made = (uint8_t)(made | seg_bit[wiring][*s - 'a']);
            }
            CHECK_EQ(bcd_map[wiring][digit], made);
        }
        /* the seven segments and the point are the eight bits of the byte, each once */
        {
            uint8_t all = bcd_dot[wiring];
            int s;

            for (s = 0; s < 7; ++s) {
                CHECK_EQ(all & seg_bit[wiring][s], 0);
                all = (uint8_t)(all | seg_bit[wiring][s]);
            }
            CHECK_EQ(all, 0xff);
        }
    }
    /* the one digit that differed from the old table: the 5 of the wiring of the lowest density digit */
    CHECK_EQ(old_bcd_map[3][5], 0x6d);
    CHECK_EQ(bcd_map[3][5], 0xd6);
    for (wiring = 0; wiring < 4; ++wiring) {
        for (digit = 0; digit < 10; ++digit) {
            if (!(wiring == 3 && digit == 5)) {
                CHECK_EQ(bcd_map[wiring][digit], old_bcd_map[wiring][digit]);
            }
        }
        CHECK_EQ(bcd_dot[wiring], old_bcd_dot[wiring]);
    }
    for (digit = 0; digit < 16; ++digit) {
        CHECK_EQ(lcd_bcd[digit], old_lcd_bcd[digit]);
    }
}

/* the letters and the dash on every wiring are their segments */
static void test_letters(void)
{
    int w;

    for (w = 0; w < 4; ++w) {
        const uint8_t *s = seg_bit[w];

        CHECK_EQ(glyph((uint8_t)w, '-'), s[6]);
        CHECK_EQ(glyph((uint8_t)w, 'E'), s[0] | s[3] | s[4] | s[5] | s[6]);
        CHECK_EQ(glyph((uint8_t)w, 'r'), s[4] | s[6]);
        CHECK_EQ(glyph((uint8_t)w, 'b'), s[2] | s[3] | s[4] | s[5] | s[6]);
        CHECK_EQ(glyph((uint8_t)w, 'A'), s[0] | s[1] | s[2] | s[4] | s[5] | s[6]);
        CHECK_EQ(glyph((uint8_t)w, 'L'), s[3] | s[4] | s[5]);
        CHECK_EQ(glyph((uint8_t)w, ' '), 0);
        CHECK_EQ(glyph((uint8_t)w, 'x'), 0);
        CHECK_EQ(glyph((uint8_t)w, '7'), bcd_map[w][7]);
    }
}

/* every amount from 0.00 to 9999.99: the same bytes as the old numeric functions, kg and litres */
static void test_amounts_like_the_old_functions(void)
{
    int n;
    char text[16];

    for (n = 0; n <= 999999; ++n) {
        int i;

        old_ShowAmountKg(n);
        old_ShowAmountL(n);
        hundredths_text(text, (uint32_t)n);
        render(kg_byte, UI_KG_DIGITS, text);
        render(litres_byte, UI_LITRES_DIGITS, text);
        for (i = 4; i < 16; ++i) {
            if (lcd_buf[i] != old_lcd_buf[i]) {
                CHECK_EQ(lcd_buf[i], old_lcd_buf[i]);
                printf("    amount %d byte %d\n", n, i);
                return;
            }
        }
        ++checks;
    }
}

/* the density from 0.00 to 99.99: the old bytes, but the 5 of the last digit (the old table had a wrong 5 there) */
static void test_density_like_the_old_function(void)
{
    int n;
    char text[16];
    int fives = 0;

    for (n = 0; n <= 9999; ++n) {
        int i;

        old_ShowDensity(n);
        hundredths_text(text, (uint32_t)n);
        render(density_byte, UI_DENSITY_DIGITS, text);
        for (i = 0; i < 4; ++i) {
            if (i == 0 && n % 10 == 5) {
                CHECK_EQ(old_lcd_buf[0], 0x6d);
                CHECK_EQ(lcd_buf[0], 0xd6);
                ++fives;
            } else {
                CHECK_EQ(lcd_buf[i], old_lcd_buf[i]);
            }
        }
    }
    CHECK_EQ(fives, 1000);
}

/* the float functions: the number is rounded, the old cut gave 0.28 for 0.29 */
static void test_floats(void)
{
    int n;
    int cut_wrong = 0;

    for (n = 0; n <= 999999; ++n) {
        const float value = (float)n / 100.0f;
        char text[16];
        int i;

        if ((int)(value * 100) != n) {
            ++cut_wrong;
        }
        ShowAmountKg(value);
        ShowAmountL(value);
        hundredths_text(text, (uint32_t)n);
        render(kg_byte, UI_KG_DIGITS, text);
        for (i = 4; i < 10; ++i) {
            if (lcd_buf[i] != lcd_buf[i + 6]) {      /* the two displays show the same number on their own wirings: the wirings of the bytes 4..9 and 10..15 are equal */
                CHECK_EQ(lcd_buf[i], lcd_buf[i + 6]);
                return;
            }
        }
        {
            uint8_t kg_now[6];
            memcpy(kg_now, lcd_buf + 4, 6);
            ShowAmountKg(value);
            CHECK_EQ(memcmp(kg_now, lcd_buf + 4, 6), 0);
        }
    }
    printf("  (the old cut gave the wrong hundredth for %d of the 1000000 amounts, the rounding for none)\n", cut_wrong);
    CHECK_EQ(cut_wrong > 0, 1);

    /* 0.29 exactly the case of the old function */
    ShowAmountKg(0.29f);
    CHECK_EQ(lcd_buf[7], bcd_map[1][9]);                              /* 9 */
    CHECK_EQ(lcd_buf[8], bcd_map[1][2]);                              /* 2 */
    CHECK_EQ(lcd_buf[9], bcd_map[1][0] | bcd_dot[1]);                  /* 0. */
    CHECK_EQ(lcd_buf[6], 0);

    /* density: 0.83 and 0.75 */
    ShowDensity(0.83f);
    CHECK_EQ(lcd_buf[0], bcd_map[3][3]);
    CHECK_EQ(lcd_buf[1], bcd_map[2][8]);
    CHECK_EQ(lcd_buf[2], bcd_map[2][0] | bcd_dot[2]);
    CHECK_EQ(lcd_buf[3], 0);
    ShowDensity(0.75f);
    CHECK_EQ(lcd_buf[0], bcd_map[3][5]);
    CHECK_EQ(lcd_buf[0], 0xd6);

    /* the limits: below zero or not a number, and too big */
    ShowAmountKg(-1.0f);
    CHECK_EQ(lcd_buf[9], glyph(1, '-') | bcd_dot[1]);   /* "----.--": the third digit from the right is a dash with the point */
    CHECK_EQ(lcd_buf[4], glyph(0, '-'));
    ShowAmountKg(NAN);
    CHECK_EQ(lcd_buf[4], glyph(0, '-'));
    ShowAmountKg(10000.0f);
    CHECK_EQ(lcd_buf[7], glyph(1, 'r'));
    CHECK_EQ(lcd_buf[8], glyph(1, 'r'));
    CHECK_EQ(lcd_buf[9], glyph(1, 'E'));
    CHECK_EQ(lcd_buf[6], 0);
    ShowAmountKg(9999.99f);
    CHECK_EQ(lcd_buf[4], bcd_map[0][9]);
    ShowDensity(100.0f);
    CHECK_EQ(lcd_buf[0], glyph(3, 'r'));
}

static void bytes_are(const uint8_t *bytes, int count, const uint8_t *want, const char *what)
{
    ++checks;
    if (memcmp(bytes, want, (size_t)count) != 0) {
        int i;

        ++failures;
        printf("  FAIL %s:", what);
        for (i = 0; i < count; ++i) {
            printf(" %02x", bytes[i]);
        }
        printf("  (want");
        for (i = 0; i < count; ++i) {
            printf(" %02x", want[i]);
        }
        printf(")\n");
    }
}

/* the texts that ui.c sends */
static void test_texts(void)
{
    /* 15.00 kg, 18.07 l, density 0.830 */
    UpdateDisplay("  15.00", " 18.07", "0.830");
    /* the kg: digits 1 5 . 0 0 -> from the right: 0, 0, 5 with the point, 1 */
    CHECK_EQ(lcd_buf[7], bcd_map[1][0]);
    CHECK_EQ(lcd_buf[8], bcd_map[1][0]);
    CHECK_EQ(lcd_buf[9], bcd_map[1][5] | bcd_dot[1]);
    CHECK_EQ(lcd_buf[6], bcd_map[0][1]);
    CHECK_EQ(lcd_buf[5], 0);
    CHECK_EQ(lcd_buf[4], 0);
    /* the litres 18.07 */
    CHECK_EQ(lcd_buf[13], bcd_map[1][7]);
    CHECK_EQ(lcd_buf[14], bcd_map[1][0]);
    CHECK_EQ(lcd_buf[15], bcd_map[1][8] | bcd_dot[1]);
    CHECK_EQ(lcd_buf[12], bcd_map[0][1]);
    CHECK_EQ(lcd_buf[11], 0);
    CHECK_EQ(lcd_buf[10], 0);
    /* the density 0.830 is shown as 0.83 */
    CHECK_EQ(lcd_buf[0], bcd_map[3][3]);
    CHECK_EQ(lcd_buf[1], bcd_map[2][8]);
    CHECK_EQ(lcd_buf[2], bcd_map[2][0] | bcd_dot[2]);
    CHECK_EQ(lcd_buf[3], 0);

    /* 0.745 rounds up to 0.75, 0.7399 is not a text of three decimals: its fourth is dropped, 0.739 -> 0.74 */
    UpdateDisplay("", "", "0.745");
    CHECK_EQ(lcd_buf[0], 0xd6);
    CHECK_EQ(lcd_buf[1], bcd_map[2][7]);
    UpdateDisplay("", "", "0.7399");
    CHECK_EQ(lcd_buf[0], bcd_map[3][4]);
    CHECK_EQ(lcd_buf[1], bcd_map[2][7]);
    /* 1.000 is " 1.00" with the digit of the units lit */
    UpdateDisplay("", "", "1.000");
    CHECK_EQ(lcd_buf[2], bcd_map[2][1] | bcd_dot[2]);
    CHECK_EQ(lcd_buf[0], bcd_map[3][0]);
    /* not a number: shown as it is */
    UpdateDisplay("", "", "----");
    CHECK_EQ(lcd_buf[0], glyph(3, '-'));
    CHECK_EQ(lcd_buf[1], glyph(2, '-'));
    CHECK_EQ(lcd_buf[2], glyph(2, '-'));
    CHECK_EQ(lcd_buf[3], glyph(2, '-'));
    UpdateDisplay("", "", "Err");
    CHECK_EQ(lcd_buf[0], glyph(3, 'r'));
    CHECK_EQ(lcd_buf[1], glyph(2, 'r'));
    CHECK_EQ(lcd_buf[2], glyph(2, 'E'));
    CHECK_EQ(lcd_buf[3], 0);
    UpdateDisplay("", "", "99.995");
    CHECK_EQ(lcd_buf[0], glyph(3, 'r'));      /* 100.00 does not fit the four digits */

    /* the kg: the total over the two displays: blanks in front on the kg, zeros in front on the litres, no point */
    UpdateDisplay("   123", "000456", "0.830");
    CHECK_EQ(lcd_buf[7], bcd_map[1][3]);       /* the last digit is the byte 7, the third from the right the byte 9 */
    CHECK_EQ(lcd_buf[8], bcd_map[1][2]);
    CHECK_EQ(lcd_buf[9], bcd_map[1][1]);
    CHECK_EQ(lcd_buf[6], 0);
    CHECK_EQ(lcd_buf[13], bcd_map[1][6]);
    CHECK_EQ(lcd_buf[14], bcd_map[1][5]);
    CHECK_EQ(lcd_buf[15], bcd_map[1][4]);
    CHECK_EQ(lcd_buf[12], bcd_map[0][0]);      /* the zeros in front are lit */
    CHECK_EQ(lcd_buf[11], bcd_map[0][0]);
    CHECK_EQ(lcd_buf[10], bcd_map[0][0]);

    /* all blank, the texts of other lengths, NULL */
    UpdateDisplay("      ", "      ", "    ");
    {
        int i;

        for (i = 0; i < 16; ++i) {
            CHECK_EQ(lcd_buf[i], 0);
        }
    }
    UpdateDisplay("----.--", "   Err", "----");
    CHECK_EQ(lcd_buf[9], glyph(1, '-') | bcd_dot[1]);
    CHECK_EQ(lcd_buf[4], glyph(0, '-'));
    CHECK_EQ(lcd_buf[15], glyph(1, 'E'));
    UpdateDisplay("1234567.89", NULL, NULL);          /* too long: the left end is lost */
    CHECK_EQ(lcd_buf[4], bcd_map[0][4]);
    CHECK_EQ(lcd_buf[5], bcd_map[0][5]);
    CHECK_EQ(lcd_buf[9], bcd_map[1][7] | bcd_dot[1]);      /* ...89 of 1234567.89: the 7 with the point is the third digit from the right */
    CHECK_EQ(lcd_buf[6], bcd_map[0][6]);
    CHECK_EQ(lcd_buf[10], 0);
    CHECK_EQ(lcd_buf[0], 0);
    /* a point twice or a point alone does not break the display */
    UpdateDisplay(".", "..5", "");
    CHECK_EQ(lcd_buf[13], bcd_map[1][5]);      /* "..5": a point belongs to the digit to its left, which is not there: the 5 stays plain */
}

/* the serial link: the same pins and waits as the old functions, for the memory of any content */
static int same_events(const int *a, int na, const int *b, int nb)
{
    return na == nb && memcmp(a, b, (size_t)na * sizeof(int)) == 0;
}

static void test_the_link_like_the_old_functions(void)
{
    static int old_events[EVENTS_MAX];
    int old_count;
    long old_wait;
    uint8_t block[16];
    char as_char[16];
    int round, i;

    for (round = 0; round < 40; ++round) {
        for (i = 0; i < 16; ++i) {
            block[i] = round == 0 ? 0 : (round == 1 ? 0xff : (round == 2 ? (uint8_t)i : (uint8_t)(rand() & 0xff)));
            as_char[i] = (char)block[i];
        }
        record_reset();
        old_WriteBlockToLcd(as_char, 16);
        old_count = event_count;
        old_wait = waited_us;
        memcpy(old_events, events, sizeof(int) * (size_t)event_count);

        record_reset();
        WriteBlockToLcd(block, 16);
        CHECK_EQ(same_events(old_events, old_count, events, event_count), 1);
        CHECK_EQ(waited_us, old_wait);
    }
    printf("  (one pass of the link: %d pin and wait steps, %ld us of waits)\n", old_count, old_wait);
    /* worked out: 71 (start) + 17 bytes x (5 + 8 x 11) + 160 (before and after select) + 5 words x (10 + 12 x 30 + 10 + 3) */
    CHECK_EQ(old_wait, 71 + 17 * (5 + 8 * 11) + 160 + 5 * (10 + 12 * 30 + 10 + 3));
    CHECK_EQ(old_wait, 3727);

    /* a byte and a word alone */
    record_reset();
    old_WriteByteToLcd(0xa5);
    old_count = event_count;
    memcpy(old_events, events, sizeof(int) * (size_t)event_count);
    record_reset();
    WriteByteToLcd(0xa5);
    CHECK_EQ(same_events(old_events, old_count, events, event_count), 1);
    record_reset();
    old_WriteChkToLcd(0x9b6);
    old_count = event_count;
    memcpy(old_events, events, sizeof(int) * (size_t)event_count);
    record_reset();
    WriteChkToLcd(0x9b6);
    CHECK_EQ(same_events(old_events, old_count, events, event_count), 1);

    /* the bits of one byte, the highest first: 0xa5 = 1010 0101 */
    record_reset();
    WriteByteToLcd(0xa5);
    {
        int data_levels[8];
        int found = 0;

        for (i = 0; i < event_count; ++i) {
            if (events[i] == PIN_EVENT + DISPLAY_PIN_DATA * 2 || events[i] == PIN_EVENT + DISPLAY_PIN_DATA * 2 + 1) {
                data_levels[found++] = events[i] - (PIN_EVENT + DISPLAY_PIN_DATA * 2);
            }
        }
        CHECK_EQ(found, 8);
        for (i = 0; i < 8 && i < found; ++i) {
            CHECK_EQ(data_levels[i], (0xa5 >> (7 - i)) & 1);
        }
    }

    /* the board's own refresh sends the display memory */
    UpdateDisplay("  15.00", " 18.07", "0.830");
    record_reset();
    RefreshDisplay();
    {
        int clocks = 0;

        for (i = 0; i < event_count; ++i) {
            if (events[i] == PIN_EVENT + DISPLAY_PIN_CLK * 2) {
                ++clocks;
            }
        }
        CHECK_EQ(clocks, 1 + 8 + 16 * 8 + 5 * 12);   /* the start edge, the command, the 16 bytes, the 5 words of 12 bits */
    }
    {
        uint8_t sent[16];
        int bit = 0, byte = 0, in_data = 0;
        int level = 0;

        /* read the bytes back from the stream: the data level at each falling clock after the command */
        memset(sent, 0, sizeof sent);
        {
            int falling = 0;

            for (i = 0; i < event_count; ++i) {
                if (events[i] == PIN_EVENT + DISPLAY_PIN_DATA * 2) {
                    level = 0;
                } else if (events[i] == PIN_EVENT + DISPLAY_PIN_DATA * 2 + 1) {
                    level = 1;
                } else if (events[i] == PIN_EVENT + DISPLAY_PIN_CLK * 2) {
                    ++falling;
                    if (falling >= 10 && falling < 10 + 128) {      /* 1 start edge + 8 command bits, then the memory */
                        in_data = 1;
                        sent[byte] = (uint8_t)((sent[byte] << 1) | level);
                        if (++bit == 8) {
                            bit = 0;
                            ++byte;
                        }
                    }
                }
            }
        }
        CHECK_EQ(in_data, 1);
        CHECK_EQ(byte, 16);
        bytes_are(sent, 16, lcd_buf, "the memory in the stream");
    }
}

int main(void)
{
    test_the_wirings_make_the_digits();
    test_letters();
    test_amounts_like_the_old_functions();
    test_density_like_the_old_function();
    test_floats();
    test_texts();
    test_the_link_like_the_old_functions();
    printf("display: %d checks, %d failed\n", checks, failures);

    return failures ? 1 : 0;
}
