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

/* a key that is refused: "Err" on the kg display for a moment */
static void note(Ui *ui)
{
    ui->note_ms = NOTE_MS;
}

/* ---------------------------------------------------------------- what is shown */

/* a number with two decimals in `digits` digits, right aligned, leading zeros blank: 15.00 in 6 digits is "  15.00"; too big for the digits: "----.--" */
static void text_fixed2(char *out, uint8_t digits, uint32_t hundredths)
{
    const uint8_t whole_digits = (uint8_t)(digits - 2);
    uint32_t cap = 1;
    uint32_t whole = hundredths / 100;
    uint32_t frac = hundredths % 100;
    uint8_t used = 1;
    uint32_t t;
    uint8_t i;
    Out o;

    o.at = out;
    o.left = UI_TEXT_MAX + 1;
    out[0] = 0;
    for (i = 0; i < whole_digits; ++i) {
        cap *= 10;
    }
    if (whole >= cap) {
        for (i = 0; i < whole_digits; ++i) {
            out_char(&o, '-');
        }
        out_text(&o, ".--");

        return;
    }
    for (t = whole; t >= 10; t /= 10) {
        ++used;
    }
    for (i = used; i < whole_digits; ++i) {
        out_char(&o, ' ');
    }
    out_uint(&o, whole);
    out_char(&o, '.');
    out_char(&o, (char)('0' + frac / 10));
    out_char(&o, (char)('0' + frac % 10));
}

/* the grams as kg with two decimals (the third decimal is dropped) */
static void text_kg(char *out, uint8_t digits, uint32_t grams)
{
    text_fixed2(out, digits, grams / 10);
}

/* a text right aligned in `digits` digits (a '.' takes no digit) */
static void text_right(char *out, uint8_t digits, const char *text)
{
    uint8_t used = 0;
    const char *p;
    Out o;

    for (p = text; *p; ++p) {
        if (*p != '.') {
            ++used;
        }
    }
    o.at = out;
    o.left = UI_TEXT_MAX + 1;
    out[0] = 0;
    while (used < digits) {
        out_char(&o, ' ');
        ++used;
    }
    out_text(&o, text);
}

/* the density, kg per litre x 10000, in 4 digits: 8300 is "0.830" */
static void text_density(char *out, const Config *c)
{
    Out o;
    uint32_t v;

    o.at = out;
    o.left = UI_TEXT_MAX + 1;
    out[0] = 0;
    if (c->density == 0) {
        out_text(&o, "----");

        return;
    }
    v = ((uint32_t)c->density + 5) / 10;       /* thousandths */
    out_uint(&o, v / 1000);
    out_char(&o, '.');
    out_char(&o, (char)('0' + (v / 100) % 10));
    out_char(&o, (char)('0' + (v / 10) % 10));
    out_char(&o, (char)('0' + v % 10));
}

enum { SHOW_KG = 0, SHOW_LITRES, SHOW_DENSITY, SHOW_KEYPAD };

/* The total of the board in whole kg over the two numeric displays as one number of 12 digits: the kg display holds the upper 6 digits (blank while the number is
   shorter), the litres display the lower 6 (zeros in front when the upper ones are lit). */
static void text_total(char *kg, char *litres, uint64_t total_kg)
{
    uint64_t high = total_kg / 1000000u;
    const uint32_t low = (uint32_t)(total_kg % 1000000u);
    char digits[8];
    Out o;
    uint8_t n = 0;
    uint8_t i;

    if (high > 999999u) {
        high = 999999u;                    /* 12 digits is all there is */
    }
    kg[0] = 0;
    litres[0] = 0;
    if (high > 0) {
        uint32_t h = (uint32_t)high;

        char hi[8];

        o.at = hi;
        o.left = sizeof(hi);
        out_uint(&o, h);
        text_right(kg, UI_KG_DIGITS, hi);
    } else {
        text_right(kg, UI_KG_DIGITS, "");
    }
    for (i = 0; i < 6; ++i) {
        digits[i] = (char)('0' + (low / (i == 0 ? 100000u : i == 1 ? 10000u : i == 2 ? 1000u : i == 3 ? 100u : i == 4 ? 10u : 1u)) % 10);
    }
    digits[6] = 0;
    if (high == 0) {
        while (n < 5 && digits[n] == '0') {
            digits[n++] = ' ';           /* no zeros in front of the first digit */
        }
    }
    text_right(litres, UI_LITRES_DIGITS, digits);
}

static void compose(const Ui *ui, char text[UI_DISPLAYS][UI_TEXT_MAX + 1])
{
    const Board *b = ui->board;
    const int manual = b->p.config.mode == MODE_MANUAL;
    char tmp[UI_TEXT_MAX + 1];

    text_fixed2(text[SHOW_KG], UI_KG_DIGITS, 0);
    text_fixed2(text[SHOW_LITRES], UI_LITRES_DIGITS, 0);
    text_density(text[SHOW_DENSITY], &b->p.config);
    text_right(text[SHOW_KEYPAD], UI_KEYPAD_VALUE_DIGITS, "");

    switch (b->state) {
    case STATE_IDLE:
        if (board_shown_grams(b) > 0) {
            text_kg(text[SHOW_KG], UI_KG_DIGITS, board_shown_grams(b));      /* SHOW_BALANCE: a card's balance, not an amount to dispense */
            text_right(text[SHOW_LITRES], UI_LITRES_DIGITS, "bAL");
        } else if (manual) {
            text_right(text[SHOW_KEYPAD], UI_KEYPAD_VALUE_DIGITS, ui->entry);       /* the amount that is being typed */
        }
        break;
    case STATE_SET:
        text_kg(text[SHOW_KG], UI_KG_DIGITS, b->preset_g);
        text_fixed2(text[SHOW_LITRES], UI_LITRES_DIGITS, board_ml_of_grams(&b->p.config, b->preset_g) / 10);   /* the same amount in litres, by the density */
        text_kg(text[SHOW_KEYPAD], UI_KEYPAD_VALUE_DIGITS, b->preset_g);
        break;
    case STATE_DISPENSING:
        text_kg(text[SHOW_KG], UI_KG_DIGITS, board_live_grams(b));
        text_fixed2(text[SHOW_LITRES], UI_LITRES_DIGITS, board_live_ml(b) / 10);
        text_kg(text[SHOW_KEYPAD], UI_KEYPAD_VALUE_DIGITS, b->preset_g);
        break;
    case STATE_DONE:
        text_kg(text[SHOW_KG], UI_KG_DIGITS, b->p.result.dispensed_g);
        text_fixed2(text[SHOW_LITRES], UI_LITRES_DIGITS, board_live_ml(b) / 10);
        break;
    default:
        text_right(text[SHOW_KG], UI_KG_DIGITS, "Err");
        tmp[0] = 0;
        {
            Out o;

            o.at = tmp;
            o.left = sizeof(tmp);
            out_uint(&o, b->fault);
        }
        text_right(text[SHOW_LITRES], UI_LITRES_DIGITS, tmp);               /* the fault code */
        break;
    }

    if (ui->total_ms > 0) {
        text_total(text[SHOW_KG], text[SHOW_LITRES], board_total_kg(b));     /* the Total key */
    }
    if (ui->note_ms > 0) {
        text_right(text[SHOW_KG], UI_KG_DIGITS, "Err");                     /* a key was refused */
    }
}

/* The first digit of the keyboard's LCD: the mode and the status. An auto board shows the number of its state (the same as the state of POLL: 0 idle, 2 a preset
   waits, 3 a fill runs, 4 a result waits), a manual board a letter (H idle, P a preset waits, F a fill runs); E for a fault; t while the Total key shows the total. */
static char keypad_code(const Ui *ui)
{
    const Board *b = ui->board;
    const int manual = b->p.config.mode == MODE_MANUAL;

    if (b->state == STATE_FAULT || b->fault != 0) {
        return 'E';
    }
    if (ui->total_ms > 0) {
        return 't';
    }
    switch (b->state) {
    case STATE_SET:
        return manual ? 'P' : '2';
    case STATE_DISPENSING:
        return manual ? 'F' : '3';
    case STATE_DONE:
        return '4';
    default:
        return manual ? 'H' : '0';
    }
}

static void draw(Ui *ui)
{
    char text[UI_DISPLAYS][UI_TEXT_MAX + 1];
    int changed = !ui->drawn;
    uint8_t d;

    compose(ui, text);
    for (d = 0; d < SHOW_KEYPAD; ++d) {
        if (strcmp(text[d], ui->shown[d]) != 0) {
            changed = 1;
        }
    }
    if (changed) {
        ui->hal->update(ui->hal->ctx, text[SHOW_KG], text[SHOW_LITRES], text[SHOW_DENSITY]);   /* the display board: only when a text changed */
    }
    memcpy(ui->shown, text, sizeof(ui->shown));
    ui->code = keypad_code(ui);                            /* the keyboard's LCD is given at every scan (below) */
    ui->drawn = 1;
}

static void leds(const Ui *ui)
{
    const uint8_t s = ui->board->state;
    const int fault = s == STATE_FAULT || ui->board->fault != 0;

    /* LED1: the heartbeat, one second on and one second off, driven by the main loop: it stops when the loop hangs */
    ui->hal->led(ui->hal->ctx, 0, ui->clock_ms < 1000);
    /* LED2 to LED4: the state; a fault makes LED4 blink (about 2 Hz) */
    ui->hal->led(ui->hal->ctx, 1, s == STATE_SET);
    ui->hal->led(ui->hal->ctx, 2, s == STATE_DISPENSING);
    ui->hal->led(ui->hal->ctx, 3, fault ? (ui->clock_ms % 500) < 250 : s == STATE_DONE);
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

    if (key == UI_KEY_TOTAL) {
        /* both modes, when no fill runs and no result waits: the total of the board for a few seconds */
        if ((b->state == STATE_IDLE || b->state == STATE_SET)) {
            ui->total_ms = UI_TOTAL_SHOW_MS;
        }

        return;
    }
    ui->total_ms = 0;                       /* any other key puts the usual display back */
    if (key == UI_KEY_FUNCTION) {
        board_key_select(b);

        return;
    }
    if (key == UI_KEY_NEXT || key == UI_KEY_PREV) {
        return;                             /* not used yet */
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
    } else if (key == UI_KEY_SET) {
        const uint32_t grams = ui_entry_grams(ui);

        if (grams == 0 || !board_manual_amount(b, grams)) {
            note(ui);                 /* no amount, or a board that cannot take it (not configured) */
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
    draw(ui);
    leds(ui);
}

void ui_tick(Ui *ui, uint32_t elapsed_ms)
{
    on_key(ui, ui->hal->scan(ui->hal->ctx, ui->code, ui->shown[SHOW_KEYPAD]));   /* about 20 ms: the keys are scanned and the LCD of the keyboard board refreshed */

    if (ui->note_ms > 0) {
        ui->note_ms = ui->note_ms > elapsed_ms ? ui->note_ms - elapsed_ms : 0;
    }
    if (ui->total_ms > 0) {
        ui->total_ms = ui->total_ms > elapsed_ms ? ui->total_ms - elapsed_ms : 0;
    }
    if (ui->board->state != ui->last_state) {
        ui->total_ms = 0;                   /* the gun is up, the fill is over: the display is for the fill */
        /* a change the person at the dispenser should notice */
        if (ui->board->state == STATE_DONE) {
            ui->hal->beep(ui->hal->ctx, 400);
        } else if (ui->board->state == STATE_FAULT) {
            ui->hal->beep(ui->hal->ctx, 800);
        }
        ui->last_state = ui->board->state;
    }
    ui->clock_ms = (ui->clock_ms + elapsed_ms) % 2000;
    draw(ui);
    leds(ui);
    ui->hal->refresh(ui->hal->ctx);                /* 8 to 10 ms: the display board gets its digits */
}
