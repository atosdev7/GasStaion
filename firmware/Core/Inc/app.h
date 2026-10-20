#ifndef APP_H
#define APP_H

/* The application of the nozzle board: main.c calls app_init() once after the peripherals are set up and app_loop() in its endless loop. */

void app_init(void);
void app_loop(void);

#endif
