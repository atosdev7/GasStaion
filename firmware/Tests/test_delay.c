/*
 * delay_us (Core/Src/delay.c) on the PC. The counter of the timer is a function that moves on a few ticks at each look, so the wait can be measured exactly: it must
 * never be shorter than asked and never longer by more than one step of the counter, whatever the counts of a microsecond are, whether the counter turns round in
 * the wait, and however long the wait is.
 */

#define DELAY_HOST_TEST
#include "../Core/Src/delay.c"

#include <stdio.h>

static int checks, failures;

#define CHECK_EQ(a, b) do { ++checks; if ((long)(a) != (long)(b)) { ++failures; if (failures < 40) printf("  FAIL test_delay.c:%d: %s == %s (%ld vs %ld)\n", __LINE__, #a, #b, (long)(a), (long)(b)); } } while (0)

uint32_t delay_ticks_per_us = 16;

static unsigned long long now_ticks;        /* the ticks that passed since the start of the fake timer */
static uint16_t base;                       /* where the counter stood at the start */
static unsigned step;                       /* how far the counter moves between two looks */
static unsigned long looks;

uint16_t delay_counter(void)
{
    now_ticks += step;
    ++looks;

    return (uint16_t)(base + now_ticks);
}

static void measure(uint32_t us, uint16_t start, unsigned each_look, uint32_t per_us)
{
    unsigned long long first;
    unsigned long long want = (unsigned long long)us * per_us;

    delay_ticks_per_us = per_us;
    base = start;
    step = each_look;
    now_ticks = 0;
    looks = 0;
    delay_us(us);
    first = step;                          /* the first look is the start of the wait */
    CHECK_EQ(now_ticks >= first + want, 1);                       /* never shorter */
    CHECK_EQ(now_ticks < first + want + step, 1);                 /* never longer than a step of the counter */
    if (us == 0) {
        CHECK_EQ(looks, 1);
    }
}

int main(void)
{
    static const uint32_t waits[] = {0, 1, 2, 3, 5, 10, 50, 150, 500, 4095, 4096, 5000, 100000};
    static const uint16_t starts[] = {0, 1, 40000, 65500, 65535};
    static const unsigned steps[] = {1, 2, 3, 7, 16};
    static const uint32_t rates[] = {16, 8, 168};
    unsigned w, s, t, r;

    for (r = 0; r < sizeof rates / sizeof rates[0]; ++r) {
        for (w = 0; w < sizeof waits / sizeof waits[0]; ++w) {
            for (s = 0; s < sizeof starts / sizeof starts[0]; ++s) {
                for (t = 0; t < sizeof steps / sizeof steps[0]; ++t) {
                    measure(waits[w], starts[s], steps[t], rates[r]);
                }
            }
        }
    }
    printf("delay: %d checks, %d failed\n", checks, failures);

    return failures ? 1 : 0;
}
