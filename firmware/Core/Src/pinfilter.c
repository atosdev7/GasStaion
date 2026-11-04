#include "pinfilter.h"

#include <string.h>

void pin_filter_init(PinFilter *f, uint32_t interval_ms, uint8_t picks, int state)
{
    memset(f, 0, sizeof(*f));
    f->interval_ms = interval_ms;
    f->picks = picks > 0 ? picks : 1;
    f->state = state != 0;
}

int pin_filter_check(PinFilter *f, uint32_t now_ms, int level)
{
    const uint8_t v = level != 0;

    if (f->started && (uint32_t)(now_ms - f->last_ms) < f->interval_ms) {
        return f->state;                    /* not yet: the previous pick is too near */
    }
    if (f->started && v == f->last) {
        if (f->run < 255) {
            ++f->run;
        }
    } else {
        f->run = 1;
        f->last = v;
    }
    f->started = 1;
    f->last_ms = now_ms;
    if (f->run >= f->picks) {
        f->state = v;                       /* all of the last picks agree */
    }                                       /* mixed: the state stays */

    return f->state;
}