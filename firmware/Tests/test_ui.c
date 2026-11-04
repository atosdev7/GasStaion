/* Tests of the display and keyboard logic on the PC: fake displays that remember their text, a queue of key presses, a fake board. */

#include "../Core/Inc/board.h"
#include "../Core/Inc/checksum.h"
#include "../Core/Inc/ui.h"

#include <stdio.h>
#include <string.h>

static int checks = 0;
static int failures = 0;

#define CHECK(cond)                                                                    \
    do {                                                                               \
        ++checks;                                                                      \
        if (!(cond)) {                                                                 \
            ++failures;                                                                \
            printf("  FAIL %s:%d: %s\n", __FILE__, __LINE__, #cond);                   \
        }                                                                              \
    } while (0)

#define CHECK_EQ(a, b)                                                                 \
    do {                                                                               \
        long long va = (long long)(a), vb = (long long)(b);                            \
        ++checks;                                                                      \
        if (va != vb) {                                                                \
            ++failures;                                                                \
            printf("  FAIL %s:%d: %s == %s (%lld vs %lld)\n", __FILE__, __LINE__, #a, #b, va, vb); \
        }                                                                              \
    } while (0)

/* the display (0 kg, 1 litres, 2 density, 3 keypad) shows exactly this text */
#define CHECK_DISPLAY(index, text)                                                     \
    do {                                                                               \
        ++checks;                                                                      \
        if (strcmp(rig.disp[index], text) != 0) {                                      \
            ++failures;                                                                \
            printf("  FAIL %s:%d: display %d is \"%s\", wanted \"%s\"\n", __FILE__, __LINE__, index, rig.disp[index], text); \
        }                                                                              \
    } while (0)

enum { KG = 0, LITRES, DENSITY, KEYPAD };

/* what the UI composed for the value of the keyboard's LCD (it reaches the driver at the next scan) */
#define CHECK_KEYPAD(text)                                                             \
    do {                                                                               \
        ++checks;                                                                      \
        if (strcmp(rig.ui.shown[KEYPAD], text) != 0) {                                 \
            ++failures;                                                                \
            printf("  FAIL %s:%d: keypad value is \"%s\", wanted \"%s\"\n", __FILE__, __LINE__, rig.ui.shown[KEYPAD], text); \
        }                                                                              \
    } while (0)

typedef struct {
    Persist stored;
    int has_record;
    uint32_t pulses;
    int pump_on;
    int hook;
} Fake;

static int f_load(void *ctx, Persist *out)
{
    Fake *f = (Fake *)ctx;

    if (f->has_record) {
        *out = f->stored;
    }

    return f->has_record;
}

static int f_save(void *ctx, const Persist *in)
{
    ((Fake *)ctx)->stored = *in;
    ((Fake *)ctx)->has_record = 1;

    return 1;
}

static uint32_t f_pulses(void *ctx) { return ((Fake *)ctx)->pulses; }
static void f_pump(void *ctx, int on) { ((Fake *)ctx)->pump_on = on; }
static int f_hook(void *ctx) { return ((Fake *)ctx)->hook; }
static int f_rtc_get(void *ctx, DateTime *out) { (void)ctx; memset(out, 0, sizeof(*out)); return 1; }
static int f_rtc_set(void *ctx, const DateTime *in) { (void)ctx; (void)in; return 1; }
static uint32_t f_serial(void *ctx) { (void)ctx; return 1; }

typedef struct {
    Fake fake;
    Hal hal;
    Board board;
    UiHal uihal;
    Ui ui;
    char disp[UI_DISPLAYS][UI_TEXT_MAX + 1];
    int updates;            /* how many times UpdateDisplay was called */
    int refreshes;          /* RefreshDisplay */
    int scans;              /* ScanKeyboardAndRefreshDisplay */
    int keys[64];
    int key_count, key_at;
    int beeps, last_beep_ms;
    int led[4];
    char scan_code;         /* what the last scan was given for the first digit of the keyboard's LCD */
    char scan_value[UI_TEXT_MAX + 1];   /* ... and for its other digits */
} Rig;

static void u_update(void *ctx, const char *kg, const char *litres, const char *density)
{
    Rig *r = (Rig *)ctx;

    ++r->updates;
    strcpy(r->disp[KG], kg);
    strcpy(r->disp[LITRES], litres);
    strcpy(r->disp[DENSITY], density);
}

static void u_refresh(void *ctx)
{
    ++((Rig *)ctx)->refreshes;
}

static int u_scan(void *ctx, char code, const char *value)
{
    Rig *r = (Rig *)ctx;

    ++r->scans;
    r->scan_code = code;
    strcpy(r->scan_value, value);

    return r->key_at < r->key_count ? r->keys[r->key_at++] : UI_KEY_NONE;
}

static void u_beep(void *ctx, uint16_t ms)
{
    Rig *r = (Rig *)ctx;

    ++r->beeps;
    r->last_beep_ms = ms;
}

static void u_led(void *ctx, uint8_t index, int on)
{
    ((Rig *)ctx)->led[index] = on;
}

static void rig_new(Rig *r)
{
    memset(r, 0, sizeof(*r));
    r->fake.hook = 1;
    r->hal.ctx = &r->fake;
    r->hal.load = f_load;
    r->hal.save = f_save;
    r->hal.pulses = f_pulses;
    r->hal.pump = f_pump;
    r->hal.hook = f_hook;
    r->hal.rtc_get = f_rtc_get;
    r->hal.rtc_set = f_rtc_set;
    r->hal.serial = f_serial;
    r->uihal.ctx = r;
    r->uihal.update = u_update;
    r->uihal.refresh = u_refresh;
    r->uihal.scan = u_scan;
    r->uihal.beep = u_beep;
    r->uihal.led = u_led;
}

static void boot(Rig *r, int mode, uint8_t address)
{
    board_init(&r->board, &r->hal, address, 0);
    if (mode >= 0) {
        r->board.p.config.mode = (uint8_t)mode;
        memcpy(r->board.p.config.fuel, "D\0\0\0", 4);
        r->board.p.config.density = 8300;
        r->board.p.config.pulses_x100 = 100000;
    }
    ui_init(&r->ui, &r->board, &r->uihal);
}

static void press(Rig *r, int key)
{
    r->keys[r->key_count++] = key;
}

static void tick(Rig *r, uint32_t ms)
{
    ui_tick(&r->ui, ms);
}

/* one key press and the pass of the main loop that reads it */
static void type(Rig *r, int key)
{
    press(r, key);
    tick(r, 10);
}

static void flow(Rig *r, int steps, uint32_t per_step)
{
    int i;

    for (i = 0; i < steps; ++i) {
        r->fake.pulses += per_step;
        board_tick(&r->board, 10);
        ui_tick(&r->ui, 10);
    }
}

/* a request to the board by the line (the Smart210's side) */
static void request(Rig *r, uint8_t seq, uint8_t cmd, uint32_t value, int with_value)
{
    uint8_t req[FRAME_MAX];
    size_t i;
    const size_t len = with_value ? 4 : 0;
    uint16_t crc;

    req[0] = 2;
    req[1] = r->board.address;
    req[2] = seq;
    req[3] = cmd;
    req[4] = (uint8_t)len;
    if (with_value) {
        req[5] = (uint8_t)value;
        req[6] = (uint8_t)(value >> 8);
        req[7] = (uint8_t)(value >> 16);
        req[8] = (uint8_t)(value >> 24);
    }
    crc = crc16(req + 1, 4 + len);
    req[5 + len] = (uint8_t)(crc & 0xFF);
    req[6 + len] = (uint8_t)(crc >> 8);
    for (i = 0; i < 7 + len; ++i) {
        board_feed(&r->board, req[i]);
    }
}

static void test_auto_screens(void)
{
    Rig rig;

    /* a board never configured */
    rig_new(&rig);
    boot(&rig, -1, 1);
    CHECK_DISPLAY(KG, "   0.00");
    CHECK_DISPLAY(LITRES, "   0.00");
    CHECK_DISPLAY(DENSITY, "----");
    CHECK_KEYPAD("       ");

    /* configured, auto: the density of the config */
    rig_new(&rig);
    boot(&rig, MODE_AUTO, 3);
    CHECK_DISPLAY(KG, "   0.00");
    CHECK_DISPLAY(DENSITY, "0.830");

    /* the select key only reports itself to the Smart210 (the state does not change); the key is heard */
    type(&rig, UI_KEY_FUNCTION);
    CHECK_EQ(rig.board.state, STATE_IDLE);
    CHECK_EQ(rig.beeps, 1);
    CHECK(rig.board.key_events & FLAG_SELECT_KEY);
    CHECK_DISPLAY(KG, "   0.00");

    /* the clear key too (auto mode) */
    type(&rig, UI_KEY_CLEAR);
    CHECK(rig.board.key_events & FLAG_CLEAR_KEY);
    CHECK_EQ(rig.board.state, STATE_IDLE);

    /* the Smart210 shows a card's balance (SHOW_BALANCE) */
    request(&rig, 1, CMD_SHOW_BALANCE, 40000, 1);
    tick(&rig, 10);
    CHECK_DISPLAY(KG, "  40.00");
    CHECK_DISPLAY(LITRES, "   bAL");
    CHECK_KEYPAD("       ");
    request(&rig, 2, CMD_SHOW_BALANCE, 0, 1);
    tick(&rig, 10);
    CHECK_DISPLAY(KG, "   0.00");
    CHECK_DISPLAY(LITRES, "   0.00");

    /* the terminal sets the preset: SET (the preset is on the kg display and on the keyboard's) */
    request(&rig, 3, CMD_SET_PRESET, 15000, 1);
    tick(&rig, 10);
    CHECK_EQ(rig.board.state, STATE_SET);
    CHECK_DISPLAY(KG, "  15.00");
    CHECK_KEYPAD("   15.00");
    CHECK_EQ(rig.led[1], 1);

    /* START with the session id; 5000 counts = 5 L = 4150 g */
    request(&rig, 4, CMD_START, 1, 1);
    flow(&rig, 10, 500);
    CHECK_EQ(rig.board.state, STATE_DISPENSING);
    CHECK_DISPLAY(KG, "   4.15");
    CHECK_DISPLAY(LITRES, "   5.00");
    CHECK_KEYPAD("   15.00");
    CHECK_EQ(rig.led[2], 1);
    CHECK_EQ(rig.led[1], 0);

    flow(&rig, 60, 500);
    flow(&rig, 30, 0);
    CHECK_EQ(rig.board.state, STATE_DONE);
    CHECK_DISPLAY(KG, "  15.00");
    CHECK_DISPLAY(LITRES, "  18.07");     /* 18.072 L: the third decimal is dropped */
    CHECK_KEYPAD("       ");
    CHECK_EQ(rig.led[3], 1);              /* LED4 is steady for a result */
    CHECK_EQ(rig.led[2], 0);
    tick(&rig, 130);
    CHECK_EQ(rig.led[3], 1);
    tick(&rig, 130);
    CHECK_EQ(rig.led[3], 1);
    CHECK_EQ(rig.last_beep_ms, 400);      /* the end of a fill is heard */

    /* a fault */
    request(&rig, 5, CMD_ACK_RESULT, 1, 1);
    CHECK_EQ(rig.board.state, STATE_IDLE);
    board_set_fault(&rig.board, FAULT_FLOW_SENSOR);
    tick(&rig, 10);
    CHECK_DISPLAY(KG, "   Err");
    CHECK_DISPLAY(LITRES, "     1");      /* the fault code */
    CHECK_EQ(rig.last_beep_ms, 800);
    {   /* a fault: LED4 blinks, 250 ms on and 250 ms off; the others are off */
        int on = 0, off = 0, i;

        for (i = 0; i < 100; ++i) {
            tick(&rig, 10);
            on += rig.led[3];
            off += !rig.led[3];
            CHECK_EQ(rig.led[1] + rig.led[2], 0);
        }
        CHECK(on >= 45 && on <= 55);
        CHECK(off >= 45 && off <= 55);
    }
}

/* LED1 is the heartbeat: one second on, one second off, by the time the main loop gives the UI */
static void test_heartbeat(void)
{
    Rig rig;
    int i, on = 0, off = 0, changes = 0, last;

    rig_new(&rig);
    boot(&rig, MODE_AUTO, 2);
    tick(&rig, 10);
    last = rig.led[0];
    for (i = 0; i < 400; ++i) {          /* 4 s in steps of 10 ms */
        tick(&rig, 10);
        on += rig.led[0];
        off += !rig.led[0];
        changes += rig.led[0] != last;
        last = rig.led[0];
    }
    CHECK(on >= 190 && on <= 210);
    CHECK(off >= 190 && off <= 210);
    CHECK_EQ(changes, 4);                 /* a change every second */
    CHECK_EQ(rig.led[1] + rig.led[2] + rig.led[3], 0);   /* IDLE: the state LEDs are off */
}

static void test_numbers(void)
{
    Rig rig;

    /* the digits of the displays: leading zeros blank, a number that does not fit shows dashes */
    rig_new(&rig);
    boot(&rig, MODE_AUTO, 1);
    request(&rig, 1, CMD_SHOW_BALANCE, 5, 1);                 /* 5 g: 0.00 (the third decimal is dropped) */
    tick(&rig, 10);
    CHECK_DISPLAY(KG, "   0.00");
    request(&rig, 2, CMD_SHOW_BALANCE, 12340, 1);
    tick(&rig, 10);
    CHECK_DISPLAY(KG, "  12.34");
    request(&rig, 3, CMD_SHOW_BALANCE, 1234560, 1);
    tick(&rig, 10);
    CHECK_DISPLAY(KG, "1234.56");
    request(&rig, 4, CMD_SHOW_BALANCE, MAX_SHOWN_G, 1);       /* 9999.99: the most the 6 digits show */
    tick(&rig, 10);
    CHECK_DISPLAY(KG, "9999.99");

    /* a preset above 9999.99 kg cannot be shown on the kg display: dashes (the Smart210 can send any weight); the keyboard's 8 digits show more */
    request(&rig, 5, CMD_SHOW_BALANCE, 0, 1);
    request(&rig, 6, CMD_SET_PRESET, 20000000UL, 1);
    tick(&rig, 10);
    CHECK_DISPLAY(KG, "----.--");
    CHECK_KEYPAD("20000.00");

    /* the density in 4 digits */
    rig_new(&rig);
    boot(&rig, MODE_AUTO, 1);
    rig.board.p.config.density = 7400;
    tick(&rig, 10);
    CHECK_DISPLAY(DENSITY, "0.740");
    rig.board.p.config.density = 12345;
    tick(&rig, 10);
    CHECK_DISPLAY(DENSITY, "1.235");
}

static void test_manual_entry(void)
{
    Rig rig;

    rig_new(&rig);
    boot(&rig, MODE_MANUAL, 2);

    /* the typed amount is on the keyboard's display */
    CHECK_KEYPAD("       ");
    type(&rig, UI_KEY_1);
    CHECK_KEYPAD("      1");
    type(&rig, UI_KEY_2);
    type(&rig, UI_KEY_POINT);
    CHECK_KEYPAD("     12.");
    type(&rig, UI_KEY_5);
    type(&rig, UI_KEY_7);
    type(&rig, UI_KEY_9);        /* a third decimal is not taken */
    type(&rig, UI_KEY_POINT);    /* nor a second point */
    CHECK_KEYPAD("   12.57");
    CHECK_EQ(ui_entry_grams(&rig.ui), 12570);
    CHECK_DISPLAY(KG, "   0.00");   /* the kg display waits for the fill */

    type(&rig, UI_KEY_CLEAR);
    type(&rig, UI_KEY_CLEAR);
    type(&rig, UI_KEY_CLEAR);
    CHECK_KEYPAD("     12");
    type(&rig, UI_KEY_CLEAR);
    type(&rig, UI_KEY_CLEAR);
    type(&rig, UI_KEY_CLEAR);    /* more clears than characters */
    CHECK_KEYPAD("       ");
    CHECK_EQ(ui_entry_grams(&rig.ui), 0);

    /* three digits before the point at most; a point first is 0. */
    type(&rig, UI_KEY_POINT);
    type(&rig, UI_KEY_2);
    type(&rig, UI_KEY_5);
    CHECK_KEYPAD("    0.25");
    CHECK_EQ(ui_entry_grams(&rig.ui), 250);
    type(&rig, UI_KEY_CLEAR); type(&rig, UI_KEY_CLEAR); type(&rig, UI_KEY_CLEAR); type(&rig, UI_KEY_CLEAR);
    type(&rig, UI_KEY_1); type(&rig, UI_KEY_2); type(&rig, UI_KEY_3); type(&rig, UI_KEY_4);
    CHECK_KEYPAD("    123");

    /* ENTER with nothing typed is refused: Err for a moment, no fill */
    rig_new(&rig);
    boot(&rig, MODE_MANUAL, 2);
    type(&rig, UI_KEY_SET);
    CHECK_DISPLAY(KG, "   Err");
    CHECK_EQ(rig.board.state, STATE_IDLE);
    tick(&rig, 2000);
    CHECK_DISPLAY(KG, "   0.00");   /* and it goes away */

    /* ENTER makes the amount the preset (SET); the fill starts when the gun is up, and the pump follows the hook */
    type(&rig, UI_KEY_8);
    type(&rig, UI_KEY_POINT);
    type(&rig, UI_KEY_3);
    type(&rig, UI_KEY_SET);
    CHECK_EQ(rig.board.state, STATE_SET);
    CHECK_EQ(rig.board.preset_g, 8300);
    CHECK_DISPLAY(KG, "   8.30");
    CHECK_KEYPAD("    8.30");
    CHECK_EQ(rig.ui.entry_len, 0);
    flow(&rig, 1, 0);                                        /* the gun is up (the rig's hook): the board starts by itself */
    CHECK_EQ(rig.board.state, STATE_DISPENSING);
    CHECK_EQ(rig.fake.pump_on, 1);

    /* the keys do not type while a fill runs */
    type(&rig, UI_KEY_5);
    CHECK_EQ(rig.ui.entry_len, 0);

    /* the fill ends by itself: a manual board holds no result, it waits for the next amount */
    flow(&rig, 100, 100);
    flow(&rig, 30, 0);
    CHECK_EQ(rig.board.state, STATE_IDLE);
    CHECK_DISPLAY(KG, "   0.00");
    CHECK_KEYPAD("       ");

    /* the select key does nothing in manual mode, and it reports nothing */
    type(&rig, UI_KEY_FUNCTION);
    CHECK_EQ(rig.board.state, STATE_IDLE);
    CHECK_EQ(rig.board.key_events, 0);

    /* the clear key with nothing typed and an amount waiting for the gun drops that amount */
    rig.fake.hook = 0;
    type(&rig, UI_KEY_4);
    type(&rig, UI_KEY_SET);
    CHECK_EQ(rig.board.state, STATE_SET);
    type(&rig, UI_KEY_CLEAR);
    CHECK_EQ(rig.board.state, STATE_IDLE);
}

/* the keyboard of 17 keys: the Total key shows the total of the board over the two numeric displays (manual mode), Function is the select key, Set takes an amount */
static void test_total_key(void)
{
    Rig rig;

    rig_new(&rig);
    boot(&rig, MODE_MANUAL, 2);

    /* nothing dispensed yet */
    type(&rig, UI_KEY_TOTAL);
    CHECK_DISPLAY(KG, "      ");
    CHECK_DISPLAY(LITRES, "     0");
    tick(&rig, 5100);
    CHECK_DISPLAY(KG, "   0.00");              /* back to the usual display after 5 seconds */

    /* a fill of 8.3 kg makes the total 8 kg (whole kg) */
    type(&rig, UI_KEY_8);
    type(&rig, UI_KEY_POINT);
    type(&rig, UI_KEY_3);
    type(&rig, UI_KEY_SET);
    flow(&rig, 1010, 10);
    flow(&rig, 130, 0);
    CHECK_EQ(rig.board.state, STATE_IDLE);
    type(&rig, UI_KEY_TOTAL);
    CHECK_DISPLAY(KG, "      ");
    CHECK_DISPLAY(LITRES, "     8");

    /* any other key puts the usual display back and works as usual */
    type(&rig, UI_KEY_5);
    CHECK_DISPLAY(KG, "   0.00");
    CHECK_KEYPAD("      5");

    /* 12 digits over the two displays: the kg display holds the upper six */
    rig.board.p.total_g = 123456789012ULL * 1000 + 999;
    type(&rig, UI_KEY_TOTAL);
    CHECK_DISPLAY(KG, "123456");
    CHECK_DISPLAY(LITRES, "789012");
    rig.board.p.total_g = 1234ULL * 1000000 * 1000 + 5ULL * 1000;
    type(&rig, UI_KEY_TOTAL);
    tick(&rig, 10);
    CHECK_DISPLAY(KG, "  1234");
    CHECK_DISPLAY(LITRES, "000005");

    /* an amount waiting for the gun: the total can be looked at, and the gun up puts the display of the fill back */
    rig.fake.hook = 0;
    type(&rig, UI_KEY_SET);                     /* the 5 that was typed: a 5 kg amount */
    CHECK_EQ(rig.board.state, STATE_SET);
    type(&rig, UI_KEY_TOTAL);
    CHECK_DISPLAY(KG, "  1234");
    CHECK_DISPLAY(LITRES, "000005");
    rig.fake.hook = 1;
    flow(&rig, 1, 0);
    CHECK_EQ(rig.board.state, STATE_DISPENSING);
    CHECK_DISPLAY(KG, "   0.00");

    /* the auto board: the Total key works too (not while a fill runs or a result waits), Function is the select key */
    rig_new(&rig);
    boot(&rig, MODE_AUTO, 2);
    rig.board.p.total_g = 4567891ULL * 1000;
    type(&rig, UI_KEY_TOTAL);
    CHECK_DISPLAY(KG, "     4");
    CHECK_DISPLAY(LITRES, "567891");
    tick(&rig, 5100);
    CHECK_DISPLAY(LITRES, "   0.00");
    type(&rig, UI_KEY_TOTAL);
    type(&rig, UI_KEY_FUNCTION);                 /* any other key puts the usual display back, and works as usual */
    CHECK_DISPLAY(LITRES, "   0.00");
    type(&rig, UI_KEY_FUNCTION);
    CHECK_EQ(rig.board.key_events, FLAG_SELECT_KEY);
    type(&rig, UI_KEY_NEXT);
    type(&rig, UI_KEY_PREV);
    type(&rig, UI_KEY_SET);
    CHECK_EQ(rig.board.key_events, FLAG_SELECT_KEY);
}
/* the keyboard's LCD: the first digit is the mode or the status, the other seven the value; both go to ScanKeyboardAndRefreshDisplay at the next scan */
static void test_keyboard_lcd(void)
{
    Rig rig;

#define CHECK_SCAN(c, v) do { tick(&rig, 10); tick(&rig, 10); ++checks; if (rig.scan_code != (c) || strcmp(rig.scan_value, (v)) != 0) { ++failures; \
        printf("  FAIL %s:%d: the scan was given '%c' \"%s\", wanted '%c' \"%s\"\n", __FILE__, __LINE__, rig.scan_code, rig.scan_value, (c), (v)); } } while (0)

    /* an auto board shows the number of its state */
    rig_new(&rig);
    boot(&rig, MODE_AUTO, 2);
    CHECK_SCAN('0', "       ");                                 /* idle: 0, no value */
    request(&rig, 1, CMD_SET_PRESET, 15000, 1);
    CHECK_SCAN('2', "   15.00");                               /* a preset waits */
    request(&rig, 2, CMD_START, 1, 1);
    flow(&rig, 10, 500);
    CHECK_SCAN('3', "   15.00");                               /* a fill runs */
    flow(&rig, 90, 500);
    flow(&rig, 30, 0);
    CHECK_SCAN('4', "       ");                                /* a result waits */
    request(&rig, 3, CMD_ACK_RESULT, 1, 1);
    board_set_fault(&rig.board, FAULT_FLOW_SENSOR);
    CHECK_SCAN('E', "       ");                                /* a fault */

    /* a manual board shows a letter, and the amount that is being typed */
    rig_new(&rig);
    boot(&rig, MODE_MANUAL, 2);
    CHECK_SCAN('H', "       ");
    type(&rig, UI_KEY_1);
    type(&rig, UI_KEY_2);
    type(&rig, UI_KEY_POINT);
    type(&rig, UI_KEY_5);
    CHECK_SCAN('H', "    12.5");
    type(&rig, UI_KEY_TOTAL);
    CHECK_SCAN('t', "    12.5");                                /* the total is on the two numeric displays */
    tick(&rig, 5100);
    rig.fake.hook = 0;
    type(&rig, UI_KEY_SET);
    CHECK_SCAN('P', "   12.50");                               /* the preset waits for the gun */
    rig.fake.hook = 1;
    flow(&rig, 2, 0);
    CHECK_SCAN('F', "   12.50");                               /* a fill runs */
#undef CHECK_SCAN
}

/* every pass of ui_tick scans the keyboard (20 ms) and refreshes the display board (8 to 10 ms); UpdateDisplay is only called when a text changed */
static void test_scan_refresh_and_update(void)
{
    Rig rig;
    int before;

    rig_new(&rig);
    boot(&rig, MODE_AUTO, 1);
    CHECK_EQ(rig.updates, 1);          /* the first texts */
    before = rig.updates;
    tick(&rig, 10);
    tick(&rig, 10);
    CHECK_EQ(rig.updates, before);     /* nothing changed: nothing is given to the displays */
    CHECK_EQ(rig.scans, 2);
    CHECK_EQ(rig.refreshes, 2);        /* but the display board is refreshed at every pass, or the digits would go dark */

    type(&rig, UI_KEY_FUNCTION);
    CHECK_EQ(rig.updates, before);     /* the select key only reports itself: the displays do not change */

    request(&rig, 1, CMD_SET_PRESET, 3000, 1);
    tick(&rig, 10);
    CHECK_EQ(rig.updates, before + 1); /* the texts changed once */
    CHECK_EQ(rig.scans, 4);
    CHECK_EQ(rig.refreshes, 4);
}

int main(void)
{
    test_auto_screens();
    test_numbers();
    test_manual_entry();
    test_total_key();
    test_heartbeat();
    test_scan_refresh_and_update();
    test_keyboard_lcd();

    printf("ui: %d checks, %d failed\n", checks, failures);

    return failures ? 1 : 0;
}
