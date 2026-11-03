# Terminal audit, 2026-11-01 (first pass)

Scope: `smart210/src` (about 15,700 lines). This is a first pass: it reads the code that moves money, tickets and fuel, and scans the whole tree for risk patterns. It builds on `AUDIT.md`, `REVIEW_2026-10-20.md`, `REVIEW_2026-10-29.md`, the terminal-against-web audit of 2026-10-29 and the review of the cloud merge (all closed or listed there). Only what is new is listed here.

## Read in full in this pass

`SpotSale`, `TicketNumbers`, `TicketSale::create`, `Checkout`, `SaleDeletion`, `Uploader`, `Purger`, `SupplyRecord`, `SupplyQueue` (scan, mix rules), `LoanSettlement::create`, `SyncStore` (the ticket and card-ledger rows), `Cards` (register, PIN check), `Session`, `PosHash`, `PinPage`, `BoardBus` (send, answer, timeout), the print code (today's changes). Pattern scans over the whole tree: floating point in money code, transactions without a rollback, SQL built from text.

## Not read in this pass (still to do)

`MachineConfig` and its page, `NozzleConfigSync`, `BoardProtocol`, `TicketSalePage`, `SpotSalePage`, `ManualSupplyPage`, `SupplyPage`, `ReviewPage` and `ReviewData` (except the date check), `CardPage` and `Cards::topUp` / `issueTicket` (both reviewed during the merge review earlier today), `MainWindow`, `AppShell`, `SyncClient`, `Settings`, `Migrator`, `AppReset`, the `input/` readers (scanner, Hangul, card reader).

## Findings

| # | Severity | Finding | Where |
|---|---|---|---|
| T1 | Medium, **fixed 2026-11-01 by the session ids (row 9.4fg)** | **A fill can be dropped as a "duplicate".** A result is taken for one already recorded when the nozzle and the board's `result_id` match a supplement. A board that loses its counter (replaced, factory reset, flash fault) starts at 100 again, so its next fills match old supplements and are acknowledged without a supplement: fuel that came out has no record. Supplements of a card are never purged, so for them the collision lasts for ever; for the others it lasts `purge_days`. Fix: a match counts only when the stored supplement has the same kg and was made recently (for example the same day). | `SupplyRecord::record`, the `seen` query |
| T2 | Low to medium, **fixed 2026-11-02** (`TicketSale::create` refuses items that are not its tickets: kg, number of tickets, ranges) | **A ticket sale's items are not checked against its tickets.** The web app refuses the whole sale when the items' kg or quantity do not add up to the tickets, so a mismatch would stay on the terminal as a refused record that cannot go up. The page builds the items from the same stock, so it should not happen; a check before saving (sum of `lines` equals `kg` and the number of tickets) turns it into a message at the sale. | `TicketSale::create` |
| T3 | Medium, **left as it is by decision 2026-11-02** (no limit on wrong cashier passwords for now) | **No limit on wrong cashier passwords.** The machine PIN locks after wrong tries; the cashier login does not, so a password can be tried without end at the login screen. Fix: count wrong tries per user and lock for a few minutes, like `PinPage`. | `Session::login` |
| T4 | Low | **Over-settling a loan is allowed without a word.** `LoanSettlement::create` takes more tickets than the loan still owes (the web app allows it too and warns). The terminal returns the negative balance but the dialog does not say the loan is over-paid. Check what `LoanSettleDialog` shows. | `LoanSettlement::create`, `LoanSettleDialog` |
| T5 | Info | **`Checkout::run` does almost nothing now.** Records get their business day when they are made, so the "open" rows (no date) exist only for data made before that change. It is harmless; the screens that still call it could be simplified. | `Checkout` |
| T6 | Info | **Clock guard blocks until time passes.** A clock that ran ahead and was corrected stays refused until real time passes the newest record (the cost of C4). The way out is to correct the dates of those records on the web. | `Clock::behindRecords` |

## Looked at and found right

- Money is whole numbers (x100) everywhere in the sales, supply, loan and card code; no floating point.
- Every write that spans tables (`SpotSale`, `TicketSale`, `SupplyRecord`, `LoanSettlement`, `SaleDeletion`, `Checkout`, `SyncStore` pages) is one transaction with a rollback; the ones without a rollback (`Purger`, the two bulk updates of `ReviewData`) are runs of independent statements.
- A ticket is re-checked inside the transaction that uses it (sale, supply, loan settlement), so a download in between cannot sell or use it twice.
- The upload keeps every row until the server names it as saved; a failed or silent answer leaves it to go again; a refused row is kept with its reason.
- `Purger` keeps a card's history, an unused remainder ticket and the tickets of the old publishes.
- `BoardBus`: a command is given up after the missed-answer limit and SEQ moves on, so a late answer cannot be taken for the next one.

## Second pass (2026-11-02)

Read in this pass: `SyncClient`, `SyncStore` (config and ticket rows), `Migrator`, `MainWindow` (start, download, upload and purge timing), `ReviewData` (the edit helpers), `Cards` (top-up, ticket, cancel), the card fill in `CardPage`, `MachineConfig` (the blockers and the board config store), `SupplySession` (start, results, failures), `ScanDecoder`. Still not read line by line: the page code of the sale, supply and review screens, `NozzleConfigSync`, `BoardProtocol`, the card reader and the Hangul input.

| # | Severity | Finding | Where |
|---|---|---|---|
| T7 | **High, fixed 2026-11-02 (row 9.4fj)** | **A nozzle can be stuck for good after a START that never reached the board.** The session is saved (started) before START. When START gets no answer the code only says "no answer to the start" and leaves the nozzle locked, "the result is recorded when it comes". If START was lost on the bus (the command never ran), the board stays SET, no fill runs and no result ever comes, and nothing starts it again: `followTheSupplier` returns at once for a locked nozzle. The queue cannot be changed (Clear is refused: "has started"), Machine Config refuses the nozzle ("a fill is open"), and a manual supply needs kg above 0. Proved with a test that saves the session, lifts the gun and waits: after 3 s no START was sent, the board is still SET, the session is started and the nozzle is locked. The same happens after a restart with such a session. Fix: while a session is started and the board reports SET with the gun up, send START again with the same session id; while it reports IDLE for more than a few seconds (the preset is gone, the board lost its power), cancel the session (`SupplyQueue::release`); add a way for the cashier to cancel a started session that the board says is not running. | `SupplySession::followTheSupplier`, `commandFailed` |
| T8 | Low | **The terminal takes the server's clock as it is.** A download sets the clock of the board when it is more than 5 s off (`setSystemClock`). A server with a wrong clock moves every terminal, and with the clock guard of C4 a terminal that is set back stays refused until time passes the newest record. Fix by procedure (the server clock is kept right) or a limit on how far a download may move the clock, with a message. | `SyncClient::replyFinished`, `Clock::behindRecords` |
| T9 | Info | **A newer database than the program is refused with a plain message** ("version 13 but this program only knows 12") and the program does not start; this happens when an older build is run on a database a newer one migrated (it hit the dev database on 2026-11-01). Nothing to fix; keep the migrations forward only, and never run an older build on a station database. | `Migrator::apply` |

Looked at and found right in this pass: the download applies each page in one transaction and acknowledges only what it stored; a row that waits for its upload is never overwritten by a download; the purge never removes a started session, an unused remainder ticket or a card's history; a card top-up and a card ticket check the card, the balance and the clock inside one transaction; the PIN of a card is checked before a fill, wrong tries are counted and uploaded; the scanner decoder drops keys that come too slowly to be one scan.
