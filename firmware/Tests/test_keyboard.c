/*
 * The keyboard driver (Core/Src/keyboard.c) on the PC. The pins, the waits and the data line are functions that record what the driver does (and feed the keys it
 * reads), so the link can be compared with the one of the old functions, pin by pin and wait by wait, and the keys can be tried with scripted scans.
 *
 * The old functions are copied below as they were (SendLcdDataToKeyboard, SendCommand1ToKeyboard, SendCommand2ToKeyboard, ScanKeyColumn, WriteByteToKeyboard and the
 * scan), written for the recorder instead of HAL, with the two compile errors put right (ScanKeyColumn is void in the old file and returns a value) and the number of
 * display bytes taken from LCD_BYTES (the old keyboard had 5; the new one has 8 digits).
 */

#define KEYBOARD_HOST_TEST
#include "../Core/Src/keyboard.c"

#include <stdio.h>
#include <stdlib.h>

static int checks, failures;

#define CHECK_EQ(a, b) do { ++checks; if ((long)(a) != (long)(b)) { ++failures; if (failures < 40) printf("  FAIL test_keyboard.c:%d: %s == %s (%ld vs %ld)\n", __LINE__, #a, #b, (long)(a), (long)(b)); } } while (0)

/* ---------------------------------------------------------------- the recorder */

#define EVENTS_MAX 16384
#define PIN_EVENT 100000      /* PIN_EVENT + pin * 2 + level */
#define MODE_EVENT 200000     /* MODE_EVENT + 1 (output) or + 0 (input) */
#define READ_EVENT 300000     /* READ_EVENT + the level that was read */
#define MS_EVENT 1000000      /* MS_EVENT + milliseconds; a plain number is microseconds */

static int events[EVENTS_MAX];
static int event_count;
static long waited_us, waited_ms;

/* the levels the data line gives while it is read: a queue of bits */
static unsigned char feed[4096];
static int feed_len, feed_at;

void keyboard_pin(int pin, int high)
{
    if (event_count < EVENTS_MAX) {
        events[event_count++] = PIN_EVENT + pin * 2 + (high ? 1 : 0);
    }
}

void keyboard_data_mode(int output)
{
    if (event_count < EVENTS_MAX) {
        events[event_count++] = MODE_EVENT + (output ? 1 : 0);
    }
}

int keyboard_data_read(void)
{
    const int level = feed_at < feed_len ? feed[feed_at++] : 0;

    if (event_count < EVENTS_MAX) {
        events[event_count++] = READ_EVENT + level;
    }

    return level;
}

void delay_us(uint32_t us)
{
    if (event_count < EVENTS_MAX) {
        events[event_count++] = (int)us;
    }
    waited_us += us;
}

void keyboard_wait_ms(uint32_t ms)
{
    if (event_count < EVENTS_MAX) {
        events[event_count++] = MS_EVENT + (int)ms;
    }
    waited_ms += ms;
}

static void record_reset(void)
{
    event_count = 0;
    waited_us = 0;
    waited_ms = 0;
}

static void feed_reset(void)
{
    feed_len = 0;
    feed_at = 0;
}

/* the bits of a byte, the highest first */
static void feed_byte(uint8_t b)
{
    int i;

    for (i = 7; i >= 0; --i) {
        feed[feed_len++] = (unsigned char)((b >> i) & 1);
    }
}

/* ---------------------------------------------------------------- the old functions, as they were */

typedef struct {
    uint16_t Pin;
    uint32_t Mode;
    uint32_t Pull;
    uint32_t Speed;
} GPIO_InitTypeDef;
typedef int GPIO_PinState;

#define GPIOE 0
#define GPIO_PIN_4 4
#define GPIO_PIN_5 5
#define GPIO_PIN_6 6
#define GPIO_PIN_SET 1
#define GPIO_PIN_RESET 0
#define GPIO_MODE_OUTPUT_PP 1
#define GPIO_MODE_INPUT 0
#define GPIO_NOPULL 0
#define GPIO_SPEED_FREQ_LOW 0
#define HAL_GPIO_WritePin(port, pin, state) keyboard_pin(6 - (pin), (state))      /* PE6 is the strobe (0), PE5 the clock (1), PE4 the data (2) */
#define HAL_GPIO_Init(port, init) keyboard_data_mode((init)->Mode == GPIO_MODE_OUTPUT_PP)
#define HAL_GPIO_ReadPin(port, pin) keyboard_data_read()
#define HAL_Delay(ms) keyboard_wait_ms(ms)

static void old_SendLcdDataToKeyboard(void);
static void old_SendCommand1ToKeyboard(void);
static void old_SendCommand2ToKeyboard(void);
static uint8_t old_ScanKeyColumn(uint8_t col);
static void old_WriteByteToKeyboard(uint8_t data);

static uint8_t old_keylcd_buf[LCD_BYTES];
static uint8_t old_scan_buf[3] = {0, 0, 0};
static uint8_t old_key_state[3] = {0, 0, 0};
static uint32_t old_key_code = 0;
static uint8_t old_scan_window[5][3] = {{0x00, 0x00, 0x00}};
static uint8_t old_w_index = 0;

static uint8_t old_kbcd_map_0[10] = {0xaf, 0xa0, 0xcb, 0xe9, 0xe4, 0x6d, 0x6f, 0xa8, 0xef, 0xed};

static int old_ScanKeyboardAndRefreshDisplay(char code, const char *value)
{
    char i;
    (void)code;
    (void)value;
    old_SendLcdDataToKeyboard();
    HAL_Delay(2);

    old_SendCommand1ToKeyboard();
    delay_us(500);
    old_SendCommand2ToKeyboard();

    HAL_Delay(5);
    old_ScanKeyColumn(0);
    HAL_Delay(5);
    old_ScanKeyColumn(1);
    HAL_Delay(5);
    old_ScanKeyColumn(2);

    old_scan_window[old_w_index][0] = old_scan_buf[0];
    old_scan_window[old_w_index][1] = old_scan_buf[1];
    old_scan_window[old_w_index][2] = old_scan_buf[2];

    for (i = 0; i < 3; i++) {
        old_key_state[(int)i] = old_scan_window[0][(int)i] & old_scan_window[1][(int)i] & old_scan_window[2][(int)i];
        old_key_code |= old_key_state[(int)i];
        old_key_code = old_key_code << 8;
    }

    old_w_index = (old_w_index + 1) % 3;

    return (int)old_key_code;
}

static void old_SendLcdDataToKeyboard(void)
{
    char i;
    GPIO_InitTypeDef GPIO_InitStruct = {0, 0, 0, 0};

    HAL_GPIO_WritePin(GPIOE, GPIO_PIN_6, GPIO_PIN_SET);
    delay_us(2);
    HAL_GPIO_WritePin(GPIOE, GPIO_PIN_6, GPIO_PIN_RESET);

    GPIO_InitStruct.Pin = GPIO_PIN_4;
    GPIO_InitStruct.Mode = GPIO_MODE_OUTPUT_PP;
    GPIO_InitStruct.Pull = GPIO_NOPULL;
    GPIO_InitStruct.Speed = GPIO_SPEED_FREQ_LOW;
    HAL_GPIO_Init(GPIOE, &GPIO_InitStruct);

    old_WriteByteToKeyboard(0x02);
    delay_us(5);

    HAL_GPIO_WritePin(GPIOE, GPIO_PIN_6, GPIO_PIN_SET);
    delay_us(2);
    HAL_GPIO_WritePin(GPIOE, GPIO_PIN_6, GPIO_PIN_RESET);
    delay_us(3);

    old_WriteByteToKeyboard(0x03);

    for (i = 0; i < LCD_BYTES; i++) {
        old_WriteByteToKeyboard(old_keylcd_buf[(int)i]);
    }

    delay_us(5);
    HAL_GPIO_WritePin(GPIOE, GPIO_PIN_6, GPIO_PIN_SET);
}

static void old_SendCommand1ToKeyboard(void)
{
    HAL_GPIO_WritePin(GPIOE, GPIO_PIN_6, GPIO_PIN_SET);
    delay_us(2);
    HAL_GPIO_WritePin(GPIOE, GPIO_PIN_6, GPIO_PIN_RESET);
    delay_us(2);

    old_WriteByteToKeyboard(0x3C);

    delay_us(5);

    HAL_GPIO_WritePin(GPIOE, GPIO_PIN_6, GPIO_PIN_SET);
    delay_us(2);
    HAL_GPIO_WritePin(GPIOE, GPIO_PIN_6, GPIO_PIN_RESET);
    delay_us(2);

    old_WriteByteToKeyboard(0x69);
}

static void old_SendCommand2ToKeyboard(void)
{
    HAL_GPIO_WritePin(GPIOE, GPIO_PIN_6, GPIO_PIN_SET);
    delay_us(2);
    HAL_GPIO_WritePin(GPIOE, GPIO_PIN_6, GPIO_PIN_RESET);
    delay_us(2);

    old_WriteByteToKeyboard(0x42);
}

static uint8_t old_ScanKeyColumn(uint8_t col)
{
    uint8_t i, data;

    GPIO_InitTypeDef GPIO_InitStruct = {0, 0, 0, 0};

    GPIO_InitStruct.Pin = GPIO_PIN_4;
    GPIO_InitStruct.Mode = GPIO_MODE_INPUT;
    GPIO_InitStruct.Pull = GPIO_NOPULL;
    GPIO_InitStruct.Speed = GPIO_SPEED_FREQ_LOW;
    HAL_GPIO_Init(GPIOE, &GPIO_InitStruct);

    delay_us(10);

    data = 0;
    for (i = 0; i < 8; i++) {
        data = (uint8_t)(data << 1);

        HAL_GPIO_WritePin(GPIOE, GPIO_PIN_5, GPIO_PIN_RESET);
        delay_us(1);
        GPIO_PinState pin_state = HAL_GPIO_ReadPin(GPIOE, GPIO_PIN_4);

        if (pin_state == GPIO_PIN_SET) {
            data |= 0x01;
        }
        delay_us(1);
        HAL_GPIO_WritePin(GPIOE, GPIO_PIN_5, GPIO_PIN_SET);
        delay_us(1);
    }

    old_scan_buf[col] = data;

    return data;
}

static void old_WriteByteToKeyboard(uint8_t data)
{
    uint8_t i;

    for (i = 0; i < 8; i++) {
        HAL_GPIO_WritePin(GPIOE, GPIO_PIN_5, GPIO_PIN_RESET);
        delay_us(1);
        if (data & 0x80) {
            HAL_GPIO_WritePin(GPIOE, GPIO_PIN_4, GPIO_PIN_SET);
        } else {
            HAL_GPIO_WritePin(GPIOE, GPIO_PIN_4, GPIO_PIN_RESET);
        }
        delay_us(1);
        HAL_GPIO_WritePin(GPIOE, GPIO_PIN_5, GPIO_PIN_SET);
        delay_us(1);

        data = (uint8_t)(data << 1);
    }
}

/* ---------------------------------------------------------------- helpers */

static int saved_events[EVENTS_MAX];
static int saved_count;
static long saved_us, saved_ms;

static void save_events(void)
{
    saved_count = event_count;
    saved_us = waited_us;
    saved_ms = waited_ms;
    memcpy(saved_events, events, sizeof(int) * (size_t)event_count);
}

static int same_as_saved(void)
{
    return saved_count == event_count && memcmp(saved_events, events, sizeof(int) * (size_t)event_count) == 0 && saved_us == waited_us && saved_ms == waited_ms;
}

/* the keys of one scan, as the board clocks them out: the three bytes */
static int scan_keys(uint8_t b0, uint8_t b1, uint8_t b2)
{
    feed_reset();
    feed_byte(b0);
    feed_byte(b1);
    feed_byte(b2);

    return ScanKeyboardAndRefreshDisplay('0', "");
}

static void forget_keys(void)
{
    int i;

    for (i = 0; i < 3; ++i) {
        scan_keys(0, 0, 0);
    }
    for (i = 0; i < DEBOUNCE_SCANS; ++i) {
        scan_window[i] = 0;
    }
    key_state = 0;
    key_pending = 0;
}

/* ---------------------------------------------------------------- the tests */

/* the pieces of the link: the same pins, modes and waits as the old functions */
static void test_the_link_like_the_old_functions(void)
{
    int round, i;

    for (round = 0; round < 30; ++round) {
        for (i = 0; i < LCD_BYTES; ++i) {
            keylcd_buf[i] = round == 0 ? 0 : (round == 1 ? 0xff : (uint8_t)(rand() & 0xff));
            old_keylcd_buf[i] = keylcd_buf[i];
        }
        record_reset();
        old_SendLcdDataToKeyboard();
        save_events();
        record_reset();
        SendLcdDataToKeyboard();
        CHECK_EQ(same_as_saved(), 1);
    }

    record_reset();
    old_SendCommand1ToKeyboard();
    save_events();
    record_reset();
    SendCommand1ToKeyboard();
    CHECK_EQ(same_as_saved(), 1);

    record_reset();
    old_SendCommand2ToKeyboard();
    save_events();
    record_reset();
    SendCommand2ToKeyboard();
    CHECK_EQ(same_as_saved(), 1);

    for (i = 0; i < 256; ++i) {
        record_reset();
        old_WriteByteToKeyboard((uint8_t)i);
        save_events();
        record_reset();
        WriteByteToKeyboard((uint8_t)i);
        CHECK_EQ(same_as_saved(), 1);
    }

    for (i = 0; i < 256; i += 5) {
        uint8_t was, now;

        feed_reset();
        feed_byte((uint8_t)i);
        record_reset();
        was = old_ScanKeyColumn(1);
        save_events();
        feed_at = 0;
        record_reset();
        now = ScanKeyColumn(1);
        CHECK_EQ(same_as_saved(), 1);
        CHECK_EQ(was, i);
        CHECK_EQ(now, i);
    }

    /* a whole scan: the old function and the new one with the same keys on the line */
    keylcd_buf[0] = 0;
    memset(old_keylcd_buf, 0, sizeof old_keylcd_buf);
    feed_reset();
    feed_byte(0x01);
    feed_byte(0x10);
    feed_byte(0x20);
    record_reset();
    old_ScanKeyboardAndRefreshDisplay('0', "");
    save_events();
    feed_at = 0;
    record_reset();
    scan_window[0] = scan_window[1] = scan_window[2] = 0;
    window_at = 0;
    ScanKeyboardAndRefreshDisplay('\0', NULL);       /* the new one makes its display memory from the text: blank here, like the old buffer */
    CHECK_EQ(same_as_saved(), 1);
    printf("  (one scan: %ld ms of HAL_Delay, %ld us of waits, %d steps of the link)\n", waited_ms, waited_us, event_count);
    CHECK_EQ(waited_ms, 2 + 5 + 5 + 5);
}

/* the data line: an output for the commands, an input while the keys are read */
static void test_the_data_line_modes(void)
{
    int i;
    int modes[8];
    int found = 0;

    feed_reset();
    feed_byte(0);
    feed_byte(0);
    feed_byte(0);
    record_reset();
    ScanKeyboardAndRefreshDisplay('0', "");
    for (i = 0; i < event_count; ++i) {
        if (events[i] == MODE_EVENT || events[i] == MODE_EVENT + 1) {
            modes[found++] = events[i] - MODE_EVENT;
        }
    }
    CHECK_EQ(found, 4);                      /* output for the display, then input for each of the three bytes */
    CHECK_EQ(modes[0], 1);
    CHECK_EQ(modes[1], 0);
    CHECK_EQ(modes[2], 0);
    CHECK_EQ(modes[3], 0);
}

/* the 17 keys are 17 distinct bits of the three bytes: 5 + 8 + 4 */
static void test_the_masks(void)
{
    static const uint32_t masks[17] = {KEY_1, KEY_2, KEY_3, KEY_4, KEY_5, KEY_6, KEY_7, KEY_8, KEY_9, KEY_0, KEY_DOT, KEY_CLEAR, KEY_NEXT, KEY_PRINT, KEY_START, KEY_STOP, KEY_PREV};
    uint32_t all = 0;
    int i;

    for (i = 0; i < 17; ++i) {
        CHECK_EQ(all & masks[i], 0);
        CHECK_EQ(masks[i] & (masks[i] - 1), 0);          /* one bit */
        all |= masks[i];
    }
    CHECK_EQ(all, KEY_BITS);
    CHECK_EQ(all & 0x000000ff, 0);                         /* nothing in the low byte: only the three bytes of the board are used */
    CHECK_EQ((all >> 24) & 0xff, 0x1f);                    /* the first byte: 5 keys */
    CHECK_EQ((all >> 16) & 0xff, 0xff);                    /* the second: 8 keys */
    CHECK_EQ((all >> 8) & 0xff, 0xf0);                     /* the third: 4 keys */
    CHECK_EQ((int)(sizeof key_map / sizeof key_map[0]), 17);
    for (i = 0; i < 17; ++i) {
        int j;
        int known = 0;

        for (j = 0; j < 17; ++j) {
            if (key_map[j].mask == masks[i]) {
                ++known;
            }
        }
        CHECK_EQ(known, 1);                                /* every key is in the map once */
        for (j = i + 1; j < 17; ++j) {
            CHECK_EQ(key_map[i].key == key_map[j].key, 0);   /* and gives a code of its own */
        }
    }
}

/* a key: 1 and 2 scans are nothing, the third gives the code, held gives nothing more, up for three scans and down again gives it again */
static void test_a_press(void)
{
    int i;

    forget_keys();
    CHECK_EQ(scan_keys(0, 0, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0x01, 0), UI_KEY_NONE);            /* KEY_5 is bit 0 of the second byte */
    CHECK_EQ(scan_keys(0, 0x01, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0x01, 0), UI_KEY_5);
    for (i = 0; i < 10; ++i) {
        CHECK_EQ(scan_keys(0, 0x01, 0), UI_KEY_NONE);        /* held */
    }
    CHECK_EQ(scan_keys(0, 0, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0x01, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0x01, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0x01, 0), UI_KEY_5);               /* pressed again */
}

/* a bounce of the contact gives nothing, when the key goes down and when it goes up */
static void test_bounce(void)
{
    forget_keys();
    CHECK_EQ(scan_keys(0, 0x02, 0), UI_KEY_NONE);            /* on, off, on, on, on: the code comes with the third scan in a row */
    CHECK_EQ(scan_keys(0, 0, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0x02, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0x02, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0x02, 0), UI_KEY_6);
    /* held, one scan off and on again: it is the same press */
    CHECK_EQ(scan_keys(0, 0, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0x02, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0x02, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0x02, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0x02, 0), UI_KEY_NONE);
    /* a single scan of a key (noise) is nothing */
    forget_keys();
    CHECK_EQ(scan_keys(0, 0, 0x10), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0, 0), UI_KEY_NONE);
    CHECK_EQ(scan_keys(0, 0, 0), UI_KEY_NONE);
    /* a key that shows in every other scan is not pressed */
    forget_keys();
    {
        int i, given = 0;

        for (i = 0; i < 20; ++i) {
            given += scan_keys(0, i % 2 ? 0x04 : 0, 0) != UI_KEY_NONE;
        }
        CHECK_EQ(given, 0);
    }
}

/* each of the 17 keys alone gives its code, and nothing else does */
static void test_every_key(void)
{
    static const struct { uint8_t b0, b1, b2; int key; } keys[17] = {
        {0, 0, 0x10, UI_KEY_1}, {0, 0, 0x20, UI_KEY_2}, {0, 0, 0x40, UI_KEY_3}, {0, 0, 0x80, UI_KEY_4},
        {0, 0x01, 0, UI_KEY_5}, {0, 0x02, 0, UI_KEY_6}, {0, 0x04, 0, UI_KEY_7}, {0, 0x08, 0, UI_KEY_8}, {0, 0x10, 0, UI_KEY_9}, {0, 0x20, 0, UI_KEY_0},
        {0, 0x40, 0, UI_KEY_POINT}, {0, 0x80, 0, UI_KEY_CLEAR},
        {0x01, 0, 0, UI_KEY_NEXT}, {0x02, 0, 0, UI_KEY_TOTAL}, {0x04, 0, 0, UI_KEY_SET}, {0x08, 0, 0, UI_KEY_FUNCTION}, {0x10, 0, 0, UI_KEY_PREV}
    };
    int i, j;

    for (i = 0; i < 17; ++i) {
        int got[4];

        forget_keys();
        for (j = 0; j < 4; ++j) {
            got[j] = scan_keys(keys[i].b0, keys[i].b1, keys[i].b2);
        }
        CHECK_EQ(got[0], UI_KEY_NONE);
        CHECK_EQ(got[1], UI_KEY_NONE);
        CHECK_EQ(got[2], keys[i].key);
        CHECK_EQ(got[3], UI_KEY_NONE);
    }
    /* the bits that are not keys (the low 3 bits of the first byte's top, the low nibble of the third) are never a key */
    forget_keys();
    for (j = 0; j < 8; ++j) {
        CHECK_EQ(scan_keys(0xe0, 0, 0x0f), UI_KEY_NONE);
    }
}

/* keys that go down together are given one by one, in the order of the map */
static void test_two_keys_together(void)
{
    int a, b;

    forget_keys();
    scan_keys(0, 0x10, 0x10);
    scan_keys(0, 0x10, 0x10);
    a = scan_keys(0, 0x10, 0x10);
    b = scan_keys(0, 0x10, 0x10);
    CHECK_EQ(a, UI_KEY_1);
    CHECK_EQ(b, UI_KEY_9);
    CHECK_EQ(scan_keys(0, 0x10, 0x10), UI_KEY_NONE);

    /* all 17 at once: all 17 codes, once each, in the order of the map */
    forget_keys();
    {
        int seen[UI_KEY_FUNCTION + 1] = {0};
        int i, total = 0;

        for (i = 0; i < 40; ++i) {
            const int k = scan_keys(0x1f, 0xff, 0xf0);

            if (k != UI_KEY_NONE) {
                ++seen[k];
                ++total;
            }
        }
        CHECK_EQ(total, 17);
        for (i = UI_KEY_0; i <= UI_KEY_FUNCTION; ++i) {
            CHECK_EQ(seen[i], 1);
        }
    }

    /* a key that is let go before its code was given is forgotten */
    forget_keys();
    scan_keys(0, 0x10, 0x10);
    scan_keys(0, 0x10, 0x10);
    CHECK_EQ(scan_keys(0, 0x10, 0x10), UI_KEY_1);
    scan_keys(0, 0x10, 0);
    scan_keys(0, 0x10, 0);
    scan_keys(0, 0x10, 0);
    CHECK_EQ(scan_keys(0, 0x10, 0), UI_KEY_NONE);
}

/* the old logic gave the keys as byte0 << 24 | byte1 << 16 | byte2 << 8 when three scans in a row had them (the shifts push the value of the scan before out again, so
   key_code is not a sum of scans): the masks of the header are that word, and the new judging agrees with it when a key is held */
static void test_the_masks_are_the_old_word(void)
{
    int i;
    int old_word = 0;

    memset(old_scan_window, 0, sizeof old_scan_window);
    old_w_index = 0;
    old_key_code = 0;
    for (i = 0; i < 6; ++i) {
        feed_reset();                     /* a key of the first byte, one of the second and one of the third */
        feed_byte(0x04);
        feed_byte(0x01);
        feed_byte(0x80);
        old_word = old_ScanKeyboardAndRefreshDisplay('0', "");
    }
    CHECK_EQ((uint32_t)old_word, KEY_START | KEY_5 | KEY_4);
    feed_reset();
    feed_byte(0);
    feed_byte(0);
    feed_byte(0);
    old_word = old_ScanKeyboardAndRefreshDisplay('0', "");
    CHECK_EQ(old_word != 0, 0);           /* (the old judging dropped a key at the first scan without it: no hysteresis) */
}
/* the display memory */
static void test_display_memory(void)
{
    int d;

    /* the segment table is the old wiring */
    for (d = 0; d < 10; ++d) {
        CHECK_EQ(segments_to_byte(digit_segments[d]), old_kbcd_map_0[d]);
    }

    /* "   15.00" with the code 0: the value from the right, the point on the third digit, the code on the left */
    render('0', "   15.00");
    CHECK_EQ(keylcd_buf[0], old_kbcd_map_0[0]);
    CHECK_EQ(keylcd_buf[1], old_kbcd_map_0[0]);
    CHECK_EQ(keylcd_buf[2], old_kbcd_map_0[5] | dot_bit);
    CHECK_EQ(keylcd_buf[3], old_kbcd_map_0[1]);
    CHECK_EQ(keylcd_buf[4], 0);
    CHECK_EQ(keylcd_buf[5], 0);
    CHECK_EQ(keylcd_buf[6], 0);
    CHECK_EQ(keylcd_buf[7], old_kbcd_map_0[0]);

    /* "    12.5" */
    render('3', "    12.5");
    CHECK_EQ(keylcd_buf[0], old_kbcd_map_0[5]);
    CHECK_EQ(keylcd_buf[1], old_kbcd_map_0[2] | dot_bit);
    CHECK_EQ(keylcd_buf[2], old_kbcd_map_0[1]);
    CHECK_EQ(keylcd_buf[7], old_kbcd_map_0[3]);

    /* the letters of the codes */
    render('E', "");
    CHECK_EQ(keylcd_buf[7], segments_to_byte(0x79));
    render('t', "");
    CHECK_EQ(keylcd_buf[7], segments_to_byte(0x78));
    render('H', "");
    CHECK_EQ(keylcd_buf[7], segments_to_byte(0x76));
    render('P', "");
    CHECK_EQ(keylcd_buf[7], segments_to_byte(0x73));
    render('F', "");
    CHECK_EQ(keylcd_buf[7], segments_to_byte(0x71));
    render('h', "");
    CHECK_EQ(keylcd_buf[7], segments_to_byte(0x76));          /* a lower-case letter is the same letter */
    render('x', "");
    CHECK_EQ(keylcd_buf[7], 0);                               /* what seven segments cannot make is dark */

    /* blank, dashes, too long, nothing */
    render(' ', "       ");
    for (d = 0; d < 8; ++d) {
        CHECK_EQ(keylcd_buf[d], 0);
    }
    render('0', "-------");
    for (d = 0; d < 7; ++d) {
        CHECK_EQ(keylcd_buf[d], segments_to_byte(0x40));
    }
    render('0', "123456789");
    CHECK_EQ(keylcd_buf[0], old_kbcd_map_0[9]);
    CHECK_EQ(keylcd_buf[6], old_kbcd_map_0[3]);
    render('0', NULL);
    for (d = 0; d < 7; ++d) {
        CHECK_EQ(keylcd_buf[d], 0);
    }
    render('0', ".");
    CHECK_EQ(keylcd_buf[0], 0);
    render('0', "..5");
    CHECK_EQ(keylcd_buf[0], old_kbcd_map_0[5]);
}

int main(void)
{
    test_the_link_like_the_old_functions();
    test_the_data_line_modes();
    test_the_masks();
    test_a_press();
    test_bounce();
    test_every_key();
    test_two_keys_together();
    test_the_masks_are_the_old_word();
    test_display_memory();
    printf("keyboard: %d checks, %d failed\n", checks, failures);

    return failures ? 1 : 0;
}
