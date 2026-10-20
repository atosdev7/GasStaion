# Nozzle board firmware (STM32F407VET6)

The board of one nozzle: it talks to the Smart210 over RS-485 by [PROTOCOL.md](../PROTOCOL.md) v0.3, measures the fuel with the flow meter, runs the pump and keeps its state in the SPI flash. The CubeMX project is `firmware.ioc` (CubeIDE); the code of the board is in `Core/Src` and `Core/Inc`, in files that CubeMX does not touch (it keeps them when it generates again).

## The files

| File | What it is |
|---|---|
| `board.c/h`, `hal.h`, `checksum.c/h` | The core, with no STM32 code: frame parser, answers, state machine, both modes, the fill (counts, pump, hook, no flow), the held result, recovery after a power cut. `hal.h` is the table of what it needs from the hardware. |
| `store.c/h` | The state over a power cut: a ring of 64-byte records with CRC-32 in the SPI flash. A write that the power cut interrupts is ignored at the next start. |
| `ui.c/h` | What the display shows in each state, the select key, the entry of the amount in manual mode. |
| `w25q.c/h` | The 25Q32 on SPI3 (`ROM_CS`) as the flash of `Store`. |
| `port.c/h` | The board under the core: flow meter counter (TIM1), relays 1 and 2 (pump), the hook (`SWITCH`), RTC, serial number, RS-485 (USART6, receive interrupt, `RS485_RE`), beeper, LEDs. |
| `app.c/h` | `app_init()` and `app_loop()`, called from `main.c` (in its USER CODE regions). |
| `board_config.h` | The address of the board, the polarity of relays, hook, beeper and LEDs, where the state lives in the flash. |
| `ui_drivers.h`, `ui_drivers_weak.c` | **The four functions you write**: `ui_lcd_init`, `ui_lcd_clear`, `ui_lcd_print(row, col, text)`, `ui_key_read()`. The weak versions in `ui_drivers_weak.c` do nothing, so the project builds before yours are in; yours replace them. |

## What you have to do

1. Write the four functions of `ui_drivers.h` with your LCD and keyboard code. Key codes are in `ui.h` (`UI_KEY_0` to `UI_KEY_9`, point, enter, clear, select). Set `UI_LCD_ROWS` and `UI_LCD_COLS` in `ui.h` to the size of the display (2 x 16 now).
2. In `board_config.h`: the address of this board (`BOARD_ADDRESS`, now 1), and check the levels that switch the beeper and the LEDs on (`BEEP_ON_LEVEL`, `LED_ON_LEVEL`; the LEDs are assumed lit by a low level).
3. **The ROM:** the board writes its state into the last 8 sectors (32 KB, from `0x3F8000`) of the 25Q32. If the ROM holds anything else there (fonts?), change `STORE_BASE`. A region that holds data that is not ours is not touched: the board reports a memory fault (fault 3 in POLL). After checking that the region is free, set `STORE_AUTOFORMAT` to 1 for the first start and back to 0.
4. Calibrate: `pulses per litre` set from the terminal's Machine Config is in **counts of the TIM1 encoder**, which counts all four edges of A/B (4 counts per cycle).

## How a fill goes

- Auto: select key (or SELECT) -> the terminal sets the preset and sends START -> the pump runs **while the nozzle is lifted** (`SWITCH` low) -> the preset is reached, the pump stops, the result is held until ACK_RESULT.
- Manual: the supplier types the amount on the keyboard and presses ENTER -> the fill starts the same way.
- The fill ends by itself (result reason 1) when: no count for 30 s after the flow began; the nozzle is put back on its hook for 3 s after the flow began; or no count at all 10 minutes after START. The values are in `Params` (`board_default_params()`), to be tuned on the bench.
- The result of a fill that reached its preset says exactly the preset (the calibration decides what was dispensed); a fill that stopped early says what the counts say, never more than the preset.
- A power cut: the `POWER_OFF` input (read in the main loop) stops the pump at once and writes the counts so far; after the restart the fill is a result with reason 2.

## Tests on the PC

```
sh Tests/run_tests.sh      (needs gcc)
```

builds and runs `test_core.c` (the protocol, the examples of PROTOCOL.md section 7, the state machine, fills, power cuts), `test_store.c` (a fake NOR flash, a power cut at every byte of a write, wear) and `test_ui.c` (a fake LCD and keyboard).

## Build

Open the folder in STM32CubeIDE as an existing project and build. The same files were compiled and linked with the ARM GCC of CubeIDE against the generated HAL (about 19 KB of flash and 4 KB of RAM).
