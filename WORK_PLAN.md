# Gas Station Management System: Work Plan

The plan is in two files: this one holds what is open, the risks and the open questions; [WORK_PLAN_DONE.md](WORK_PLAN_DONE.md) holds the finished tasks of Phases 0 to 9 with their full notes (task numbers are the same in both).

Three tracks: **Web**, **Smart210** and **Firmware**, built one after another: the whole web app first, then the Smart210 app, then the firmware.

**Remaining web order:** the sales corrections (8.3a to 8.3c) are done and 8.3d was dropped, so the web side is complete up to reports and later features; the Smart210 app is next. The sync API (phase 3) is done.

**Smart210 sync model (decided 2026-10-07):** the Smart210 works offline almost all the time and is the main point of sales and supplies. Download (pull) is automatic; upload (push) is never automatic: the cashier checks the day's records at check time and presses an **Upload data** button. Handing tickets from the assistant to the cashier is manual work outside the system: the system does not track who holds which ticket.

Sizes are relative: S = a few days, M = about a week or two, L = several weeks.

Stack: PHP 7.2, Laravel 6 LTS, AdminLTE 3 / Bootstrap 4.6 / jQuery, MariaDB (XAMPP) on the web side. Qt 5.3.2 (replaces Qt 4.8.5) with its bundled SQLite 3.8.4.3 on the Smart210 (confirm with `SELECT sqlite_version()` on the board). STM32F407 firmware, protocol v0.3.

## Where things stand (2026-10-29)

- **Web:** complete, with its reports, dashboard, daily confirmation, backup and restore.
- **Smart210:** complete up to the hardware: sales, tickets, supplies, cards, review, upload; tested against the simulator in `simulator/` and by the end-to-end harness in `e2e/`.
- **Firmware:** the CubeMX project is built (6.1) but not tried on a real board, so the supply workflow has only run against the simulator.
- **Reviews:** `AUDIT.md` (2026-10-10), `REVIEW_2026-10-20.md` and `REVIEW_2026-10-29.md` (the last on branch `claude/review-fixes-2026-10-29`). The open findings are listed below.

## Open tasks

| Phase | Done | # | Task | Size | Notes |
|---|---|---|---|---|---|
| 0 | [ ] | 0.1 | Close the open stack items: HTTP or HTTPS | S | Blocks Phase 4. Sync format decided: JSON (`QJsonDocument` on the Smart210, Laravel JSON on the server). XML dropped once Qt 5.3.2 ran on the board. Database decided: the existing XAMPP MariaDB (`D:\xampp\mysql`), no separate install. AdminLTE 3 confirmed and vendored |
| 0 | [ ] | 0.4 | Run the SQLite benchmark on the SD card: 100k tickets, lookups, allocation query, power-cut test | S |  |
| 0 | [ ] | 0.5 | Hand protocol v0.3 to the firmware developer and agree the STM32 delivery plan | S | Starts the firmware track early. The brief for the developer is written (2026-10-10): `FIRMWARE_BRIEF.md` (what the board does, the rules that matter, how to test against the simulator, six open questions, a suggested delivery order). Still to do: give it to the developer together with `PROTOCOL.md` and `simulator/`, and agree the plan |
| 6 | [ ] | 6.1 | STM32 firmware implementing protocol v0.3 (firmware track) | L | Protocol v0.3 adds SET_TIME / GET_TIME (the terminal sets every board's clock); the board needs a battery-backed RTC**Built 2026-10-11 (not yet on a real board):** the CubeMX project `firmware/` (STM32F407VET6, 25 MHz not yet the system clock: it runs on the internal 16 MHz oscillator, RTC on the internal low-speed oscillator) holds the whole firmware in `Core/Src` and `Core/Inc`: `board` (protocol v0.3, both modes, pulse counting from the A/B flow meter on TIM1, the pump on relays 1+2 only while the nozzle is lifted, no-flow / hook-down / start-wait ends, recovery after a power cut, POWER_OFF save), `store` (ring of CRC-32 records in the 25Q32), `w25q`, `ui` (display and keys, amount entry), `port`, `app`; PC tests `firmware/Tests` (358 + 6689 + 52 checks pass: PROTOCOL.md examples, power cut at every byte of a flash write); it compiles and links with CubeIDE's ARM GCC (19 KB flash, 4 KB RAM). **Open (A1, a fill that overshoots its tickets, is left to the firmware by decision B1: the board must never go past its preset):** the LCD and keyboard drivers (`ui_drivers.h`, four functions, written by the user), the free area of the ROM (`NB_STORE_BASE`), LED and beeper polarity, bench tuning of the times (6.6), field firmware update (not designed) |
| 6 | [ ] | 6.6 | Bench test with a real board and dispenser | M |  |
| 9 | [ ] | 9.4 | Provision the server, Smart210, printer and boards | M |  |
| 9 | [ ] | 9.5 | Enter opening balances and pre-existing paper data, train staff by role, then go live | M |  |

## Open findings of the logic review (REVIEW_2026-10-29.md)

Fixed on `claude/review-fixes-2026-10-29`: A5 (a second use of a ticket is logged), A3 (by procedure and a check, below), A4 (card balances count as owed fuel in the ordering figures), A6 (a terminal sale's total must be price x kg), B4 (the Review screen has a Print ticket button for a spot sale and for the remainder ticket of a supply; a card top-up has no ticket, and the ticket of a card fill is printed from the Cards screen), B1 (decided 2026-10-31: the 0.05 kg tolerance is removed on the terminal and the web; the firmware of the board makes it stop at the preset, any shortfall is a remainder ticket and an overshoot is refused), C1 (only cashiers' password hashes go to the terminals), C7 (a remainder ticket that is not used yet is never purged and always listed), the stale migration tests. A2 (the terminal reports when nothing of a finished day waits, `POST /api/sync/uploaded`; a push no longer marks an upload). Open:

| Ref | Finding | Where |
|---|---|---|
| A3 | Opening debts, loans and remainders have the same ids on both servers by procedure: the backup server is copied from the live server after the opening balances are entered (README, Backups). `backup:import` counts payments and settlements that point at an opening balance the backup server lacks (done on the branch). Still open: B5, freezing the opening balances after the copy | `ImportBackup::orphans`, `BalanceController` |
| B2 | A card ticket's unused part: refund to the card on the web, a paper remainder ticket on the terminal | `CardLedger::refund`, `SupplyRecord::record` |
| B3 | The default cost is read when the row uploads, not when the sale happened | `Push::sales`, `Push::supplements`, `DefaultCost` |
| B5 | Opening balances never freeze after go-live | `BalanceController` |
| C2 | Card PINs are a salted SHA-256 of 4 to 8 digits | `Cards::registerCard`, `CardPin` |
| C4 | No guard against a wrong terminal clock | `Clock`, `Session` |
| C5 | An edit saved while an upload is in flight is never uploaded (skipped by decision) | `Uploader::replyFinished` |
| C6 | Default admin password, PIN 0000 and `APP_DEBUG=true` (skipped by decision) | `DatabaseSeeder`, `.env.example` |
| C8 | A ticket can settle two loans, and a remainder ticket can belong to two remainders, across web and terminal | `Push::ticket_loan_settlements`, `Push::remainders` |
| D, E | Small items and the whole-table queries that will need SQL aggregates (see the review) | |

Decided and closed in `AUDIT.md`: H1 (one terminal per station), H3 (LAN only), H4 (the cashier hash stays), M2, M3 and M5 (by procedure).

## Critical path and risks

- **Critical path:** web → Smart210 → firmware (6.1) → 6.2 -> 6.5 -> 6.6. Tracks run one after another by choice, so the firmware and hardware bench test (6.6) come last and set the go-live date.
- **Riskiest piece:** the sync design (3.2, 3.3, 4.3). Build a small test harness that simulates dropped connections before anything depends on it.
- **C++ level on the Smart210.** The compiler is GCC 4.5.1: write C++03 style. `-std=gnu++0x` would only add a subset (`auto`, `static_assert`, lambdas, rvalue references) and not `nullptr`, range-based `for` or `override`/`final`, so it is not worth the risk. Any extra library must build with GCC 4.5.1.

## Open questions

- Team size, and who builds the STM32 firmware.
- HTTP or HTTPS.
- Ticket-sale reversal design, report list and dashboard alerts (decided later, see Phase 8).
