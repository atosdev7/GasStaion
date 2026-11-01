# Nozzle board simulator

A PC program that plays the RS485-to-Ethernet converter and the six nozzle boards (protocol v0.3, see [PROTOCOL.md](../PROTOCOL.md)), so the Smart210 side can be tried and tested without an STM32 board. Like the real converter it is a TCP **client**: it connects to the port the Smart210 listens on (Machine Config > Nozzles, default 4001) and connects again by itself after a drop.

## Build and run

Needs the desktop Qt (Qt 5.15 or newer) with Widgets and Network.

```
qmake simulator.pro
make            (mingw32-make on Windows)
nozzle-simulator
```

Enter the Smart210's address and port and press **Connect**. What the boards keep in flash (config, result counter, flow speed) is saved in `simulator.ini` next to the program, so a restart finds the boards as they were.

## What it shows and does

Six nozzle panels (boards 1 to 6, as configured on the Smart210). Each shows the board's state (IDLE, SELECTED, ARMED, DISPENSING, RESULT, FAULT), its config (mode, fuel type, density, pulses per litre), the fill running with its weight and volume, and the result it holds until the Smart210 acknowledges it. A change the Smart210 sends (SET_CONFIG, preset, start, ...) shows at once.

| Control | What it does |
|---|---|
| Three displays | Like the dispenser: **kg** (6 digits), **litres** (6 digits) and **density** (4 digits, ".8300"). The kg display shows what is typed, the weight of a fill (while it runs, and the result that is held), the preset, or a card's balance (SHOW_BALANCE); litres show the volume of the fill; density is the board's config ("----" for a new board) |
| Keypad | Digits 0 to 9 and the point type an amount (up to 6 digits, 2 after the point; manual mode only), **Enter** makes it the preset, **Clear** clears what is typed. **Select** and **Clear** are also the keys of an auto dispenser: the board reports them in its next POLL answer (SELECT_KEY, CLEAR_KEY), the Smart210 answers |
| Gun up / Gun down | The supplier takes the gun up (the board reports GUN_UP; the Smart210 sends START when tickets are queued; a manual board with a preset starts by itself) and puts it back on its hook; a fill that runs ends 3 seconds after the gun is down (reason 1), unless it is taken up again before |
| Stop fill | The fill ends early: the result is held (reason: tank full or no flow) |
| Flow (g/s) | How fast a fill runs |
| Fault | A flow sensor, valve, memory or other fault (a fill that runs ends with reason fault); "No fault" clears it |
| Power cut (3 s) | The board is dark for three seconds; a fill that ran comes back as a result with reason interrupted |
| Not answering | The board never answers (the Smart210 shows it offline) |
| Lose / Spoil next 3 answers | A bad bus: answers are not sent, or go out with a wrong CRC |
| Factory reset | The board forgets its config, like a new board |

Every board has a clock that the Smart210 sets with SET_TIME (a new board starts at 2000-01-01); the flag `supportsTime` of a board in the code makes it behave like an older v0.2 board that answers BAD_COMMAND.

The log lists every exchange (`Nozzle 1  <- SET_CONFIG  -> OK`); POLL is left out unless "Show POLL in the log" is on.

Auto mode: the Smart210 selects the nozzle, sets the amount and starts (the select key makes the Smart210 select it too). Manual mode: the Smart210 sends none of that; the board refuses it with WRONG_STATE, and the fill is started by typing the amount here.

## Code

`BoardSimulator` is the boards' logic (the state machine of PROTOCOL.md, no network) and `ConverterClient` is the TCP client. The Smart210 tests (`smart210/tests/FakeBoards.h`) use the same two classes, so the tests and the simulator behave the same way. `BoardProtocol` (frames, CRC, config) is the Smart210's own source file, shared.
