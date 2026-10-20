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

/* Sends the answer: the line driver is switched on, the bytes go out, the line driver is switched off. Returns after the last bit. */
void port_send(const uint8_t *data, size_t len);

/* 1 while the POWER_OFF input says the supply is cut. */
int port_power_cut(void);

/* Housekeeping that has a time: switches the beeper off when its time is over. */
void port_poll(void);

#endif
