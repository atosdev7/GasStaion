#include "delay.h"

#ifdef DELAY_HOST_TEST

/* the tests on the PC: the counter is a function that the test moves on at each look, and the counts of a microsecond are a number */
extern uint16_t delay_counter(void);
#define TICKS_PER_US delay_ticks_per_us
extern uint32_t delay_ticks_per_us;

#else

#include "main.h"

/* the counter of TIM6; it is started the first time (the clock of the timer is enabled by HAL_TIM_Base_Init in main.c) */
static uint16_t delay_counter(void)
{
    if (!(TIM6->CR1 & TIM_CR1_CEN)) {
        TIM6->CR1 |= TIM_CR1_CEN;
    }

    return (uint16_t)TIM6->CNT;
}

#define TICKS_PER_US (SystemCoreClock / 1000000u)

#endif

void delay_us(uint32_t us)
{
    uint32_t left = us * TICKS_PER_US;
    uint16_t last = delay_counter();

    /* the counter turns round every 4 ms at 16 MHz, so it is looked at far more often than that: what passed since the last look is the difference of two readings */
    while (left > 0) {
        const uint16_t now = delay_counter();
        const uint16_t passed = (uint16_t)(now - last);

        if (passed >= left) {
            break;
        }
        left -= passed;
        last = now;
    }
}
