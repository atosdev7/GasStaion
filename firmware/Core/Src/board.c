#include "board.h"

#include "checksum.h"

#include <string.h>

#define STX 0x02
#define ANSWER 0x80
#define CONFIG_SIZE 11
#define TIME_SIZE 7
#define MIN_DENSITY 1000
#define MAX_DENSITY 20000
#define MAX_PULSES_X100 100000000UL

/* ---------------------------------------------------------------- numbers */

static void put16(uint8_t *at, uint16_t v)
{
    at[0] = (uint8_t)(v & 0xFF);
    at[1] = (uint8_t)(v >> 8);
}

static void put32(uint8_t *at, uint32_t v)
{
    at[0] = (uint8_t)(v & 0xFF);
    at[1] = (uint8_t)((v >> 8) & 0xFF);
    at[2] = (uint8_t)((v >> 16) & 0xFF);
    at[3] = (uint8_t)((v >> 24) & 0xFF);
}

static uint16_t get16(const uint8_t *at)
{
    return (uint16_t)(at[0] | (at[1] << 8));
}

static uint32_t get32(const uint8_t *at)
{
    return (uint32_t)at[0] | ((uint32_t)at[1] << 8) | ((uint32_t)at[2] << 16) | ((uint32_t)at[3] << 24);
}

static int configured(const Config *c)
{
    return c->density != 0 && c->pulses_x100 != 0;
}

/* counts of the preset: litres = g x 10 / density, counts = litres x pulses_x100 / 100; rounded down, so the fill never goes past it */
uint32_t board_target_pulses(const Config *c, uint32_t preset_g)
{
    uint64_t pulses;

    if (!configured(c)) {
        return 0;
    }
    pulses = (uint64_t)preset_g * c->pulses_x100 / ((uint64_t)c->density * 10);

    return pulses > 0xFFFFFFFFULL ? 0xFFFFFFFFUL : (uint32_t)pulses;
}

/* the weight of a number of counts, rounded to the nearest gram */
uint32_t board_grams_of(const Config *c, uint32_t pulses)
{
    uint64_t num, den, g;

    if (!configured(c)) {
        return 0;
    }
    num = (uint64_t)pulses * c->density * 10;
    den = c->pulses_x100;
    g = (num + den / 2) / den;

    return g > 0xFFFFFFFFULL ? 0xFFFFFFFFUL : (uint32_t)g;
}

static uint32_t ml_of_pulses(const Config *c, uint32_t pulses)
{
    uint64_t num, den, ml;

    if (!configured(c)) {
        return 0;
    }
    num = (uint64_t)pulses * 100000ULL;
    den = c->pulses_x100;
    ml = (num + den / 2) / den;

    return ml > 0xFFFFFFFFULL ? 0xFFFFFFFFUL : (uint32_t)ml;
}

uint32_t board_ml_of_grams(const Config *c, uint32_t grams)
{
    uint64_t ml;

    if (c->density == 0) {
        return 0;
    }
    ml = ((uint64_t)grams * 10000ULL + c->density / 2) / c->density;

    return ml > 0xFFFFFFFFULL ? 0xFFFFFFFFUL : (uint32_t)ml;
}

/* ---------------------------------------------------------------- the calendar */

static int leap(unsigned year)
{
    return (year % 4 == 0 && year % 100 != 0) || year % 400 == 0;
}

static int valid_time(const DateTime *t)
{
    static const uint8_t days[12] = {31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31};
    unsigned limit;

    if (t->year < 2000 || t->year > 2099 || t->month < 1 || t->month > 12 || t->hour > 23 || t->minute > 59 || t->second > 59) {
        return 0;
    }
    limit = days[t->month - 1] + ((t->month == 2 && leap(t->year)) ? 1u : 0u);

    return t->day >= 1 && t->day <= limit;
}

static void time_to_bytes(const DateTime *t, uint8_t *out)
{
    put16(out, t->year);
    out[2] = t->month;
    out[3] = t->day;
    out[4] = t->hour;
    out[5] = t->minute;
    out[6] = t->second;
}

static void time_from_bytes(const uint8_t *in, DateTime *t)
{
    t->year = get16(in);
    t->month = in[2];
    t->day = in[3];
    t->hour = in[4];
    t->minute = in[5];
    t->second = in[6];
}

/* ---------------------------------------------------------------- answers */

static void build(uint8_t *out, size_t *out_len, uint8_t address, uint8_t seq, uint8_t cmd, const uint8_t *data, size_t len)
{
    uint16_t crc;

    out[0] = STX;
    out[1] = address;
    out[2] = seq;
    out[3] = cmd;
    out[4] = (uint8_t)len;
    if (len) {
        memcpy(out + 5, data, len);
    }
    crc = crc16(out + 1, 4 + len);
    out[5 + len] = (uint8_t)(crc & 0xFF);
    out[6 + len] = (uint8_t)(crc >> 8);
    *out_len = 7 + len;
}

static void answer(Board *b, uint8_t seq, uint8_t cmd, const uint8_t *data, size_t len)
{
    build(b->tx, &b->tx_len, b->address, seq, (uint8_t)(cmd | ANSWER), data, len);
}

static void nak(Board *b, uint8_t seq, uint8_t cmd, uint8_t error)
{
    uint8_t d[2];

    d[0] = error;
    d[1] = cmd;
    build(b->tx, &b->tx_len, b->address, seq, CMD_NAK, d, 2);
}

/* ---------------------------------------------------------------- the memory */

/* The record of the fill in progress, and at a power cut: written without an erase (a program takes under a millisecond). It may be refused when the memory was not
   prepared; then the last record stays and nothing is lost but the counts since it. Never a fault. */
static void save_progress(Board *b)
{
    if (b->hal->save_now) {
        b->hal->save_now(b->hal->ctx, &b->p);
    } else {
        b->hal->save(b->hal->ctx, &b->p);
    }
}

static int save(Board *b)
{
    if (b->hal->save(b->hal->ctx, &b->p)) {
        return 1;
    }
    /* the memory refused: nothing is promised any more, so the board stops working until it is power cycled */
    if (b->fault != FAULT_MEMORY) {
        board_set_fault(b, FAULT_MEMORY);
    }

    return 0;
}

/* ---------------------------------------------------------------- the pump */

static int hook_lifted(const Board *b)
{
    return b->hal->hook ? b->hal->hook(b->hal->ctx) != 0 : 1;
}

/* The pump is stopped by the hardware too, at the count where the core would stop it (see Hal.arm_stop): the count of START plus the target, less the early close. */
static void arm_stop(Board *b)
{
    if (b->hal->arm_stop) {
        const uint32_t target = b->p.inflight.target_pulses;
        const uint32_t early = target > b->params.early_close_pulses ? b->params.early_close_pulses : 0;

        b->hal->arm_stop(b->hal->ctx, b->pulse_base + (target - early));
    }
}

static void disarm_stop(Board *b)
{
    if (b->hal->disarm_stop) {
        b->hal->disarm_stop(b->hal->ctx);
    }
}

static void set_pump(Board *b, int on)
{
    if (b->pump_on != (uint8_t)(on != 0)) {
        b->pump_on = (uint8_t)(on != 0);
        b->hal->pump(b->hal->ctx, on != 0);
    }
}

/* ---------------------------------------------------------------- the fill */

/* the counts of the fill so far; a backflow takes them off again but they do not go below 0 */
static uint32_t counts_since_start(const Board *b)
{
    const int32_t d = (int32_t)(b->hal->pulses(b->hal->ctx) - b->pulse_base);

    return d < 0 ? 0 : (uint32_t)d;
}

static void hold_result(Board *b, uint32_t id, uint32_t preset_g, uint32_t g, uint32_t ml, uint8_t reason)
{
    Result *r = &b->p.result;

    b->p.total_g += g;                  /* the total of the board counts every fill, whatever the mode; it is saved with the result below */

    if (b->p.config.mode == MODE_MANUAL) {
        /* a manual board holds no result and tells nothing about the end of a fill */
        b->p.inflight.active = 0;
        b->p.result.held = 0;
        save(b);

        return;
    }

    r->held = 1;
    r->id = id;
    r->preset_g = preset_g;
    r->dispensed_g = g;
    r->dispensed_ml = ml;
    r->reason = reason;
    b->p.inflight.active = 0;
    save(b);
}

/* the weight and the volume of a fill that did not reach its target: what the counts say, never more than the preset */
static void partial_amounts(const Config *c, uint32_t pulses, uint32_t preset_g, uint32_t *g, uint32_t *ml)
{
    const uint32_t most_ml = board_ml_of_grams(c, preset_g);

    *g = board_grams_of(c, pulses);
    if (*g > preset_g) {
        *g = preset_g;
    }
    *ml = ml_of_pulses(c, pulses);
    if (*ml > most_ml) {
        *ml = most_ml;
    }
}

/* The tolerance of the board: a fill that ends this close to the preset (and has dispensed something) counts as the preset. The terminal and the web app have no tolerance of
   their own: whatever the result says short of the preset is a remainder ticket, so a few grams are settled here, by this one rule. */
static int within_tolerance(const Board *b, uint32_t grams)
{
    return grams > 0 && b->preset_g - grams <= b->params.preset_tolerance_g;
}

/* The preset is reached and the pump is stopping: the fill is the preset (the result will say so too), whatever the last counts are, so the display does not stop a few grams
   short of it (the pump stops a little before the target: the lead, and the counts are whole), and a fill that ends within the tolerance is the preset too. */
static int reached_preset(const Board *b)
{
    uint32_t g, ml;

    if (b->state != STATE_DISPENSING || !b->closing) {
        return 0;
    }
    if (b->closing_reason == REASON_PRESET) {
        return 1;
    }
    if (b->closing_reason == REASON_NO_FLOW) {
        partial_amounts(&b->p.config, b->pulses_seen, b->preset_g, &g, &ml);

        return within_tolerance(b, g);
    }

    return 0;
}

uint32_t board_live_grams(const Board *b)
{
    uint32_t g, ml;

    if (b->state == STATE_DONE) {
        return b->p.result.dispensed_g;
    }
    if (b->state != STATE_DISPENSING) {
        return 0;
    }
    if (reached_preset(b)) {
        return b->preset_g;
    }
    partial_amounts(&b->p.config, b->pulses_seen, b->preset_g, &g, &ml);

    return g;
}

uint32_t board_live_ml(const Board *b)
{
    uint32_t g, ml;

    if (b->state == STATE_DONE) {
        return b->p.result.dispensed_ml;
    }
    if (b->state != STATE_DISPENSING) {
        return 0;
    }
    if (reached_preset(b)) {
        return board_ml_of_grams(&b->p.config, b->preset_g);
    }
    partial_amounts(&b->p.config, b->pulses_seen, b->preset_g, &g, &ml);

    return ml;
}

/* The fill is over: the result is held (in the memory first), then the state shows it. */
static void finish_fill(Board *b, uint8_t reason)
{
    uint32_t g, ml;
    const Config *c = &b->p.config;

    set_pump(b, 0);
    disarm_stop(b);
    if (reason == REASON_PRESET) {
        /* the target was reached: by the calibration that is the preset, whatever the last few counts while the pump stops say */
        g = b->preset_g;
        ml = board_ml_of_grams(c, g);
    } else {
        partial_amounts(c, b->pulses_seen, b->preset_g, &g, &ml);
        if (reason == REASON_NO_FLOW && within_tolerance(b, g)) {
            /* short of the preset by the tolerance or less (0.03 kg): the preset is taken as reached, and no remainder ticket is made for a few grams */
            g = b->preset_g;
            ml = board_ml_of_grams(c, g);
            reason = REASON_PRESET;
        }
    }
    b->closing = 0;
    hold_result(b, b->p.inflight.id, b->preset_g, g, ml, reason);
    if (b->p.config.mode == MODE_MANUAL) {
        b->preset_g = 0;                  /* the fill is over and nothing waits: the dispenser is free for the next amount */
        b->held_g = g;                    /* ... and the displays keep what was dispensed until the next preset is set */
        b->held_ml = ml;
        b->state = STATE_IDLE;
    } else {
        b->state = STATE_DONE;
    }
}

/* The pump stops; the counts that still come are counted, then the result is held. */
static void begin_closing(Board *b, uint8_t reason)
{
    set_pump(b, 0);
    disarm_stop(b);
    b->closing = 1;
    b->closing_reason = reason;
    b->closing_ms = 0;
    b->quiet_ms = 0;
}

/* The fill starts with the session id of START (0 for a manual board, which has no session). */
static int start_fill(Board *b, uint32_t session_id)
{
    Inflight *f = &b->p.inflight;

    f->active = 1;
    f->id = session_id;
    f->preset_g = b->preset_g;
    f->target_pulses = board_target_pulses(&b->p.config, b->preset_g);
    f->pulses = 0;
    b->p.result.held = 0;
    if (!save(b)) {
        return 0; /* the session id is not promised before it is stored */
    }
    b->pulse_base = b->hal->pulses(b->hal->ctx);
    b->pulses_seen = 0;
    b->flow_started = 0;
    b->closing = 0;
    b->since_start_ms = 0;
    b->no_flow_for_ms = 0;
    b->hook_down_ms = 0;
    b->since_save_ms = 0;
    b->saved_pulses = 0;
    b->state = STATE_DISPENSING;
    arm_stop(b);                       /* the hardware stops the pump at the target, whatever the main loop is doing then */
    set_pump(b, hook_lifted(b) && !b->powering_down);

    return 1;
}

static void run_fill(Board *b, uint32_t elapsed_ms)
{
    const uint32_t seen = counts_since_start(b);
    const uint32_t target = b->p.inflight.target_pulses;
    const int changed = seen != b->pulses_seen;
    int lifted;
    uint32_t early;

    b->pulses_seen = seen;
    b->p.inflight.pulses = seen;

    if (b->closing) {
        b->closing_ms += elapsed_ms;
        b->quiet_ms = changed ? 0 : b->quiet_ms + elapsed_ms;
        if (b->quiet_ms >= b->params.settle_ms || b->closing_ms >= b->params.settle_max_ms) {
            finish_fill(b, b->closing_reason);
        }

        return;
    }

    b->since_start_ms += elapsed_ms;
    if (changed) {
        b->flow_started = 1;
        b->no_flow_for_ms = 0;
    } else if (b->flow_started) {
        b->no_flow_for_ms += elapsed_ms;
    }

    /* the target first: when it is reached the pump is not switched on again (the hardware may have stopped it already) */
    early = target > b->params.early_close_pulses ? b->params.early_close_pulses : 0;
    if (seen >= target - early) {
        begin_closing(b, REASON_PRESET);

        return;
    }

    /* the pump runs only while the nozzle is lifted */
    lifted = hook_lifted(b);
    set_pump(b, lifted && !b->powering_down);
    b->hook_down_ms = lifted ? 0 : b->hook_down_ms + elapsed_ms;
    arm_stop(b);                       /* (again: a stop that was too far off for the 16 bit timer is armed when it comes near) */
    if ((b->flow_started && !lifted && b->hook_down_ms >= b->params.hook_down_end_ms)
        || (b->flow_started && b->no_flow_for_ms >= b->params.no_flow_ms)
        || (!b->flow_started && b->since_start_ms >= b->params.start_wait_ms)) {
        begin_closing(b, REASON_NO_FLOW);

        return;
    }

    b->since_save_ms += elapsed_ms;
    if (b->since_save_ms >= b->params.save_ms) {
        b->since_save_ms = 0;
        if (seen != b->saved_pulses) {
            b->saved_pulses = seen;
            save_progress(b);
        }
    }
}

/* ---------------------------------------------------------------- start up */

void board_default_params(Params *params)
{
    params->rx_gap_ms = 10;
    params->no_flow_ms = 30000;
    params->start_wait_ms = 600000;
    params->hook_down_end_ms = 3000;
    params->settle_ms = 200;
    params->settle_max_ms = 1000;
    params->save_ms = 2000;
    params->early_close_pulses = 0;
    params->balance_show_ms = 60000;
    params->preset_tolerance_g = 30;
}

void board_init(Board *b, const Hal *hal, uint8_t address, const Params *params)
{
    int loaded;

    memset(b, 0, sizeof(*b));
    b->hal = hal;
    b->address = address;
    if (params) {
        b->params = *params;
    } else {
        board_default_params(&b->params);
    }
    hal->pump(hal->ctx, 0);

    loaded = hal->load(hal->ctx, &b->p);
    if (loaded < 0) {
        /* a record is there but cannot be read: the result ids cannot be trusted, so the board does not work (fault 3) until it is serviced */
        memset(&b->p, 0, sizeof(b->p));
        b->fault = FAULT_MEMORY;
        b->state = STATE_FAULT;

        return;
    }
    if (loaded == 0) {
        memset(&b->p, 0, sizeof(b->p)); /* a new board: not configured */
    }
    if (b->p.inflight.active && b->p.config.mode == MODE_MANUAL) {
        /* a manual board holds no result: a fill that a power cut interrupted is dropped (what it dispensed is in the total) */
        uint32_t g, ml;

        partial_amounts(&b->p.config, b->p.inflight.pulses, b->p.inflight.preset_g, &g, &ml);
        b->p.total_g += g;
        b->p.inflight.active = 0;
        b->p.result.held = 0;
        save(b);
    }
    if (b->p.inflight.active) {
        /* a power cut in the middle of a fill: it comes back as a result holding the last stored value */
        const Inflight f = b->p.inflight;
        uint32_t g, ml;

        partial_amounts(&b->p.config, f.pulses, f.preset_g, &g, &ml);
        b->preset_g = f.preset_g;
        hold_result(b, f.id, f.preset_g, g, ml, REASON_INTERRUPTED);
    }
    if (b->p.result.held) {
        b->preset_g = b->p.result.preset_g;
        b->state = STATE_DONE;
    } else {
        b->state = STATE_IDLE;
    }
}

/* ---------------------------------------------------------------- commands */

static int config_ok(const Config *c)
{
    return (c->mode == MODE_AUTO || c->mode == MODE_MANUAL) && c->fuel[0] != 0 && c->density >= MIN_DENSITY && c->density <= MAX_DENSITY
        && c->pulses_x100 >= 1 && c->pulses_x100 <= MAX_PULSES_X100;
}

static void config_to_bytes(const Config *c, uint8_t *out)
{
    out[0] = c->mode;
    memcpy(out + 1, c->fuel, FUEL_LEN);
    put16(out + 5, c->density);
    put32(out + 7, c->pulses_x100);
}

static void config_from_bytes(const uint8_t *in, Config *c)
{
    c->mode = in[0];
    memcpy(c->fuel, in + 1, FUEL_LEN);
    c->density = get16(in + 5);
    c->pulses_x100 = get32(in + 7);
}

static void do_poll(Board *b, uint8_t seq)
{
    uint8_t d[19];
    uint8_t flags = (uint8_t)(b->key_events & (FLAG_SELECT_KEY | FLAG_CLEAR_KEY));
    uint32_t id = 0, g = 0, ml = 0, preset = 0;

    if (b->state == STATE_DONE) {
        flags |= FLAG_RESULT;
        id = b->p.result.id;
        g = b->p.result.dispensed_g;
        ml = b->p.result.dispensed_ml;
        preset = b->p.result.preset_g;
    } else if (b->state == STATE_DISPENSING) {
        id = b->p.inflight.id;
        partial_amounts(&b->p.config, b->pulses_seen, b->preset_g, &g, &ml);
        preset = b->preset_g;
    } else if (b->state == STATE_SET) {
        preset = b->preset_g;
    }
    if (b->state == STATE_FAULT || b->fault != 0) {
        flags |= FLAG_FAULT;
    }
    if (hook_lifted(b)) {
        flags |= FLAG_GUN_UP;     /* a level: set as long as the gun is up */
    }
    d[0] = b->state;
    d[1] = flags;
    d[2] = b->fault;
    put32(d + 3, id);
    put32(d + 7, g);
    put32(d + 11, ml);
    put32(d + 15, preset);
    b->key_events = 0; /* raised once */
    answer(b, seq, CMD_POLL, d, sizeof(d));
}

/* The data length each command takes; -1 for a command this board does not know. */
static int data_length(uint8_t cmd)
{
    switch (cmd) {
    case CMD_INFO: case CMD_POLL: case CMD_SELECT: case CMD_GET_RESULT: case CMD_GET_CONFIG: case CMD_GET_TIME: case CMD_GET_TOTAL: case CMD_RESET_TOTAL:
        return 0;
    case CMD_SET_PRESET: case CMD_START: case CMD_ACK_RESULT: case CMD_SHOW_BALANCE:
        return 4;
    case CMD_SET_CONFIG:
        return CONFIG_SIZE;
    case CMD_SET_TIME:
        return TIME_SIZE;
    default:
        return -1;
    }
}

static void execute(Board *b, uint8_t seq, uint8_t cmd, const uint8_t *data, size_t len)
{
    const int manual = b->p.config.mode == MODE_MANUAL;     /* a manual board answers POLL, INFO, the config and the time; the rest is WRONG_STATE */
    const int want = data_length(cmd);
    uint8_t d[17];

    if (want < 0) {
        nak(b, seq, cmd, ERR_BAD_COMMAND);

        return;
    }
    if ((size_t)want != len) {
        nak(b, seq, cmd, ERR_BAD_LENGTH);

        return;
    }

    switch (cmd) {
    case CMD_POLL:
        do_poll(b, seq);
        break;
    case CMD_INFO:
        d[0] = PROTOCOL_VERSION;
        d[1] = FW_MAJOR;
        d[2] = FW_MINOR;
        put32(d + 3, b->hal->serial(b->hal->ctx));
        answer(b, seq, cmd, d, 7);
        break;
    case CMD_SELECT:
        if (b->state == STATE_FAULT) {
            nak(b, seq, cmd, ERR_FAULT);
        } else if (manual || (b->state != STATE_IDLE && b->state != STATE_SET)) {
            nak(b, seq, cmd, ERR_WRONG_STATE);
        } else {
            /* the board only beeps: its state does not change, the choice of the nozzle is kept by the Smart210 */
            if (b->hal->beep) {
                b->hal->beep(b->hal->ctx);
            }
            answer(b, seq, cmd, 0, 0);
        }
        break;
    case CMD_SET_PRESET:
        if (b->state == STATE_FAULT) {
            nak(b, seq, cmd, ERR_FAULT);
        } else if (manual || (b->state != STATE_IDLE && b->state != STATE_SET)) {
            nak(b, seq, cmd, ERR_WRONG_STATE);
        } else {
            b->preset_g = get32(data);
            if (b->preset_g > 0) {
                b->held_g = 0;                  /* the last fill goes from the displays: they show this preset */
                b->held_ml = 0;
            }
            b->shown_g = 0;                     /* the display shows the preset now, not a balance */
            b->shown_left_ms = 0;
            b->state = b->preset_g == 0 ? STATE_IDLE : STATE_SET;   /* a preset of zero is the cancel */
            answer(b, seq, cmd, data, 4);
        }
        break;
    case CMD_SHOW_BALANCE:
        if (b->state == STATE_FAULT) {
            nak(b, seq, cmd, ERR_FAULT);
        } else if (manual || b->state != STATE_IDLE) {
            nak(b, seq, cmd, ERR_WRONG_STATE);
        } else if (get32(data) > MAX_SHOWN_G) {
            nak(b, seq, cmd, ERR_BAD_VALUE);    /* more digits than the display has */
        } else {
            b->shown_g = get32(data);           /* it arms nothing: no preset, the state stays IDLE, a gun taken up changes nothing */
            b->shown_left_ms = b->shown_g ? b->params.balance_show_ms : 0;
            answer(b, seq, cmd, data, 4);
        }
        break;
    case CMD_START:
        if (!configured(&b->p.config)) {
            nak(b, seq, cmd, ERR_NOT_CONFIGURED);
        } else if (b->state == STATE_FAULT) {
            nak(b, seq, cmd, ERR_FAULT);
        } else if (manual || b->state != STATE_SET) {
            nak(b, seq, cmd, ERR_WRONG_STATE);
        } else if (get32(data) == 0) {
            nak(b, seq, cmd, ERR_BAD_VALUE);    /* 0 is no session */
        } else if (board_target_pulses(&b->p.config, b->preset_g) == 0) {
            nak(b, seq, cmd, ERR_BAD_VALUE);    /* less than one count */
        } else if (!start_fill(b, get32(data))) {
            nak(b, seq, cmd, ERR_FAULT);
        } else {
            put32(d, b->p.inflight.id);
            answer(b, seq, cmd, d, 4);
        }
        break;
    case CMD_GET_RESULT:
        if (b->state != STATE_DONE) {
            nak(b, seq, cmd, ERR_NO_RESULT);
        } else {
            put32(d, b->p.result.id);
            put32(d + 4, b->p.result.preset_g);
            put32(d + 8, b->p.result.dispensed_g);
            put32(d + 12, b->p.result.dispensed_ml);
            d[16] = b->p.result.reason;
            answer(b, seq, cmd, d, 17);
        }
        break;
    case CMD_ACK_RESULT: {
        const uint32_t id = get32(data);

        if (b->state == STATE_DONE && id == b->p.result.id) {
            b->p.last_acked_id = id;
            b->p.result.held = 0;
            save(b);
            b->held_g = b->p.result.dispensed_g;   /* the result is gone, the displays keep its amount until the next preset */
            b->held_ml = b->p.result.dispensed_ml;
            b->preset_g = 0;
            b->state = b->fault != 0 ? STATE_FAULT : STATE_IDLE;
            answer(b, seq, cmd, data, 4);
        } else if (id == b->p.last_acked_id && id != 0) {
            answer(b, seq, cmd, data, 4); /* already done: a lost answer is repeated */
        } else {
            nak(b, seq, cmd, b->state == STATE_DONE ? ERR_ID_MISMATCH : ERR_NO_RESULT);
        }
        break;
    }
    case CMD_GET_CONFIG:
        config_to_bytes(&b->p.config, d);
        answer(b, seq, cmd, d, CONFIG_SIZE);
        break;
    case CMD_SET_CONFIG: {
        Config wanted;

        config_from_bytes(data, &wanted);
        if (b->state != STATE_IDLE) {
            nak(b, seq, cmd, ERR_WRONG_STATE);
        } else if (!config_ok(&wanted)) {
            nak(b, seq, cmd, ERR_BAD_VALUE);
        } else {
            const Config old = b->p.config;

            b->p.config = wanted;
            if (!save(b)) {
                b->p.config = old;
                nak(b, seq, cmd, ERR_FAULT);
            } else {
                b->held_g = 0;                  /* another calibration or mode: the last fill is not shown any more */
                b->held_ml = 0;
                config_to_bytes(&b->p.config, d);
                answer(b, seq, cmd, d, CONFIG_SIZE);
            }
        }
        break;
    }
    case CMD_GET_TOTAL:
        put32(d, (uint32_t)(b->p.total_g & 0xFFFFFFFFu));
        put32(d + 4, (uint32_t)(b->p.total_g >> 32));
        answer(b, seq, cmd, d, 8);
        break;
    case CMD_RESET_TOTAL:
        /* everything this board has dispensed goes back to 0; the answer is what it was (a manual board answers too: it is a service command, like the config) */
        if (b->state == STATE_FAULT) {
            nak(b, seq, cmd, ERR_FAULT);
        } else if (b->state == STATE_DISPENSING) {
            nak(b, seq, cmd, ERR_WRONG_STATE);
        } else {
            const uint64_t old = b->p.total_g;

            b->p.total_g = 0;
            if (!save(b)) {
                b->p.total_g = old;
                nak(b, seq, cmd, ERR_FAULT);
            } else {
                put32(d, (uint32_t)(old & 0xFFFFFFFFu));
                put32(d + 4, (uint32_t)(old >> 32));
                answer(b, seq, cmd, d, 8);
            }
        }
        break;
    case CMD_SET_TIME: {
        DateTime t;

        time_from_bytes(data, &t);
        if (!valid_time(&t)) {
            nak(b, seq, cmd, ERR_BAD_VALUE);
        } else if (!b->hal->rtc_set(b->hal->ctx, &t)) {
            nak(b, seq, cmd, ERR_FAULT);
        } else {
            time_to_bytes(&t, d);
            answer(b, seq, cmd, d, TIME_SIZE);
        }
        break;
    }
    case CMD_GET_TIME: {
        DateTime t;

        if (!b->hal->rtc_get(b->hal->ctx, &t)) {
            nak(b, seq, cmd, ERR_FAULT);
        } else {
            time_to_bytes(&t, d);
            answer(b, seq, cmd, d, TIME_SIZE);
        }
        break;
    }
    default:
        nak(b, seq, cmd, ERR_BAD_COMMAND);
        break;
    }
}

/* ---------------------------------------------------------------- the line */

static void handle_frame(Board *b, const uint8_t *f)
{
    const uint8_t seq = f[2];
    const uint8_t cmd = f[3];

    if (b->have_seq && b->last_seq == seq) {
        /* the same ADDR and SEQ again: nothing runs a second time, the last answer goes out again */
        memcpy(b->tx, b->last_reply, b->last_reply_len);
        b->tx_len = b->last_reply_len;

        return;
    }
    execute(b, seq, cmd, f + 5, f[4]);
    b->have_seq = 1;
    b->last_seq = seq;
    memcpy(b->last_reply, b->tx, b->tx_len);
    b->last_reply_len = b->tx_len;
}

static void drop(Board *b, size_t n)
{
    if (n >= b->rx_len) {
        b->rx_len = 0;
    } else {
        memmove(b->rx, b->rx + n, b->rx_len - n);
        b->rx_len -= n;
    }
}

/* Is there a complete frame with a good CRC starting at this offset of the receive buffer? Returns its length, or 0. */
static size_t frame_at(const Board *b, size_t at)
{
    size_t total;
    uint16_t crc;

    if (b->rx[at] != STX || b->rx_len - at < 5 || b->rx[at + 4] > MAX_DATA) {
        return 0;
    }
    total = (size_t)b->rx[at + 4] + 7;
    if (b->rx_len - at < total) {
        return 0;
    }
    crc = crc16(b->rx + at + 1, 4 + (size_t)b->rx[at + 4]);

    return (b->rx[at + total - 2] == (uint8_t)(crc & 0xFF) && b->rx[at + total - 1] == (uint8_t)(crc >> 8)) ? total : 0;
}

int board_feed(Board *b, uint8_t byte)
{
    int answered = 0;

    b->rx_idle_ms = 0;
    b->rx_fresh = 1;
    if (b->rx_len < sizeof(b->rx)) {
        b->rx[b->rx_len++] = byte;
    }

    for (;;) {
        size_t at = 0;
        size_t total = 0;

        /* A frame is taken as soon as it is complete and its CRC is right, wherever it starts: a stray 0x02 (noise, or the STX byte of a
           frame that was cut) in front of it must not hold it back until more bytes arrive. */
        while (at < b->rx_len && (total = frame_at(b, at)) == 0) {
            ++at;
        }
        if (at < b->rx_len) {
            drop(b, at);
            if (b->rx[1] == b->address) {
                handle_frame(b, b->rx);
                answered = 1;
            }
            drop(b, total);
            continue;
        }

        /* no good frame in the buffer: keep the bytes from the first STX that can still become one, throw the rest away */
        at = 0;
        while (at < b->rx_len) {
            if (b->rx[at] == STX && (b->rx_len - at < 5 || (b->rx[at + 4] <= MAX_DATA && b->rx_len - at < (size_t)b->rx[at + 4] + 7))) {
                break; /* still incomplete: wait for the rest */
            }
            ++at;
        }
        drop(b, at);
        break;
    }

    return answered;
}

const uint8_t *board_reply(const Board *b, size_t *len)
{
    *len = b->tx_len;

    return b->tx;
}

/* ---------------------------------------------------------------- time, keys, faults, power */

/* A half frame is dropped when no byte follows it for rx_gap_ms. The main loop takes the bytes out of the receive ring in bursts (a pass can last 30 ms while the
   display and the keyboard are served), so the time between two passes is not quiet time: a pass that took bytes starts the quiet time again, and the rest of a frame that
   was cut at the end of the ring is not thrown away. */
void board_tick(Board *b, uint32_t elapsed_ms)
{
    if (b->rx_fresh) {
        b->rx_fresh = 0;
    } else if (b->rx_len) {
        b->rx_idle_ms += elapsed_ms;
        if (b->rx_idle_ms >= b->params.rx_gap_ms) {
            b->rx_len = 0; /* a half frame is dropped */
            b->rx_idle_ms = 0;
        }
    }
    if (b->shown_left_ms) {
        b->shown_left_ms = b->shown_left_ms > elapsed_ms ? b->shown_left_ms - elapsed_ms : 0;
        if (b->shown_left_ms == 0) {
            b->shown_g = 0;               /* the balance goes from the display by itself */
        }
    }
    if (b->p.config.mode == MODE_MANUAL && b->state == STATE_SET && hook_lifted(b) && !b->powering_down && !start_fill(b, 0)) {
        b->state = STATE_FAULT;           /* the memory refused: the board stops working */
    }
    if (b->state == STATE_DISPENSING) {
        run_fill(b, elapsed_ms);
    }
}

/* The keys of the dispenser only report themselves (auto mode): the Smart210 sees the event in its next POLL and acts. */
void board_key_select(Board *b)
{
    if (b->p.config.mode == MODE_MANUAL) {
        return; /* the select key is not used in manual mode */
    }
    b->key_events |= FLAG_SELECT_KEY;
}

void board_key_clear(Board *b)
{
    if (b->p.config.mode == MODE_MANUAL) {
        if (b->state == STATE_SET) {
            b->preset_g = 0;              /* the amount that waited for the gun is dropped */
            b->state = STATE_IDLE;
        }

        return; /* a manual board reports no keys */
    }
    b->key_events |= FLAG_CLEAR_KEY;
}

uint32_t board_shown_grams(const Board *b)
{
    return b->shown_g;
}

uint32_t board_held_grams(const Board *b)
{
    return b->held_g;
}

uint32_t board_held_ml(const Board *b)
{
    return b->held_ml;
}

uint64_t board_total_kg(const Board *b)
{
    return b->p.total_g / 1000;
}

/* Manual mode: the amount the supplier confirmed is the preset (the dispenser is SET); the fill starts when the gun is up. */
int board_manual_amount(Board *b, uint32_t grams)
{
    if (b->p.config.mode != MODE_MANUAL || b->state != STATE_IDLE || grams == 0 || !configured(&b->p.config)
        || board_target_pulses(&b->p.config, grams) == 0) {
        return 0;
    }
    b->preset_g = grams;
    b->held_g = 0;                        /* the last fill goes from the displays: they show this preset now */
    b->held_ml = 0;
    b->state = STATE_SET;

    return 1;
}

void board_set_fault(Board *b, uint8_t code)
{
    b->fault = code;
    if (code != 0) {
        if (b->state == STATE_DISPENSING) {
            finish_fill(b, REASON_FAULT); /* the result is held; the board is in fault after it is acknowledged */
            if (b->p.config.mode == MODE_MANUAL) {
                b->state = STATE_FAULT;   /* a manual board holds no result to acknowledge */
            }
        } else if (b->state != STATE_DONE) {
            b->state = STATE_FAULT;
        }
    } else if (b->state == STATE_FAULT) {
        b->state = STATE_IDLE;
    }
}

/* The supply is going: the pump stops at once and the fill so far is written, so that after the restart it comes back as a result with
   reason 2 holding these counts (and not the ones of the last periodic write). */
void board_power_fail(Board *b)
{
    if (b->powering_down) {
        return;
    }
    b->powering_down = 1;
    set_pump(b, 0);
    disarm_stop(b);
    if (b->state == STATE_DISPENSING) {
        b->pulses_seen = counts_since_start(b);
        b->p.inflight.pulses = b->pulses_seen;
        save_progress(b);                  /* no erase: it is only a program, which the supply still lasts for */
    }
}

void board_idle_work(Board *b)
{
    if (b->hal->housekeeping && !b->powering_down && (b->state == STATE_IDLE || b->state == STATE_DONE)) {
        b->hal->housekeeping(b->hal->ctx);
    }
}

void board_power_restored(Board *b)
{
    b->powering_down = 0;
}
