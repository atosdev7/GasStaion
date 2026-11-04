#ifndef HAL_H
#define HAL_H

/*
 * What the nozzle-board core needs from the hardware. The core (board.c) has no STM32 code in it: port.c fills in this table for the
 * STM32F407 board, and the tests on the PC (firmware/Tests) fill it in with a fake board.
 */

#include <stdint.h>

#define FUEL_LEN 4

typedef struct {
    uint16_t year;   /* 2000..2099 */
    uint8_t month;   /* 1..12 */
    uint8_t day;     /* 1..31 */
    uint8_t hour;    /* 0..23 */
    uint8_t minute;  /* 0..59 */
    uint8_t second;  /* 0..59 */
} DateTime;

/* The config the terminal sets (PROTOCOL.md, GET_CONFIG / SET_CONFIG). */
typedef struct {
    uint8_t mode;                  /* 0 auto, 1 manual */
    uint8_t fuel[FUEL_LEN];     /* ASCII, padded with 0 */
    uint16_t density;              /* kg per litre x 10000 */
    uint32_t pulses_x100;          /* counts of the flow meter per litre x 100 */
} Config;

/* The result of the last fill, held until the terminal acknowledges it. */
typedef struct {
    uint8_t held;                  /* 1 while a result waits for ACK_RESULT */
    uint32_t id;                   /* the session id the fill was started with (START carries it) */
    uint32_t preset_g;
    uint32_t dispensed_g;
    uint32_t dispensed_ml;
    uint8_t reason;                /* 0 preset reached, 1 tank full or no flow, 2 interrupted, 3 fault */
} Result;

/* The fill in progress, saved while it runs so that a power cut can be recovered as a result with reason 2. */
typedef struct {
    uint8_t active;
    uint32_t id;                   /* the session id of START; 0 for a fill of a manual board (it has no session) */
    uint32_t preset_g;
    uint32_t target_pulses;
    uint32_t pulses;               /* counted so far */
} Inflight;

/*
 * Everything the board keeps over a power cut (Store writes it as records with a check sum into the SPI flash). A board never configured
 * has config.density and config.pulses_x100 = 0.
 */
typedef struct {
    Config config;
    uint32_t last_acked_id;        /* the session id of the last result that was acknowledged (a repeated ACK_RESULT is answered again) */
    Result result;
    Inflight inflight;
} Persist;

typedef struct {
    void *ctx;

    /* Fills *out with the last stored record and returns 1; returns 0 when there is none (a new board); returns -1 when records are
       there but none can be read (the core then shows fault 3 and does not work until it is serviced). */
    int (*load)(void *ctx, Persist *out);
    /* Stores the record; returns 1 when it is stored, 0 when the memory refused (the core then reports fault 3, memory). It may take long (a flash sector erase, 30 to
       200 ms and more with a worn sector): the core uses it only where that does not matter (START, a config, the end of a fill, an acknowledgement). */
    int (*save)(void *ctx, const Persist *in);
    /* Stores the record without ever erasing (a few milliseconds at most): for the fill in progress and for the power cut. Returns 1 when it is stored, 0 when it could not be
       (nothing is written; the core does not treat that as a fault: the last record stays the state). May be 0: then save is used. */
    int (*save_now)(void *ctx, const Persist *in);
    /* Work that takes long and can wait for an idle moment (erasing the sector of the memory that is needed next). Called by board_idle_work() only when no fill runs and
       nothing waits. May be 0. */
    void (*housekeeping)(void *ctx);

    /* The flow meter's count, free running and widened to 32 bits by the port; it goes down with a backflow. Called at every tick. */
    uint32_t (*pulses)(void *ctx);
    /* The pump (relays 1 and 2): 1 runs it, 0 stops it. The core calls it only when the wish changes, and stops it at start up. */
    void (*pump)(void *ctx, int on);
    /* The hardware stops the pump by itself when the flow counter reaches this count (the value pulses() has then: 32 bit, wrapping): an interrupt on a compare channel of the
       flow meter's timer, so that the end of a fill does not wait for the main loop (a pass can last 30 ms, which at a fast flow is a few grams past the preset). The core arms
       it at START and again at every tick (the timer is 16 bit: a stop that is far off is armed when it comes near) and disarms it when the pump is stopped. Both may be 0. */
    void (*arm_stop)(void *ctx, uint32_t at_count);
    void (*disarm_stop)(void *ctx);
    /* The nozzle hook sensor: 1 when the nozzle is lifted from its holder. May be 0 (a board without the sensor: the nozzle counts as lifted). */
    int (*hook)(void *ctx);

    /* The beeper: one short beep (SELECT). May be 0 (a board without a beeper). */
    void (*beep)(void *ctx);

    /* The battery-backed clock; both return 1 when done. */
    int (*rtc_get)(void *ctx, DateTime *out);
    int (*rtc_set)(void *ctx, const DateTime *in);

    /* The board's serial number (for example from the unique id of the chip). */
    uint32_t (*serial)(void *ctx);
} Hal;

#endif
