#include "ui.h"

#include <string.h>

#define NOTE_MS 1500

/* ---------------------------------------------------------------- small text helpers (no printf: it would pull in a lot of code) */

typedef struct {
    char *at;
    size_t left;
} Out;

static void out_char(Out *o, char c)
{
    if (o->left > 1) {
        *o->at++ = c;
        --o->left;
    }
    *o->at = 0;
}

static void out_text(Out *o, const char *s)
{
    while (*s) {
        out_char(o, *s++);
    }
}

static void out_uint(Out *o, uint32_t v)
{
    char digits[10];
    int n = 0;

    do {
        digits[n++] = (char)('0' + v % 10);
        v /= 10;
    } while (v);
    while (n) {
        out_char(o, digits[--n]);
    }
}

/* grams as kilograms with two decimals: 12340 -> "12.34" (the third decimal is dropped) */
static void out_kg(Out *o, uint32_t grams)
{
    const uint32_t hundredths = grams / 10;

    out_uint(o, hundredths / 100);
    out_char(o, '.');
    out_char(o, (char)('0' + (hundredths / 10) % 10));
    out_char(o, (char)('0' + hundredths % 10));
}

/* ---------------------------------------------------------------- the entry of an amount */

/* "12.5" is 12 kg 500 g. At most 3 digits before the point and 2 after. Returns 0 for nothing or a lone point. */
uint32_t ui_entry_grams(const Ui *ui)
{
    uint32_t whole = 0, frac = 0;
    uint32_t scale = 100;
    int after = 0;
    int any = 0;
    uint8_t i;

    for (i = 0; i < ui->entry_len; ++i) {
        const char c = ui->entry[i];

        if (c == '.') {
            after = 1;
        } else if (!after) {
            whole = whole * 10 + (uint32_t)(c - '0');
            any = 1;
        } else {
            frac += (uint32_t)(c - '0') * scale / 10; /* 1 decimal = 100 g, 2 decimals = 10 g */
            scale /= 10;
            any = 1;
        }
    }

    return any ? whole * 1000 + frac * 10 : 0;
}

static void entry_clear(Ui *ui)
{
    ui->entry_len = 0;
    ui->entry[0] = 0;
}

static void entry_add(Ui *ui, char c)
{
    uint8_t whole = 0, frac = 0;
    int point = 0;
    uint8_t i;

    for (i = 0; i < ui->entry_len; ++i) {
        if (ui->entry[i] == '.') {
            point = 1;
        } else if (point) {
            ++frac;
        } else {
            ++whole;
        }
    }
    if (c == '.') {
        if (point) {
            return;
        }
        if (ui->entry_len == 0) {
            ui->entry[ui->entry_len++] = '0'; /* ".5" is "0.5" */
        }
    } else if (point ? frac >= 2 : whole >= 3) {
        return;
    }
    if (ui->entry_len < UI_ENTRY_MAX) {
        ui->entry[ui->entry_len++] = c;
        ui->entry[ui->entry_len] = 0;
    }
}

static void note(Ui *ui, const char *text)
{
    strncpy(ui->note, text, UI_LCD_COLS);
    ui->note[UI_LCD_COLS] = 0;
    ui->note_ms = NOTE_MS;
}

/* ---------------------------------------------------------------- what is shown */

static void compose(const Ui *ui, char lines[UI_LCD_ROWS][UI_LCD_COLS + 1])
{
    const Board *b = ui->board;
    Out o0, o1;
    char fuel[2];
    uint8_t r;

    for (r = 0; r < UI_LCD_ROWS; ++r) {
        lines[r][0] = 0;
    }
    o0.at = lines[0];
    o0.left = UI_LCD_COLS + 1;
    o1.at = lines[UI_LCD_ROWS > 1 ? 1 : 0];
    o1.left = UI_LCD_COLS + 1;
    if (UI_LCD_ROWS < 2) {
        return; /* a one-line display shows the state only */
    }
    fuel[0] = b->p.config.fuel[0] ? (char)b->p.config.fuel[0] : '-';
    fuel[1] = 0;

    switch (b->state) {
    case STATE_IDLE:
        if (board_shown_grams(b) > 0) {
            out_text(&o0, "Balance");                      /* SHOW_BALANCE: a card's balance, not an amount to dispense */
            out_kg(&o1, board_shown_grams(b));
            out_text(&o1, " kg");
        } else if (b->p.config.mode == MODE_MANUAL) {
            out_text(&o0, "Enter amount kg");
            out_text(&o1, ui->entry);
            out_char(&o1, '_');
        } else {
            out_char(&o0, 'N');
            out_uint(&o0, b->address);
            out_char(&o0, ' ');
            out_text(&o0, fuel);
            out_text(&o0, " AUTO");
            out_text(&o1, (b->p.config.density == 0 || b->p.config.pulses_x100 == 0) ? "Not configured" : "Press SELECT");
        }
        break;
    case STATE_SET:
        out_text(&o0, "Preset ");
        out_kg(&o0, b->preset_g);
        out_text(&o0, "kg");
        out_text(&o1, "Lift the nozzle");
        break;
    case STATE_DISPENSING:
        out_text(&o0, "Set ");
        out_kg(&o0, b->preset_g);
        out_text(&o0, " kg");
        out_text(&o1, "Now ");
        out_kg(&o1, board_live_grams(b));
        out_text(&o1, " kg");
        break;
    case STATE_DONE:
        out_text(&o0, "Done ");
        out_kg(&o0, b->p.result.dispensed_g);
        out_text(&o0, " kg");
        out_text(&o1, b->p.config.mode == MODE_MANUAL ? "Wait for POS" : "Wait for POS");
        break;
    default:
        out_text(&o0, "FAULT");
        out_text(&o1, "Code ");
        out_uint(&o1, b->fault);
        break;
    }

    if (ui->note_ms > 0 && ui->note[0]) {
        o1.at = lines[1];
        o1.left = UI_LCD_COLS + 1;
        lines[1][0] = 0;
        out_text(&o1, ui->note);
    }
}

static void draw(Ui *ui)
{
    char lines[UI_LCD_ROWS][UI_LCD_COLS + 1];
    uint8_t r;
    uint8_t c;

    compose(ui, lines);
    for (r = 0; r < UI_LCD_ROWS; ++r) {
        char padded[UI_LCD_COLS + 1];
        size_t len = strlen(lines[r]);

        memset(padded, ' ', UI_LCD_COLS);
        padded[UI_LCD_COLS] = 0;
        for (c = 0; c < len && c < UI_LCD_COLS; ++c) {
            padded[c] = lines[r][c];
        }
        if (!ui->drawn || strcmp(padded, ui->shown[r]) != 0) {
            ui->hal->lcd_print(ui->hal->ctx, r, 0, padded);
            memcpy(ui->shown[r], padded, UI_LCD_COLS + 1);
        }
    }
    ui->drawn = 1;
}

static void leds(const Ui *ui)
{
    const uint8_t s = ui->board->state;

    ui->hal->led(ui->hal->ctx, 0, s == STATE_SET);
    ui->hal->led(ui->hal->ctx, 1, s == STATE_DISPENSING);
    ui->hal->led(ui->hal->ctx, 2, s == STATE_DONE);
    ui->hal->led(ui->hal->ctx, 3, s == STATE_FAULT || ui->board->fault != 0);
}

/* ---------------------------------------------------------------- keys */

static void on_key(Ui *ui, int key)
{
    Board *b = ui->board;
    const int manual = b->p.config.mode == MODE_MANUAL;

    if (key == UI_KEY_NONE) {
        return;
    }
    ui->hal->beep(ui->hal->ctx, 30);

    if (key == UI_KEY_SELECT) {
        board_key_select(b);

        return;
    }
    if (key == UI_KEY_CLEAR && (!manual || (b->state == STATE_IDLE && ui->entry_len == 0) || b->state == STATE_SET)) {
        board_key_clear(b);   /* auto: reported to the Smart210; manual: drops an amount that waits for the gun */

        return;
    }
    if (!manual || b->state != STATE_IDLE) {
        return; /* the keys type an amount only when a manual board waits for one */
    }
    if (key >= UI_KEY_0 && key <= UI_KEY_9) {
        entry_add(ui, (char)('0' + (key - UI_KEY_0)));
    } else if (key == UI_KEY_POINT) {
        entry_add(ui, '.');
    } else if (key == UI_KEY_CLEAR) {
        if (ui->entry_len > 0) {
            ui->entry[--ui->entry_len] = 0;
        }
    } else if (key == UI_KEY_ENTER) {
        const uint32_t grams = ui_entry_grams(ui);

        if (grams == 0) {
            note(ui, "Enter an amount");
        } else if (!board_manual_amount(b, grams)) {
            note(ui, b->p.config.density == 0 ? "Not configured" : "Cannot start");
        } else {
            entry_clear(ui);
            ui->hal->beep(ui->hal->ctx, 100);
        }
    }
}

void ui_init(Ui *ui, Board *board, const UiHal *hal)
{
    memset(ui, 0, sizeof(*ui));
    ui->hal = hal;
    ui->board = board;
    ui->last_state = board->state;
    hal->lcd_clear(hal->ctx);
    draw(ui);
    leds(ui);
}

void ui_tick(Ui *ui, uint32_t elapsed_ms)
{
    on_key(ui, ui->hal->key(ui->hal->ctx));

    if (ui->note_ms > 0) {
        ui->note_ms = ui->note_ms > elapsed_ms ? ui->note_ms - elapsed_ms : 0;
    }
    if (ui->board->state != ui->last_state) {
        /* a change the person at the dispenser should notice */
        if (ui->board->state == STATE_DONE) {
            ui->hal->beep(ui->hal->ctx, 400);
        } else if (ui->board->state == STATE_FAULT) {
            ui->hal->beep(ui->hal->ctx, 800);
        }
        ui->last_state = ui->board->state;
    }
    draw(ui);
    leds(ui);
}
