# Nozzle board firmware: workflow diagrams

How the firmware in `Core/Src` works, from the start of the program to a fill and a power cut. The diagrams follow the code (firmware 1.1, protocol v0.4 with SHOW_BALANCE and the session id of START). The protocol itself is in [../PROTOCOL.md](../PROTOCOL.md), the files in [README.md](README.md).

## 1. The parts and who calls whom

```mermaid
flowchart LR
    S210["Smart210<br/>(master)"] <-->|"RS-485, 115200 baud<br/>converter, transparent"| LINE["USART6<br/>RS485_RE pin"]
    LINE -->|"receive interrupt<br/>one byte"| RING["receive ring<br/>512 bytes (port.c)"]
    RING -->|"port_rx_byte()"| LOOP["main loop<br/>app_loop() (app.c)"]
    LOOP -->|"board_feed(byte)"| CORE["board core (board.c)<br/>frame parser, answers,<br/>state machine, fill"]
    CORE -->|"answer"| TX["port_send()<br/>copy + DMA2 stream 6"]
    TX --> LINE
    CORE <-->|"Hal table (hal.h)"| PORT["port.c"]
    PORT --- TIM["TIM1 encoder<br/>flow meter counts"]
    PORT --- PUMP["relays 1 and 2<br/>pump"]
    PORT --- HOOK["hook switch<br/>(the gun)"]
    PORT --- RTC["RTC"]
    PORT --- STORE["store.c + w25q.c"]
    STORE --- ROM[("25Q32 on SPI3<br/>ring of records")]
    LOOP -->|"ui_tick()"| UI["ui.c<br/>what the displays show,<br/>the keys"]
    UI --- DRV["your drivers<br/>UpdateDisplay<br/>RefreshDisplay<br/>ScanKeyboardAndRefreshDisplay"]
    PORT --- POFF["POWER_OFF input"]
```

## 2. Start-up

```mermaid
flowchart TD
    A["reset"] --> B["HAL_Init, clocks, GPIO, SPI3, TIM1, USART6<br/>(CubeMX code, main.c)"]
    B --> C["app_init()"]
    C --> D["port_init(): pump off, flow counter on,<br/>DMA for USART6_TX, receive interrupt armed"]
    D --> E["w25q_init(): wake the chip, check the id<br/>(Winbond 32 Mbit) and the block protection"]
    E -- "not there or protected" --> F["flash_ok = 0<br/>the memory reads as damaged"]
    E -- ok --> G["store_init(), flash_ok = 1"]
    F --> H["board_init(): load the memory"]
    G --> H
    H -- "damaged (-1)" --> I["FAULT, fault code 3<br/>the board does not work"]
    H -- "no record (0): a new board" --> J["not configured, IDLE"]
    H -- "record found (1)" --> K{"a fill was running<br/>when the power went?"}
    K -- "auto" --> L["held as a result,<br/>reason 2 (interrupted)<br/>state DONE"]
    K -- "manual" --> M["dropped, IDLE"]
    K -- no --> N{"a result waits?"}
    N -- yes --> O["state DONE"]
    N -- no --> P["state IDLE"]
    J --> Q["ui_init(): first texts to the displays"]
    I --> Q
    L --> Q
    M --> Q
    O --> Q
    P --> Q
    Q --> R["main loop"]
```

## 3. One pass of the main loop

```mermaid
flowchart TD
    A["app_loop()"] --> B{"a byte in<br/>the ring?"}
    B -- yes --> C["board_feed(byte)"]
    C --> D{"a frame for this<br/>address was answered?"}
    D -- yes --> E["port_send(): copy the answer,<br/>driver on, DMA starts"]
    D -- no --> B
    E --> B
    B -- no --> F{"power status CUT?<br/>(check_power_status():<br/>the last 3 picks of the 10 ms timer agree)"}
    F -- yes --> G["board_power_fail():<br/>pump off, one save without erase"]
    F -- no --> H["board_power_restored()"]
    G --> I{"milliseconds<br/>passed?"}
    H --> I
    I -- no --> A
    I -- yes --> J["board_tick(elapsed)<br/>half frame timeout, balance timer,<br/>manual start with the gun, the fill"]
    J --> K["board_idle_work()<br/>erase the next flash sector<br/>(only IDLE or DONE)"]
    K --> L["ui_tick(elapsed)<br/>scan keys 20 ms, displays, refresh 8-10 ms"]
    L --> M["port_poll(): beeper off when its time is over"]
    M --> A
```

## 4. A request and its answer

```mermaid
sequenceDiagram
    participant M as Smart210
    participant I as USART6 interrupt
    participant R as ring (512)
    participant L as main loop
    participant B as board core
    participant D as DMA + TC interrupt
    M->>I: request frame (STX ADDR SEQ CMD LEN DATA CRC)
    I->>R: each byte into the ring, receiver armed again
    L->>R: port_rx_byte()
    L->>B: board_feed(byte)
    Note over B: finds a frame by STX, LEN and CRC-16<br/>at any offset, other addresses are ignored
    alt same ADDR and SEQ as the last request
        B-->>L: the last answer again (nothing runs twice)
    else new request
        B->>B: execute(cmd): state checks, then the work
        B-->>L: answer or NAK (error, cmd)
    end
    L->>D: port_send(): copy, RS485_RE high, HAL_UART_Transmit_DMA
    D-->>M: the bytes go out, the main loop goes on
    D->>D: transmit complete: RS485_RE low, receiver listens again
```

## 5. The states of the nozzle

```mermaid
stateDiagram-v2
    [*] --> IDLE
    IDLE --> SET: SET_PRESET (not 0)
    SET --> IDLE: SET_PRESET 0 (the cancel)
    SET --> DISPENSING: START with the session id (auto mode)
    DISPENSING --> DONE: the fill ends, the result is held (auto mode)
    DONE --> IDLE: ACK_RESULT with that session id
    IDLE --> IDLE: SHOW_BALANCE (the display shows a number for 60 s)
    IDLE --> SET: Set key at the keypad (manual mode, the amount is the preset)
    SET --> DISPENSING: the gun is up (manual mode, the board starts by itself)
    DISPENSING --> IDLE: the fill ends, nothing is held (manual mode)
    SET --> IDLE: CLEAR key (manual mode, drops the amount)
    IDLE --> FAULT: a fault is raised
    FAULT --> IDLE: the fault is gone
    DISPENSING --> DONE: a fault ends the fill (reason 3)
    DONE --> FAULT: ACK_RESULT while the fault stays
    note right of IDLE
        SELECT only beeps. The select and clear keys
        only show in the next POLL (SELECT_KEY, CLEAR_KEY).
        The state never changes by a key.
    end note
```

## 6. A fill, auto mode

```mermaid
sequenceDiagram
    participant S as Smart210
    participant B as board
    participant P as pump relays
    participant F as flow meter (TIM1)
    participant M as memory (25Q32)
    Note over B: supplier presses SELECT key
    S->>B: POLL
    B-->>S: flag SELECT_KEY, IDLE
    S->>B: SELECT
    B-->>S: OK, the board beeps
    S->>B: SET_PRESET grams (after every scan)
    B-->>S: OK, state SET, the preset is shown
    Note over B: the gun is taken up
    S->>B: POLL
    B-->>S: flag GUN_UP (a level)
    S->>B: START session_id (a number made by the Smart210)
    B->>M: save the fill and its session id (may erase)
    B->>P: pump on (only while the gun is up)
    B-->>S: OK, session_id
    loop every board_tick
        F-->>B: counts since START
        B->>B: target reached? no flow for 30 s? gun down for 3 s?
        B->>M: every 2 s: the counts so far (no erase)
    end
    B->>P: pump off, count the last pulses (settle)
    B->>M: hold the result (session id, preset, grams, ml, reason)
    S->>B: POLL
    B-->>S: state DONE, flag RESULT, session_id
    S->>B: GET_RESULT
    B-->>S: session_id, preset, grams, ml, reason
    Note over S: the supply is saved in the Smart210 first
    S->>B: ACK_RESULT session_id
    B->>M: forget the result
    B-->>S: OK, state IDLE
```

## 7. How a fill ends

```mermaid
flowchart TD
    A["board_tick -> run_fill"] --> B["counts = TIM1 counter - counter at START<br/>(a backflow takes counts off, never below 0)"]
    B --> C["pump on only while the gun is up"]
    C --> D{"counts reached<br/>target - early close?"}
    D -- yes --> E["reason 0: preset reached"]
    D -- no --> F{"gun down for 3 s<br/>after the flow began?"}
    F -- yes --> G["reason 1: stopped"]
    F -- no --> H{"no count for 30 s<br/>after the flow began?"}
    H -- yes --> G
    H -- no --> I{"no count at all<br/>10 min after START?"}
    I -- yes --> G
    I -- no --> J{"a fault raised?"}
    J -- yes --> K["reason 3: fault"]
    J -- no --> L{"every 2 s"}
    L --> M["save the counts (no erase)"]
    M --> A
    E --> N["pump off<br/>(begin_closing)"]
    G --> N
    K --> N
    N --> O["count the pulses that still come<br/>until quiet for 200 ms (at most 1 s)"]
    O --> P{"reason 0?"}
    P -- yes --> Q["the result is exactly the preset"]
    P -- no --> R["the result is what the counts say,<br/>never more than the preset"]
    Q --> S["hold the result in the memory"]
    R --> S
    S --> T["auto: state DONE, wait for ACK_RESULT<br/>manual: state IDLE, nothing held"]
```

## 8. The memory over a power cut

```mermaid
flowchart TD
    subgraph run["While the board runs"]
        A["IDLE or DONE, nothing waits"] --> B["board_idle_work()"]
        B --> C{"the sector the ring needs<br/>next is blank?"}
        C -- no --> D["store_erase_ahead():<br/>erase it (30-200 ms)"]
        C -- yes --> E["nothing to do"]
        F["a fill runs"] --> G["every 2 s: store_save_noerase()<br/>a program of 64 bytes (0.2-0.8 ms)"]
    end
    subgraph cut["POWER_OFF goes high"]
        H["pump off at once"] --> I{"a fill is running?"}
        I -- yes --> J["store_save_noerase():<br/>the counts so far"]
        I -- no --> K["nothing to write"]
    end
    subgraph up["Power on"]
        L["w25q_init: id, protection,<br/>writes wait 10 ms"] --> M["store_load(): read the 512 slots,<br/>the newest record with a good CRC-32"]
        M --> N{"a fill was in progress?"}
        N -- yes --> O["auto: a result with reason 2<br/>manual: dropped"]
        N -- no --> P["the state as it was"]
    end
    G -.->|"the power goes"| H
    J -.->|"the supply is gone"| L
    K -.-> L
```

A record that a power cut interrupts has a wrong CRC and is ignored, so the previous record is the state. The newest record is never in the sector that is erased ahead.

## 9. A pass of the user interface

```mermaid
flowchart TD
    A["ui_tick(elapsed)"] --> B["ScanKeyboardAndRefreshDisplay(code, value)<br/>about 20 ms, returns a key code"]
    B --> C{"a key?"}
    C -- "select" --> D["board_key_select(): only the event<br/>SELECT_KEY for the next POLL"]
    C -- "clear" --> E{"auto mode?"}
    E -- yes --> F["board_key_clear(): event CLEAR_KEY"]
    E -- "manual, something typed" --> G["delete the last character"]
    E -- "manual, nothing typed or SET" --> H["board_key_clear(): drop the amount that waits"]
    C -- "digit or point" --> I{"manual and IDLE?"}
    I -- yes --> J["add to the amount (3 digits, 2 decimals)"]
    I -- no --> K["ignored"]
    C -- "enter" --> L{"an amount, board configured?"}
    L -- yes --> M["board_manual_amount(): state SET"]
    L -- no --> N["Err on the kg display for 1.5 s"]
    D --> O["compose the four texts"]
    F --> O
    G --> O
    H --> O
    J --> O
    K --> O
    M --> O
    N --> O
    C -- none --> O
    O --> P{"a text of the display board changed?"}
    P -- yes --> Q["UpdateDisplay(kg, litres, density)"]
    P -- no --> R["LEDs"]
    Q --> R
    R --> S["RefreshDisplay()<br/>8 to 10 ms"]
```

| Display | Shows |
|---|---|
| kg (6 digits) | 0.00 idle, the balance (SHOW_BALANCE), the preset (SET), the kg so far, the kg of the result, `Err` |
| litres (6 digits) | 0.00, `bAL` while a balance shows, the litres so far, the fault code |
| density (4 digits) | the density of the config, `0.830`; `----` when never configured |
| keyboard board (8 digits) | the amount being typed (manual), the preset while SET and DISPENSING |

## 10. A fill, manual mode (the amount typed on the keyboard)

The supplier does everything at the dispenser; the Smart210 only watches. The first digit of the keyboard's LCD (`code`) shows where the board is: `H` idle, `P` a preset waits, `F` a fill runs (`t` while the Total key shows the total, `E` for a fault); the other seven digits (`value`) show the amount.

```mermaid
flowchart TD
    A["Idle, manual board<br/>LCD: H and blank"] --> B["Type the amount<br/>digits and Dot, Clear takes the last one off<br/>at most 3 digits before the point, 2 after<br/>LCD: H and the typed text"]
    B --> C{"Set key"}
    C -- "nothing typed or zero" --> X["Err on the kg display for 1.5 s<br/>nothing changes"]
    X --> B
    C -- "an amount" --> D["Preset waits, state SET<br/>long beep, the typed text is cleared<br/>LCD: P and the preset"]
    D -- "Clear key" --> A
    D -- "gun up, 10 picks agree, about 100 ms" --> E["Fill runs, state DISPENSING<br/>pump on while the gun is up<br/>LCD: F and the preset, kg and litres count up"]
    E -- "gun hung" --> P["Pump stops, the fill waits<br/>3 s on the hook after the flow began ends it"]
    P -- "gun up again" --> E
    P -- "3 s on the hook" --> F
    E -- "preset reached, TIM1 compare stops the pump<br/>or no flow 30 s, tank full, fault" --> F["Fill ends<br/>grams added to the total of the board<br/>no result held, nothing reported<br/>preset cleared"]
    F --> A
    T["Total key, idle or preset waits<br/>kg and litres show the total for 5 s<br/>LCD: t"] -.-> B
```

- No key does anything while a fill runs. There is no cancel: the supplier stops by hanging the gun.
- A power cut in the middle of a manual fill drops the fill (nothing is reported), but what it dispensed up to the last save is added to the total.
- The Function, Next and Prev keys do nothing in manual mode.
## 11. Where the time goes in one pass of the main loop

| Part | Takes | Notes |
|---|---|---|
| receive ring and `board_feed` | microseconds | the interrupt only stores bytes |
| `port_send` | microseconds | the DMA sends the bytes in the background (up to 7 ms on the line) |
| `board_tick`, the fill | microseconds | pump stop decided here |
| `board_idle_work` | 0, or 30-200 ms once in 64 saves | only in IDLE or DONE, never while the supply goes |
| `ScanKeyboardAndRefreshDisplay` | about 20 ms | blocks |
| `RefreshDisplay` | 8 to 10 ms | blocks |
| **one pass** | **about 30 ms** | an answer can be up to 20 ms late; POWER_OFF and the gun switch are picked by a 10 ms timer and looked at once per pass: a cut is seen within about 60 ms, the gun within about 130 ms; the pump stop of a fill is the TIM1 compare interrupt (see README) |
