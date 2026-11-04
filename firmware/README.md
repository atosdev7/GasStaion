# Nozzle board firmware (STM32F407VET6)

The board of one nozzle: it talks to the Smart210 over RS-485 by [PROTOCOL.md](../PROTOCOL.md) v0.4 (firmware 1.2: four states, the gun in POLL, keys that only report, SHOW_BALANCE, the session id of START), measures the fuel with the flow meter, runs the pump and keeps its state in the SPI flash. The CubeMX project is `firmware.ioc` (CubeIDE); the code of the board is in `Core/Src` and `Core/Inc`, in files that CubeMX does not touch (it keeps them when it generates again).

**Diagrams of how it works** (the parts, start-up, the main loop, a request and its answer, the states, a fill, how a fill ends, the memory over a power cut, the display and keyboard pass, and where the time goes): [WORKFLOW.md](WORKFLOW.md).

## The files

| File | What it is |
|---|---|
| `board.c/h`, `hal.h`, `checksum.c/h` | The core, with no STM32 code: frame parser, answers, state machine, both modes, the fill (counts, pump, hook, no flow), the held result, recovery after a power cut. `hal.h` is the table of what it needs from the hardware (it now has `beep`, for SELECT). |
| `pinfilter.c/h` | The filter of an input pin: one pick every so many ms, a number of picks that agree change the state (no hardware in it). Used for the POWER_OFF input (3 picks, 3 ms) and the hook switch of the gun (10 picks, 10 ms). |
| `store.c/h` | The state over a power cut: a ring of 64-byte records with CRC-32 in the SPI flash. A write that the power cut interrupts is ignored at the next start. |
| `ui.c/h` | What the four displays show in each state (kg, litres, density, and the keyboard board's display), the keys (Function and Clear are reported in auto mode; the digits, Dot and Set type and take the amount in manual mode; Total shows the total), and the pass of the main loop that scans the keyboard and refreshes the display board. |
| `w25q.c/h` | The 25Q32 on SPI3 (`ROM_CS`) as the flash of `Store`. |
| `port.c/h` | The board under the core: flow meter counter (TIM1), relays 1 and 2 (pump), the hook (`SWITCH`), RTC, serial number, RS-485 (USART6: receive interrupt into a ring, transmit by DMA, `RS485_RE`), beeper, LEDs. |
| `app.c/h` | `app_init()` and `app_loop()`, called from `main.c` (in its USER CODE regions). |
| `board_config.h` | The address of the board, the polarity of relays, hook, beeper and LEDs, where the state lives in the flash. |
| `ui_drivers.h`, `ui_drivers_weak.c` | **The three functions of your display and keyboard drivers** (only declared here): `UpdateDisplay(kg, litres, density, keypad)` stores the texts of the four displays, `RefreshDisplay()` sends them to the display board (2 x 6 digits and 1 x 4 digits; blocks 8 to 10 ms), `ScanKeyboardAndRefreshDisplay()` scans the keys and refreshes the keyboard board's own display of 6 to 8 digits (blocks about 20 ms) and returns a `UI_KEY_*` code for a new press. The weak versions in `ui_drivers_weak.c` do nothing, so the project builds before yours are in; yours replace them. |

## What you have to do

1. Your display and keyboard drivers must provide the three functions of `ui_drivers.h`. The text of a display has one character per digit, right aligned (a `.` after a digit lights its point, it takes no digit); the characters are `0`-`9`, blank, `-` and the letters `E`, `r`, `b`, `A`, `L` ("Err", "bAL"). Key codes are in `ui.h` (`UI_KEY_0` to `UI_KEY_9`, point, enter, clear, select). `UI_KEYPAD_DIGITS` in `ui_drivers.h` is 8: set it to the digits of the keyboard board's display.
2. In `board_config.h`: the address of this board (`BOARD_ADDRESS`, now 1), and check the levels that switch the beeper and the LEDs on (`BEEP_ON_LEVEL`, `LED_ON_LEVEL`; the LEDs are assumed lit by a low level).
3. **The ROM:** the board writes its state into the last 8 sectors (32 KB, from `0x3F8000`) of the 25Q32. If the ROM holds anything else there (fonts?), change `STORE_BASE`. A region that holds data that is not ours is not touched: the board reports a memory fault (fault 3 in POLL). After checking that the region is free, set `STORE_AUTOFORMAT` to 1 for the first start and back to 0.
4. Calibrate: `pulses per litre` set from the terminal's Machine Config is in **counts of the TIM1 encoder**, which counts all four edges of A/B (4 counts per cycle).

## How a fill goes

- Auto: the supplier presses the select key (the **Function** key of the keyboard) (it only shows up as SELECT_KEY in POLL; the Smart210 answers with SELECT and the board **beeps**, nothing else changes) -> the Smart210 sets the preset (SET_PRESET, the board is SET and shows it) -> the gun is taken up (GUN_UP in POLL, a level) -> the Smart210 sends **START with the session id** (4 bytes, never 0) -> the board keeps the id in the memory with the fill, runs, and the pump runs **while the nozzle is lifted** (`SWITCH` low) -> the preset is reached, the pump stops, the result (with that id) is held until ACK_RESULT. The board **never starts an auto fill by itself** and has **no counter of fills**: the id is the Smart210's. A preset of zero is the cancel (SET_PRESET 0 in IDLE or SET); there are no DESELECT and CANCEL commands.
- The **Clear** key is reported too (CLEAR_KEY); the Smart210 drops its queue and sets the preset to zero.
- **SHOW_BALANCE** (IDLE only, 4 bytes of grams, at most 9999.99 kg = `MAX_SHOWN_G`): the display shows the number (a card's balance) for 60 s (`Params.balance_show_ms`) or until SHOW_BALANCE 0, SET_PRESET or a fill; it is not a preset and arms nothing (`board_shown_grams()` is what the display reads).
- Manual: the supplier types the amount and presses **Set** -> the board is SET with that preset (`board_manual_amount`) -> when the gun is up the board starts, runs and ends the fill by itself. It reports no keys, **holds no result and reports no end of a fill** (back to IDLE, preset cleared), refuses SELECT, SET_PRESET, START and SHOW_BALANCE with WRONG_STATE and answers only POLL, INFO, the config and the time. The clear key drops an amount that waits for the gun. A power cut in the middle of a manual fill just drops the fill.
- The fill ends by itself (result reason 1) when: no count for 30 s after the flow began; the nozzle is put back on its hook for 3 s after the flow began; or no count at all 10 minutes after START. The values are in `Params` (`board_default_params()`), to be tuned on the bench.
- The result of a fill that reached its preset says exactly the preset (the calibration decides what was dispensed); a fill that stopped early says what the counts say, never more than the preset.
### The LEDs

LED1 (PE12) is the **heartbeat**: one second on and one second off, driven by the time the main loop gives to the UI, so it stops when the main loop hangs. LED2 to LED4 show the state: **LED2** a preset waits (SET), **LED3** a fill runs (DISPENSING), **LED4** a result waits (DONE, steady); a **fault** makes LED4 blink (about 2 Hz). All off in IDLE.

### The keyboard (17 keys)

The keyboard driver returns one code per press (`ui.h`): `UI_KEY_0` .. `UI_KEY_9`, `UI_KEY_POINT` (Dot), `UI_KEY_NEXT`, `UI_KEY_PREV`, `UI_KEY_TOTAL`, `UI_KEY_CLEAR`, `UI_KEY_SET`, `UI_KEY_FUNCTION`. A key beeps shortly when it is pressed.

| Key | Auto mode | Manual mode |
|---|---|---|
| 0 to 9, Dot | nothing | IDLE: types the amount in kg on the keypad display (at most 3 digits before the point, 2 after, one point); a key that does not fit is ignored |
| Clear | reported (CLEAR_KEY) | IDLE with an amount typed: removes the last character; IDLE with nothing typed: nothing; SET (the amount waits for the gun): drops the amount |
| Set | nothing | IDLE: takes the typed amount as the preset (long beep, the keypad clears, the board is SET; with nothing typed or 0: "Err" on the kg display for 1.5 s). The fill starts when the gun is up |
| Function | the select key: only beeps and is reported (SELECT_KEY) | nothing |
| Total | IDLE or SET: shows the total of the board for 5 s (below) | IDLE or SET: the same |
| Next, Prev | not used yet | not used yet |

In manual mode no key does anything while the fill runs (the gun down stops the pump, the gun up goes on) and in FAULT.

**The total (Total key, both modes; not while a fill runs, a result waits or the board is in FAULT):** the board keeps every gram it has dispensed (`Persist.total_g`: all fills, both modes, 48 bits in the flash record, added when a fill ends; a fill cut by the power adds what its last save of the count had). The Total key shows it in **whole kg** as one number of 12 digits over the two numeric displays: the kg display holds the upper six digits (blank while the total is shorter), the litres display the lower six (with zeros in front when the upper digits are lit). It goes back to the usual display after 5 s, with any other key and when the state changes (the gun up). The keys of the dispenser cannot reset it. It is read with **GET_TOTAL** and reset with **RESET_TOTAL** (0x0F and 0x10, 8 bytes of grams; the reset answers the value it had, is refused with WRONG_STATE while a fill runs and with FAULT in FAULT, and a manual board answers both): the Smart210 does it from Machine Config (firmware 1.2). **A flash record written by older firmware (before 2026-11-03) has another layout: the flags `mode`, `held`, `active` and `reason` are in one byte now, and the board must be started with an erased flash.**

- A power cut: the `POWER_OFF` input (picked by `check_power_status()`, see the timing section) stops the pump at once and writes the counts so far; after the restart an auto fill is a result with reason 2 (a manual fill is dropped).
- POLL answers 19 bytes: state, flags (SELECT_KEY, RESULT, FAULT, CLEAR_KEY, GUN_UP), fault, session id (while a fill runs or a result waits, else 0), grams, millilitres, and the preset (while SET, DISPENSING or DONE).

## Tests on the PC

```
sh Tests/run_tests.sh      (needs gcc)
```

builds and runs `test_core.c` (the protocol, the examples of PROTOCOL.md section 7, the state machine, fills, power cuts), `test_store.c` (a fake NOR flash, a power cut at every byte of a write, wear) and `test_ui.c` (a fake LCD and keyboard).

## Build

Open the folder in STM32CubeIDE as an existing project and build. The same files were compiled and linked with the ARM GCC of CubeIDE against the generated HAL (about 19 KB of flash and 4 KB of RAM).

## Time that the display and keyboard calls take

`ui_tick` calls `ScanKeyboardAndRefreshDisplay()` (about 20 ms) and `RefreshDisplay()` (8 to 10 ms) at every pass of the main loop, and both block, so a pass takes about 30 ms. Two things follow, to be dealt with on the bench (work plan 6.6):

- **The answer to a request** goes out from the main loop, so a request that arrives while a call blocks is answered up to about 20 ms late (the bytes are not lost: the receive interrupt keeps them). PROTOCOL.md says 20 ms and the Smart210 waits 100 ms, so it works, but the 20 ms of the document is not kept; either the document says 30 ms or the display work moves out of the way of the line.
- **The power status and the hook** are picked by `check_power_status()` and `check_hook_status()` (`port.c`; the filter is `pinfilter.c`). They never wait: each call takes one pick of its pin only when the pick time has passed since the last pick, and the last picks decide - when all of them agree the state becomes what they say, a mixed set leaves the state as it was (a glitch changes nothing). POWER_OFF: 3 picks, 3 ms apart. The hook switch: 10 picks, 10 ms apart (10 picks per 100 ms). `app_loop` calls each of them once at the top of a pass, and the board reads the buffered state (`port_power_cut()`, `Hal.hook`), so the state is only acted on at that place of the main loop. **But a pass takes about 30 ms, so the real time between two picks is one pass, not 3 or 10 ms:** a cut is seen after 3 passes (60 to 90 ms) and a gun that is lifted or hung after 10 passes (about 300 ms; the pump follows the hook, so a gun that is hung keeps the pump running for that long). To get the 3 ms and 10 ms the check must be called more often than once per pass (for example between the blocking display calls), or the number of picks made smaller; to be decided with the bench figures (the supply time after POWER_OFF, how fast the pump must stop when the gun is hung).
- **The end of a fill** is decided in `board_tick`, which now runs about every 30 ms. At a flow of F counts per second the pump stops up to F x 0.03 counts late (at 1000 counts per litre and 0.5 L/s that is 15 counts, 15 ml, about 12 g) and the fill goes past its preset by that: the station never accepts that (the board must never give more than the preset). **The pump is stopped by an interrupt, not by the main loop:** the compare channel 3 of TIM1 (CH1/CH2 are the encoder inputs; the compare needs no pin) is set to `start count + target - early_close_pulses`, and `TIM1_CC_IRQHandler` in `port.c` switches the relay off the moment the counter gets there, whatever the main loop is doing (the core arms it at START and again at every tick, `Hal.arm_stop`; `board_tick` then only finishes the result). The counter is 16 bit, so a stop more than 30000 counts ahead is armed in the compare when it comes near; the widening to 32 bit runs with the interrupts off because the main loop and the interrupt both read it. The POWER_OFF input is **not** an interrupt on purpose (user, 2026-11-03): the board saves its state at a known place of the main loop, where it is not busy, and an interrupt would cut in anywhere (in the middle of a flash write or of a change of the state). It is picked by `check_power_status()` instead (next paragraph).

## The RS-485 line

The USART6 receive interrupt (`HAL_UART_RxCpltCallback` in `port.c`) puts each byte into a circular ring (512 bytes: it must hold what the line carries while the main loop is busy with the display for about 30 ms) and arms the receiver again. The main loop takes the bytes out of the ring one at a time into `board_feed`, which finds a packet by its STX, its length and its CRC-16 at any offset of the buffer (a stray STX in front of a good frame does not hide it) and answers a frame for this address at once. A half frame is dropped after `rx_gap_ms` (10 ms) of quiet; a pass of the main loop that took bytes does not count as quiet time (`rx_fresh`), so a frame that was cut at the end of the ring is completed by the next pass. A full ring drops the byte and the master asks again.

**Sending** is by DMA (`port_send` in `port.c`): the answer is copied into the port's own buffer (the core reuses its answer buffer for the next frame), the driver of the line is switched on (`RS485_RE`, RE and DE are one pin) and `HAL_UART_Transmit_DMA` starts the transfer; the main loop goes on at once. The transmit-complete interrupt (`HAL_UART_TxCpltCallback`, which comes after the last bit has left the shift register) switches the driver off. USART6_TX is **DMA2 stream 6, channel 5** (stream 7 is the other choice of the chip); `port_tx_dma_init()` sets it up and `DMA2_Stream6_IRQHandler` is in `port.c`, not in the files that CubeMX writes, so a regeneration of the project keeps them (the CubeMX project `firmware.ioc` does not list this DMA). `port_send` returns 0 when the previous answer is still going or the DMA refuses; the answer is then not sent, the master asks again and a repeated request gets the same answer again (`port_tx_dropped()` counts them, for the bench). The whole project (the HAL and the core) was built and linked with the ARM GCC of CubeIDE for this: 19,992 bytes of flash, 4,476 bytes of RAM.

## The memory (the 25Q32 on SPI3)

The state of the board (config, the fill in progress, the result that waits and the id of the last acknowledged one) is kept in the 25Q32 as a ring of 64-byte records with a CRC-32 (`store.c`); at power-on `board_init` reads the newest good record back and recovers a fill that a power cut interrupted. Time matters here (the datasheet of the W25Q32BV, Rev I): a 64-byte program takes 0.2 ms typically and 0.8 ms at most (20 us for the first byte and 2.5 us for each one after it; a whole 256-byte page 0.7 ms typically, 3 ms at most) but **a 4 KB sector erase takes 30 ms typically and 200 ms at most, 400 ms once the sector has had 50,000 cycles**, and the supply does not last that long after POWER_OFF, and an erase in the middle of a fill would hold the main loop (the pump stop and the answers) for as long. So:

- **The sector that the ring enters next is erased ahead of time**, in an idle moment: `board_idle_work()` (called every pass of the main loop; it does something only in IDLE and DONE, never while the supply is going) lets the port run `store_erase_ahead()`, which erases that sector when it is not blank (it checks the whole sector: an erase that was cut short leaves bytes in the middle). It costs one long pass in 64 saves and nothing otherwise (no SPI traffic when the sector is ready).
- **The chip is checked at the start** (`w25q_init`): the JEDEC id must be Winbond (EFh), 32 Mbit (16h), and the status register must show no block protection (a protected block ignores a write without a word); otherwise the board reports the memory fault (fault 3) and does not work, instead of starting as a new board with no config. **Writes wait until 10 ms after the start** (the chip ignores writes for tPUW, up to 10 ms, after its power came on).
- **Wear:** a sector is good for 100,000 erase cycles and the ring erases each of its sectors once in 64 records (the 8 sectors of 32 KB hold 512). A fill saves every 2 s plus about 5 records for START, the end and the acknowledgement, so a fill of 30 s is about 20 records: with 1000 such fills a day that is 20,000 records, 39 erases for each sector a day, 100,000 cycles in about 7 years. A ring of 64 sectors (256 KB, `STORE_SECTORS`) makes it 8 times longer; it needs a region of the ROM that holds nothing else.
- **Hardware** (datasheet section 8.3 and the pin descriptions): /CS must follow VCC while the supply comes up and goes down (a pull-up on /CS to VCC), and /WP and /HOLD must be tied to VCC (or driven high) when the chip is used in the plain SPI mode: a floating pin can stop the chip or protect it.
- **The fill in progress** (every 2 s) **and the power cut** are saved with `store_save_noerase()`: a program of a blank slot, never an erase. When the sector was not prepared that save is refused and nothing is written: the last record stays the state (a fill then comes back with the counts of the save before) and it is not a fault.
- **START, a config, the end of a fill and an acknowledgement** use `store_save()`, which still erases when it must (the pump is stopped or not yet running at those moments; the answer may be late).
- The newest record is never in the sector that is erased ahead, so a power cut in the middle of an erase leaves the state intact.

How long the supply lasts after POWER_OFF is the number to check on the bench: it has to cover the pump relay and one program (a few milliseconds), and the POWER_OFF input is still read in the main loop (a pass can take about 30 ms, see above; the end of a fill is already stopped by the TIM1 compare interrupt).
