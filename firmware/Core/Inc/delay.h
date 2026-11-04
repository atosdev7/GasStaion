#ifndef DELAY_H
#define DELAY_H

/*
 * A wait of microseconds, for the whole firmware (the display and the keyboard drivers use it, and so can anything else that is called from the main loop).
 *
 * It counts the free running 16 bit counter of TIM6 (main.c sets TIM6 up with no prescaler, so it counts at the timer clock, 16 MHz on the HSI; delay.c starts it the
 * first time it is used). Nothing else may use TIM6. TIM7 is set up the same way and stays free. The wait does not stop the interrupts: it can be longer than asked
 * by the time of an interrupt that runs in it (the SysTick, the receive of the RS-485), never shorter. It works for any length (a long wait counts the turns of the
 * counter) but it BLOCKS, so use it for the few microseconds of a serial link and HAL_Delay for milliseconds.
 */

#include <stdint.h>

void delay_us(uint32_t us);

#endif
