#ifndef PINFILTER_H
#define PINFILTER_H

/*
 * The filter of an input pin (the POWER_OFF input and the switch of the gun). Two parts that run in two places:
 *   pin_filter_pick()   is called by a timer (every 10 ms, port.c) and only puts what the pin says into the buffer of the last picks;
 *   pin_filter_check()  is called by the main loop, where the board can act, and only looks at the buffer: when all of the last `picks` picks agree, the
 *                       state becomes what they say; a mixed set leaves the state as it was (a glitch of the pin changes nothing).
 * The timer decides nothing and the state changes only in the main loop, so the board never reacts at an unknown place. The buffer is one 32 bit word
 * (written by the timer, read by the main loop: no lock is needed on the Cortex-M). No hardware in here, so the PC tests run it.
 */

#include <stdint.h>

#define PIN_FILTER_MAX_PICKS 32

typedef struct {
    volatile uint32_t bits;   /* the last picks, the newest in bit 0; 1 = the pin says "active" */
    uint8_t picks;            /* how many picks must agree (1 to PIN_FILTER_MAX_PICKS) */
    uint8_t state;            /* the filtered state, 1 or 0; written by pin_filter_check() only */
} PinFilter;

/* The buffer holds `picks` picks of `state`: the state at the start is that until the picks say otherwise. */
void pin_filter_init(PinFilter *f, uint8_t picks, int state);

/* The timer: one pick (level: what the pin says now, 0 or 1). */
void pin_filter_pick(PinFilter *f, int level);

/* The main loop: the state after looking at the buffer. */
int pin_filter_check(PinFilter *f);

#endif