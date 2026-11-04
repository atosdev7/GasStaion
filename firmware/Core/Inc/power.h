#ifndef POWER_H
#define POWER_H

/*
 * The power status from the POWER_OFF input, without an interrupt and without waiting. The main loop calls power_check() as often as it can;
 * each call takes one pick of the pin only when POWER_PICK_MS have passed since the last pick and returns at once otherwise. The last 3 picks
 * decide: all 3 say "cut" -> the status is CUT, all 3 say "not cut" -> the status is ON, a mixed set leaves the status as it was (a glitch of the
 * pin does not stop a fill). The status is only read at the places of the main loop where the board is not busy and can save its state.
 * No hardware in here (the pin and the time come in as arguments), so the PC tests run it.
 */

#include <stdint.h>

#define POWER_PICK_MS 3     /* the time between two picks */
#define POWER_PICKS 3       /* how many picks decide */

typedef struct {
    uint8_t picks[POWER_PICKS];   /* the last picks, 1 = the pin says cut */
    uint8_t count;                /* how many picks are in (up to POWER_PICKS) */
    uint8_t next;                 /* where the next pick goes */
    uint8_t started;              /* 0 until the first pick */
    uint32_t last_ms;             /* the time of the last pick */
    int cut;                      /* the status: 1 the supply is cut */
} PowerStatus;

/* The status at the start: cut = 0 (the supply is on). A pin that says cut at the start is seen after the first 3 picks. */
void power_init(PowerStatus *p, int cut);

/* Takes a pick if the time has come (pin_cut: the pin says cut now) and returns the status. Never waits. */
int power_check(PowerStatus *p, uint32_t now_ms, int pin_cut);

#endif