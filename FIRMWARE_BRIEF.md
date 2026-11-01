# Nozzle board firmware: brief for the firmware developer (work plan 0.5 and 6.1)

One STM32F407 board per nozzle talks to the Smart210 cashier terminal over RS-485 (through an RS485-to-Ethernet converter that the terminal listens to). The board measures the fuel with a flow sensor, controls the valve, and does what the terminal asks. **The full interface is [PROTOCOL.md](PROTOCOL.md) (v0.4 draft); it is the only thing the Smart210 side depends on. [FIRMWARE_LOGIC.md](FIRMWARE_LOGIC.md) turns it into the logic the board runs, with diagrams (main loop, frame handler, command table, state machines, fill control, manual mode).**

## What the board does

- Answers the terminal's requests (it never speaks first): INFO, POLL, SELECT (a beep), SET_PRESET, START, GET_RESULT, ACK_RESULT, GET_CONFIG, SET_CONFIG, SET_TIME, GET_TIME (v0.3: the terminal sets the board's clock when it comes online and every hour; a battery-backed RTC is wanted); frame `STX ADDR SEQ CMD LEN DATA CRC16/Modbus (lo, hi)`, 115200 8N1 half duplex.
- Keeps a state machine: IDLE, SET, DISPENSING, DONE, FAULT (PROTOCOL.md section 4; v0.4 has no selected state).
- Runs a fill: converts the preset (grams) to pulses with its config (density, pulses per litre), opens the valve, counts pulses, stops at the preset, when the tank is full, or after the no-flow time. **It never gives more than the preset.**
- Holds the result (id, preset, dispensed grams and ml, reason) in flash until the terminal acknowledges it; the id of the fill is the `session_id` that the Smart210 sends with START (the board has no counter of its own). A power cut in the middle of a fill comes back as a result with reason 2 (interrupted) holding the last stored value.
- Keeps its config in flash: mode (auto/manual), fuel type, density, pulses per litre. Set only in IDLE.
- **Polling rules that matter:** the terminal repeats the same `SEQ` until it gets a good answer; a board that sees the same `ADDR`+`SEQ` again must **not run the command again but send its last answer again** (flags included). The terminal moves `SEQ` on when it gives a command up. Key events (select key, clear key) are raised once in the answer to the next POLL.
- Two modes: in **auto** the terminal sets the amount and starts (the gun is reported, the terminal answers with START); in **manual** the supplier types the preset on the dispenser's own keyboard and takes the gun up, and the board runs the fill by itself. A manual board only answers POLL (status, gun, preset, dispensed amount), SET_TIME and the config: it reports no key strokes and no end of a fill, holds no result, and refuses SELECT, SET_PRESET and START.

## How to test without the terminal

`simulator/` is a PC program that plays the converter and six boards by the same protocol. `simulator/src/BoardSimulator.cpp` is a working reference of the state machine and the answers (including the repeat rule, the NAK codes and both modes), and the terminal's automated tests plus the end-to-end tests (`e2e/`) run against it. If your board answers like the simulator, the terminal will work with it. The other way round, you can point a PC at the board with the same frames (PROTOCOL.md section 7 has hex examples with their CRCs).

## Open points we need from you (PROTOCOL.md section 8)

1. Is 115200 baud with the converter in transparent mode fast enough for the number of boards on one bus? (A 22-byte answer is about 2 ms; the terminal polls a board every 100-200 ms.)
2. The no-flow time (default 30 s) and how often the fill in progress is stored in flash for the power-cut case (flash wear against lost grams).
3. Whether the keypad needs more keys than select (bits 4-7 of the POLL flags are free).
4. Manual mode: nothing is reported about a manual fill after it ends; POLL answers carry `preset_g` (19 bytes now) for both modes.
5. How the flow sensor and valve are wired and what faults can be detected (fault codes 1 flow sensor, 2 valve, 3 memory, 4 other); how a fault is cleared.
6. Firmware update: how it is done in the field (the terminal does not update it).

## Suggested delivery

1. Frame parser, CRC and the answers that need no hardware (INFO, GET_CONFIG, SET_CONFIG with flash): check with the simulator's frames and the hex examples.
2. State machine and presets without a valve (simulated pulses): the terminal's start/result flow works against the board on a bench.
3. Real flow sensor and valve; calibration (pulses per litre) from the terminal's Machine Config.
4. Power-cut and fault behaviour; then the bench test with a real dispenser (work plan 6.6).

Please tell us anything in the protocol you want changed before you start: it is easier to change the document and the simulator now than the firmware later.

## Changes for protocol v0.4 (draft)

- **Gun up is reported:** the board never starts a fill by itself. It reports the state of the gun (hook) in every POLL answer as flag GUN_UP (bit5, a level). The Smart210 sends START when the gun is up and tickets are queued; with the queue cleared it sets the preset to zero instead (the board is IDLE). START is accepted in SET as before.
- **Keys only report:** the select key and the clear key do not change the board's state. The board raises SELECT_KEY or CLEAR_KEY (POLL flags bit0, bit4) once, in its next POLL answer. The Smart210 answers a SELECT_KEY with the SELECT command for that board's address; a CLEAR_KEY with SELECT again (to drop the preset).
- The Smart210 sends SET_PRESET again after every scanned ticket; the board must accept it in IDLE and SET and replace the old preset (as it does now).
- See PROTOCOL.md sections 4 to 6 and the sequence diagram.
- **No selected state:** the board has IDLE, SET, DISPENSING and DONE. SELECT (addressed to the board) only makes it beep: the state does not change and the next POLL answer is the same as before. The choice of the nozzle is kept by the Smart210. SET_PRESET is accepted in IDLE and SET; a preset of zero is the cancel (state IDLE). There are no DESELECT and CANCEL commands.
