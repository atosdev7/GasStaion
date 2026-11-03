# Nozzle board firmware (STM32F407VET6)

The board of one nozzle: it talks to the Smart210 over RS-485 by [PROTOCOL.md](../PROTOCOL.md) v0.4 (firmware 1.1: four states, the gun in POLL, keys that only report, SHOW_BALANCE, the session id of START), measures the fuel with the flow meter, runs the pump and keeps its state in the SPI flash. The CubeMX project is `firmware.ioc` (CubeIDE); the code of the board is in `Core/Src` and `Core/Inc`, in files that CubeMX does not touch (it keeps them when it generates again).

## The files

| File | What it is |
|---|---|
| `board.c/h`, `hal.h`, `checksum.c/h` | The core, with no STM32 code: frame parser, answers, state machine, both modes, the fill (counts, pump, hook, no flow), the held result, recovery after a power cut. `hal.h` is the table of what it needs from the hardware (it now has `beep`, for SELECT). |
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

- Auto: the supplier presses the select key (it only shows up as SELECT_KEY in POLL; the Smart210 answers with SELECT and the board **beeps**, nothing else changes) -> the Smart210 sets the preset (SET_PRESET, the board is SET and shows it) -> the gun is taken up (GUN_UP in POLL, a level) -> the Smart210 sends **START with the session id** (4 bytes, never 0) -> the board keeps the id in the memory with the fill, runs, and the pump runs **while the nozzle is lifted** (`SWITCH` low) -> the preset is reached, the pump stops, the result (with that id) is held until ACK_RESULT. The board **never starts an auto fill by itself** and has **no counter of fills**: the id is the Smart210's. A preset of zero is the cancel (SET_PRESET 0 in IDLE or SET); there are no DESELECT and CANCEL commands.
- The clear key is reported too (CLEAR_KEY); the Smart210 drops its queue and sets the preset to zero.
- **SHOW_BALANCE** (IDLE only, 4 bytes of grams, at most 9999.99 kg = `MAX_SHOWN_G`): the display shows the number (a card's balance) for 60 s (`Params.balance_show_ms`) or until SHOW_BALANCE 0, SET_PRESET or a fill; it is not a preset and arms nothing (`board_shown_grams()` is what the display reads).
- Manual: the supplier types the amount and presses ENTER -> the board is SET with that preset (`board_manual_amount`) -> when the gun is up the board starts, runs and ends the fill by itself. It reports no keys, **holds no result and reports no end of a fill** (back to IDLE, preset cleared), refuses SELECT, SET_PRESET, START and SHOW_BALANCE with WRONG_STATE and answers only POLL, INFO, the config and the time. The clear key drops an amount that waits for the gun. A power cut in the middle of a manual fill just drops the fill.
- The fill ends by itself (result reason 1) when: no count for 30 s after the flow began; the nozzle is put back on its hook for 3 s after the flow began; or no count at all 10 minutes after START. The values are in `Params` (`board_default_params()`), to be tuned on the bench.
- The result of a fill that reached its preset says exactly the preset (the calibration decides what was dispensed); a fill that stopped early says what the counts say, never more than the preset.
- A power cut: the `POWER_OFF` input (read in the main loop) stops the pump at once and writes the counts so far; after the restart an auto fill is a result with reason 2 (a manual fill is dropped).
- POLL answers 19 bytes: state, flags (SELECT_KEY, RESULT, FAULT, CLEAR_KEY, GUN_UP), fault, session id (while a fill runs or a result waits, else 0), grams, millilitres, and the preset (while SET, DISPENSING or DONE).

## Tests on the PC

```
sh Tests/run_tests.sh      (needs gcc)
```

builds and runs `test_core.c` (the protocol, the examples of PROTOCOL.md section 7, the state machine, fills, power cuts), `test_store.c` (a fake NOR flash, a power cut at every byte of a write, wear) and `test_ui.c` (a fake LCD and keyboard).

## Build

Open the folder in STM32CubeIDE as an existing project and build. The same files were compiled and linked with the ARM GCC of CubeIDE against the generated HAL (about 19 KB of flash and 4 KB of RAM).
