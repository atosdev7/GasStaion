#include "store.h"

#include "checksum.h"

#include <string.h>

#define MAGIC0 0xA5
#define MAGIC1 0x5A
#define PAYLOAD_AT 6
#define CRC_AT 60

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

/* the record: magic(2) seq(4) payload(54) crc32(4) */
static void pack(uint8_t *rec, uint32_t seq, const Persist *p)
{
    uint8_t *q = rec + PAYLOAD_AT;

    memset(rec, 0, STORE_SLOT);
    rec[0] = MAGIC0;
    rec[1] = MAGIC1;
    put32(rec + 2, seq);

    *q++ = p->config.mode;
    memcpy(q, p->config.fuel, FUEL_LEN);
    q += FUEL_LEN;
    put16(q, p->config.density);
    q += 2;
    put32(q, p->config.pulses_x100);
    q += 4;
    put32(q, p->next_result_id);
    q += 4;
    put32(q, p->last_acked_id);
    q += 4;
    *q++ = p->result.held;
    put32(q, p->result.id);
    q += 4;
    put32(q, p->result.preset_g);
    q += 4;
    put32(q, p->result.dispensed_g);
    q += 4;
    put32(q, p->result.dispensed_ml);
    q += 4;
    *q++ = p->result.reason;
    *q++ = p->inflight.active;
    put32(q, p->inflight.id);
    q += 4;
    put32(q, p->inflight.preset_g);
    q += 4;
    put32(q, p->inflight.target_pulses);
    q += 4;
    put32(q, p->inflight.pulses);

    put32(rec + CRC_AT, crc32(rec, CRC_AT));
}

static void unpack(const uint8_t *rec, Persist *p)
{
    const uint8_t *q = rec + PAYLOAD_AT;

    p->config.mode = *q++;
    memcpy(p->config.fuel, q, FUEL_LEN);
    q += FUEL_LEN;
    p->config.density = get16(q);
    q += 2;
    p->config.pulses_x100 = get32(q);
    q += 4;
    p->next_result_id = get32(q);
    q += 4;
    p->last_acked_id = get32(q);
    q += 4;
    p->result.held = *q++;
    p->result.id = get32(q);
    q += 4;
    p->result.preset_g = get32(q);
    q += 4;
    p->result.dispensed_g = get32(q);
    q += 4;
    p->result.dispensed_ml = get32(q);
    q += 4;
    p->result.reason = *q++;
    p->inflight.active = *q++;
    p->inflight.id = get32(q);
    q += 4;
    p->inflight.preset_g = get32(q);
    q += 4;
    p->inflight.target_pulses = get32(q);
    q += 4;
    p->inflight.pulses = get32(q);
}

static int record_good(const uint8_t *rec)
{
    return rec[0] == MAGIC0 && rec[1] == MAGIC1 && get32(rec + CRC_AT) == crc32(rec, CRC_AT);
}

static int blank(const uint8_t *rec)
{
    uint32_t i;

    for (i = 0; i < STORE_SLOT; ++i) {
        if (rec[i] != 0xFF) {
            return 0;
        }
    }

    return 1;
}

static uint32_t slot_addr(const Store *s, uint32_t slot)
{
    return s->base + slot * (uint32_t)STORE_SLOT;
}

void store_init(Store *s, const Flash *flash, uint32_t base, int autoformat)
{
    memset(s, 0, sizeof(*s));
    s->flash = flash;
    s->base = base;
    s->autoformat = autoformat;
}

int store_format(Store *s)
{
    uint32_t i;

    for (i = 0; i < STORE_SECTORS; ++i) {
        if (!s->flash->erase(s->flash->ctx, s->base + i * (uint32_t)STORE_SECTOR)) {
            return 0;
        }
    }
    s->ready = 1;
    s->seq = 0;
    s->next = 0;

    return 1;
}

int store_load(Store *s, Persist *out)
{
    uint8_t rec[STORE_SLOT];
    uint8_t best[STORE_SLOT];
    uint32_t slot;
    int found = 0;
    int other = 0;     /* something that is not blank and not a good record */
    uint32_t best_seq = 0;
    uint32_t best_slot = 0;

    s->ready = 0;
    for (slot = 0; slot < STORE_SLOTS; ++slot) {
        if (!s->flash->read(s->flash->ctx, slot_addr(s, slot), rec, STORE_SLOT)) {
            return -1;
        }
        if (record_good(rec)) {
            const uint32_t seq = get32(rec + 2);

            if (!found || (int32_t)(seq - best_seq) > 0) {
                found = 1;
                best_seq = seq;
                best_slot = slot;
                memcpy(best, rec, STORE_SLOT);
            }
        } else if (!blank(rec)) {
            other = 1;
        }
    }

    if (found) {
        unpack(best, out);
        s->seq = best_seq;
        s->next = (best_slot + 1) % STORE_SLOTS;
        s->ready = 1;

        return 1;
    }
    if (!other) {
        s->seq = 0;
        s->next = 0;
        s->ready = 1;

        return 0; /* blank: a new board */
    }
    if (s->autoformat && store_format(s)) {
        return 0;
    }

    return -1;
}

int store_save(Store *s, const Persist *in)
{
    uint8_t rec[STORE_SLOT];
    uint8_t check[STORE_SLOT];
    uint32_t tries;
    uint32_t slot = s->next;
    const uint32_t seq = s->seq + 1;

    if (!s->ready) {
        return 0;
    }
    pack(rec, seq, in);

    for (tries = 0; tries < STORE_SLOTS; ++tries) {
        const uint32_t addr = slot_addr(s, slot);

        if (slot % STORE_SLOTS_PER_SECTOR == 0) {
            /* the ring enters a sector again: what is in it is the oldest; the newest is in the sector before */
            if (!s->flash->erase(s->flash->ctx, addr)) {
                return 0;
            }
        }
        if (s->flash->read(s->flash->ctx, addr, check, STORE_SLOT) && blank(check)) {
            if (s->flash->program(s->flash->ctx, addr, rec, STORE_SLOT) && s->flash->read(s->flash->ctx, addr, check, STORE_SLOT)
                && memcmp(check, rec, STORE_SLOT) == 0) {
                s->seq = seq;
                s->next = (slot + 1) % STORE_SLOTS;

                return 1;
            }
        }
        slot = (slot + 1) % STORE_SLOTS; /* a slot that a power cut or a bad cell spoiled is passed by */
    }

    return 0;
}
