#include "pinfilter.h"

#include <stdio.h>

static int checks, failures;

#define CHECK_EQ(a, b) do { ++checks; if ((long)(a) != (long)(b)) { ++failures; printf("  FAIL test_pinfilter.c:%d: %s == %s (%ld vs %ld)\n", __LINE__, #a, #b, (long)(a), (long)(b)); } } while (0)

/* a pick after `step` ms */
static int pick(PinFilter *f, uint32_t *now, uint32_t step, int level)
{
    *now += step;

    return pin_filter_check(f, *now, level);
}

/* the power input: 3 picks, 3 ms apart */
static void test_power_three_picks(void)
{
    PinFilter p;
    uint32_t now = 1000;

    pin_filter_init(&p, 3, 3, 0);
    CHECK_EQ(p.state, 0);

    CHECK_EQ(pick(&p, &now, 3, 1), 0);
    CHECK_EQ(pick(&p, &now, 3, 1), 0);
    CHECK_EQ(pick(&p, &now, 3, 1), 1);
    CHECK_EQ(pick(&p, &now, 3, 1), 1);

    /* a mixed set leaves the state: a glitch does not end the cut */
    CHECK_EQ(pick(&p, &now, 3, 0), 1);
    CHECK_EQ(pick(&p, &now, 3, 1), 1);
    CHECK_EQ(pick(&p, &now, 3, 0), 1);
    CHECK_EQ(pick(&p, &now, 3, 0), 1);

    /* three that say "not cut" in a row: the supply is on again */
    CHECK_EQ(pick(&p, &now, 3, 0), 0);
    CHECK_EQ(pick(&p, &now, 3, 0), 0);

    /* and one cut pick in a good supply does nothing */
    CHECK_EQ(pick(&p, &now, 3, 1), 0);
    CHECK_EQ(pick(&p, &now, 3, 0), 0);
    CHECK_EQ(pick(&p, &now, 3, 1), 0);
    CHECK_EQ(pick(&p, &now, 3, 1), 0);
    CHECK_EQ(pick(&p, &now, 3, 1), 1);
}

/* the hook switch: 10 picks, 10 ms apart (10 picks per 100 ms) */
static void test_hook_ten_picks(void)
{
    PinFilter h;
    uint32_t now = 0;
    int i;

    pin_filter_init(&h, 10, 10, 0);

    for (i = 1; i <= 9; ++i) {
        CHECK_EQ(pick(&h, &now, 10, 1), 0);        /* 9 picks are not enough */
    }
    CHECK_EQ(pick(&h, &now, 10, 1), 1);             /* the 10th: lifted */

    /* a bounce: one pick of the other kind in the middle starts the count again */
    for (i = 1; i <= 9; ++i) {
        CHECK_EQ(pick(&h, &now, 10, 0), 1);
    }
    CHECK_EQ(pick(&h, &now, 10, 1), 1);             /* back: the nine are lost */
    for (i = 1; i <= 9; ++i) {
        CHECK_EQ(pick(&h, &now, 10, 0), 1);
    }
    CHECK_EQ(pick(&h, &now, 10, 0), 0);             /* ten in a row: hung */
}

static void test_waits_for_the_time(void)
{
    PinFilter p;

    /* calls that come sooner than the pick time take no pick */
    pin_filter_init(&p, 3, 3, 0);
    CHECK_EQ(pin_filter_check(&p, 100, 1), 0);     /* pick 1 */
    CHECK_EQ(pin_filter_check(&p, 101, 1), 0);
    CHECK_EQ(pin_filter_check(&p, 102, 1), 0);
    CHECK_EQ(pin_filter_check(&p, 103, 1), 0);     /* pick 2 */
    CHECK_EQ(p.run, 2);
    CHECK_EQ(pin_filter_check(&p, 104, 1), 0);
    CHECK_EQ(pin_filter_check(&p, 106, 1), 1);     /* pick 3 */

    /* a slow main loop (30 ms between the calls): every call is a pick */
    pin_filter_init(&p, 3, 3, 0);
    CHECK_EQ(pin_filter_check(&p, 0, 1), 0);
    CHECK_EQ(pin_filter_check(&p, 30, 1), 0);
    CHECK_EQ(pin_filter_check(&p, 60, 1), 1);

    pin_filter_init(&p, 10, 10, 0);
    {
        uint32_t t;
        int state = 0, n = 0;

        for (t = 0; t < 400 && !state; t += 30) {
            state = pin_filter_check(&p, t, 1);
            ++n;
        }
        CHECK_EQ(state, 1);
        CHECK_EQ(n, 10);                            /* 10 calls, 270 ms */
    }

    /* the time wraps at 2^32 ms without a wrong gap */
    pin_filter_init(&p, 3, 3, 0);
    CHECK_EQ(pin_filter_check(&p, 0xFFFFFFFEu, 1), 0);
    CHECK_EQ(pin_filter_check(&p, 0xFFFFFFFFu, 1), 0);   /* 1 ms later: no pick */
    CHECK_EQ(p.run, 1);
    CHECK_EQ(pin_filter_check(&p, 2, 1), 0);             /* 4 ms later: pick 2 */
    CHECK_EQ(p.run, 2);
    CHECK_EQ(pin_filter_check(&p, 5, 1), 1);
}

static void test_start(void)
{
    PinFilter p;
    uint32_t now = 0;

    /* the pin says cut from the start: seen after the picks */
    pin_filter_init(&p, 3, 3, 0);
    CHECK_EQ(pick(&p, &now, 3, 1), 0);
    CHECK_EQ(pick(&p, &now, 3, 1), 0);
    CHECK_EQ(pick(&p, &now, 3, 1), 1);

    /* a state given at the start stays until the picks disagree with it */
    pin_filter_init(&p, 3, 3, 1);
    CHECK_EQ(pick(&p, &now, 3, 1), 1);
    CHECK_EQ(pick(&p, &now, 3, 0), 1);
    CHECK_EQ(pick(&p, &now, 3, 0), 1);
    CHECK_EQ(pick(&p, &now, 3, 0), 0);
}

int main(void)
{
    test_power_three_picks();
    test_hook_ten_picks();
    test_waits_for_the_time();
    test_start();
    printf("pinfilter: %d checks, %d failed\n", checks, failures);

    return failures ? 1 : 0;
}