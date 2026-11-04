# Nozzle board firmware: hand-over for the firmware developer (work plan 0.5, 6.1 and 6.6)

One STM32F407 board per nozzle talks to the Smart210 cashier terminal over RS-485 (through an RS485-to-Ethernet converter that the terminal listens to). The board measures the fuel with a flow meter, runs the pump, and does what the terminal asks. **The interface is [PROTOCOL.md](PROTOCOL.md) (v0.4 plus the additions of section 11 there); it is the only thing the Smart210 side depends on.**

**Status, 2026-11-04: the firmware is written.** `firmware/` is the CubeIDE project (`firmware.ioc`) with the whole logic of the board, checked on the PC and compiled and linked with CubeIDE's ARM GCC (about 49 KB of flash). **It has never run on a board.** What is left for you is the hardware side and the bench test (work plan 6.6). Read in this order:

1. [firmware/README.md](firmware/README.md): the files, what you have to do, the timing, the memory.
2. [firmware/WORKFLOW.md](firmware/WORKFLOW.md): ten diagrams (the parts, start-up, the main loop, a request and its answer, the states, a fill, how a fill ends, the memory over a power cut, the display and keyboard pass, a manual fill typed on the keyboard).
3. [PROTOCOL.md](PROTOCOL.md) for the frames and the commands. [FIRMWARE_LOGIC.md](FIRMWARE_LOGIC.md) is the older design document: its status box at the top says what the code does differently.

## What the firmware does (all of it tested on the PC)

- Answers the terminal's requests (it never speaks first): INFO, POLL, SELECT (a beep), SET_PRESET, START, GET_RESULT, ACK_RESULT, GET_CONFIG, SET_CONFIG, SET_TIME, GET_TIME, SHOW_BALANCE (shows a card's balance), GET_TOTAL and RESET_TOTAL (everything the board has dispensed); frame `STX ADDR SEQ CMD LEN DATA CRC16/Modbus (lo, hi)`, 115200 8N1 half duplex. The bytes arrive by interrupt into a ring and the main loop finds the frames by STX and CRC; the answer is sent by DMA.
- Keeps the states IDLE, SET, DISPENSING, DONE, FAULT (no selected state). A fill is started by START with a `session_id` that the terminal makes (the board keeps it with the result and sends it back; it has no counter of its own).
- Runs a fill: converts the preset (grams) to counts with its config (density, counts per litre), runs the pump only while the gun is lifted, stops at the preset, when the tank is full or after the no-flow time. **It never gives more than the preset**: the pump is stopped by a compare interrupt of the flow meter's timer (TIM1) at the preset count, not by the main loop.
- Holds the result in the SPI flash (25Q32) until the terminal acknowledges it. A power cut in a fill comes back as a result with reason 2 (auto mode); a manual fill is dropped.
- **Polling rules that matter:** the terminal repeats the same `SEQ` until it gets a good answer; a board that sees the same `ADDR`+`SEQ` again does **not** run the command again but sends its last answer again (flags included). Key events (select key, clear key) are raised once in the answer to the next POLL.
- Two modes. **Auto:** the terminal sets the amount and starts (the gun is reported, the terminal answers with START). **Manual:** the supplier types the preset on the dispenser's own keyboard (digits, Dot, Set) and takes the gun up, and the board runs the fill by itself; it answers only POLL, INFO, the config, the time and the two total commands, reports no keys and no end of a fill, and holds no result.
- The dispenser's own keyboard has 17 keys (0 to 9, Dot, Next, Prev, Total, Clear, Set, Function). Function is the select key (auto mode), Set takes the typed amount (manual), Total shows the board's total for 5 s, Next and Prev are not used yet. LED1 is a heartbeat, LED2 to LED4 show the state, a fault blinks LED4.

## What you have to do (details in firmware/README.md)

1. **The display and keyboard drivers:** the functions of `display.h` and `keyboard.h`, in `display.c` (done 2026-11-04: see `Tests/test_display.c`) and `keyboard.c` (`UpdateDisplay(kg, litres, density)`, `RefreshDisplay()` about 4 ms, and `ScanKeyboardAndRefreshDisplay(code, value)` about 20 ms, which refreshes the 8-digit LCD of the keyboard board - the first digit shows the mode or status `code`, the other seven the `value` - and returns one key code per press). The project builds without them (the two files hold empty functions).
2. **`board_config.h`:** the address of the board, and the levels of the relays, the gun switch (`SWITCH`), POWER_OFF, the beeper and the LEDs: they are assumptions until you look at the board.
3. **The ROM:** the board keeps its state in the last 8 sectors (32 KB, from `0x3F8000`) of the 25Q32. If something else lives there, change `STORE_BASE`.
4. **Faults of the flow sensor and the valve:** the code only has the entry (`board_set_fault()`); what the hardware can detect is yours to wire (fault codes 1 flow sensor, 2 valve, 3 memory, 4 other).
5. **The serial number** (INFO) comes from the chip's unique id; check it is what you want.

## What the bench test has to settle (work plan 6.6)

- **The supply time after POWER_OFF** against the filter of the input: three picks, 10 ms apart, then the main loop (the board saves within about 60 ms of the cut). It has to cover the pump relay and one program of the flash (a few ms).
- **The gun switch** is filtered with ten picks, 10 ms apart (it is seen within about 130 ms): is that fast enough for the pump to stop when the gun is hung?
- **The pump overshoot** and `early_close_pulses` (0 now): measure how far past the preset a fill goes and set the lead in counts (one count is 1/`counts per litre`; the flow meter counts all four edges of A/B).
- **The 30 ms pass of the main loop:** the displays block it. A request is answered up to about 50 ms after it arrives (the terminal waits 100 ms), the SysTick keeps counting during the long flash calls (the pin picks are taken there).
- **The flash:** the chip id and the block protection are checked at start-up; a sector erase takes 30 to 400 ms, so the next sector is erased ahead in idle moments and the fill and the power cut only program. Check the wear plan (8 sectors, about 7 years at 1000 fills a day) fits.
- The four bench checks (a) to (d) listed near the top of PROTOCOL.md, and by hand: a lost answer (the same `SEQ` twice) for START, ACK_RESULT and a key event; a power cut in a fill; a fault in a fill.

## How to test without the terminal

`simulator/` is a PC program that plays the converter and six boards by the same protocol (`simulator/src/BoardSimulator.cpp`); the terminal's automated tests and the end-to-end tests (`e2e/`) run against it. The firmware's own tests run on the PC without any hardware: from `firmware/`, `sh Tests/run_tests.sh` (needs gcc): the protocol and the state machine (681 checks), the flash store (13,820), the displays and keys (215), the pin filter (94).

## Decisions made (the old open points)

- No-flow time 30 s after the first count; the gun on its hook for 3 s after the flow began ends the fill; no count at all within 10 minutes of START also ends it. The fill in progress is stored every 2 s and at once at POWER_OFF.
- The keyboard needs more keys than select: it has 17 (above); POLL flag bits 6 and 7 are still free, and no more were needed.
- **Tolerance:** the board has the only tolerance of the system, 0.03 kg (`preset_tolerance_g`): a fill that ends within it of the preset is reported as the preset reached (reason 0, `dispensed_g` = `preset_g`). The terminal and the web app have none (any shortfall there is a remainder ticket).
- A manual fill cut by the power is dropped (nothing is reported), but what it dispensed is added to the board's total.
- Firmware update in the field: not designed yet.

## Changes since protocol v0.3, in short

- **Gun up is reported:** the board never starts a fill by itself in auto mode; POLL carries GUN_UP (bit5).
- **Keys only report:** SELECT_KEY and CLEAR_KEY (bits 0 and 4) once; SELECT only beeps; no DESELECT and no CANCEL (a preset of zero is the cancel); the terminal sends SET_PRESET again after every scanned ticket.
- **Session id:** START carries a 4-byte `session_id` (never 0); GET_RESULT, ACK_RESULT and POLL return it.
- **SHOW_BALANCE (0x0E), GET_TOTAL (0x0F), RESET_TOTAL (0x10)** are new (PROTOCOL.md section 5). `INFO` says protocol 4, firmware 1.2.
- See PROTOCOL.md sections 4 to 6 and 11.

Please tell us anything in the protocol you want changed: it is easier to change the document and the simulator now than the firmware later.
