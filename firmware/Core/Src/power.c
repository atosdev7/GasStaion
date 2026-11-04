#include "power.h"

#include <string.h>

void power_init(PowerStatus *p, int cut)
{
    memset(p, 0, sizeof(*p));
    p->cut = cut != 0;
}

int power_check(PowerStatus *p, uint32_t now_ms, int pin_cut)
{
    uint8_t sum = 0;
    uint8_t i;

    if (p->started && (uint32_t)(now_ms - p->last_ms) < POWER_PICK_MS) {
        return p->cut;                      /* not yet: the previous pick is too near */
    }
    p->started = 1;
    p->last_ms = now_ms;
    p->picks[p->next] = pin_cut != 0;
    p->next = (uint8_t)((p->next + 1) % POWER_PICKS);
    if (p->count < POWER_PICKS) {
        ++p->count;
    }
    if (p->count == POWER_PICKS) {
        for (i = 0; i < POWER_PICKS; ++i) {
            sum = (uint8_t)(sum + p->picks[i]);
        }
        if (sum == POWER_PICKS) {
            p->cut = 1;
        } else if (sum == 0) {
            p->cut = 0;
        }                                   /* mixed: the status stays */
    }

    return p->cut;
}