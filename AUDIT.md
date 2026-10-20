# Architecture and logic audit (2026-10-10)

## Decisions and status (2026-10-10, after the review)

| Finding | Decision | Status |
|---|---|---|
| H1 two terminals mint the same ticket serials | One terminal per gas station: a hard limit of the design | Closed (limit written down) |
| H2 restore loses uploads since the dump | The dump is taken when the accountant has confirmed the whole day (each morning); the server then tells the terminal by timestamp to set its data as unsent after a restore | Built (9.4b, 9.4c); shown by the e2e `restore` scenario |
| H3 converter port accepts any client | LAN only, the admin owns addresses and code | Closed, not needed |
| H4 weak cashier hash, M5 plain HTTP | HTTP is not a problem in this project | M5 closed, not needed. H4: the user decided on 2026-10-10 not to change the cashier hash now (left as it is on purpose) |
| H5 device clock | The terminal sets the date and time of every board | Built: protocol v0.3 SET_TIME/GET_TIME, `BoardClock`; the terminal's own RTC (battery, `hwclock`) is still for the hardware task |
| M1 three meanings of a day | The web server runs only in office hours and web work for a day starts after yesterday is confirmed, so the web's calendar date and the terminal's date agree; the terminal (24 h) needs the 9:00 rule. A record has its time stamp and one date field, `date` (renamed from `checkout_date` on 2026-10-10, work plan 9.4d) | Closed (2026-10-10): the web reports (sales, cash, supply) and the dashboard group and filter by `date`; a record uploaded before its checkout has no date yet and counts under the day of its time stamp |
| M2 case 1 (a ticket sold twice, web and terminal) | By procedure: the assistant gives tickets to the cashier and then does not sell them on the web | Closed by procedure (the system does not enforce it) |
| M2 case 2 (a loan settled twice with one ticket) | Ticket loans are the cashier's: a temporary method when a well-known customer forgot to take tickets; the assistant never handles them (on the web only the accountant role may settle) | Closed by procedure |
| M3 (web deletes or returns a sale whose ticket the terminal already used) | A web delete is not a normal action: only for a wrong input, when the whole tickets come back from the customer or were never given | Closed by procedure |
| M4 indexes | Add them | Built: `tickets` indexes on `(synced, barcode)`, `sale_id`, `supplement_id` (migration run on `gasstation` and the e2e copy); no foreign keys (not asked) |
| M5 API limit | 3 to 5 clients: no need | Closed, not needed (the e2e run raises it only for itself) |
| M6 reprint remainder tickets | Add it | Built: tab **Remainder tickets** (what was printed, what was used, Print as often as needed, count of unprinted on the tab) |
| M7 tolerances | 0.05 kg and only ever favour the station | Built: 0.05 kg; a fill short of its preset by up to 0.05 kg gives no remainder; the tickets of a manual supply must cover what was dispensed and may exceed it by 0.05 kg at most; more dispensed than tickets is never accepted (terminal and web) |
| M8 PHP 7.2 / Laravel 6 | Fine | Closed |
| M9 database time zone follows the server's OS | Fine | Closed |
| L1, L2, L3 | fixed 2026-10-10 (work plan 9.4e) | Closed |
| L4 | closed by decision (2026-10-10): a supply keeps no user at all, work plan 9.4f | Closed |
| L7 | closed by decision (2026-10-10): not handled; the cashier checks all data before the upload and can correct a problem afterwards | Closed |
| L5 | fixed 2026-10-10 (work plan 9.4g): the PIN stays (the admin owns Machine Config, the cashier uses the terminal), five wrong PINs shut it for five minutes | Closed |
| M10 | unsent records of a lost card: not handled by decision; machine configuration kept apart from the SD card instead (9.4h) | Closed |
| L6 | tried 2026-10-10 on Qt 5.3.2 + MinGW 4.8.2 (work plan 9.4i); the user still builds with the board's GCC 4.5.1 himself | Partly closed |

Side finding while doing this: the Smart210 tests had a time bomb (fixtures dated 2026-10-07 were purged once they were more than 3 days old); the fixtures now use a long purge time.

Scope: the web app (`web/`, about 5000 lines of app code, Laravel 6 / PHP 7.2 / MariaDB), the Smart210 app (`smart210/`, about 12 000 lines, Qt/C++ with SQLite), the nozzle protocol (`PROTOCOL.md`), the test harnesses (`e2e/`). Method: read the code of every money, ticket, sync and security path, read the real schemas, run the e2e and rehearsal suites, grep for risky patterns. Not covered: the Qt 5.3.2 / GCC 4.5.1 board build, real hardware (boards, dispenser, printer, scanner), HTTPS, load tests, the Blade views line by line.

"Shown" = reproduced by a test or run; "read" = found by reading the code (not run).

## Verdict

The core design is sound and unusually well tested for its size: every record has a key and is stored idempotently, money is exact (integers on the terminal, DECIMAL on the server), multi-step changes are transactions, the terminal survives a bad network and power cuts (shown: `e2e/` 9.1 and 9.2), and backups restore identically (shown: 9.3). The weaknesses are not in the code paths that exist but in **rules that were never written down or decided across the two systems**: what a "day" is, who may mint ticket numbers, what happens when web and terminal touch the same ticket, and what protects the network edges. Nothing found loses money in the normal single-terminal flow. Several findings only matter with more than one terminal, a restore, or a hostile or careless person on the LAN.

## Findings

Severity: **High** = fix before go-live; **Medium** = decide and fix soon; **Low** = note or schedule.

### H1. Two terminals would mint the same ticket numbers (read)
`TicketNumbers::next` numbers a terminal's own tickets (spot sale 21-24, remainder 25) from `ticket_serial_base + 1` upwards using only its own database and the floor it downloaded. Two terminals offline both start at the same serial. On upload the first wins; for the second `Push::tickets` finds the barcode already there and, for a sold ticket, does nothing and still answers "saved": two paper tickets with one barcode, one of them unknown to the server, no error. The schema already supports several devices (`nozzles` are unique per device). **Fix:** give every device its own block, for example `serial = base + device_id * 1 000 000 + n` (the server knows the device id; send it in the config), and make the server refuse a device ticket whose serial is outside the caller's block. If only one terminal is planned, write that down as a hard limit.

### H2. Restoring a backup silently loses what terminals uploaded since the dump (shown by the 9.3 rehearsal reasoning, not run)
A terminal sends only records it counts as unsent and deletes sent ones after `purge_days`. After a restore from a dump the web app lacks records the terminal believes are safe. **Fix (pick one):** a server `restored_at` stamp in the config that makes terminals mark records newer than it as unsent; or a "resend since <date>" action on the terminal. Needs your decision.

### H3. The converter port accepts any client and a new one replaces the real link (read)
`BoardBus` listens on port 4001 with no check of who connects, and "a new connection replaces the old one". Anyone on the LAN can take over the link: deny service, or answer as the boards (for example fake results, or "done" with 0 kg). **Fix:** accept only the converter's configured IP (a Machine Config field) and keep the old link if the new peer is not that address; firewall the port on the LAN as well.

### H4. The password hash for cashiers is weak and travels in clear (read)
`users.pos_hash = SHA-256(salt + password)` is sent to every registered device on every pull, over plain HTTP, and includes admin and manager accounts (`pos_roles`). It is the same password as the web login. A fast hash of a short password is cracked in minutes; the SD card of a lost terminal exposes every cashier and manager. The terminal accepts legacy SHA-1 hashes too and has no attempt limit. **Fix:** a slow keyed derivation (PBKDF2-HMAC-SHA256 with 10 000+ rounds, available with `QMessageAuthenticationCode` on Qt 5.3), a separate cashier PIN instead of the web password, no admin/manager hashes on the device, rate limit with a lockout on the terminal login, and HTTPS (open item 0.1).

### H5. The device clock can be wrong after a power cut and the business day depends on it (read)
`Clock::setSystemClock` does not write the hardware clock (`hwclock -w` is commented out) and the terminal runs offline most of the time. After a power cut the board may start in 1970 or hours off until the next download (every few minutes only when the network works). Sales, checkout days and the 9 AM business day all come from this clock. **Fix:** a battery-backed RTC and `hwclock` at start and after each sync; a guard that refuses a sale or supply when "now" is earlier than the newest record in the database; the footer warning already exists, make it blocking after a threshold.

### M1. "Day" means three different things (read)
Terminal: the business day 9 AM to 9 AM (`Checkout::businessDay`). Web sales and loans: `date = today()` (the field was called `checkout_date` when this was written) (calendar day). Web reports: `DATE(sold_at)` and `DATE(supplied_at)`. Confirmation and backup use `checkout_date`. A device sale at 07:00 belongs to yesterday for the accountant's confirmation but to today in the sales report; a web sale at 07:00 is "today". Totals do not reconcile across screens. **Fix:** one definition, used everywhere (store `checkout_date` by the 9 AM rule on the web as well; reports group by `checkout_date`).

### M2. A ticket can be sold or used in two places at once; conflicts are only logged (read)
Published stock is visible to the web and to the terminals. A ticket sold on the web and, offline, on a terminal ends as two sales with one ticket; a ticket supplied at a nozzle and settled against a loan on the web is used twice. `Push::tickets` logs a conflict in `sync_errors` and still answers "saved", and a settlement push (`ticket_loan_settlements`) checks nothing about the ticket, so a loan is credited twice. There is no unique key on `ticket_loan_settlements.ticket_barcode` or `remainders.ticket_barcode`. **Fix:** unique keys; reject a second settlement of one ticket with a clear reason; show open conflicts on the dashboard until resolved; decide who owns which stock (for example the hand-over of tickets to a terminal marks them as the terminal's, as planned in the notes).

### M3. A ticket given back to stock or restricted on the web, then used at the pump, is not reported (read)
`Push::tickets`: when the web sets a ticket to "restricted" a conflict is logged; when the web has put it back to "published" (a deleted or returned ticket sale) and the terminal reports it supplied, the branch applies it silently. Fuel went out for a sale that no longer exists. **Fix:** log a conflict in that branch too, and list it for the accountant.

### M4. No foreign keys, few indexes (read, schemas dumped)
Neither database has any `FOREIGN KEY`. Orphans (a supplement for a nozzle deleted by a push, a settlement for a missing loan) rely on application code and on the order of pushes. Missing indexes: `tickets(sale_id)`, `tickets(supplement_id)`, `tickets(synced, barcode)` (every pull page scans the primary key from the start, so a 100 000-ticket first sync is quadratic), `ticket_loan_settlements(ticket_barcode)`. **Fix:** add the indexes; add foreign keys where deletes are soft anyway; run an integrity query set (orphans) in the daily job and show the result.

### M5. The device API limit and unauthenticated HTTP (shown / read)
60 requests a minute per address stops a big first download (about 1000 requests for 100 000 tickets): the e2e run hit "Too Many Attempts". The API is plain HTTP and authenticates only by source IP, so anyone who takes an address (static, but ARP-spoofable) gets the whole customer list, all tickets and every password hash, and can push data. The server time that sets the terminal clock is unauthenticated too. **Fix:** a higher limit for devices only; HTTPS with a pinned certificate or a shared request signature (HMAC of body + time with a per-device secret); switch off unused ports.

### M6. A remainder ticket is not reprintable after a crash (read)
After a short fill the remainder ticket is real value for the customer. It is printed once after the supplement is saved; if the app stops or the printer is off, the only reprint is a button that disappears when the screen changes (it keeps the barcode in memory). **Fix:** list remainder tickets in Review (or Recent) with a Print button, and keep a "printed" flag.

### M7. Money-neutral tolerances are one-way (read)
A fill that stops within 0.1 kg of the preset counts as complete and gives no remainder (customer short up to 0.1 kg per fill); a manual supply may exceed its tickets by 0.1 kg (web allows it too since 6.5). Over a year this adds up. **Fix:** decide it as policy (and a daily total of "unreturned grams" in a report), or lower the tolerance to what the board can really hit.

### M8. Server stack is out of support (read)
PHP 7.2.34 (end of life Nov 2020) and Laravel 6 (security fixes ended Sep 2022); `APP_DEBUG=true` in the dev `.env`. Acceptable on a closed LAN only if nothing else reaches the server. **Fix:** plan the upgrade (PHP 8.1+, Laravel 9/10) before go-live or at least the first year; a production `.env` checklist (debug off, key, no seed password).

### M9. Database time zone is the server's OS zone (read)
Columns are `TIMESTAMP` and the MariaDB session zone is `SYSTEM`, while PHP writes Shanghai time. It round-trips only while the OS zone never changes; a server on a DST zone would produce nonexistent or ambiguous hours, and the 2038 limit applies. **Fix:** `DATETIME` for business times or set `'timezone' => '+08:00'` in `config/database.php`, and state the server zone in the install notes.

### M10. Data lives only on the SD card until the cashier presses Upload (read)
By design uploads are manual. A card failure or theft loses every record since the last upload, and nothing tells the cashier that the last upload was days ago. **Decision 2026-10-10:** the unsent records of a lost card are not handled (no warning, no copy). What was built instead (work plan 9.4h): the machine configuration is kept apart from the SD card, so a replaced card needs no new setup.

### L1. A "poison" row would fail a whole push batch (read)
`PushController` catches only `RejectedRow`. Any other exception (a lock timeout during a backup, a database error) returns 500 for the batch; earlier rows stay saved and the terminal retries the whole batch, which is safe, but a row that always throws would block uploads for good. **Fix:** catch `\Throwable` per row, record it as rejected with the message, and continue. **Done 2026-10-10.**

### L2. First-use race in `SerialCounter` (read)
`Setting::lockForUpdate()->find()` locks nothing when the counter row does not exist yet; two sales at the very first use could take the same serial. **Fix:** create the counter rows in a seed or migration. **Done 2026-10-10:** `SerialCounter` makes the row (INSERT IGNORE) before it locks it, so no seed is needed for the codes.

### L3. Spot-sale cost is taken at upload time (read)
`Push::sales` fills `cost` from the default inbound when the row arrives, not when the sale happened; after days offline and a changed default cost the margin report drifts. **Fix:** use the default that was valid at `sold_at`. **Done 2026-10-10:** `fuel_inbounds.default_from/default_until` record when an inbound was the default; `FuelInbound::costAt`.

### L4. Cashier attribution of a supply (read)
A result is recorded with the cashier logged in when it is read, not the one who pressed Start. **Fix:** use the session's `user_id` saved at Start. **Closed 2026-10-10 differently:** the user decided that who supplies is not important, so supplements and the running fill (`supply_sessions`) no longer keep a user, and the fill code no longer checks the login (the cashier stays logged in until logout, so normal work is unchanged).

### L5. Persistent login and no lockout on the Machine Config PIN (read, partly a decision of yours)
The login stays over restarts (your decision). The PIN is 4-8 digits, hashed with SHA-256 and has no attempt limit: it only keeps out honest people. **Fix:** an attempt limit with delay; accept that it is not a security boundary. **Done 2026-10-10.**

### L6. The Qt 5.3.2 / GCC 4.5.1 build is unproven (read)
The source is C++03 clean by grep (no lambdas, `auto`, range-for, `override`, functor connects) but has never been built for the board. **Fix:** do it early (it is the first thing 6.6 needs), with the tests kept on the desktop Qt.

### L7. Web recomputes and the device overwrites (read)
A device re-push of a sale (after a comment edit) overwrites every field of an unconfirmed sale, including changes the assistant made on the web; confirmed sales are protected. That is the documented rule ("the device is the source of truth") but staff do not know it. **Fix:** say so on the screen, or lock a sale for device edits once it was touched on the web.

## What is solid (keep as is)

- Idempotent keyed push, ack by timestamp, local-wins on unsent rows, date-limited upload, deletion rules before upload only.
- Integer money and weights on the terminal; DECIMAL(12,2)/(14,2) on the server; the same rounding (half up) on both sides; every multi-table write in a transaction.
- SQLite with WAL and `synchronous = FULL`; migrations in a transaction with `user_version`.
- The protocol: polling with repeat-safe SEQ, CRC, result held until acknowledged, saved supply session, result recorded before the acknowledgement (shown with kills, power cuts and lost answers).
- Permissions by role with route-level `can:` checks, CSRF on, no unescaped output (`{!!` nowhere), no user-supplied raw SQL, Eloquent soft deletes everywhere, daily audit log, confirmation lock per record.
- Tests: 236 Qt, 309 web, e2e (basic 51 + 50, offline 79 + 164), rehearsal 24.

## Suggested order

1. Decide H1 (how many terminals ever), H2 (restore marker), H4 (cashier credential), M1 (one definition of a day). These change data and protocol, so decide before the firmware work and before any real data.
2. Build H3, H5, M2, M3, M4, M5, M6 (small, mostly one file each) and extend the e2e suite for each (two terminals; a restore; a double settlement).
3. Then the schedule items: M7 to M10, L1 to L7, the board build (L6) together with 6.6.

## Integrated review of the web and the Smart210 (2026-10-14)

Scope: the changes of the last rounds (cards, sessions, manual input, protocol v0.4 draft, review deletion) checked across `web/`, `smart210/`, `simulator/`, `e2e/` and the documents.

| # | Finding | Result |
|---|---|---|
| A1 | The end-to-end harness (`e2e/`) was stale: it used `SupplySession::start`, `SupplyRecord::assign`, `recordByHand`, `tickets_pending` and a manual fill recorded by the board | Fixed: the harness now chooses the nozzle, scans, waits for the preset, lifts the gun in the simulator and waits until the terminal saw the fill start; the manual supply is typed in with its tickets (`recordManual`). **All e2e checks pass** (basic 50, offline 52 and the rest, 164 in the longest run), the dev database was not touched |
| A2 | Schema parity web / terminal (`information_schema` against `001_initial.sql`) | The shared tables have the same columns except the intended ones (terminal: `balance_kg`, `synced`, `uploaded_at`, `board_result_id`, `printed_at`; web: `cost`, `confirmed_*`, `device_id`, timestamps, `comment` of a card). `cards.holder_phone` is on both sides, goes up and comes down (tests on both sides) |
| A3 | Values on both sides after a full shift and after a restore | Verified by `verify.php` (sales, supplements, remainders, loans, tickets, tank stock, sync errors): same values |
| A4 | Web routes: every mutating route has a permission middleware or checks the permission inside (`SaleEditController::sale`) | No unprotected mutating route found |
| A5 | Dead code in the terminal after the redesign | Removed: `SupplySession::start`, `assign`, `pending`, `DESELECT` / `CANCEL` commands, `SupplySelection::problem`; `SaleDeletion` is used again (Review). Left on purpose: `StateSelected` (value 1) and the poll flag bit1 as reserved in the protocol |
| A6 | C++03 / Qt 5.3 safety of the new code | No C++11 constructs in `smart210/src` (checked by search); a local struct used as a template argument was caught and removed. **Not compiled with Qt 5.3.2 / GCC 4.x**: the user does that at the end |
| A7 | Open risks | (1) Protocol v0.4 (gun start, key events, four states, SET_PRESET 0 as cancel) is a draft that the firmware does not implement yet: the supply workflow only runs against the simulator. (2) The Cards screen got taller (phone row): check it on the board's screen. (3) A nozzle that loses the choice before its fill starts gives its preset back (IDLE) and keeps its queue (decided 2026-10-14). (4) The terminal's upload overwrites a web edit of an unconfirmed sale (decided, `SMART210_SYNC.md`). (5) The permission names are partly built dynamically: a unit test that lists every route and its permission would be safer than the manual check done here |
