#include "power.h"

#include <stdio.h>

static int checks, failures;

#define CHECK_EQ(a, b) do { ++checks; if ((long)(a) != (long)(b)) { ++failures; printf("  FAIL test_power.c:%d: %s == %s (%ld vs %ld)\n", __LINE__, #a, #b, (long)(a), (long)(b)); } } while (0)

/* picks at 3 ms steps */
static int pick(PowerStatus *p, uint32_t *now, int pin_cut)
{
    *now += POWER_PICK_MS;

    return power_check(p, *now, pin_cut);
}

static void test_three_picks_decide(void)
{
    PowerStatus p;
    uint32_t now = 1000;

    power_init(&p, 0);
    CHECK_EQ(p.cut, 0);

    /* the pin says cut: two picks are not enough, the third decides */
    CHECK_EQ(pick(&p, &now, 1), 0);
    CHECK_EQ(pick(&p, &now, 1), 0);
    CHECK_EQ(pick(&p, &now, 1), 1);
    CHECK_EQ(pick(&p, &now, 1), 1);

    /* a mixed set leaves the status: a glitch does not end the cut */
    CHECK_EQ(pick(&p, &now, 0), 1);
    CHECK_EQ(pick(&p, &now, 1), 1);
    CHECK_EQ(pick(&p, &now, 0), 1);
    CHECK_EQ(pick(&p, &now, 0), 1);

    /* three that say "not cut" in a row: the supply is on again */
    CHECK_EQ(pick(&p, &now, 0), 0);
    CHECK_EQ(pick(&p, &now, 0), 0);

    /* and a single cut pick in the middle of a good supply does nothing */
    CHECK_EQ(pick(&p, &now, 1), 0);
    CHECK_EQ(pick(&p, &now, 0), 0);
    CHECK_EQ(pick(&p, &now, 1), 0);
    CHECK_EQ(pick(&p, &now, 1), 0);
    CHECK_EQ(pick(&p, &now, 1), 1);
}

static void test_picks_wait_for_the_time(void)
{
    PowerStatus p;

    power_init(&p, 0);

    /* calls that come sooner than 3 ms after a pick take no pick and wait for nothing */
    CHECK_EQ(power_check(&p, 100, 1), 0);      /* pick 1 */
    CHECK_EQ(power_check(&p, 101, 1), 0);      /* too near */
    CHECK_EQ(power_check(&p, 102, 1), 0);      /* too near */
    CHECK_EQ(power_check(&p, 103, 1), 0);      /* pick 2 */
    CHECK_EQ(power_check(&p, 104, 1), 0);
    CHECK_EQ(power_check(&p, 105, 1), 0);
    CHECK_EQ(p.count, 2);
    CHECK_EQ(power_check(&p, 106, 1), 1);      /* pick 3: cut */

    /* the main loop is slow (30 ms between calls): every call is a pick, and the status needs 3 calls */
    power_init(&p, 0);
    CHECK_EQ(power_check(&p, 0, 1), 0);
    CHECK_EQ(power_check(&p, 30, 1), 0);
    CHECK_EQ(power_check(&p, 60, 1), 1);

    /* the time wraps at 2^32 ms without a wrong gap */
    power_init(&p, 0);
    CHECK_EQ(power_check(&p, 0xFFFFFFFEu, 1), 0);
    CHECK_EQ(power_check(&p, 0xFFFFFFFFu, 1), 0);   /* 1 ms later: no pick */
    CHECK_EQ(p.count, 1);
    CHECK_EQ(power_check(&p, 2, 1), 0);             /* 4 ms later: pick 2 */
    CHECK_EQ(p.count, 2);
    CHECK_EQ(power_check(&p, 5, 1), 1);             /* pick 3 */
}

static void test_start(void)
{
    PowerStatus p;
    uint32_t now = 0;

    /* the board starts while the pin already says cut: it is seen after 3 picks */
    power_init(&p, 0);
    CHECK_EQ(pick(&p, &now, 1), 0);
    CHECK_EQ(pick(&p, &now, 1), 0);
    CHECK_EQ(pick(&p, &now, 1), 1);

    /* a status given at the start stays until 3 picks disagree */
    power_init(&p, 1);
    CHECK_EQ(pick(&p, &now, 0), 1);
    CHECK_EQ(pick(&p, &now, 0), 1);
    CHECK_EQ(pick(&p, &now, 0), 0);
}

int main(void)
{
    test_three_picks_decide();
    test_picks_wait_for_the_time();
    test_start();
    printf("power: %d checks, %d failed\n", checks, failures);

    return failures ? 1 : 0;
}