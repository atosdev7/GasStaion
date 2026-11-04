#ifndef BOARD_CONFIG_H
#define BOARD_CONFIG_H

/* The settings of this board and its wiring that are not in CubeMX. Change them here. */

#include "main.h"

/* The address of this nozzle board on the RS-485 line (PROTOCOL.md: 1 to 247). The Smart210 takes the address as the nozzle number: 1 to 6. */
#define BOARD_ADDRESS 1

/* The relays are active high (low at start means off). Relays 1 and 2 together run the pump. */
#define RELAY_ON_LEVEL GPIO_PIN_SET

/* The SWITCH input is high with the nozzle on its hook and low when the nozzle is lifted. */
#define HOOK_LIFTED_LEVEL GPIO_PIN_RESET

/* POWER_OFF is high when the supply is cut. */
#define POWER_CUT_LEVEL GPIO_PIN_SET

/* The filters of the inputs (pinfilter.h). A timer (SysTick, every INPUT_PICK_MS) puts one pick of each pin into its buffer; the main loop looks at the
   buffer once in a pass (check_power_status, check_gun_status) and the state changes when the last picks all agree. The power input: POWER_PICKS picks (3 x 10 ms =
   30 ms). The switch of the gun: GUN_PICKS picks (10 x 10 ms = 100 ms). Both numbers are 1 to 32. */
#define INPUT_PICK_MS 10
#define POWER_PICKS 3
#define GUN_PICKS 10

/* Beeper and LEDs: the level that switches them on (check on the board: the LEDs of many boards are lit by a low level). */
#define BEEP_ON_LEVEL GPIO_PIN_SET
#define LED_ON_LEVEL GPIO_PIN_RESET

/*
 * Where the board keeps its state in the 25Q32 (4 MB): the last 8 sectors (32 KB). NOTHING ELSE may be stored there. If the ROM holds
 * other data (fonts, for example) in that area, choose another base address, a multiple of 4096.
 * STORE_AUTOFORMAT: 0 = a region that holds anything that is not ours makes the board report a memory fault (fault 3) and not touch
 * it; set 1 for the first start of a board whose region is free but not blank, then set it back to 0.
 */
#define STORE_BASE 0x3F8000UL
#define STORE_AUTOFORMAT 0

#endif
