#include "pinfilter.h"

static uint32_t mask_of(uint8_t picks)
{
    return picks >= PIN_FILTER_MAX_PICKS ? 0xFFFFFFFFu : ((1u << picks) - 1u);
}

void pin_filter_init(PinFilter *f, uint8_t picks, int state)
{
    f->picks = picks < 1 ? 1 : (picks > PIN_FILTER_MAX_PICKS ? PIN_FILTER_MAX_PICKS : picks);
    f->state = state != 0;
    f->bits = state ? mask_of(f->picks) : 0;
}

void pin_filter_pick(PinFilter *f, int level)
{
    f->bits = (f->bits << 1) | (level != 0 ? 1u : 0u);
}

int pin_filter_check(PinFilter *f)
{
    const uint32_t mask = mask_of(f->picks);
    const uint32_t last = f->bits & mask;

    if (last == mask) {
        f->state = 1;                       /* all of the last picks say active */
    } else if (last == 0) {
        f->state = 0;                       /* all of them say not active */
    }                                       /* mixed: the state stays */

    return f->state;
}