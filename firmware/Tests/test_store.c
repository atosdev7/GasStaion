/* Tests of the flash record store on the PC with a fake NOR flash (bits only go from 1 to 0 by a write; an erase sets a whole sector to 1). */

#include "../Core/Inc/store.h"

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

#define SIZE (16UL * 4096UL)  /* a small chip; the region sits at its end */
#define BASE (8UL * 4096UL)

typedef struct {
    uint8_t mem[SIZE];
    int erases[16];
    int programs;
    long power_cut_after_bytes; /* >= 0: the next program writes only this many bytes and then fails (the power goes) */
    int fail_erase;
    int dead;                   /* the power went: nothing works until the restart */
} Chip;

static int c_read(void *ctx, uint32_t addr, uint8_t *buf, uint32_t len)
{
    Chip *c = (Chip *)ctx;

    if (addr + len > SIZE) {
        return 0;
    }
    memcpy(buf, c->mem + addr, len);

    return 1;
}

static int c_program(void *ctx, uint32_t addr, const uint8_t *buf, uint32_t len)
{
    Chip *c = (Chip *)ctx;
    uint32_t i;
    uint32_t n = len;

    if (c->dead || addr + len > SIZE || (addr / 256) != ((addr + len - 1) / 256) || len > 64) {
        return 0;
    }
    ++c->programs;
    if (c->power_cut_after_bytes >= 0 && (uint32_t)c->power_cut_after_bytes < len) {
        n = (uint32_t)c->power_cut_after_bytes;
    }
    for (i = 0; i < n; ++i) {
        c->mem[addr + i] &= buf[i];
    }
    if (n < len) {
        c->power_cut_after_bytes = -1;
        c->dead = 1;

        return 0;
    }

    return 1;
}

static int c_erase(void *ctx, uint32_t addr)
{
    Chip *c = (Chip *)ctx;
    const uint32_t sector = addr / 4096;

    if (c->dead || c->fail_erase || sector >= 16) {
        return 0;
    }
    memset(c->mem + sector * 4096, 0xFF, 4096);
    ++c->erases[sector];

    return 1;
}

static void chip_new(Chip *c)
{
    memset(c, 0, sizeof(*c));
    memset(c->mem, 0xFF, sizeof(c->mem));
    c->power_cut_after_bytes = -1;
}

static Flash flash_of(Chip *c)
{
    Flash f;

    f.ctx = c;
    f.read = c_read;
    f.program = c_program;
    f.erase = c_erase;

    return f;
}

static Persist sample(uint32_t n)
{
    Persist p;

    memset(&p, 0, sizeof(p));
    p.config.mode = 1;
    memcpy(p.config.fuel, "D\0\0\0", 4);
    p.config.density = 8300;
    p.config.pulses_x100 = 123450;
    p.last_acked_id = n - 1;
    p.result.held = 1;
    p.result.id = n;
    p.result.preset_g = 15000;
    p.result.dispensed_g = 14990;
    p.result.dispensed_ml = 18060;
    p.result.reason = 2;
    p.inflight.active = 1;
    p.inflight.id = n + 1;
    p.inflight.preset_g = 20000;
    p.inflight.target_pulses = 24096;
    p.inflight.pulses = n * 3;

    return p;
}

static int same(const Persist *a, const Persist *b)
{
    return a->config.mode == b->config.mode && memcmp(a->config.fuel, b->config.fuel, 4) == 0 && a->config.density == b->config.density
        && a->config.pulses_x100 == b->config.pulses_x100 && a->last_acked_id == b->last_acked_id
        && a->result.held == b->result.held && a->result.id == b->result.id && a->result.preset_g == b->result.preset_g
        && a->result.dispensed_g == b->result.dispensed_g && a->result.dispensed_ml == b->result.dispensed_ml
        && a->result.reason == b->result.reason && a->inflight.active == b->inflight.active && a->inflight.id == b->inflight.id
        && a->inflight.preset_g == b->inflight.preset_g && a->inflight.target_pulses == b->inflight.target_pulses
        && a->inflight.pulses == b->inflight.pulses;
}

static void test_blank_and_roundtrip(void)
{
    Chip c;
    Flash f;
    Store s;
    Persist in, out;

    chip_new(&c);
    f = flash_of(&c);
    store_init(&s, &f, BASE, 0);

    /* a blank chip is a new board */
    CHECK_EQ(store_load(&s, &out), 0);
    CHECK_EQ(c.programs, 0);

    in = sample(7);
    CHECK_EQ(store_save(&s, &in), 1);
    CHECK_EQ(c.erases[8], 1);  /* the first sector of the ring is erased when the ring enters it */

    /* after a restart the record is read back, field by field */
    store_init(&s, &f, BASE, 0);
    CHECK_EQ(store_load(&s, &out), 1);
    CHECK(same(&in, &out));

    /* the newest wins */
    in = sample(8);
    CHECK_EQ(store_save(&s, &in), 1);
    in = sample(9);
    CHECK_EQ(store_save(&s, &in), 1);
    store_init(&s, &f, BASE, 0);
    CHECK_EQ(store_load(&s, &out), 1);
    CHECK(same(&in, &out));

    /* nothing outside the region is touched */
    {
        uint32_t i;
        int clean = 1;

        for (i = 0; i < BASE; ++i) {
            clean = clean && c.mem[i] == 0xFF;
        }
        for (i = BASE + STORE_SECTORS * 4096; i < SIZE; ++i) {
            clean = clean && c.mem[i] == 0xFF;
        }
        CHECK(clean);
    }

    /* a save before load is refused */
    store_init(&s, &f, BASE, 0);
    CHECK_EQ(store_save(&s, &in), 0);
}

static void test_power_cut_in_a_write(void)
{
    Chip c;
    Flash f;
    Store s;
    Persist a, b, out;
    long cut;

    /* a power cut at every byte of a write: the previous record is always there afterwards, and the store goes on working */
    for (cut = 0; cut < 64; ++cut) {
        chip_new(&c);
        f = flash_of(&c);
        store_init(&s, &f, BASE, 0);
        store_load(&s, &out);
        a = sample(10);
        b = sample(11);
        CHECK_EQ(store_save(&s, &a), 1);

        c.power_cut_after_bytes = cut;
        CHECK_EQ(store_save(&s, &b), 0);   /* the write that the power cut interrupted */

        c.dead = 0;
        store_init(&s, &f, BASE, 0);        /* the restart */
        CHECK_EQ(store_load(&s, &out), 1);
        CHECK(same(&a, &out));                 /* the old state, not half of the new one */

        CHECK_EQ(store_save(&s, &b), 1);    /* the spoiled slot is passed by */
        store_init(&s, &f, BASE, 0);
        CHECK_EQ(store_load(&s, &out), 1);
        CHECK(same(&b, &out));
    }
}

static void test_ring_and_wear(void)
{
    Chip c;
    Flash f;
    Store s;
    Persist p, out;
    uint32_t n;
    uint32_t i;
    int min_erases = 1000000, max_erases = 0;

    chip_new(&c);
    f = flash_of(&c);
    store_init(&s, &f, BASE, 0);
    store_load(&s, &out);

    /* many more records than the ring holds: always the newest comes back, also with a restart in between */
    for (n = 1; n <= 5000; ++n) {
        p = sample(n);
        CHECK_EQ(store_save(&s, &p), 1);
        if (n % 777 == 0) {
            store_init(&s, &f, BASE, 0);
            CHECK_EQ(store_load(&s, &out), 1);
            CHECK(same(&p, &out));
        }
    }
    store_init(&s, &f, BASE, 0);
    CHECK_EQ(store_load(&s, &out), 1);
    CHECK_EQ(out.result.id, 5000);

    /* the wear is spread: every sector of the ring is erased about the same number of times */
    for (i = 0; i < STORE_SECTORS; ++i) {
        const int e = c.erases[BASE / 4096 + i];

        if (e < min_erases) {
            min_erases = e;
        }
        if (e > max_erases) {
            max_erases = e;
        }
    }
    CHECK(max_erases - min_erases <= 1);
    CHECK(max_erases >= 5000 / (int)STORE_SLOTS);

    /* power cuts all the way: after each restart the newest record that was stored is the state */
    chip_new(&c);
    f = flash_of(&c);
    store_init(&s, &f, BASE, 0);
    store_load(&s, &out);
    {
        uint32_t stored = 0;

        for (n = 1; n <= 3000; ++n) {
            p = sample(n);
            if (n % 5 == 0) {
                c.power_cut_after_bytes = (long)(n % 64);
            }
            if (store_save(&s, &p)) {
                stored = n;
            } else {
                c.dead = 0;
                store_init(&s, &f, BASE, 0);
                CHECK_EQ(store_load(&s, &out), 1);
                CHECK_EQ(out.result.id, stored);
            }
        }
        store_init(&s, &f, BASE, 0);
        CHECK_EQ(store_load(&s, &out), 1);
        CHECK_EQ(out.result.id, stored);
    }
}

static void test_foreign_data_and_damage(void)
{
    Chip c;
    Flash f;
    Store s;
    Persist p, out;
    uint32_t i;

    /* something that is not ours in the region (a font, say): refused unless the board is told to format */
    chip_new(&c);
    f = flash_of(&c);
    for (i = 0; i < 100; ++i) {
        c.mem[BASE + 5000 + i] = (uint8_t)(i * 7);
    }
    store_init(&s, &f, BASE, 0);
    CHECK_EQ(store_load(&s, &out), -1);
    CHECK_EQ(c.mem[BASE + 5000 + 1], 7);          /* nothing was erased */
    p = sample(1);
    CHECK_EQ(store_save(&s, &p), 0);

    store_init(&s, &f, BASE, 1);
    CHECK_EQ(store_load(&s, &out), 0);         /* formatted: a new board */
    CHECK_EQ(c.mem[BASE + 5000 + 1], 0xFF);
    CHECK_EQ(store_save(&s, &p), 1);
    store_init(&s, &f, BASE, 0);
    CHECK_EQ(store_load(&s, &out), 1);

    /* records that are all spoiled (a bad CRC in each): damaged, not a new board */
    chip_new(&c);
    f = flash_of(&c);
    store_init(&s, &f, BASE, 0);
    store_load(&s, &out);
    p = sample(1);
    store_save(&s, &p);
    c.mem[BASE + 20] ^= 0x01;
    store_init(&s, &f, BASE, 0);
    CHECK_EQ(store_load(&s, &out), -1);

    /* a spoiled newest record: the one before it is used */
    chip_new(&c);
    f = flash_of(&c);
    store_init(&s, &f, BASE, 0);
    store_load(&s, &out);
    p = sample(1);
    store_save(&s, &p);
    p = sample(2);
    store_save(&s, &p);
    c.mem[BASE + 64 + 30] ^= 0x01;
    store_init(&s, &f, BASE, 0);
    CHECK_EQ(store_load(&s, &out), 1);
    CHECK_EQ(out.result.id, 1);

    /* an erase that fails is reported */
    chip_new(&c);
    f = flash_of(&c);
    store_init(&s, &f, BASE, 0);
    store_load(&s, &out);
    c.fail_erase = 1;
    p = sample(1);
    CHECK_EQ(store_save(&s, &p), 0);
}

int main(void)
{
    test_blank_and_roundtrip();
    test_power_cut_in_a_write();
    test_ring_and_wear();
    test_foreign_data_and_damage();

    printf("store: %d checks, %d failed\n", checks, failures);

    return failures ? 1 : 0;
}
