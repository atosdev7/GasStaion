#include "pinfilter.h"

#include <stdio.h>

static int checks, failures;

#define CHECK_EQ(a, b) do { ++checks; if ((long)(a) != (long)(b)) { ++failures; printf("  FAIL test_pinfilter.c:%d: %s == %s (%ld vs %ld)\n", __LINE__, #a, #b, (long)(a), (long)(b)); } } while (0)

/* one timer pick, then one look of the main loop */
static int pick_check(PinFilter *f, int level)
{
    pin_filter_pick(f, level);

    return pin_filter_check(f);
}

/* the power input: 3 picks */
static void test_power_three_picks(void)
{
    PinFilter p;

    pin_filter_init(&p, 3, 0);
    CHECK_EQ(p.state, 0);

    CHECK_EQ(pick_check(&p, 1), 0);
    CHECK_EQ(pick_check(&p, 1), 0);
    CHECK_EQ(pick_check(&p, 1), 1);
    CHECK_EQ(pick_check(&p, 1), 1);

    /* a mixed set leaves the state: a glitch does not end the cut */
    CHECK_EQ(pick_check(&p, 0), 1);
    CHECK_EQ(pick_check(&p, 1), 1);
    CHECK_EQ(pick_check(&p, 0), 1);
    CHECK_EQ(pick_check(&p, 0), 1);

    /* three that say "not cut" in a row: the supply is on again */
    CHECK_EQ(pick_check(&p, 0), 0);
    CHECK_EQ(pick_check(&p, 0), 0);

    /* and one cut pick in a good supply does nothing */
    CHECK_EQ(pick_check(&p, 1), 0);
    CHECK_EQ(pick_check(&p, 0), 0);
    CHECK_EQ(pick_check(&p, 1), 0);
    CHECK_EQ(pick_check(&p, 1), 0);
    CHECK_EQ(pick_check(&p, 1), 1);
}

/* the gun switch: 10 picks (10 picks per 100 ms with the 10 ms timer) */
static void test_gun_ten_picks(void)
{
    PinFilter g;
    int i;

    pin_filter_init(&g, 10, 0);

    for (i = 1; i <= 9; ++i) {
        CHECK_EQ(pick_check(&g, 1), 0);            /* 9 picks are not enough */
    }
    CHECK_EQ(pick_check(&g, 1), 1);                 /* the 10th: lifted */

    /* a bounce: one pick of the other kind in the middle starts the count again */
    for (i = 1; i <= 9; ++i) {
        CHECK_EQ(pick_check(&g, 0), 1);
    }
    CHECK_EQ(pick_check(&g, 1), 1);                 /* back: the nine are lost */
    for (i = 1; i <= 9; ++i) {
        CHECK_EQ(pick_check(&g, 0), 1);
    }
    CHECK_EQ(pick_check(&g, 0), 0);                 /* ten in a row: hung */
}

/* the main loop looks later than the timer picks: it sees the last picks, and a pick that came and went between two looks changes nothing */
static void test_check_is_only_a_look(void)
{
    PinFilter p;
    int i;

    pin_filter_init(&p, 3, 0);
    for (i = 0; i < 5; ++i) {
        pin_filter_pick(&p, 1);                     /* five picks and no look */
    }
    CHECK_EQ(p.state, 0);                           /* the timer decides nothing */
    CHECK_EQ(pin_filter_check(&p), 1);              /* the look: the last three agree */

    pin_filter_pick(&p, 0);
    pin_filter_pick(&p, 1);
    pin_filter_pick(&p, 1);
    pin_filter_pick(&p, 1);
    CHECK_EQ(pin_filter_check(&p), 1);              /* the glitch came and went between two looks */

    pin_filter_pick(&p, 0);
    pin_filter_pick(&p, 0);
    CHECK_EQ(pin_filter_check(&p), 1);              /* two are not three */
    pin_filter_pick(&p, 0);
    CHECK_EQ(pin_filter_check(&p), 0);
}

static void test_start_and_limits(void)
{
    PinFilter p;
    int i;

    /* the pin says cut from the start: seen after the picks */
    pin_filter_init(&p, 3, 0);
    CHECK_EQ(pick_check(&p, 1), 0);
    CHECK_EQ(pick_check(&p, 1), 0);
    CHECK_EQ(pick_check(&p, 1), 1);

    /* a state given at the start stays until the picks disagree with it */
    pin_filter_init(&p, 3, 1);
    CHECK_EQ(pin_filter_check(&p), 1);
    CHECK_EQ(pick_check(&p, 1), 1);
    CHECK_EQ(pick_check(&p, 0), 1);
    CHECK_EQ(pick_check(&p, 0), 1);
    CHECK_EQ(pick_check(&p, 0), 0);

    /* the longest buffer is 32 picks; a bigger number is cut to it, 0 is 1 */
    pin_filter_init(&p, 32, 0);
    for (i = 1; i < 32; ++i) {
        CHECK_EQ(pick_check(&p, 1), 0);
    }
    CHECK_EQ(pick_check(&p, 1), 1);
    pin_filter_init(&p, 200, 0);
    CHECK_EQ(p.picks, 32);
    pin_filter_init(&p, 0, 0);
    CHECK_EQ(p.picks, 1);
    CHECK_EQ(pick_check(&p, 1), 1);
}

int main(void)
{
    test_power_three_picks();
    test_gun_ten_picks();
    test_check_is_only_a_look();
    test_start_and_limits();
    printf("pinfilter: %d checks, %d failed\n", checks, failures);

    return failures ? 1 : 0;
}