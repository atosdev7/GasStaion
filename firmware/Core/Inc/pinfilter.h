#ifndef PINFILTER_H
#define PINFILTER_H

/*
 * The filter of an input pin (the POWER_OFF input and the hook switch of the gun), without an interrupt and without waiting. The main loop calls
 * pin_filter_check() once in each pass; a call takes one pick of the pin only when the pick time has passed since the last pick and returns at
 * once otherwise. The last `picks` picks decide: when all of them agree the state becomes what they say, a mixed set leaves the state as it was
 * (a glitch of the pin changes nothing). The state is read where the board can act on it. No hardware in here (the pin and the time come in as
 * arguments), so the PC tests run it.
 */

#include <stdint.h>

typedef struct {
    uint8_t picks;        /* how many equal picks in a row change the state */
    uint8_t run;          /* how many equal picks in a row there are now (up to 255) */
    uint8_t last;         /* what the last pick said */
    uint8_t started;      /* 0 until the first pick */
    uint8_t state;        /* the filtered state: 1 or 0 */
    uint32_t interval_ms; /* the time between two picks */
    uint32_t last_ms;     /* the time of the last pick */
} PinFilter;

/* `picks` picks, one every `interval_ms`, and the state at the start (what the pin says is taken as the state after `picks` picks). */
void pin_filter_init(PinFilter *f, uint32_t interval_ms, uint8_t picks, int state);

/* Takes a pick if the time has come (level: what the pin says now, 0 or 1) and returns the state. Never waits. */
int pin_filter_check(PinFilter *f, uint32_t now_ms, int level);

#endif