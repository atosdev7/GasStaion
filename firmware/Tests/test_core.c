/* Tests of the nozzle-board core on the PC: PROTOCOL.md (v0.4, SHOW_BALANCE, session ids) against board.c with a fake board (no STM32 code). */

#include "../Core/Inc/board.h"
#include "../Core/Inc/checksum.h"

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

/* ---------------------------------------------------------------- a fake board */

typedef struct {
    Persist stored;
    int has_record;     /* 1 a record exists, 0 none, -1 damaged */
    int fail_save;      /* the memory refuses */
    int fail_save_now;  /* the save without an erase refuses (the sector was not prepared) */
    int saves;
    int saves_now;      /* the saves without an erase (the fill in progress, the power cut) */
    int idle_work;      /* housekeeping calls */
    int stop_armed;     /* the hardware stop of the pump (Hal.arm_stop) */
    uint32_t stop_at;
    int arms, disarms;
    uint32_t pulses;
    int pump_on;
    int pump_changes;
    int hook;           /* 1 the nozzle is lifted */
    int beeps;          /* SELECT beeps */
    DateTime rtc;
    int rtc_sets;
    uint32_t serial;
} Fake;

static int f_load(void *ctx, Persist *out)
{
    Fake *f = (Fake *)ctx;

    if (f->has_record == 1) {
        *out = f->stored;

        return 1;
    }

    return f->has_record;
}

static int f_save(void *ctx, const Persist *in)
{
    Fake *f = (Fake *)ctx;

    if (f->fail_save) {
        return 0;
    }
    f->stored = *in;
    f->has_record = 1;
    ++f->saves;

    return 1;
}

static int f_save_now(void *ctx, const Persist *in)
{
    Fake *f = (Fake *)ctx;

    if (f->fail_save_now) {
        return 0;
    }
    f->stored = *in;
    f->has_record = 1;
    ++f->saves_now;

    return 1;
}

static void f_arm_stop(void *ctx, uint32_t at)
{
    Fake *f = (Fake *)ctx;

    f->stop_armed = 1;
    f->stop_at = at;
    ++f->arms;
}

static void f_disarm_stop(void *ctx)
{
    Fake *f = (Fake *)ctx;

    f->stop_armed = 0;
    ++f->disarms;
}

static void f_housekeeping(void *ctx)
{
    ++((Fake *)ctx)->idle_work;
}

static uint32_t f_pulses(void *ctx)
{
    return ((Fake *)ctx)->pulses;
}

static void f_pump(void *ctx, int on)
{
    Fake *f = (Fake *)ctx;

    if (f->pump_on != on) {
        ++f->pump_changes;
    }
    f->pump_on = on;
}

static int f_hook(void *ctx)
{
    return ((Fake *)ctx)->hook;
}

static void f_beep(void *ctx)
{
    ++((Fake *)ctx)->beeps;
}

static int f_rtc_get(void *ctx, DateTime *out)
{
    *out = ((Fake *)ctx)->rtc;

    return 1;
}

static int f_rtc_set(void *ctx, const DateTime *in)
{
    ((Fake *)ctx)->rtc = *in;
    ++((Fake *)ctx)->rtc_sets;

    return 1;
}

static uint32_t f_serial(void *ctx)
{
    return ((Fake *)ctx)->serial;
}

static Hal hal_of(Fake *f)
{
    Hal h;

    memset(&h, 0, sizeof(h));
    h.ctx = f;
    h.load = f_load;
    h.save = f_save;
    h.save_now = f_save_now;
    h.housekeeping = f_housekeeping;
    h.arm_stop = f_arm_stop;
    h.disarm_stop = f_disarm_stop;
    h.pulses = f_pulses;
    h.pump = f_pump;
    h.hook = f_hook;
    h.beep = f_beep;
    h.rtc_get = f_rtc_get;
    h.rtc_set = f_rtc_set;
    h.serial = f_serial;

    return h;
}

typedef struct {
    Fake fake;
    Hal hal;
    Board board;
    uint8_t seq;
    uint32_t session;   /* the last session id START was given by start_auto() */
    uint8_t reply[FRAME_MAX];
    size_t reply_len;
} Rig;

static void rig_boot(Rig *r, uint8_t address)
{
    r->hal = hal_of(&r->fake);
    board_init(&r->board, &r->hal, address, 0);
}

static void rig_new(Rig *r, uint8_t address)
{
    memset(r, 0, sizeof(*r));
    r->fake.serial = 0xCAFE0001;
    r->fake.hook = 1;
    r->fake.rtc.year = 2000;
    r->fake.rtc.month = 1;
    r->fake.rtc.day = 1;
    rig_boot(r, address);
}

/* the power goes and comes back: the memory stays, everything else starts again */
static void rig_power_cycle(Rig *r)
{
    const uint8_t address = r->board.address;
    const uint32_t pulses = r->fake.pulses;
    const int hook = r->fake.hook;

    memset(&r->board, 0, sizeof(r->board));
    r->fake.pulses = pulses;
    r->fake.hook = hook;
    rig_boot(r, address);
}

/* ---------------------------------------------------------------- frames */

static size_t frame(uint8_t *out, uint8_t addr, uint8_t seq, uint8_t cmd, const uint8_t *data, size_t len)
{
    uint16_t crc;

    out[0] = 0x02;
    out[1] = addr;
    out[2] = seq;
    out[3] = cmd;
    out[4] = (uint8_t)len;
    if (len) {
        memcpy(out + 5, data, len);
    }
    crc = crc16(out + 1, 4 + len);
    out[5 + len] = (uint8_t)(crc & 0xFF);
    out[6 + len] = (uint8_t)(crc >> 8);

    return 7 + len;
}

static int feed_all(Rig *r, const uint8_t *bytes, size_t len)
{
    size_t i;
    int answered = 0;

    r->reply_len = 0;
    for (i = 0; i < len; ++i) {
        if (board_feed(&r->board, bytes[i])) {
            const uint8_t *rep;

            rep = board_reply(&r->board, &r->reply_len);
            memcpy(r->reply, rep, r->reply_len);
            answered = 1;
        }
    }

    return answered;
}

/* sends a request with a new SEQ; returns the answer's CMD (0 when there is none) */
static int ask(Rig *r, uint8_t cmd, const uint8_t *data, size_t len)
{
    uint8_t out[FRAME_MAX];
    const size_t n = frame(out, r->board.address, ++r->seq, cmd, data, len);

    return feed_all(r, out, n) ? r->reply[3] : 0;
}

static int ask0(Rig *r, uint8_t cmd)
{
    return ask(r, cmd, 0, 0);
}

static uint32_t u32at(const uint8_t *p)
{
    return (uint32_t)p[0] | ((uint32_t)p[1] << 8) | ((uint32_t)p[2] << 16) | ((uint32_t)p[3] << 24);
}

static void put32(uint8_t *p, uint32_t v)
{
    p[0] = (uint8_t)v;
    p[1] = (uint8_t)(v >> 8);
    p[2] = (uint8_t)(v >> 16);
    p[3] = (uint8_t)(v >> 24);
}

/* the data of the last answer */
#define DATA(r) ((r)->reply + 5)
#define NAK_ERROR(r) ((r)->reply[5])

static int ask_u32(Rig *r, uint8_t cmd, uint32_t value)
{
    uint8_t d[4];

    put32(d, value);

    return ask(r, cmd, d, 4);
}

static void configure(Rig *r, int mode, uint32_t density, uint32_t pulses_x100)
{
    uint8_t d[11];

    d[0] = (uint8_t)mode;
    d[1] = 'D';
    d[2] = d[3] = d[4] = 0;
    d[5] = (uint8_t)(density & 0xFF);
    d[6] = (uint8_t)(density >> 8);
    put32(d + 7, pulses_x100);
    CHECK_EQ(ask(r, CMD_SET_CONFIG, d, 11), CMD_SET_CONFIG | 0x80);
}

static int state_of(Rig *r)
{
    ask0(r, CMD_POLL);

    return DATA(r)[0];
}

static void poll(Rig *r)
{
    CHECK_EQ(ask0(r, CMD_POLL), CMD_POLL | 0x80);
}

/* time passes in 10 ms steps while the flow meter gives `pulses_per_step` counts in every step */
static void run(Rig *r, int steps, uint32_t pulses_per_step)
{
    int i;

    for (i = 0; i < steps; ++i) {
        r->fake.pulses += pulses_per_step;
        board_tick(&r->board, 10);
    }
}

/* auto mode with the usual calibration: density 0.8300, 1000.00 counts per litre; select (a beep), preset, start with the next session id (1, 2, ...) */
static uint32_t start_auto(Rig *r, uint32_t grams)
{
    const uint32_t session = ++r->session;

    CHECK_EQ(ask0(r, CMD_SELECT), CMD_SELECT | 0x80);
    CHECK_EQ(ask_u32(r, CMD_SET_PRESET, grams), CMD_SET_PRESET | 0x80);
    CHECK_EQ(ask_u32(r, CMD_START, session), CMD_START | 0x80);
    CHECK_EQ(u32at(DATA(r)), session);   /* the answer repeats the id */

    return session;
}

/* ---------------------------------------------------------------- tests */

static void test_crc_and_examples(void)
{
    uint8_t out[FRAME_MAX];
    static const uint8_t poll_req[] = {0x02, 0x01, 0x05, 0x02, 0x00, 0x10, 0xB9};
    static const uint8_t preset_req[] = {0x02, 0x02, 0x06, 0x05, 0x04, 0x98, 0x3A, 0x00, 0x00, 0x59, 0x2A};
    static const uint8_t time_req[] = {0x02, 0x01, 0x07, 0x0C, 0x07, 0xEA, 0x07, 0x0A, 0x0A, 0x09, 0x1E, 0x0F, 0xEF, 0x22};
    static const uint8_t time_ans[] = {0x02, 0x01, 0x07, 0x8C, 0x07, 0xEA, 0x07, 0x0A, 0x0A, 0x09, 0x1E, 0x0F, 0x8E, 0xE4};
    static const uint8_t config_req[] = {0x02, 0x03, 0x09, 0x0B, 0x0B, 0x00, 0x44, 0x00, 0x00, 0x00, 0x6C, 0x20, 0x3A, 0xE2, 0x01, 0x00, 0x93, 0x33};
    static const uint8_t nak_ans[] = {0x02, 0x02, 0x07, 0xFF, 0x02, 0x03, 0x06, 0xA5, 0x1F};
    static const uint8_t dispensing_ans[] = {0x02, 0x01, 0x05, 0x82, 0x13, 0x03, 0x00, 0x00, 0x2A, 0x00, 0x00, 0x00, 0xD4, 0x30, 0x00, 0x00,
                                              0x6C, 0x5A, 0x00, 0x00, 0x98, 0x3A, 0x00, 0x00, 0xCC, 0x8D};
    Rig r;
    size_t n;

    CHECK_EQ(crc16((const uint8_t *)"123456789", 9), 0x4B37);
    CHECK(crc32((const uint8_t *)"123456789", 9) == 0xCBF43926UL);

    /* the requests of section 7 are built by the same frame rule */
    n = frame(out, 1, 5, 0x02, 0, 0);
    CHECK(n == sizeof(poll_req) && memcmp(out, poll_req, n) == 0);
    n = frame(out, 2, 6, 0x05, (const uint8_t *)"\x98\x3A\x00\x00", 4);
    CHECK(n == sizeof(preset_req) && memcmp(out, preset_req, n) == 0);

    /* SET_TIME of board 1: the answer is the one of the document */
    rig_new(&r, 1);
    CHECK(feed_all(&r, time_req, sizeof(time_req)));
    CHECK(r.reply_len == sizeof(time_ans) && memcmp(r.reply, time_ans, r.reply_len) == 0);
    CHECK_EQ(r.fake.rtc.year, 2026);
    CHECK_EQ(r.fake.rtc.hour, 9);
    CHECK_EQ(r.fake.rtc.minute, 30);

    /* SET_CONFIG of board 3: the answer has CMD 0x8B and the same data */
    rig_new(&r, 3);
    CHECK(feed_all(&r, config_req, sizeof(config_req)));
    CHECK_EQ(r.reply[3], 0x8B);
    CHECK(r.reply_len == sizeof(config_req) && memcmp(r.reply + 5, config_req + 5, 11) == 0);

    /* START of board 2 in IDLE with a configured board: WRONG_STATE */
    rig_new(&r, 2);
    configure(&r, MODE_AUTO, 8300, 100000);
    r.seq = 6;
    CHECK(ask_u32(&r, CMD_START, 42) == CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
    CHECK(r.reply_len == sizeof(nak_ans) && memcmp(r.reply, nak_ans, r.reply_len) == 0);

    /* the document's POLL answer of a running fill: built the same way */
    n = frame(out, 1, 5, 0x82, dispensing_ans + 5, 19);
    CHECK(n == sizeof(dispensing_ans) && memcmp(out, dispensing_ans, n) == 0);
}

static void test_line(void)
{
    Rig r;
    uint8_t buf[64];
    uint8_t good[FRAME_MAX];
    size_t n;
    size_t i;

    rig_new(&r, 1);

    /* noise before the frame */
    n = frame(good, 1, 1, CMD_POLL, 0, 0);
    buf[0] = 0x55;
    buf[1] = 0x02;
    memcpy(buf + 2, good, n);
    CHECK(feed_all(&r, buf, n + 2));
    CHECK_EQ(r.reply[3], CMD_POLL | 0x80);

    /* a frame for another board: no answer */
    n = frame(good, 2, 2, CMD_POLL, 0, 0);
    CHECK(!feed_all(&r, good, n));

    /* a wrong CRC: dropped; a good frame that follows at once is still answered */
    n = frame(good, 1, 3, CMD_POLL, 0, 0);
    memcpy(buf, good, n);
    buf[n - 1] ^= 0x55;
    {
        uint8_t second[FRAME_MAX];
        const size_t m = frame(second, 1, 4, CMD_POLL, 0, 0);

        memcpy(buf + n, second, m);
        CHECK(feed_all(&r, buf, n + m));
        CHECK_EQ(r.reply[2], 4);
    }

    /* the bad frame hides a good one inside it: 02 01 then the good frame */
    n = frame(good, 1, 9, CMD_POLL, 0, 0);
    buf[0] = 0x02;
    buf[1] = 0x01;
    memcpy(buf + 2, good, n);
    CHECK(feed_all(&r, buf, n + 2));
    CHECK_EQ(r.reply[2], 9);

    /* delivered one byte at a time */
    n = frame(good, 1, 10, CMD_GET_CONFIG, 0, 0);
    for (i = 0; i + 1 < n; ++i) {
        CHECK(!board_feed(&r.board, good[i]));
    }
    CHECK(board_feed(&r.board, good[n - 1]));

    /* a half frame is thrown away when no byte follows (a tick that took bytes does not count as quiet time): the next frame is read from its start */
    n = frame(good, 1, 11, CMD_POLL, 0, 0);
    for (i = 0; i < 3; ++i) {
        board_feed(&r.board, good[i]);
    }
    board_tick(&r.board, 50);
    CHECK_EQ(r.board.rx_len, 3);       /* the pass that took the bytes: not dropped yet */
    board_tick(&r.board, 50);
    CHECK_EQ(r.board.rx_len, 0);       /* a quiet pass: dropped */
    CHECK(feed_all(&r, good, n));

    /* the main loop takes the bytes in bursts and a pass can last 30 ms: a frame that is cut at the end of the ring is completed by the next pass */
    n = frame(good, 1, 14, CMD_POLL, 0, 0);
    for (i = 0; i < 4; ++i) {
        CHECK(!board_feed(&r.board, good[i]));
    }
    board_tick(&r.board, 30);                                      /* the pass ends, 30 ms go by (the display and the keyboard) */
    for (i = 4; i + 1 < n; ++i) {
        CHECK(!board_feed(&r.board, good[i]));
    }
    CHECK(board_feed(&r.board, good[n - 1]));
    {
        size_t len;

        CHECK_EQ(board_reply(&r.board, &len)[2], 14);   /* the answer to that request */
    }

    /* a LEN above 64 is not a frame */
    buf[0] = 0x02;
    buf[1] = 1;
    buf[2] = 12;
    buf[3] = CMD_POLL;
    buf[4] = 200;
    CHECK(!feed_all(&r, buf, 5));
    n = frame(good, 1, 13, CMD_POLL, 0, 0);
    CHECK(feed_all(&r, good, n));
}

static void test_repeat_rule(void)
{
    Rig r;
    uint8_t req[FRAME_MAX];
    uint8_t first[FRAME_MAX];
    uint8_t session[4];
    size_t first_len;
    size_t n;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    CHECK_EQ(ask0(&r, CMD_SELECT), CMD_SELECT | 0x80);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 15000), CMD_SET_PRESET | 0x80);

    /* START twice with the same SEQ: it runs once, the same answer comes again */
    put32(session, 7);
    n = frame(req, 1, 100, CMD_START, session, 4);
    CHECK(feed_all(&r, req, n));
    memcpy(first, r.reply, r.reply_len);
    first_len = r.reply_len;
    CHECK_EQ(u32at(first + 5), 7);
    CHECK(feed_all(&r, req, n));
    CHECK(r.reply_len == first_len && memcmp(r.reply, first, first_len) == 0);
    CHECK_EQ(r.board.p.inflight.id, 7);

    /* with a new SEQ it is refused */
    n = frame(req, 1, 101, CMD_START, session, 4);
    CHECK(feed_all(&r, req, n));
    CHECK_EQ(r.reply[3], CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
}

/* A repeat is the same request, byte for byte. The same SEQ with another command or other data is a new request and is carried out (a master that started again counts its SEQ from
   the start, and the board must not answer it with the answer of the request before). */
static void test_repeat_needs_the_same_request(void)
{
    Rig r;
    uint8_t req[FRAME_MAX];
    uint8_t first[FRAME_MAX];
    uint8_t d[4];
    size_t first_len;
    size_t n;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);

    /* POLL with SEQ 5, then INFO with the same SEQ: the answer is the answer to INFO */
    n = frame(req, 1, 5, CMD_POLL, 0, 0);
    CHECK(feed_all(&r, req, n));
    CHECK_EQ(r.reply[3], CMD_POLL | 0x80);
    n = frame(req, 1, 5, CMD_INFO, 0, 0);
    CHECK(feed_all(&r, req, n));
    CHECK_EQ(r.reply[3], CMD_INFO | 0x80);
    CHECK_EQ(r.reply[4], 7);                                   /* the INFO answer has 7 bytes of data, the POLL answer 19 */

    /* the same command with other data and the same SEQ: carried out, the answer holds the new data */
    put32(d, 5000);
    n = frame(req, 1, 9, CMD_SET_PRESET, d, 4);
    CHECK(feed_all(&r, req, n));
    CHECK_EQ(u32at(r.reply + 5), 5000);
    CHECK_EQ(r.board.preset_g, 5000);
    put32(d, 7000);
    n = frame(req, 1, 9, CMD_SET_PRESET, d, 4);
    CHECK(feed_all(&r, req, n));
    CHECK_EQ(u32at(r.reply + 5), 7000);
    CHECK_EQ(r.board.preset_g, 7000);

    /* the same request again (a lost answer): not carried out again, the same answer; a change in between shows it */
    memcpy(first, r.reply, r.reply_len);
    first_len = r.reply_len;
    r.board.preset_g = 1234;                                   /* (what a second run would overwrite) */
    CHECK(feed_all(&r, req, n));
    CHECK(r.reply_len == first_len && memcmp(r.reply, first, first_len) == 0);
    CHECK_EQ(r.board.preset_g, 1234);

    /* another request in between: the old one is no longer a repeat, it runs again */
    n = frame(req, 1, 10, CMD_POLL, 0, 0);
    CHECK(feed_all(&r, req, n));
    put32(d, 7000);
    n = frame(req, 1, 9, CMD_SET_PRESET, d, 4);
    CHECK(feed_all(&r, req, n));
    CHECK_EQ(r.board.preset_g, 7000);
}

static void test_auto_fill(void)
{
    Rig r;
    uint32_t id;
    uint8_t d[4];

    rig_new(&r, 1);
    CHECK_EQ(board_target_pulses(&r.board.p.config, 15000), 0); /* not configured yet */

    /* not configured: START is refused */
    CHECK_EQ(ask0(&r, CMD_SELECT), CMD_SELECT | 0x80);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 15000), CMD_SET_PRESET | 0x80);
    CHECK_EQ(ask_u32(&r, CMD_START, 1), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_NOT_CONFIGURED);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 0), CMD_SET_PRESET | 0x80);   /* the cancel: a preset of zero */
    CHECK_EQ(state_of(&r), STATE_IDLE);

    configure(&r, MODE_AUTO, 8300, 100000);
    CHECK_EQ(board_target_pulses(&r.board.p.config, 15000), 18072); /* 15 kg at 0.83 kg/L is 18.072 L, 1000 counts per litre */

    id = start_auto(&r, 15000);
    CHECK_EQ(id, 1);
    CHECK_EQ(r.fake.pump_on, 1);
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_DISPENSING);
    CHECK_EQ(u32at(DATA(&r) + 3), 1);

    /* the fill runs: 500 counts in each 10 ms step */
    run(&r, 10, 500);
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_DISPENSING);
    CHECK_EQ(u32at(DATA(&r) + 7), 4150);  /* 5000 counts = 5 L = 4150 g */
    CHECK_EQ(u32at(DATA(&r) + 11), 5000); /* ml */
    CHECK_EQ(board_live_grams(&r.board), 4150);

    /* the cancel (a preset of zero), a new preset, SELECT and a second START are refused while it runs */
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 0), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 2000), CMD_NAK);
    CHECK_EQ(ask0(&r, CMD_SELECT), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
    CHECK_EQ(ask_u32(&r, CMD_START, 99), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);

    run(&r, 40, 500); /* 20000 more counts: well past the 18072 */
    CHECK_EQ(r.fake.pump_on, 0);
    run(&r, 30, 0);   /* the settle time passes */
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_DONE);
    CHECK(DATA(&r)[1] & FLAG_RESULT);
    CHECK_EQ(u32at(DATA(&r) + 3), 1);

    CHECK_EQ(ask0(&r, CMD_GET_RESULT), CMD_GET_RESULT | 0x80);
    CHECK_EQ(u32at(DATA(&r)), 1);
    CHECK_EQ(u32at(DATA(&r) + 4), 15000);
    CHECK_EQ(u32at(DATA(&r) + 8), 15000);  /* the preset was reached: the result is the preset, never more */
    CHECK_EQ(u32at(DATA(&r) + 12), 18072); /* 15000 g at 0.83 */
    CHECK_EQ(DATA(&r)[16], REASON_PRESET);

    /* the result is in the memory */
    CHECK_EQ(r.fake.stored.result.held, 1);

    /* ACK with another id, then the right one, then the same again */
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, 7), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_ID_MISMATCH);
    put32(d, 1);
    CHECK_EQ(ask(&r, CMD_ACK_RESULT, d, 4), CMD_ACK_RESULT | 0x80);
    CHECK_EQ(state_of(&r), STATE_IDLE);
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, 1), CMD_ACK_RESULT | 0x80); /* a lost answer repeated */
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, 2), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_NO_RESULT);
    CHECK_EQ(ask0(&r, CMD_GET_RESULT), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_NO_RESULT);

    /* the next fill has the id its START carried */
    CHECK_EQ(start_auto(&r, 5000), 2);
}

static void test_early_close(void)
{
    Rig r;
    Params p;

    memset(&r, 0, sizeof(r));
    r.fake.hook = 1;
    r.hal = hal_of(&r.fake);
    board_default_params(&p);
    p.early_close_pulses = 40;
    board_init(&r.board, &r.hal, 1, &p);
    configure(&r, MODE_AUTO, 8300, 100000);
    start_auto(&r, 15000);
    run(&r, 1, 18000);
    CHECK_EQ(r.fake.pump_on, 1);
    CHECK(board_live_grams(&r.board) < 15000);                       /* on the way: what the counts say */
    run(&r, 1, 32); /* 18032 = target - 40 */
    CHECK_EQ(r.fake.pump_on, 0);
    /* the pump stopped 40 counts (about 33 g) before the target: the display is the preset, not 14.97 kg */
    CHECK_EQ(board_live_grams(&r.board), 15000);
    CHECK_EQ(board_live_ml(&r.board), board_ml_of_grams(&r.board.p.config, 15000));
    /* 60 more counts come while the pump stops: they are counted but the result is still the preset */
    r.fake.pulses += 60;
    run(&r, 40, 0);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(r.board.p.result.dispensed_g, 15000);
    CHECK_EQ(r.board.p.result.reason, REASON_PRESET);
}

/* the tolerance of the board (0.03 kg): a fill that ends that close to the preset is the preset; further short it is what the counts say */
static void test_preset_tolerance(void)
{
    Rig r;
    int i;
    int seen_preset = 0;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);

    /* 21 g short (18048 counts of 18072): the gun is hung for good, the fill ends, the result is the preset */
    start_auto(&r, 15000);
    run(&r, 1, 18048);
    r.fake.hook = 0;
    for (i = 0; i < 330 && r.board.state == STATE_DISPENSING; ++i) {
        run(&r, 1, 0);
        if (r.board.state == STATE_DISPENSING && board_live_grams(&r.board) == 15000) {
            seen_preset = 1;                                            /* the display says the preset while the pump is stopping */
        }
    }
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(r.board.p.result.reason, REASON_PRESET);
    CHECK_EQ(r.board.p.result.dispensed_g, 15000);
    CHECK_EQ(r.board.p.result.dispensed_ml, board_ml_of_grams(&r.board.p.config, 15000));
    CHECK(seen_preset);
    ask_u32(&r, CMD_ACK_RESULT, r.session);
    CHECK_EQ(r.board.state, STATE_IDLE);

    /* 100 g short: no tolerance, the result is what the counts say, reason 1 */
    r.fake.hook = 1;
    r.fake.pulses = 0;
    start_auto(&r, 15000);
    run(&r, 1, 17951);
    r.fake.hook = 0;
    run(&r, 330, 0);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(r.board.p.result.reason, REASON_NO_FLOW);
    CHECK(r.board.p.result.dispensed_g >= 14890 && r.board.p.result.dispensed_g <= 14900);
    ask_u32(&r, CMD_ACK_RESULT, r.session);

    /* nothing came out at all of a tiny preset (20 g is inside the tolerance): still no fuel, still reason 1 and 0 g */
    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    start_auto(&r, 20);
    run(&r, 60100, 0);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(r.board.p.result.reason, REASON_NO_FLOW);
    CHECK_EQ(r.board.p.result.dispensed_g, 0);
}
static void test_no_flow(void)
{
    Rig r;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    start_auto(&r, 15000);

    /* the nozzle is lifted after a while: the wait for the first count is long, the no-flow time counts only from then on */
    run(&r, 2000, 0); /* 20 s without a count */
    CHECK_EQ(r.board.state, STATE_DISPENSING);
    run(&r, 100, 100); /* flow: 10000 counts */
    run(&r, 2900, 0);  /* 29 s of quiet */
    CHECK_EQ(r.board.state, STATE_DISPENSING);
    run(&r, 200, 0); /* the 30 s are over, then the settle time */
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(r.board.p.result.reason, REASON_NO_FLOW);
    CHECK_EQ(r.board.p.result.dispensed_g, 8300); /* 10 L */
    CHECK_EQ(r.board.p.result.dispensed_ml, 10000);
    CHECK_EQ(r.fake.pump_on, 0);

    /* the nozzle is never lifted: after the start wait the fill ends with nothing */
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, r.board.p.result.id), CMD_ACK_RESULT | 0x80);
    start_auto(&r, 15000);
    run(&r, 59990, 0);
    CHECK_EQ(r.board.state, STATE_DISPENSING);
    run(&r, 200, 0);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(r.board.p.result.dispensed_g, 0);
    CHECK_EQ(r.board.p.result.reason, REASON_NO_FLOW);
}

static void test_hook(void)
{
    Rig r;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);

    /* START with the nozzle on its hook: the pump does not run until it is lifted */
    r.fake.hook = 0;
    start_auto(&r, 15000);
    CHECK_EQ(r.board.state, STATE_DISPENSING);
    CHECK_EQ(r.fake.pump_on, 0);
    run(&r, 100, 0);
    CHECK_EQ(r.fake.pump_on, 0);
    r.fake.hook = 1;
    run(&r, 1, 0);
    CHECK_EQ(r.fake.pump_on, 1);

    /* flow; the nozzle is put back for a moment: the pump stops, lifted again it runs again, the fill goes on */
    run(&r, 50, 100);
    r.fake.hook = 0;
    run(&r, 10, 0);
    CHECK_EQ(r.fake.pump_on, 0);
    CHECK_EQ(r.board.state, STATE_DISPENSING);
    r.fake.hook = 1;
    run(&r, 1, 0);
    CHECK_EQ(r.fake.pump_on, 1);
    run(&r, 50, 100);
    CHECK_EQ(r.board.pulses_seen, 10000);

    /* put back for good after the flow began: the fill ends after the hook-down time with what was dispensed */
    r.fake.hook = 0;
    run(&r, 290, 0);
    CHECK_EQ(r.board.state, STATE_DISPENSING);
    run(&r, 20, 0);  /* 3 s */
    run(&r, 30, 0);  /* the settle time */
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(r.board.p.result.reason, REASON_NO_FLOW);
    CHECK_EQ(r.board.p.result.dispensed_g, 8300);
    CHECK_EQ(r.fake.pump_on, 0);

    /* a board without the hook sensor counts the nozzle as lifted */
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, r.board.p.result.id), CMD_ACK_RESULT | 0x80);
    r.hal.hook = 0;
    start_auto(&r, 5000);
    CHECK_EQ(r.fake.pump_on, 1);
}

static void test_backflow_and_wrap(void)
{
    Rig r;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);

    /* a backflow takes counts off again, and the fill never counts below 0 */
    start_auto(&r, 15000);
    r.fake.pulses += 1000;
    run(&r, 1, 0);
    CHECK_EQ(r.board.pulses_seen, 1000);
    r.fake.pulses -= 300;
    run(&r, 1, 0);
    CHECK_EQ(r.board.pulses_seen, 700);
    r.fake.pulses -= 5000;
    run(&r, 1, 0);
    CHECK_EQ(r.board.pulses_seen, 0);
    CHECK_EQ(r.board.state, STATE_DISPENSING);

    /* a jitter of one edge forward and back does not add up */
    r.fake.pulses += 5000;
    run(&r, 1, 0);
    {
        int i;
        const uint32_t before = r.board.pulses_seen;

        for (i = 0; i < 100; ++i) {
            r.fake.pulses += 1;
            run(&r, 1, 0);
            r.fake.pulses -= 1;
            run(&r, 1, 0);
        }
        CHECK_EQ(r.board.pulses_seen, before);
    }

    /* the counter wraps at 2^32 in the middle of a fill */
    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    r.fake.pulses = 0xFFFFFF00UL;
    start_auto(&r, 15000);
    run(&r, 1, 1000);
    CHECK_EQ(r.board.pulses_seen, 1000);
    run(&r, 40, 500);
    run(&r, 30, 0);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(r.board.p.result.reason, REASON_PRESET);
}

static void test_config(void)
{
    Rig r;
    uint8_t d[11];

    rig_new(&r, 1);

    /* a board never configured */
    CHECK_EQ(ask0(&r, CMD_GET_CONFIG), CMD_GET_CONFIG | 0x80);
    CHECK_EQ(u32at(DATA(&r) + 7), 0);
    CHECK_EQ(DATA(&r)[5] | (DATA(&r)[6] << 8), 0);

    memset(d, 0, sizeof(d));
    d[0] = 0;
    d[1] = 'D';
    d[5] = 8300 & 0xFF;
    d[6] = 8300 >> 8;
    put32(d + 7, 123450);
    CHECK_EQ(ask(&r, CMD_SET_CONFIG, d, 11), CMD_SET_CONFIG | 0x80);
    CHECK(memcmp(DATA(&r), d, 11) == 0);
    CHECK_EQ(ask0(&r, CMD_GET_CONFIG), CMD_GET_CONFIG | 0x80);
    CHECK(memcmp(DATA(&r), d, 11) == 0);
    CHECK_EQ(r.fake.stored.config.pulses_x100, 123450); /* in the memory */

    /* wrong length */
    CHECK_EQ(ask(&r, CMD_SET_CONFIG, d, 10), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_LENGTH);
    CHECK_EQ(ask(&r, CMD_GET_CONFIG, d, 1), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_LENGTH);

    /* out of range: nothing is stored */
    d[0] = 2;
    CHECK_EQ(ask(&r, CMD_SET_CONFIG, d, 11), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_VALUE);
    d[0] = 0;
    d[5] = 999 & 0xFF;
    d[6] = 999 >> 8;
    CHECK_EQ(ask(&r, CMD_SET_CONFIG, d, 11), CMD_NAK);
    d[5] = 20001 & 0xFF;
    d[6] = 20001 >> 8;
    CHECK_EQ(ask(&r, CMD_SET_CONFIG, d, 11), CMD_NAK);
    d[5] = 8300 & 0xFF;
    d[6] = 8300 >> 8;
    put32(d + 7, 0);
    CHECK_EQ(ask(&r, CMD_SET_CONFIG, d, 11), CMD_NAK);
    put32(d + 7, 100000001UL);
    CHECK_EQ(ask(&r, CMD_SET_CONFIG, d, 11), CMD_NAK);
    put32(d + 7, 100000000UL);
    CHECK_EQ(ask(&r, CMD_SET_CONFIG, d, 11), CMD_SET_CONFIG | 0x80);
    put32(d + 7, 123450);
    d[1] = 0; /* an empty fuel type */
    CHECK_EQ(ask(&r, CMD_SET_CONFIG, d, 11), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_VALUE);
    CHECK_EQ(r.board.p.config.pulses_x100, 100000000UL);

    /* only in IDLE */
    d[1] = 'D';
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 5000), CMD_SET_PRESET | 0x80);
    CHECK_EQ(ask(&r, CMD_SET_CONFIG, d, 11), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
}

static void test_time(void)
{
    Rig r;
    uint8_t d[7];

    rig_new(&r, 1);
    d[0] = 2028 & 0xFF; d[1] = 2028 >> 8; d[2] = 2; d[3] = 29; d[4] = 23; d[5] = 59; d[6] = 59;
    CHECK_EQ(ask(&r, CMD_SET_TIME, d, 7), CMD_SET_TIME | 0x80); /* a leap day */
    CHECK_EQ(ask0(&r, CMD_GET_TIME), CMD_GET_TIME | 0x80);
    CHECK(memcmp(DATA(&r), d, 7) == 0);

    d[0] = 2027 & 0xFF; d[1] = 2027 >> 8;
    CHECK_EQ(ask(&r, CMD_SET_TIME, d, 7), CMD_NAK); /* no 29 February in 2027 */
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_VALUE);
    d[0] = 2100 & 0xFF; d[1] = 2100 >> 8; d[2] = 1; d[3] = 1;
    CHECK_EQ(ask(&r, CMD_SET_TIME, d, 7), CMD_NAK);
    d[0] = 2026 & 0xFF; d[1] = 2026 >> 8; d[2] = 13;
    CHECK_EQ(ask(&r, CMD_SET_TIME, d, 7), CMD_NAK);
    d[2] = 4; d[3] = 31;
    CHECK_EQ(ask(&r, CMD_SET_TIME, d, 7), CMD_NAK); /* 31 April */
    d[3] = 30; d[4] = 24;
    CHECK_EQ(ask(&r, CMD_SET_TIME, d, 7), CMD_NAK);
    d[4] = 23; d[5] = 60;
    CHECK_EQ(ask(&r, CMD_SET_TIME, d, 7), CMD_NAK);
    d[5] = 0; d[6] = 60;
    CHECK_EQ(ask(&r, CMD_SET_TIME, d, 7), CMD_NAK);
    CHECK_EQ(ask(&r, CMD_SET_TIME, d, 6), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_LENGTH);
    CHECK_EQ(r.fake.rtc_sets, 1); /* the refused ones did not touch the clock */

    /* accepted while a fill runs, and the fill goes on */
    configure(&r, MODE_AUTO, 8300, 100000);
    start_auto(&r, 15000);
    d[6] = 5;
    CHECK_EQ(ask(&r, CMD_SET_TIME, d, 7), CMD_SET_TIME | 0x80);
    run(&r, 10, 100);
    CHECK_EQ(r.board.state, STATE_DISPENSING);
    CHECK_EQ(r.board.pulses_seen, 1000);
}

static void test_keys(void)
{
    Rig r;
    uint8_t req[FRAME_MAX];
    size_t n;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);

    /* the select key only reports itself: the state does not change, the event comes once in the next POLL */
    board_key_select(&r.board);
    CHECK_EQ(r.board.state, STATE_IDLE);
    n = frame(req, 1, 50, CMD_POLL, 0, 0);
    CHECK(feed_all(&r, req, n));
    CHECK_EQ(DATA(&r)[0], STATE_IDLE);
    CHECK(DATA(&r)[1] & FLAG_SELECT_KEY);
    CHECK(feed_all(&r, req, n)); /* a repeated POLL gets the same flags */
    CHECK(DATA(&r)[1] & FLAG_SELECT_KEY);
    poll(&r);                    /* a new one does not */
    CHECK(!(DATA(&r)[1] & FLAG_SELECT_KEY));

    /* the clear key too (bit 4); both together */
    board_key_clear(&r.board);
    board_key_select(&r.board);
    CHECK_EQ(r.board.state, STATE_IDLE);
    poll(&r);
    CHECK(DATA(&r)[1] & FLAG_CLEAR_KEY);
    CHECK(DATA(&r)[1] & FLAG_SELECT_KEY);
    poll(&r);
    CHECK_EQ(DATA(&r)[1] & (FLAG_CLEAR_KEY | FLAG_SELECT_KEY), 0);

    /* with a preset on the board (SET) and during a fill the keys change nothing either */
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 5000), CMD_SET_PRESET | 0x80);
    board_key_select(&r.board);
    board_key_clear(&r.board);
    CHECK_EQ(r.board.state, STATE_SET);
    CHECK_EQ(r.board.preset_g, 5000);
    start_auto(&r, 5000);
    board_key_select(&r.board);
    board_key_clear(&r.board);
    CHECK_EQ(r.board.state, STATE_DISPENSING);
}

/* Manual mode: the supplier types the amount, takes the gun up and the board runs by itself; nothing is held, nothing is reported. */
static void test_manual_mode(void)
{
    Rig r;
    uint8_t d[11];

    rig_new(&r, 1);
    configure(&r, MODE_MANUAL, 8300, 100000);

    /* the Smart210 only watches: these are refused */
    CHECK_EQ(ask0(&r, CMD_SELECT), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 5000), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
    CHECK_EQ(ask_u32(&r, CMD_START, 1), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
    CHECK_EQ(ask_u32(&r, CMD_SHOW_BALANCE, 1000), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
    /* and these are answered */
    CHECK_EQ(ask0(&r, CMD_INFO), CMD_INFO | 0x80);
    CHECK_EQ(ask0(&r, CMD_GET_CONFIG), CMD_GET_CONFIG | 0x80);
    CHECK_EQ(ask0(&r, CMD_GET_TIME), CMD_GET_TIME | 0x80);
    CHECK_EQ(ask0(&r, CMD_GET_CONFIG), CMD_GET_CONFIG | 0x80);
    memcpy(d, DATA(&r), 11);
    CHECK_EQ(ask(&r, CMD_SET_CONFIG, d, 11), CMD_SET_CONFIG | 0x80);
    CHECK_EQ(ask0(&r, CMD_GET_RESULT), CMD_NAK);        /* it holds no result */
    CHECK_EQ(NAK_ERROR(&r), ERR_NO_RESULT);

    /* the keys report nothing */
    board_key_select(&r.board);
    board_key_clear(&r.board);
    poll(&r);
    CHECK_EQ(DATA(&r)[1] & (FLAG_SELECT_KEY | FLAG_CLEAR_KEY), 0);

    CHECK(!board_manual_amount(&r.board, 0));
    r.fake.hook = 0;                                     /* the gun is down */
    CHECK(board_manual_amount(&r.board, 8300));          /* 8.3 kg = 10 L = 10000 counts: the preset only, the board is SET */
    CHECK_EQ(r.board.state, STATE_SET);
    run(&r, 20, 0);
    CHECK_EQ(r.board.state, STATE_SET);                  /* it waits for the gun */
    CHECK_EQ(r.fake.pump_on, 0);
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_SET);
    CHECK_EQ(u32at(DATA(&r) + 15), 8300);                /* the preset typed at the dispenser is in POLL */
    CHECK(!(DATA(&r)[1] & FLAG_GUN_UP));
    CHECK(!board_manual_amount(&r.board, 100));          /* not while it waits */

    r.fake.hook = 1;                                     /* the gun is taken up: the fill starts by itself */
    run(&r, 1, 0);
    CHECK_EQ(r.board.state, STATE_DISPENSING);
    CHECK_EQ(r.board.p.inflight.id, 0);                  /* a manual fill has no session */
    CHECK_EQ(r.fake.pump_on, 1);
    poll(&r);
    CHECK(DATA(&r)[1] & FLAG_GUN_UP);
    CHECK_EQ(u32at(DATA(&r) + 3), 0);
    CHECK(!board_manual_amount(&r.board, 100));
    run(&r, 100, 100);
    run(&r, 30, 0);

    /* the end of the fill is not reported: IDLE, no result, no flag, no preset */
    CHECK_EQ(r.board.state, STATE_IDLE);
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_IDLE);
    CHECK_EQ(DATA(&r)[1] & (FLAG_RESULT | FLAG_SELECT_KEY | FLAG_CLEAR_KEY), 0);
    CHECK_EQ(u32at(DATA(&r) + 7), 0);
    CHECK_EQ(u32at(DATA(&r) + 15), 0);
    CHECK_EQ(ask0(&r, CMD_GET_RESULT), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_NO_RESULT);
    CHECK_EQ(r.board.p.result.held, 0);
    CHECK_EQ(r.fake.pump_on, 0);

    /* the clear key at the dispenser drops an amount that waits for the gun */
    r.fake.hook = 0;
    CHECK(board_manual_amount(&r.board, 5000));
    board_key_clear(&r.board);
    CHECK_EQ(r.board.state, STATE_IDLE);
    CHECK_EQ(r.board.preset_g, 0);

    /* with the gun already up the fill starts as soon as the amount is confirmed */
    r.fake.hook = 1;
    CHECK(board_manual_amount(&r.board, 8300));
    run(&r, 1, 0);
    CHECK_EQ(r.board.state, STATE_DISPENSING);

    /* a manual board that is not configured does not take an amount */
    rig_new(&r, 1);
    memset(&r.board.p.config, 0, sizeof(r.board.p.config));
    r.board.p.config.mode = MODE_MANUAL;
    CHECK(!board_manual_amount(&r.board, 1000));
}

/* manual mode: the last fill is kept for the displays until the next preset is set */
static void test_manual_hold(void)
{
    Rig r;

    rig_new(&r, 1);
    configure(&r, MODE_MANUAL, 8300, 100000);
    CHECK_EQ(board_held_grams(&r.board), 0);
    CHECK(board_manual_amount(&r.board, 8300));
    run(&r, 1200, 10);
    CHECK_EQ(r.board.state, STATE_IDLE);
    CHECK_EQ(board_held_grams(&r.board), 8300);                      /* the preset was reached: the preset */
    CHECK_EQ(board_held_ml(&r.board), 10000);
    run(&r, 500, 0);
    CHECK_EQ(board_held_grams(&r.board), 8300);                      /* it stays, whatever the time */
    CHECK(board_manual_amount(&r.board, 5000));                      /* the next preset: gone */
    CHECK_EQ(board_held_grams(&r.board), 0);
    CHECK_EQ(board_held_ml(&r.board), 0);
}
/* auto mode: the amount of a fill is kept for the displays when its result is acknowledged, until the next preset is set */
static void test_auto_hold(void)
{
    Rig r;
    uint32_t id;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    id = start_auto(&r, 5000);
    run(&r, 800, 10);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(board_held_grams(&r.board), 0);                          /* the result itself is shown while it waits */
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, id), CMD_ACK_RESULT | 0x80);
    CHECK_EQ(r.board.state, STATE_IDLE);
    CHECK_EQ(board_held_grams(&r.board), 5000);
    CHECK_EQ(board_held_ml(&r.board), board_ml_of_grams(&r.board.p.config, 5000));
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 0), CMD_SET_PRESET | 0x80);  /* a cancel is not a preset: it stays */
    CHECK_EQ(board_held_grams(&r.board), 5000);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 3000), CMD_SET_PRESET | 0x80);
    CHECK_EQ(board_held_grams(&r.board), 0);                          /* the next preset: gone */
    CHECK_EQ(r.board.state, STATE_SET);
}
static void test_power_cut(void)
{
    Rig r;
    uint32_t id;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    id = start_auto(&r, 15000);
    run(&r, 100, 100); /* 1000 ms: 10000 counts, the memory is written every 2 s: not yet */
    run(&r, 250, 20);  /* 3500 ms in all */
    {
        const uint32_t stored = r.fake.stored.inflight.pulses;

        CHECK(r.fake.stored.inflight.active);
        CHECK(stored > 0 && stored <= r.board.pulses_seen);

        rig_power_cycle(&r);
        CHECK_EQ(r.board.state, STATE_DONE);
        CHECK_EQ(r.fake.pump_on, 0);
        CHECK_EQ(ask0(&r, CMD_GET_RESULT), CMD_GET_RESULT | 0x80);
        CHECK_EQ(u32at(DATA(&r)), id);
        CHECK_EQ(DATA(&r)[16], REASON_INTERRUPTED);
        CHECK_EQ(u32at(DATA(&r) + 8), board_grams_of(&r.board.p.config, stored));
        CHECK_EQ(u32at(DATA(&r) + 4), 15000);
    }

    /* a power cut while the result waits: it is still there */
    rig_power_cycle(&r);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, id), CMD_ACK_RESULT | 0x80);
    CHECK_EQ(r.board.state, STATE_IDLE);

    /* after the acknowledgement a restart leaves the board IDLE (the acknowledged id is remembered) */
    rig_power_cycle(&r);
    CHECK_EQ(r.board.state, STATE_IDLE);
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, id), CMD_ACK_RESULT | 0x80);
    CHECK_EQ(start_auto(&r, 5000), id + 1);

    /* a power cut right after START, before any count */
    rig_power_cycle(&r);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(ask0(&r, CMD_GET_RESULT), CMD_GET_RESULT | 0x80);
    CHECK_EQ(u32at(DATA(&r) + 8), 0);
    CHECK_EQ(DATA(&r)[16], REASON_INTERRUPTED);

    /* a manual fill holds no result: after the power cut the board is IDLE and nothing waits */
    ask_u32(&r, CMD_ACK_RESULT, id + 1);
    configure(&r, MODE_MANUAL, 8300, 100000);
    CHECK(board_manual_amount(&r.board, 8300));
    run(&r, 300, 10);
    CHECK_EQ(r.board.state, STATE_DISPENSING);
    rig_power_cycle(&r);
    CHECK_EQ(r.board.state, STATE_IDLE);
    CHECK_EQ(r.board.p.result.held, 0);
    CHECK_EQ(r.board.p.inflight.active, 0);
    CHECK_EQ(r.fake.pump_on, 0);
}

/* the total of the board: every gram of every fill, both modes, a fill cut by the power included; kept over a power cut */
static void test_total(void)
{
    Rig r;
    uint64_t before;
    uint32_t id;

    rig_new(&r, 1);
    configure(&r, MODE_MANUAL, 8300, 100000);
    CHECK_EQ((long long)r.board.p.total_g, 0);
    CHECK_EQ((long long)board_total_kg(&r.board), 0);

    /* a manual fill that reaches the preset */
    CHECK(board_manual_amount(&r.board, 8300));
    run(&r, 1200, 10);
    CHECK_EQ(r.board.state, STATE_IDLE);
    CHECK_EQ((long long)r.board.p.total_g, 8300);
    CHECK_EQ((long long)board_total_kg(&r.board), 8);

    /* a manual fill cut by the power: what it dispensed is in the total (what the last save of the count had, about 1.65 kg), and the total survives the restart */
    before = r.board.p.total_g;
    CHECK(board_manual_amount(&r.board, 8300));
    run(&r, 300, 10);
    rig_power_cycle(&r);
    CHECK_EQ(r.board.state, STATE_IDLE);
    CHECK(r.board.p.total_g > before + 1000 && r.board.p.total_g < before + 2500);   /* the count of the last save (every 2 s) */

    /* an auto fill counts when its result is held, once (the acknowledgement adds nothing) */
    configure(&r, MODE_AUTO, 8300, 100000);
    before = r.board.p.total_g;
    id = start_auto(&r, 5000);
    run(&r, 800, 10);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ((long long)r.board.p.total_g, (long long)(before + 5000));
    ask_u32(&r, CMD_ACK_RESULT, id);
    CHECK_EQ((long long)r.board.p.total_g, (long long)(before + 5000));

    /* GET_TOTAL: 8 bytes, the grams, little endian; a wrong length is refused */
    r.board.p.total_g = 0x0000012345678901ULL;
    CHECK_EQ(ask0(&r, CMD_GET_TOTAL), CMD_GET_TOTAL | 0x80);
    CHECK_EQ(u32at(DATA(&r)), 0x45678901u);
    CHECK_EQ(u32at(DATA(&r) + 4), 0x0123u);
    CHECK_EQ(ask_u32(&r, CMD_GET_TOTAL, 1), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_LENGTH);
    CHECK_EQ(ask_u32(&r, CMD_RESET_TOTAL, 1), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_LENGTH);

    /* RESET_TOTAL: the answer is what it was, the total is 0 and stays 0 over a power cut */
    CHECK_EQ(ask0(&r, CMD_RESET_TOTAL), CMD_RESET_TOTAL | 0x80);
    CHECK_EQ(u32at(DATA(&r)), 0x45678901u);
    CHECK_EQ(u32at(DATA(&r) + 4), 0x0123u);
    CHECK_EQ((long long)r.board.p.total_g, 0);
    rig_power_cycle(&r);
    CHECK_EQ((long long)r.board.p.total_g, 0);
    CHECK_EQ(ask0(&r, CMD_GET_TOTAL), CMD_GET_TOTAL | 0x80);
    CHECK_EQ(u32at(DATA(&r)), 0);

    /* not while a fill runs; a manual board answers both (a service command, like the config) */
    configure(&r, MODE_AUTO, 8300, 100000);
    id = start_auto(&r, 5000);
    run(&r, 100, 10);
    CHECK_EQ(r.board.state, STATE_DISPENSING);
    CHECK_EQ(ask0(&r, CMD_RESET_TOTAL), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
    CHECK_EQ(ask0(&r, CMD_GET_TOTAL), CMD_GET_TOTAL | 0x80);      /* reading is always fine */
    run(&r, 800, 10);
    ask_u32(&r, CMD_ACK_RESULT, id);
    configure(&r, MODE_MANUAL, 8300, 100000);
    r.board.p.total_g = 1234567;
    CHECK_EQ(ask0(&r, CMD_GET_TOTAL), CMD_GET_TOTAL | 0x80);
    CHECK_EQ(u32at(DATA(&r)), 1234567);
    CHECK_EQ(ask0(&r, CMD_RESET_TOTAL), CMD_RESET_TOTAL | 0x80);
    CHECK_EQ(u32at(DATA(&r)), 1234567);
    CHECK_EQ((long long)r.board.p.total_g, 0);
}
static void test_power_fail_signal(void)
{
    Rig r;
    int saves;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    start_auto(&r, 15000);
    run(&r, 150, 100); /* 1.5 s: 15000 counts; the periodic write (every 2 s) has not come yet */
    CHECK_EQ(r.fake.stored.inflight.pulses, 0);

    /* the POWER_OFF input goes high: the pump stops at once and the counts so far are written */
    board_power_fail(&r.board);
    CHECK_EQ(r.fake.pump_on, 0);
    CHECK_EQ(r.fake.stored.inflight.pulses, 15000);
    CHECK(r.fake.stored.inflight.active);
    saves = r.fake.saves;
    board_power_fail(&r.board);  /* a second signal writes nothing */
    CHECK_EQ(r.fake.saves, saves);
    run(&r, 10, 0);
    CHECK_EQ(r.fake.pump_on, 0); /* and the pump does not start again while the supply is going */

    rig_power_cycle(&r);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(r.board.p.result.reason, REASON_INTERRUPTED);
    CHECK_EQ(r.board.p.result.dispensed_g, board_grams_of(&r.board.p.config, 15000));

    /* a power fail with nothing running writes nothing; the signal can come back to low */
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, r.board.p.result.id), CMD_ACK_RESULT | 0x80);
    saves = r.fake.saves;
    board_power_fail(&r.board);
    CHECK_EQ(r.fake.saves, saves);
    board_power_restored(&r.board);
    start_auto(&r, 5000);
    CHECK_EQ(r.fake.pump_on, 1);
}

static void test_faults(void)
{
    Rig r;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);

    /* a fault while a fill runs: the result is held with reason 3, after the acknowledgement the board is in FAULT */
    start_auto(&r, 15000);
    run(&r, 10, 100);
    board_set_fault(&r.board, FAULT_FLOW_SENSOR);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(r.fake.pump_on, 0);
    poll(&r);
    CHECK_EQ(DATA(&r)[2], FAULT_FLOW_SENSOR);
    CHECK(DATA(&r)[1] & FLAG_FAULT);
    CHECK(DATA(&r)[1] & FLAG_RESULT);
    CHECK_EQ(ask0(&r, CMD_GET_RESULT), CMD_GET_RESULT | 0x80);
    CHECK_EQ(DATA(&r)[16], REASON_FAULT);
    CHECK_EQ(u32at(DATA(&r) + 8), 830); /* 1000 counts = 1 L */
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, 1), CMD_ACK_RESULT | 0x80);
    CHECK_EQ(r.board.state, STATE_FAULT);

    CHECK_EQ(ask0(&r, CMD_SELECT), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_FAULT);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 5000), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_FAULT);
    CHECK_EQ(ask_u32(&r, CMD_START, 9), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_FAULT);

    board_set_fault(&r.board, FAULT_NONE);
    CHECK_EQ(r.board.state, STATE_IDLE);

    /* a fault in IDLE */
    board_set_fault(&r.board, FAULT_VALVE);
    CHECK_EQ(r.board.state, STATE_FAULT);
    board_set_fault(&r.board, FAULT_NONE);
    CHECK_EQ(r.board.state, STATE_IDLE);
}

static void test_memory(void)
{
    Rig r;

    /* the memory refuses at START: the id is not promised, the board shows fault 3 */
    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    CHECK_EQ(ask0(&r, CMD_SELECT), CMD_SELECT | 0x80);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 15000), CMD_SET_PRESET | 0x80);
    r.fake.fail_save = 1;
    CHECK_EQ(ask_u32(&r, CMD_START, 1), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_FAULT);
    CHECK_EQ(r.fake.pump_on, 0);
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_FAULT);
    CHECK_EQ(DATA(&r)[2], FAULT_MEMORY);

    /* the memory refuses at SET_CONFIG: the old config stays */
    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    r.fake.fail_save = 1;
    {
        uint8_t d[11] = {0, 'A', 0, 0, 0, 0, 0, 0, 0, 0, 0};

        d[5] = 7400 & 0xFF;
        d[6] = 7400 >> 8;
        put32(d + 7, 5000);
        CHECK_EQ(ask(&r, CMD_SET_CONFIG, d, 11), CMD_NAK);
        CHECK_EQ(r.board.p.config.density, 8300);
    }

    /* a damaged record: the board does not work */
    memset(&r, 0, sizeof(r));
    r.fake.has_record = -1;
    rig_boot(&r, 1);
    CHECK_EQ(r.board.state, STATE_FAULT);
    CHECK_EQ(r.board.fault, FAULT_MEMORY);
    CHECK_EQ(r.fake.pump_on, 0);

}

/* The memory: a save that erases takes 45 to 400 ms, so the fill in progress and the power cut are written without an erase, the long work waits for an idle moment, and
   a refused save without an erase is not a fault. */
static void test_memory_timing(void)
{
    Rig r;
    int saves, now;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);

    /* housekeeping only in IDLE and DONE, and never while the supply is going */
    board_idle_work(&r.board);
    CHECK_EQ(r.fake.idle_work, 1);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 15000), CMD_SET_PRESET | 0x80);
    board_idle_work(&r.board);
    CHECK_EQ(r.fake.idle_work, 1);                    /* SET: a fill may start any moment */
    CHECK_EQ(ask_u32(&r, CMD_START, 1), CMD_START | 0x80);
    board_idle_work(&r.board);
    CHECK_EQ(r.fake.idle_work, 1);                    /* DISPENSING */

    /* the periodic save of the fill (every 2 s) is a save without an erase */
    saves = r.fake.saves;
    now = r.fake.saves_now;
    run(&r, 250, 20);                                 /* 2.5 s of flow */
    CHECK(r.fake.saves_now > now);
    CHECK_EQ(r.fake.saves, saves);                    /* the one that may erase was not used */

    /* a refused save without an erase is not a fault: the fill goes on, the last record stays */
    r.fake.fail_save_now = 1;
    run(&r, 250, 20);
    CHECK_EQ(r.board.state, STATE_DISPENSING);
    CHECK_EQ(r.board.fault, FAULT_NONE);

    /* the power cut: one save without an erase, at once */
    r.fake.fail_save_now = 0;
    saves = r.fake.saves;
    now = r.fake.saves_now;
    board_power_fail(&r.board);
    CHECK_EQ(r.fake.saves_now, now + 1);
    CHECK_EQ(r.fake.saves, saves);
    board_idle_work(&r.board);
    CHECK_EQ(r.fake.idle_work, 1);                    /* and no long work while the supply is going */

    /* the power cut when the memory wants an erase first (a long fill has used the blank sectors up): the save without an erase is refused, so the save that may erase is used
       (the backup battery lasts 1 to 2 minutes): the record is the last one of the fill, and the board is not in fault */
    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    start_auto(&r, 15000);
    run(&r, 250, 20);
    r.fake.fail_save_now = 1;
    run(&r, 250, 20);                                 /* the progress saves are refused from here on */
    {
        const uint32_t pulses_at_cut = r.fake.pulses - r.board.pulse_base;
        const uint32_t stored_before = r.fake.stored.inflight.pulses;

        saves = r.fake.saves;
        now = r.fake.saves_now;
        board_power_fail(&r.board);
        CHECK_EQ(r.fake.saves_now, now);              /* the refused one wrote nothing */
        CHECK_EQ(r.fake.saves, saves + 1);            /* the one that may erase did */
        CHECK_EQ(r.fake.stored.inflight.pulses, pulses_at_cut);
        CHECK(stored_before < pulses_at_cut);         /* more than the last progress record had */
        CHECK_EQ(r.board.fault, FAULT_NONE);
        CHECK_EQ(r.board.state, STATE_DISPENSING);
    }
    r.fake.fail_save = 1;                             /* even a memory that refuses both is no fault at a power cut */
    r.board.powering_down = 0;
    board_power_fail(&r.board);
    CHECK_EQ(r.board.fault, FAULT_NONE);

    /* the end of the fill, an acknowledgement and a config are saves that may take long: they use the other one (and a refusal is a fault) */
    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    start_auto(&r, 5000);
    run(&r, 100, 100);
    run(&r, 30, 0);
    CHECK_EQ(r.board.state, STATE_DONE);
    board_idle_work(&r.board);
    CHECK_EQ(r.fake.idle_work, 1);                    /* DONE: nothing runs */
    saves = r.fake.saves;
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, r.session), CMD_ACK_RESULT | 0x80);
    CHECK_EQ(r.fake.saves, saves + 1);
    r.fake.fail_save = 1;
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 3000), CMD_SET_PRESET | 0x80);
    CHECK_EQ(ask_u32(&r, CMD_START, 9), CMD_NAK);     /* START has to be saved: the memory refuses it */
    CHECK_EQ(NAK_ERROR(&r), ERR_FAULT);
}
/* The pump is stopped by the hardware too (a compare interrupt of the flow meter timer, Hal.arm_stop): the core arms it at START with the count where the fill must stop
   and again at every tick, disarms it when the pump is stopped, and does not switch the pump on again after the hardware stopped it. */
static void test_hardware_stop(void)
{
    Rig r;
    Params p;
    int arms;
    int changes;

    /* armed at START: the count of START plus the target (18072 counts for 15 kg), the counter wraps */
    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    r.fake.pulses = 1000;
    start_auto(&r, 15000);
    CHECK_EQ(r.fake.stop_armed, 1);
    CHECK_EQ(r.fake.stop_at, 1000 + 18072);
    arms = r.fake.arms;
    run(&r, 10, 100);
    CHECK(r.fake.arms > arms);                           /* again at every tick */
    CHECK_EQ(r.fake.stop_at, 1000 + 18072);

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    r.fake.pulses = 0xFFFFFF00UL;
    start_auto(&r, 15000);
    CHECK_EQ(r.fake.stop_at, (uint32_t)(0xFFFFFF00UL + 18072UL));      /* modulo 2^32 */

    /* with an early close the stop is that many counts before the target */
    memset(&r, 0, sizeof(r));
    r.fake.hook = 1;
    r.hal = hal_of(&r.fake);
    board_default_params(&p);
    p.early_close_pulses = 40;
    board_init(&r.board, &r.hal, 1, &p);
    configure(&r, MODE_AUTO, 8300, 100000);
    start_auto(&r, 15000);
    CHECK_EQ(r.fake.stop_at, 18072 - 40);

    /* it is disarmed when the pump is stopped by the core: the end of the fill */
    run(&r, 200, 200);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(r.fake.stop_armed, 0);

    /* the hardware stops the pump between two ticks (what the interrupt does): the core then closes the fill and does not switch the pump on again, also when the nozzle was
       put back and lifted again at that moment */
    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    start_auto(&r, 15000);
    run(&r, 170, 100);                                   /* 17000 counts */
    r.fake.hook = 0;
    run(&r, 1, 0);
    CHECK_EQ(r.fake.pump_on, 0);                         /* the nozzle is down: no pump */
    r.fake.hook = 1;                                     /* lifted again ... */
    r.fake.pulses += 1100;                               /* ... and the target is passed before the next tick (18100) */
    r.fake.pump_on = 0;                                  /* the interrupt stopped the pump at 18072 */
    changes = r.fake.pump_changes;
    board_tick(&r.board, 30);
    CHECK_EQ(r.fake.pump_on, 0);
    CHECK_EQ(r.fake.pump_changes, changes);              /* the pump was not switched on for a moment */
    run(&r, 30, 0);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(r.board.p.result.reason, REASON_PRESET);
    CHECK_EQ(r.board.p.result.dispensed_g, 15000);

    /* the power cut and a fault disarm it too */
    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    start_auto(&r, 15000);
    CHECK_EQ(r.fake.stop_armed, 1);
    board_power_fail(&r.board);
    CHECK_EQ(r.fake.stop_armed, 0);
    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    start_auto(&r, 15000);
    board_set_fault(&r.board, FAULT_FLOW_SENSOR);
    CHECK_EQ(r.fake.stop_armed, 0);

    /* a manual fill is armed the same way */
    rig_new(&r, 1);
    configure(&r, MODE_MANUAL, 8300, 100000);
    r.fake.pulses = 500;
    CHECK(board_manual_amount(&r.board, 8300));
    run(&r, 1, 0);
    CHECK_EQ(r.board.state, STATE_DISPENSING);
    CHECK_EQ(r.fake.stop_armed, 1);
    CHECK_EQ(r.fake.stop_at, 500 + 10000);
}
static void test_unknown_and_lengths(void)
{
    Rig r;

    rig_new(&r, 1);
    CHECK_EQ(ask0(&r, 0x7E), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_COMMAND);
    CHECK_EQ(r.reply[6], 0x7E);
    CHECK_EQ(ask(&r, CMD_SELECT, (const uint8_t *)"x", 1), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_LENGTH);
    CHECK_EQ(ask(&r, CMD_SET_PRESET, (const uint8_t *)"abc", 3), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_LENGTH);
    CHECK_EQ(ask(&r, CMD_ACK_RESULT, 0, 0), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_LENGTH);
    CHECK_EQ(ask(&r, CMD_START, 0, 0), CMD_NAK);                 /* START carries the session id */
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_LENGTH);
    CHECK_EQ(ask(&r, CMD_SHOW_BALANCE, 0, 0), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_LENGTH);

    /* INFO: protocol 4 */
    CHECK_EQ(ask0(&r, CMD_INFO), CMD_INFO | 0x80);
    CHECK_EQ(DATA(&r)[0], 4);
    CHECK_EQ(u32at(DATA(&r) + 3), 0xCAFE0001UL);

}

/* The session id: START carries it (4 bytes, never 0), the board keeps it with the fill and the result and sends it back; it has no counter of its own. */
static void test_session_ids(void)
{
    Rig r;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 15000), CMD_SET_PRESET | 0x80);

    CHECK_EQ(ask_u32(&r, CMD_START, 0), CMD_NAK);                 /* 0 is no session */
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_VALUE);
    CHECK_EQ(r.board.state, STATE_SET);
    CHECK_EQ(ask(&r, CMD_START, (const uint8_t *)"abc", 3), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_LENGTH);

    /* any number is taken: the board does not count */
    CHECK_EQ(ask_u32(&r, CMD_START, 987654321UL), CMD_START | 0x80);
    CHECK_EQ(u32at(DATA(&r)), 987654321UL);
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_DISPENSING);
    CHECK_EQ(u32at(DATA(&r) + 3), 987654321UL);                    /* in POLL while the fill runs */
    CHECK_EQ(r.fake.stored.inflight.id, 987654321UL);             /* kept in the memory with the fill */

    run(&r, 200, 200);
    run(&r, 30, 0);
    CHECK_EQ(r.board.state, STATE_DONE);
    poll(&r);
    CHECK_EQ(u32at(DATA(&r) + 3), 987654321UL);                    /* and while the result waits */
    CHECK_EQ(ask0(&r, CMD_GET_RESULT), CMD_GET_RESULT | 0x80);
    CHECK_EQ(u32at(DATA(&r)), 987654321UL);
    CHECK_EQ(r.fake.stored.result.id, 987654321UL);

    /* the result comes back with its id after a restart; ACK_RESULT takes that id and no other */
    rig_power_cycle(&r);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(ask0(&r, CMD_GET_RESULT), CMD_GET_RESULT | 0x80);
    CHECK_EQ(u32at(DATA(&r)), 987654321UL);
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, 1), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_ID_MISMATCH);
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, 987654321UL), CMD_ACK_RESULT | 0x80);
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_IDLE);
    CHECK_EQ(u32at(DATA(&r) + 3), 0);                              /* no fill, no result: 0 */

    /* the same id again later (a new board, a reset counter of the Smart210) is just a new fill: nothing on the board stops it */
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 5000), CMD_SET_PRESET | 0x80);
    CHECK_EQ(ask_u32(&r, CMD_START, 987654321UL), CMD_START | 0x80);
    CHECK_EQ(r.board.state, STATE_DISPENSING);
}

/* SHOW_BALANCE: a number on the display, in IDLE only; it is not a preset and arms nothing. */
static void test_show_balance(void)
{
    Rig r;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);
    CHECK_EQ(board_shown_grams(&r.board), 0);

    CHECK_EQ(ask_u32(&r, CMD_SHOW_BALANCE, 40000), CMD_SHOW_BALANCE | 0x80);
    CHECK_EQ(u32at(DATA(&r)), 40000);
    CHECK_EQ(board_shown_grams(&r.board), 40000);
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_IDLE);                          /* the state stays IDLE */
    CHECK_EQ(u32at(DATA(&r) + 15), 0);                          /* and there is no preset */
    CHECK_EQ(u32at(DATA(&r) + 3), 0);

    /* a gun taken up changes nothing: the board starts nothing by itself */
    r.fake.hook = 1;
    run(&r, 50, 0);
    CHECK_EQ(r.board.state, STATE_IDLE);
    CHECK_EQ(r.fake.pump_on, 0);
    CHECK_EQ(board_shown_grams(&r.board), 40000);

    /* the digits of the display: 9999.99 kg is the most */
    CHECK_EQ(ask_u32(&r, CMD_SHOW_BALANCE, MAX_SHOWN_G), CMD_SHOW_BALANCE | 0x80);
    CHECK_EQ(ask_u32(&r, CMD_SHOW_BALANCE, MAX_SHOWN_G + 1), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_VALUE);
    CHECK_EQ(board_shown_grams(&r.board), MAX_SHOWN_G);          /* the refused number changed nothing */
    CHECK_EQ(ask(&r, CMD_SHOW_BALANCE, (const uint8_t *)"abc", 3), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_LENGTH);

    /* zero clears it */
    CHECK_EQ(ask_u32(&r, CMD_SHOW_BALANCE, 0), CMD_SHOW_BALANCE | 0x80);
    CHECK_EQ(board_shown_grams(&r.board), 0);

    /* it goes by itself after 60 s */
    CHECK_EQ(ask_u32(&r, CMD_SHOW_BALANCE, 12345), CMD_SHOW_BALANCE | 0x80);
    run(&r, 5990, 0);
    CHECK_EQ(board_shown_grams(&r.board), 12345);
    run(&r, 20, 0);
    CHECK_EQ(board_shown_grams(&r.board), 0);

    /* a preset (and the cancel, a preset of zero) takes it off the display */
    CHECK_EQ(ask_u32(&r, CMD_SHOW_BALANCE, 500), CMD_SHOW_BALANCE | 0x80);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 3000), CMD_SET_PRESET | 0x80);
    CHECK_EQ(board_shown_grams(&r.board), 0);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 0), CMD_SET_PRESET | 0x80);
    CHECK_EQ(ask_u32(&r, CMD_SHOW_BALANCE, 500), CMD_SHOW_BALANCE | 0x80);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 0), CMD_SET_PRESET | 0x80);
    CHECK_EQ(board_shown_grams(&r.board), 0);

    /* refused in SET, DISPENSING and DONE (WRONG_STATE) and in FAULT (FAULT) */
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 3000), CMD_SET_PRESET | 0x80);
    CHECK_EQ(ask_u32(&r, CMD_SHOW_BALANCE, 500), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
    CHECK_EQ(ask_u32(&r, CMD_START, 5), CMD_START | 0x80);
    CHECK_EQ(ask_u32(&r, CMD_SHOW_BALANCE, 500), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
    run(&r, 100, 100);
    run(&r, 30, 0);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(ask_u32(&r, CMD_SHOW_BALANCE, 500), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, 5), CMD_ACK_RESULT | 0x80);
    board_set_fault(&r.board, FAULT_VALVE);
    CHECK_EQ(ask_u32(&r, CMD_SHOW_BALANCE, 500), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_FAULT);
}

/* The POLL answer of the v0.4 board: 19 bytes, the gun is a level, the preset is in it while the fill is SET, runs or waits as a result. */
static void test_poll_status(void)
{
    Rig r;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);

    poll(&r);
    CHECK_EQ(r.reply[4], 19);
    CHECK_EQ(DATA(&r)[0], STATE_IDLE);
    CHECK(DATA(&r)[1] & FLAG_GUN_UP);                           /* the hook sensor says the gun is up */
    r.fake.hook = 0;
    poll(&r);
    CHECK(!(DATA(&r)[1] & FLAG_GUN_UP));
    CHECK_EQ(u32at(DATA(&r) + 15), 0);

    /* SET: the preset; the gun up with the preset on the board starts nothing in auto mode */
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 15000), CMD_SET_PRESET | 0x80);
    r.fake.hook = 1;
    run(&r, 100, 0);
    CHECK_EQ(r.board.state, STATE_SET);
    poll(&r);
    CHECK(DATA(&r)[1] & FLAG_GUN_UP);
    CHECK_EQ(u32at(DATA(&r) + 15), 15000);
    CHECK_EQ(r.fake.pump_on, 0);

    /* DISPENSING: the preset stays, the gun is up */
    CHECK_EQ(ask_u32(&r, CMD_START, 3), CMD_START | 0x80);
    run(&r, 10, 100);
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_DISPENSING);
    CHECK_EQ(u32at(DATA(&r) + 15), 15000);
    CHECK(DATA(&r)[1] & FLAG_GUN_UP);

    /* DONE: the flag RESULT, the id, the preset of the fill; after ACK_RESULT nothing of it is left */
    run(&r, 250, 100);
    run(&r, 30, 0);
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_DONE);
    CHECK(DATA(&r)[1] & FLAG_RESULT);
    CHECK_EQ(u32at(DATA(&r) + 3), 3);
    CHECK_EQ(u32at(DATA(&r) + 15), 15000);
    CHECK_EQ(ask_u32(&r, CMD_ACK_RESULT, 3), CMD_ACK_RESULT | 0x80);
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_IDLE);
    CHECK_EQ(DATA(&r)[1] & FLAG_RESULT, 0);
    CHECK_EQ(u32at(DATA(&r) + 3), 0);
    CHECK_EQ(u32at(DATA(&r) + 15), 0);
}
/* SELECT only makes the board beep; SET_PRESET works in IDLE and SET and a preset of zero is the cancel; DESELECT and CANCEL are gone. */
static void test_select_and_preset(void)
{
    Rig r;

    rig_new(&r, 1);
    configure(&r, MODE_AUTO, 8300, 100000);

    CHECK_EQ(ask0(&r, CMD_SELECT), CMD_SELECT | 0x80);
    CHECK_EQ(r.fake.beeps, 1);
    CHECK_EQ(r.board.state, STATE_IDLE);               /* nothing changes but the beep */
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_IDLE);
    CHECK_EQ(u32at(DATA(&r) + 15), 0);                 /* no preset */

    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 15000), CMD_SET_PRESET | 0x80);    /* accepted in IDLE */
    CHECK_EQ(u32at(DATA(&r)), 15000);
    poll(&r);
    CHECK_EQ(DATA(&r)[0], STATE_SET);
    CHECK_EQ(u32at(DATA(&r) + 15), 15000);              /* the preset is in POLL */
    CHECK_EQ(ask0(&r, CMD_SELECT), CMD_SELECT | 0x80);  /* accepted in SET too, the state stays */
    CHECK_EQ(r.fake.beeps, 2);
    CHECK_EQ(r.board.state, STATE_SET);

    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 20000), CMD_SET_PRESET | 0x80);    /* again: the old preset is replaced */
    CHECK_EQ(r.board.preset_g, 20000);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 0), CMD_SET_PRESET | 0x80);        /* the cancel */
    CHECK_EQ(r.board.state, STATE_IDLE);
    poll(&r);
    CHECK_EQ(u32at(DATA(&r) + 15), 0);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 0), CMD_SET_PRESET | 0x80);        /* also in IDLE */

    /* DESELECT (0x04) and CANCEL (0x07) do not exist any more */
    CHECK_EQ(ask0(&r, 0x04), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_COMMAND);
    CHECK_EQ(ask0(&r, 0x07), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_BAD_COMMAND);

    /* a result that waits (DONE): SELECT and SET_PRESET are refused */
    start_auto(&r, 5000);
    run(&r, 100, 100);
    run(&r, 30, 0);
    CHECK_EQ(r.board.state, STATE_DONE);
    CHECK_EQ(ask0(&r, CMD_SELECT), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 3000), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
    CHECK_EQ(ask_u32(&r, CMD_SET_PRESET, 0), CMD_NAK);
    CHECK_EQ(NAK_ERROR(&r), ERR_WRONG_STATE);
}


int main(void)
{
    test_crc_and_examples();
    test_line();
    test_repeat_rule();
    test_repeat_needs_the_same_request();
    test_auto_fill();
    test_early_close();
    test_preset_tolerance();
    test_no_flow();
    test_hook();
    test_backflow_and_wrap();
    test_config();
    test_time();
    test_keys();
    test_select_and_preset();
    test_session_ids();
    test_show_balance();
    test_poll_status();
    test_manual_mode();
    test_manual_hold();
    test_auto_hold();
    test_total();
    test_power_cut();
    test_power_fail_signal();
    test_faults();
    test_memory();
    test_memory_timing();
    test_hardware_stop();
    test_unknown_and_lengths();

    printf("core: %d checks, %d failed\n", checks, failures);

    return failures ? 1 : 0;
}
