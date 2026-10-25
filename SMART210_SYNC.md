# Smart210 follow-ups of the web app

Workflow (user, 2026-10-13): the web app is updated first. When a web change also concerns the Smart210 (the data it sends or receives,
a screen that mirrors a web form, a table both sides keep), it is written here, and the Qt app is updated afterwards, when the user asks.
The Qt code is not changed for a web change unless the user asks.

Each entry: the date, the web change (with the `WORK_PLAN.md` row), what the Smart210 has to do, and a status (`open` or `done`).

## Open

| Date | Web change | What the Smart210 has to do | Status |
| --- | --- | --- | --- |

## Done (the Smart210 already follows the web app)

The migration numbers 013 to 016 named below are from before 2026-10-14: all of them are in `001_initial.sql` now.

| Date | Web change | Smart210 |
| --- | --- | --- |
| 2026-10-14 | **Card ledger** `card_transactions` (new pull table: `id`, `card_id`, `type` topup / fill / reversal, `kg` signed, `balance_kg` after, `price`, `total_amount`, `source_id`, `occurred_at`, `seq` (the order the web app made the entries in: the lists go by `occurred_at`, then `seq`), `comment`); `cards.balance_kg` is now the sum of the ledger | done 2026-10-14: migration `002_card_transactions.sql`, `SyncStore::storeCardTransaction`, `CardLedger`, the History dialog shows it (and the entries not made yet, marked); the terminal pushes nothing new: the web makes the entries from the pushed card sales and supplements |
| 2026-10-14 | `POST /api/confirmed` (new): the terminal sends the ids of the uploaded sales and supplements it still holds (500 of each at most), the answer names the ones the accountant has confirmed (`sales`, `supplements`) | done 2026-10-14: `SyncClient` asks after every download, `Purger::removeConfirmed` removes them (a card's records stay); an older web app without the call is tolerated |
| 2026-10-14 | `remainders.comment` (VARCHAR 255, optional) is new on the web: typed on the Add remainder form, the opening remainders and edited in the Remainders list; the push of a remainder may carry `comment` | done 2026-10-14: migration `003_remainder_comment.sql`, the Remainders screen has the comment (Save comment marks the remainder not uploaded), the upload sends `comment`; the Manual input screen has a Comment kept as the comment of the supplement |
| 2026-10-14 | `remainders.supplement_id` may be NULL on the web (an opening remainder, Initialize > Remainder tickets) and a backup never clears remainders | nothing on the terminal: remainders only go up, and an opening remainder reaches the terminal as a ticket (sold, code of the remainders) with the ticket pull |
| 2026-10-14 | `cards.holder_phone` and `cards.comment` are new (row 9.4ay) | the register form of the Cards screen asks for the holder's **phone number** (optional, up to 30 characters); `cards.holder_phone` is in `001_initial.sql`, goes up with the card (`holder_phone` in the push row, for a new card only), comes down with the pull (the web's `Pull` sends it) and shows on the card's information; the comment stays on the web |
| 2026-10-14 | A sale's date, comment and receipt can be edited on the web while it is unconfirmed (row 9.4ay) | **Decided: the terminal overwrites.** The terminal never pulls sales; a sale it uploads again (after an edit on the Review screen, or after a restore of the web's database) replaces the web's date, comment and receipt as long as the sale is not confirmed (the push refuses a confirmed one). Nothing to change |
| 2026-10-14 | The web migrations are merged into one file per table with the columns in order (27 files instead of 70) | the 16 migrations are one `001_initial.sql` with the same tables, the columns in the order of the web tables, and `nozzles.board_host` / `board_port` dropped (nothing read them); an older development database (user_version 16) is refused, delete it |
| 2026-10-13 | **RFID fuel cards** (rows 9.4aq to 9.4at): the web has the cards, their balance, the top-up sales (`sale_type` `card`, `sales.card_id`), the supplements with `card_id`, the push table `cards` and the pull table `cards` | done 2026-10-13: migration 015, `Cards`, `CardReader` (+ Machine Config "Card reader" tab), `CardPage` (sidebar "Cards"), `SupplySession::startCard`, upload of cards / card sales / card supplements, pull of `cards`; texts and tests (smart210 README) |
| 2026-10-25 | The web app no longer adds, changes or removes nozzles: **Config for Admin > Nozzles** only lists them (no New, Edit, Delete, trash or restore, no routes for them), and a **tank cannot be deleted while a nozzle draws from it** (the message names the nozzles) | the Smart210 is the only place that manages nozzles (Machine Config); the web app learns them from its push (`Push::nozzles`). `NozzleController::readOnly()`, `TankController::destroy()` |
| 2026-10-13 | Nozzles can be set up on the web (**Config for Admin > Nozzles**: device, number, tank, board address; the fuel type is the tank's; row 9.4ap) | done 2026-10-13, no work: the terminal stays the source of truth for nozzles (the web page is for looking at them and correcting a tank) |
| 2026-10-13 | `remainders`: `date` is `remainder_date`, `remainder_at` is gone (`created_at` is the moment), `complete_date` is new (row 9.4ag) | done 2026-10-13: migration 016, `remainders.created_at` / `remainder_date` |
| 2026-10-13 | `supplements.amount_kg` / `liters_dispensed` are renamed `out_amount_kg` / `out_amount_lt` (row 9.4af) | done 2026-10-13: migration 016, `out_amount_kg` / `out_amount_lt`, e2e scripts follow |
| 2026-10-13 | `supplements.density` (kg per litre) is new and `supplements.manual_reading` is gone (row 9.4ae) | done 2026-10-13: migration 016, `density` (x10000) from the nozzle config, `manual_reading` is gone (a reading typed by hand is mode `manual`) |
| 2026-10-13 | `supplements.date` is renamed `supply_date` (row 9.4ad), like `sale_date` of a sale | done 2026-10-13: migration 016, `supply_date` |
| 2026-10-13 | `supplements.mode` = `auto` or `manual` (row 9.4ab): the nozzle mode a supply was made in; `manual_reading` stays as it is | done 2026-10-13: migration 016, `mode` auto / manual, sent in the upload row |
| 2026-10-12 | Sales have no `sold_at`: `created_at` / `updated_at` and `sale_date` (9.4v) | migration 013, upload sends `created_at` and `sale_date`, checkout / review / print / purge use them |
| 2026-10-12 | Receipt of a sale is a yes/no (9.4u) | `Input.receipt` on both sale screens, a **Receipt issued** box; stored as `1` or empty, the web reads it as yes |
| 2026-10-12 | Ticket sale form: items (spec, start serial, count, end serial, Check) (9.4v) | `TicketSalePage` rebuilt the same way (9.4x), no scanning |
| 2026-10-12 | Spot sale form arranged like the ticket form (9.4w) | `SpotSalePage` rearranged (9.4z) |
| 2026-10-13 | `sale_items`: the items are kept as entered (9.4aa) | table `sale_items` (migration 014), written by `TicketSale::create`, sent inside the sale's row (`items`) |
| 2026-10-13 | Start and end of an item shown as full barcodes (9.4aa) | the items table of the ticket sale screen shows them |

## Web changes that need nothing on the Smart210

Menus (Sales, Supplements, Daily Confirmation, Debts Management, Loan), Select2 and the date pickers (AdminLTE components), the sales list
(filters, paging, the Actions drop-down, the detail popup), the supplements list (filters, paging), the customer field of the web sale forms (a new customer can be added there; the
terminal gets it with its next download), ticket lookup and summary, backup export / clearing of `sale_items`, adding a remainder on the web (the terminal gets its ticket through the ticket pull), the Audit Log page.

## Not checked yet

- Qt 5.3.2 and Qt 5.15.2 both ran the full suite on 2026-10-14 (267 tests, one migration); the e2e scenarios pass on the web app's merged migrations too, and the ticket sales there now carry item lines that are checked on the web side.
- The board's compiler (GCC 4.5.1) was not available: every source file was checked as C++98 (`-std=gnu++98 -Wall -Wextra`) with GCC 4.8.2 against the Qt 5.3.2 headers (no errors, no warnings), the Linux serial branch of `CardReader` was compiled against a stand-in `termios.h`, and the unoptimised (-O0) Qt 5.3.2 test build links and passes (267). Only a build with the real toolchain proves it.
- The Smart210 screens (the sale screens, the Cards screen) were never looked at on the real board (1280 x 720); a test guards the minimum size of the sale screens.
- The card reader was never tried on a real serial port (`/dev/ttyUSB0`), and a card fill was never run with a real dispenser.
