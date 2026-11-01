# Terminal audit, 2026-11-01 (first pass)

Scope: `smart210/src` (about 15,700 lines). This is a first pass: it reads the code that moves money, tickets and fuel, and scans the whole tree for risk patterns. It builds on `AUDIT.md`, `REVIEW_2026-10-20.md`, `REVIEW_2026-10-29.md`, the terminal-against-web audit of 2026-10-29 and the review of the cloud merge (all closed or listed there). Only what is new is listed here.

## Read in full in this pass

`SpotSale`, `TicketNumbers`, `TicketSale::create`, `Checkout`, `SaleDeletion`, `Uploader`, `Purger`, `SupplyRecord`, `SupplyQueue` (scan, mix rules), `LoanSettlement::create`, `SyncStore` (the ticket and card-ledger rows), `Cards` (register, PIN check), `Session`, `PosHash`, `PinPage`, `BoardBus` (send, answer, timeout), the print code (today's changes). Pattern scans over the whole tree: floating point in money code, transactions without a rollback, SQL built from text.

## Not read in this pass (still to do)

`MachineConfig` and its page, `NozzleConfigSync`, `BoardProtocol`, `TicketSalePage`, `SpotSalePage`, `ManualSupplyPage`, `SupplyPage`, `ReviewPage` and `ReviewData` (except the date check), `CardPage` and `Cards::topUp` / `issueTicket` (both reviewed during the merge review earlier today), `MainWindow`, `AppShell`, `SyncClient`, `Settings`, `Migrator`, `AppReset`, the `input/` readers (scanner, Hangul, card reader).

## Findings

| # | Severity | Finding | Where |
|---|---|---|---|
| T1 | Medium | **A fill can be dropped as a "duplicate".** A result is taken for one already recorded when the nozzle and the board's `result_id` match a supplement. A board that loses its counter (replaced, factory reset, flash fault) starts at 100 again, so its next fills match old supplements and are acknowledged without a supplement: fuel that came out has no record. Supplements of a card are never purged, so for them the collision lasts for ever; for the others it lasts `purge_days`. Fix: a match counts only when the stored supplement has the same kg and was made recently (for example the same day). | `SupplyRecord::record`, the `seen` query |
| T2 | Low to medium | **A ticket sale's items are not checked against its tickets.** The web app refuses the whole sale when the items' kg or quantity do not add up to the tickets, so a mismatch would stay on the terminal as a refused record that cannot go up. The page builds the items from the same stock, so it should not happen; a check before saving (sum of `lines` equals `kg` and the number of tickets) turns it into a message at the sale. | `TicketSale::create` |
| T3 | Medium | **No limit on wrong cashier passwords.** The machine PIN locks after wrong tries; the cashier login does not, so a password can be tried without end at the login screen. Fix: count wrong tries per user and lock for a few minutes, like `PinPage`. | `Session::login` |
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
