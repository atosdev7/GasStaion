#ifndef PORT_H
#define PORT_H

/* The STM32F407 board under the nozzle-board core: the flow meter counter, the pump relays, the hook, the clock, the flash, the RS-485 line. */

#include "hal.h"
#include "ui.h"

#include <stddef.h>
#include <stdint.h>

/* Starts the flow counter, the receiver and the flash; fills the two tables for board_init() and ui_init(). */
void port_init(Hal *hal, UiHal *ui);

/* The next received byte of the RS-485 line, or -1 when there is none. */
int port_rx_byte(void);

/* The answer is copied and sent by DMA: the line driver is switched on, the bytes go out without the main loop, and the driver is switched off by the transmit-complete
   interrupt (after the last bit). Returns 1 when the answer is on its way, 0 when it could not be sent (the previous answer is still going, or the DMA refused): the
   master asks again. */
int port_send(const uint8_t *data, size_t len);

/* How many answers could not be sent since the start (for the bench). */
uint16_t port_tx_dropped(void);

#define PORT_TX_MAX 71   /* the longest frame: STX ADDR SEQ CMD LEN 64 data CRC CRC */

/* Takes a pick of the POWER_OFF input when 3 ms have passed since the last one and returns at once (power.h: 3 picks that agree change the status). Call it as
   often as the main loop can; it does nothing else. */
void check_power_status(void);

/* The buffered status: 1 while the last 3 picks of the POWER_OFF input said the supply is cut. The main loop reads it where the board can save its state. */
int port_power_cut(void);

/* Housekeeping that has a time: switches the beeper off when its time is over. */
void port_poll(void);

#endif
