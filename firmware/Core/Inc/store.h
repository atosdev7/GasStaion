#ifndef STORE_H
#define STORE_H

/*
 * The memory of the board over a power cut: the Persist record kept in a ring of 64-byte records in an SPI NOR flash (the 25Q32 ROM).
 * Every save appends one record (magic, sequence number, the fields, CRC-32); the newest good one is the state. A write that a power cut
 * interrupts leaves a record with a wrong CRC, which is ignored, so the previous record is still there. A sector is erased only when the
 * ring reaches it again, and the newest record is never in the sector that is erased.
 *
 * The region is STORE_SECTORS sectors of 4 KB from `base`: it must be flash that nothing else uses.
 */

#include "hal.h"

#include <stdint.h>

#define STORE_SECTOR 4096UL
#define STORE_SLOT 64UL
#define STORE_SECTORS 8UL
#define STORE_SLOTS_PER_SECTOR (STORE_SECTOR / STORE_SLOT)
#define STORE_SLOTS (STORE_SECTORS * STORE_SLOTS_PER_SECTOR)

typedef struct {
    void *ctx;
    int (*read)(void *ctx, uint32_t addr, uint8_t *buf, uint32_t len);
    /* NOR programming: only clears bits; len is never more than 64 and never crosses a 256 byte page. Returns 1 when done. */
    int (*program)(void *ctx, uint32_t addr, const uint8_t *buf, uint32_t len);
    /* Erases the 4 KB sector that holds addr (all bytes 0xFF). Returns 1 when done. */
    int (*erase)(void *ctx, uint32_t addr);
} Flash;

typedef struct {
    const Flash *flash;
    uint32_t base;
    int autoformat;      /* 1: a region that holds something that is not ours is erased and used; 0: it is reported as damaged */
    int ready;
    uint32_t seq;        /* sequence number of the newest record */
    uint32_t next;       /* the slot to try next */
} Store;

void store_init(Store *s, const Flash *flash, uint32_t base, int autoformat);

/* The newest good record into *out: returns 1; 0 when the region is blank (a new board; the store is ready for the first save);
   -1 when it is not blank and holds no good record (damaged, or not ours when autoformat is 0). */
int store_load(Store *s, Persist *out);

/* Appends the record. Returns 1 when it is stored and read back right, 0 otherwise. */
int store_save(Store *s, const Persist *in);

/* Erases the whole region. */
int store_format(Store *s);

#endif
