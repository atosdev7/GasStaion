#ifndef BOARD_H
#define BOARD_H

/*
 * The nozzle board of PROTOCOL.md (v0.4, with SHOW_BALANCE and the session ids) without any hardware: the frame parser, the answers, the state machine, the two modes, the fill with
 * its pulse counting, the held result and the recovery after a power cut. The hardware comes in through hal.h; the main loop calls:
 *
 *   board_init()            once, after the HAL is ready
 *   board_feed(byte)        for every byte received on the RS-485 line; when it returns 1 a frame was answered: send board_reply()
 *   board_tick(ms)          at least every 5 ms, with the milliseconds since the last call: runs the fill, the timers
 *   board_key_select()      the select key was pressed (auto mode: only reported to the Smart210 in POLL)
 *   board_key_clear()       the clear key was pressed (auto mode: reported in POLL; manual mode: drops a preset that waits for the gun)
 *   board_manual_amount()   manual mode: the supplier confirmed an amount at the dispenser's keyboard (the fill starts when the gun is up)
 *   board_set_fault()       the hardware found (or no longer finds) a fault
 *   board_power_fail()      the supply is going away (POWER_OFF input): the pump stops and the fill is written to the memory
 *   board_power_restored()  the POWER_OFF input is low again
 *
 * The board never starts an auto fill by itself: the Smart210 sends START (with the session id) when the gun is up and the preset is on the board. A manual board
 * starts, runs and ends its own fill (no result is held, nothing is reported). The pump runs while a fill is going on AND the nozzle is lifted from its hook. A fill whose nozzle is put back after the flow began
 * ends after Params.hook_down_end_ms.
 */

#include "hal.h"

#include <stddef.h>
#include <stdint.h>

#define PROTOCOL_VERSION 4
#define FW_MAJOR 1
#define FW_MINOR 1

#define MAX_DATA 64
#define FRAME_MAX (MAX_DATA + 7)   /* STX ADDR SEQ CMD LEN DATA CRC CRC */

/* states */
enum { STATE_IDLE = 0, STATE_SET = 2, STATE_DISPENSING = 3, STATE_DONE = 4, STATE_FAULT = 5 };   /* 1 is not used: the board has no selected state */
/* POLL flags: bits 0 and 4 are key events (raised once), 2, 3 and 5 are levels */
enum { FLAG_SELECT_KEY = 1, FLAG_RESULT = 4, FLAG_FAULT = 8, FLAG_CLEAR_KEY = 16, FLAG_GUN_UP = 32 };
/* fault codes */
enum { FAULT_NONE = 0, FAULT_FLOW_SENSOR = 1, FAULT_VALVE = 2, FAULT_MEMORY = 3, FAULT_OTHER = 4 };
/* result reasons */
enum { REASON_PRESET = 0, REASON_NO_FLOW = 1, REASON_INTERRUPTED = 2, REASON_FAULT = 3 };
/* modes */
enum { MODE_AUTO = 0, MODE_MANUAL = 1 };
/* NAK codes */
enum {
    ERR_BAD_COMMAND = 1, ERR_BAD_LENGTH = 2, ERR_WRONG_STATE = 3, ERR_BAD_VALUE = 4,
    ERR_NOT_CONFIGURED = 5, ERR_NO_RESULT = 6, ERR_ID_MISMATCH = 7, ERR_FAULT = 8
};
/* commands */
enum {   /* 0x04 (DESELECT) and 0x07 (CANCEL) are gone: a preset of zero is the cancel */
    CMD_INFO = 0x01, CMD_POLL = 0x02, CMD_SELECT = 0x03, CMD_SET_PRESET = 0x05, CMD_START = 0x06,
    CMD_GET_RESULT = 0x08, CMD_ACK_RESULT = 0x09, CMD_GET_CONFIG = 0x0A, CMD_SET_CONFIG = 0x0B,
    CMD_SET_TIME = 0x0C, CMD_GET_TIME = 0x0D, CMD_SHOW_BALANCE = 0x0E, CMD_NAK = 0xFF
};

/* The most SHOW_BALANCE takes: 9999.99 kg, what the 6 digits of the kg display can show (more is BAD_VALUE). */
#define MAX_SHOWN_G 9999990UL

/* Times and limits that can be tuned once the real dispenser is on the bench (work plan 6.6). */
typedef struct {
    uint32_t rx_gap_ms;            /* a half frame is thrown away when no byte follows for this long */
    uint32_t no_flow_ms;           /* a fill ends (reason 1) when no pulse is seen for this long after the first one */
    uint32_t start_wait_ms;        /* ... and when no pulse comes at all within this long after START (the nozzle is not lifted) */
    uint32_t hook_down_end_ms;     /* a fill whose flow began ends (reason 1) when the nozzle has been on its hook for this long */
    uint32_t settle_ms;            /* after the pump is stopped the pulses that still come are counted until none comes for this long */
    uint32_t settle_max_ms;        /* ... but at most this long */
    uint32_t save_ms;              /* the fill in progress is written to the memory this often (flash wear against lost grams) */
    uint32_t early_close_pulses;   /* the pump is stopped this many counts before the target (the coasting after the stop) */
    uint32_t balance_show_ms;      /* a balance shown with SHOW_BALANCE goes from the display after this long (60 s) */
} Params;

typedef struct {
    const Hal *hal;
    uint8_t address;
    Params params;
    Persist p;

    uint8_t state;
    uint8_t key_events;            /* FLAG_SELECT_KEY / FLAG_CLEAR_KEY waiting for the next POLL answer */
    uint8_t fault;
    uint32_t preset_g;
    uint32_t shown_g;              /* the balance of a card shown with SHOW_BALANCE (not a preset); 0: none */
    uint32_t shown_left_ms;
    uint8_t pump_on;               /* what the pump has been told */
    uint8_t powering_down;         /* board_power_fail() was called and the supply has not come back */

    /* the fill */
    uint32_t pulse_base;           /* the HAL's counter at START */
    uint32_t pulses_seen;          /* counts of this fill (including the ones counted while the pump stops) */
    uint8_t flow_started;
    uint8_t closing;               /* the pump is stopped, waiting for the last pulses */
    uint8_t closing_reason;
    uint32_t since_start_ms;
    uint32_t no_flow_for_ms;
    uint32_t hook_down_ms;
    uint32_t closing_ms;
    uint32_t quiet_ms;
    uint32_t since_save_ms;
    uint32_t saved_pulses;

    /* the line */
    uint8_t rx[FRAME_MAX];
    size_t rx_len;
    uint32_t rx_idle_ms;
    uint8_t have_seq;
    uint8_t last_seq;
    uint8_t last_reply[FRAME_MAX];
    size_t last_reply_len;
    uint8_t tx[FRAME_MAX];
    size_t tx_len;
} Board;

void board_default_params(Params *params);

/* Stops the pump, loads what the memory holds and recovers a fill that a power cut interrupted (it becomes a result with reason 2). */
void board_init(Board *b, const Hal *hal, uint8_t address, const Params *params);

/* One received byte. Returns 1 when a frame for this board was answered: the answer is in board_reply(). */
int board_feed(Board *b, uint8_t byte);
const uint8_t *board_reply(const Board *b, size_t *len);

void board_tick(Board *b, uint32_t elapsed_ms);

void board_key_select(Board *b);
void board_key_clear(Board *b);
int board_manual_amount(Board *b, uint32_t grams);

/* The balance the display shows (SHOW_BALANCE), 0 when none. */
uint32_t board_shown_grams(const Board *b);
void board_set_fault(Board *b, uint8_t code);
void board_power_fail(Board *b);
void board_power_restored(Board *b);

/* Conversions of the calibration, exposed for the tests and for the display. */
uint32_t board_target_pulses(const Config *c, uint32_t preset_g);
uint32_t board_grams_of(const Config *c, uint32_t pulses);
uint32_t board_ml_of_grams(const Config *c, uint32_t grams);

/* What the display shows while a fill runs: the grams so far (never more than the preset). */
uint32_t board_live_grams(const Board *b);

#endif
