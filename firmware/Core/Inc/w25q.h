#ifndef W25Q_H
#define W25Q_H

/* The SPI NOR flash of the board (25Q32, SPI3, chip select ROM_CS) as the Flash of store.h. */

#include "store.h"
#include "main.h"

/* Wakes the chip up and checks it: the id must be a Winbond 32 Mbit chip (EFh .. 16h) and the status register must show no block protection (BP, TB, SEC). Returns 1
   when the chip is there and can be written; 0 otherwise (the board then reports a memory fault). Writes and erases wait until 10 ms after the start (the chip ignores them
   for tPUW after its power came on). */
int w25q_init(SPI_HandleTypeDef *spi);

const Flash *w25q_flash(void);

#endif
