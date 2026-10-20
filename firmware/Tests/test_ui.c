/* Tests of the display and keyboard logic on the PC: a fake LCD that remembers its text, a queue of key presses, a fake board. */

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

#define CHECK_TEXT(row, text)                                                          \
    do {                                                                               \
        char want[UI_LCD_COLS + 1];                                                    \
        memset(want, ' ', UI_LCD_COLS);                                                \
        want[UI_LCD_COLS] = 0;                                                         \
        memcpy(want, text, strlen(text));                                              \
        ++checks;                                                                      \
        if (strcmp(rig.lcd[row], want) != 0) {                                         \
            ++failures;                                                                \
            printf("  FAIL %s:%d: row %d is \"%s\", wanted \"%s\"\n", __FILE__, __LINE__, row, rig.lcd[row], want); \
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
    char lcd[UI_LCD_ROWS][UI_LCD_COLS + 1];
    int prints;
    int keys[64];
    int key_count, key_at;
    int beeps, last_beep_ms;
    int led[4];
} Rig;

static void u_clear(void *ctx)
{
    Rig *r = (Rig *)ctx;
    int i;

    for (i = 0; i < UI_LCD_ROWS; ++i) {
        memset(r->lcd[i], ' ', UI_LCD_COLS);
        r->lcd[i][UI_LCD_COLS] = 0;
    }
}

static void u_print(void *ctx, uint8_t row, uint8_t col, const char *text)
{
    Rig *r = (Rig *)ctx;

    ++r->prints;
    memcpy(r->lcd[row] + col, text, strlen(text));
}

static int u_key(void *ctx)
{
    Rig *r = (Rig *)ctx;

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
    Persist none;

    memset(r, 0, sizeof(*r));
    memset(&none, 0, sizeof(none));
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
    r->uihal.lcd_clear = u_clear;
    r->uihal.lcd_print = u_print;
    r->uihal.key = u_key;
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

static void flow(Rig *r, int steps, uint32_t per_step)
{
    int i;

    for (i = 0; i < steps; ++i) {
        r->fake.pulses += per_step;
        board_tick(&r->board, 10);
        ui_tick(&r->ui, 10);
    }
}

static void test_auto_screens(void)
{
    Rig rig;

    rig_new(&rig);
    boot(&rig, -1, 1); /* a board never configured */
    CHECK_TEXT(0, "N1 - AUTO");
    CHECK_TEXT(1, "Not configured");

    rig_new(&rig);
    boot(&rig, MODE_AUTO, 3);
    CHECK_TEXT(0, "N3 D AUTO");
    CHECK_TEXT(1, "Press SELECT");

    /* the select key */
    press(&rig, UI_KEY_SELECT);
    tick(&rig, 10);
    CHECK_EQ(rig.board.state, STATE_SELECTED);
    CHECK_TEXT(0, "Selected");
    CHECK_TEXT(1, "Wait for POS");
    CHECK_EQ(rig.led[0], 1);
    CHECK_EQ(rig.beeps, 1);

    /* the terminal sets the preset and starts */
    rig.board.preset_g = 15000;
    rig.board.state = STATE_ARMED;
    tick(&rig, 10);
    CHECK_TEXT(0, "Preset 15.00kg");
    CHECK_TEXT(1, "Lift the nozzle");

    rig.board.state = STATE_IDLE;
    rig.board.preset_g = 0;
    {
        /* a fill through the real commands: select, preset, start by the line */
        uint8_t req[FRAME_MAX];
        size_t i, n;
        uint8_t data[4] = {0x98, 0x3A, 0x00, 0x00};
        uint16_t crc;
        uint8_t cmds[3] = {CMD_SELECT, CMD_SET_PRESET, CMD_START};
        int c;

        for (c = 0; c < 3; ++c) {
            const size_t len = cmds[c] == CMD_SET_PRESET ? 4 : 0;

            req[0] = 2; req[1] = 3; req[2] = (uint8_t)(10 + c); req[3] = cmds[c]; req[4] = (uint8_t)len;
            if (len) {
                memcpy(req + 5, data, 4);
            }
            crc = crc16(req + 1, 4 + len);
            req[5 + len] = (uint8_t)(crc & 0xFF);
            req[6 + len] = (uint8_t)(crc >> 8);
            n = 7 + len;
            for (i = 0; i < n; ++i) {
                board_feed(&rig.board, req[i]);
            }
        }
    }
    flow(&rig, 10, 500); /* 5000 counts = 5 L = 4150 g */
    CHECK_EQ(rig.board.state, STATE_DISPENSING);
    CHECK_TEXT(0, "Set 15.00 kg");
    CHECK_TEXT(1, "Now 4.15 kg");
    CHECK_EQ(rig.led[1], 1);
    CHECK_EQ(rig.led[0], 0);

    flow(&rig, 60, 500);
    flow(&rig, 30, 0);
    CHECK_EQ(rig.board.state, STATE_RESULT);
    CHECK_TEXT(0, "Done 15.00 kg");
    CHECK_TEXT(1, "Wait for POS");
    CHECK_EQ(rig.led[2], 1);
    CHECK_EQ(rig.last_beep_ms, 400); /* the end of a fill is heard */

    /* a fault */
    rig.board.state = STATE_IDLE;
    board_set_fault(&rig.board, FAULT_FLOW_SENSOR);
    tick(&rig, 10);
    CHECK_TEXT(0, "FAULT");
    CHECK_TEXT(1, "Code 1");
    CHECK_EQ(rig.led[3], 1);
    CHECK_EQ(rig.last_beep_ms, 800);
}

static void test_manual_entry(void)
{
    Rig rig;

    rig_new(&rig);
    boot(&rig, MODE_MANUAL, 2);
    CHECK_TEXT(0, "Enter amount kg");
    CHECK_TEXT(1, "_");

    press(&rig, UI_KEY_1);
    press(&rig, UI_KEY_2);
    tick(&rig, 10);
    tick(&rig, 10);
    CHECK_TEXT(1, "12_");
    CHECK_EQ(ui_entry_grams(&rig.ui), 12000);

    press(&rig, UI_KEY_POINT);
    press(&rig, UI_KEY_5);
    tick(&rig, 10);
    tick(&rig, 10);
    CHECK_TEXT(1, "12.5_");
    CHECK_EQ(ui_entry_grams(&rig.ui), 12500);

    press(&rig, UI_KEY_7);
    press(&rig, UI_KEY_9); /* a third decimal is not taken */
    press(&rig, UI_KEY_POINT); /* nor a second point */
    tick(&rig, 10);
    tick(&rig, 10);
    tick(&rig, 10);
    CHECK_TEXT(1, "12.57_");
    CHECK_EQ(ui_entry_grams(&rig.ui), 12570);

    press(&rig, UI_KEY_CLEAR);
    press(&rig, UI_KEY_CLEAR);
    press(&rig, UI_KEY_CLEAR);
    tick(&rig, 10);
    tick(&rig, 10);
    tick(&rig, 10);
    CHECK_TEXT(1, "12_");
    press(&rig, UI_KEY_CLEAR);
    press(&rig, UI_KEY_CLEAR);
    press(&rig, UI_KEY_CLEAR); /* more clears than characters */
    tick(&rig, 10);
    tick(&rig, 10);
    tick(&rig, 10);
    CHECK_TEXT(1, "_");
    CHECK_EQ(ui_entry_grams(&rig.ui), 0);

    /* three digits before the point at most; a point first is 0. */
    press(&rig, UI_KEY_POINT);
    press(&rig, UI_KEY_2);
    press(&rig, UI_KEY_5);
    tick(&rig, 10);
    tick(&rig, 10);
    tick(&rig, 10);
    CHECK_TEXT(1, "0.25_");
    CHECK_EQ(ui_entry_grams(&rig.ui), 250);
    press(&rig, UI_KEY_CLEAR); press(&rig, UI_KEY_CLEAR); press(&rig, UI_KEY_CLEAR); press(&rig, UI_KEY_CLEAR);
    press(&rig, UI_KEY_1); press(&rig, UI_KEY_2); press(&rig, UI_KEY_3); press(&rig, UI_KEY_4);
    {
        int i;

        for (i = 0; i < 8; ++i) {
            tick(&rig, 10);
        }
    }
    CHECK_TEXT(1, "123_");

    /* ENTER with nothing typed is a note, not a fill */
    rig_new(&rig);
    boot(&rig, MODE_MANUAL, 2);
    press(&rig, UI_KEY_ENTER);
    tick(&rig, 10);
    CHECK_TEXT(1, "Enter an amount");
    tick(&rig, 2000);
    CHECK_TEXT(1, "_"); /* and it goes away */

    /* ENTER starts the fill; the pump follows the hook */
    press(&rig, UI_KEY_8);
    press(&rig, UI_KEY_POINT);
    press(&rig, UI_KEY_3);
    press(&rig, UI_KEY_ENTER);
    tick(&rig, 10); tick(&rig, 10); tick(&rig, 10); tick(&rig, 10);
    CHECK_EQ(rig.board.state, STATE_DISPENSING);
    CHECK_EQ(rig.board.preset_g, 8300);
    CHECK_EQ(rig.fake.pump_on, 1);
    CHECK_TEXT(0, "Set 8.30 kg");
    CHECK_EQ(rig.ui.entry_len, 0);
    flow(&rig, 100, 100);
    flow(&rig, 30, 0);
    CHECK_EQ(rig.board.state, STATE_RESULT);
    CHECK_TEXT(0, "Done 8.30 kg");

    /* the keys do not type while a fill runs or a result waits */
    press(&rig, UI_KEY_5);
    tick(&rig, 10);
    CHECK_EQ(rig.ui.entry_len, 0);

    /* the select key does nothing in manual mode */
    rig.board.state = STATE_IDLE;
    press(&rig, UI_KEY_SELECT);
    tick(&rig, 10);
    CHECK_EQ(rig.board.state, STATE_IDLE);
}

static void test_redraw_only_what_changed(void)
{
    Rig rig;
    int before;

    rig_new(&rig);
    boot(&rig, MODE_AUTO, 1);
    before = rig.prints;
    tick(&rig, 10);
    tick(&rig, 10);
    CHECK_EQ(rig.prints, before); /* nothing changed: nothing is written to the LCD */

    press(&rig, UI_KEY_SELECT);
    tick(&rig, 10);
    CHECK_EQ(rig.prints, before + 2); /* both lines changed */
}

int main(void)
{
    test_auto_screens();
    test_manual_entry();
    test_redraw_only_what_changed();

    printf("ui: %d checks, %d failed\n", checks, failures);

    return failures ? 1 : 0;
}
