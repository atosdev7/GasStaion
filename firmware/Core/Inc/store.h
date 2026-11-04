#ifndef STORE_H
#define STORE_H

/*
 * The memory of the board over a power cut: the Persist record kept in a ring of 64-byte records in an SPI NOR flash (the 25Q32 ROM).
 * Every save appends one record (magic, sequence number, the fields, CRC-32); the newest good one is the state. A write that a power cut
 * interrupts leaves a record with a wrong CRC, which is ignored, so the previous record is still there. A sector is erased only when the
 * ring reaches it again, and the newest record is never in the sector that is erased.
 *
 * ERASING TAKES LONG (the W25Q32BV datasheet: a 4 KB sector erase takes 30 ms typically and 200 ms at most, 400 ms after 50,000 cycles of the sector; a 64-byte
 * program 0.2 ms typically and 0.8 ms at most), so the sector that the ring
 * enters next is erased AHEAD OF TIME, when the board is idle (store_erase_ahead), and the saves while a fill runs and at a power cut never erase
 * (store_save_noerase): they only program a blank slot. store_save, which may still erase when the sector was not prepared, is for the saves that
 * can wait (START, a config, an acknowledgement).
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
    uint32_t blank_sector;   /* the sector that is known to be blank (erased ahead of time), STORE_SECTORS when none */
} Store;

void store_init(Store *s, const Flash *flash, uint32_t base, int autoformat);

/* The newest good record into *out: returns 1; 0 when the region is blank (a new board; the store is ready for the first save);
   -1 when it is not blank and holds no good record (damaged, or not ours when autoformat is 0). */
int store_load(Store *s, Persist *out);

/* Appends the record. Returns 1 when it is stored and read back right, 0 otherwise. Erases the sector that the ring enters when it was not erased ahead of time. */
int store_save(Store *s, const Persist *in);

/* The same without ever erasing: 1 when it is stored, 0 when the next slot is the start of a sector that was not erased ahead of time (nothing is written then). A program
   takes under a millisecond: this is the save for a power cut and for the saves while a fill runs. */
int store_save_noerase(Store *s, const Persist *in);

/* Makes sure that the sector the ring needs next is blank: when the next slot starts a sector, that one, else the sector after the one being written. Call it when the
   board is idle: an erase takes 45 to 400 ms. Returns 1 when it erased, 0 when there was nothing to do, -1 when the erase failed. Cheap when there is nothing to do. */
int store_erase_ahead(Store *s);

/* Erases the whole region. */
int store_format(Store *s);

#endif
