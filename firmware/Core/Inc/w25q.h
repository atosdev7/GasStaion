#ifndef W25Q_H
#define W25Q_H

/* The SPI NOR flash of the board (25Q32, SPI3, chip select ROM_CS) as the Flash of store.h. */

#include "store.h"
#include "main.h"

/* Wakes the chip up and reads its id. Returns 1 when a chip answers. */
int w25q_init(SPI_HandleTypeDef *spi);

const Flash *w25q_flash(void);

#endif
