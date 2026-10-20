# End-to-end tests (work plan 9.1 and 9.2)

The web app and a simulated Smart210 terminal, working together. Run everything with

```
powershell -ExecutionPolicy Bypass -File e2e\run.ps1          # both
powershell -ExecutionPolicy Bypass -File e2e\run.ps1 basic    # 9.1
powershell -ExecutionPolicy Bypass -File e2e\run.ps1 offline  # 9.2
```

It builds the Smart210 side (qmake, Qt 5.15 desktop), makes the scratch database `gasstation_e2e` new, starts the web app on port 8124 against it
(the 60-requests-a-minute API limit is raised with `API_THROTTLE_PER_MINUTE`), runs the terminal, then compares the two. The dev database
`gasstation` is never used: `verify.php` checks at the end that its row counts are what they were before. XAMPP's MariaDB must be running; it is left
running.

| File | What |
|---|---|
| `common.php` | boots the web app on `gasstation_e2e` and refuses to go on if it is on any other database |
| `prepare.php` | drops and makes the scratch database: schema, staff, a device for 127.0.0.1, fuel and a tank with stock, customers, tickets in stock / sold on the web, a ticket loan made on the web |
| `e2e.cpp`, `Scenarios.inc`, `Offline.inc`, `Cards.inc`, `Proxy.inc` | the terminal: the app's own classes (no screens) + simulated boards (`simulator/`) |
| `verify.php` | every record of the terminal is on the server with the same values; the numbers of the scenario that no terminal knows (tank stock, ticket weights, sums) |

## 9.1 `basic`: one shift

Download; spot sales of the four uses; ticket sales; a loan made on the web and a loan made here, both settled with tickets (and a ticket cannot
settle twice); supplies on boards (auto with a remainder, the remainder ticket used in the next supply, manual with tickets assigned afterwards,
a typed-in reading); a comment; checkout and upload; edits after the upload sent again one date at a time; the next download.

## 9.2 `offline`

- **A** four days with no network (sales, ticket sales and supplies of each day): a download and an upload fail and lose nothing; the automatic
  checkout stamps the finished days; the clean-up keeps what has not been uploaded; the network returns and each day goes up on its own.
- **B** a bad network (a proxy between the terminal and the web app): the answer to a push is lost (the server saved it, the terminal does not know),
  the request is cut, the server says 503 busy, a slow answer makes the terminal give up, downloads that are cut, the server not there at all. Every
  time the next upload settles it and nothing is doubled or lost.
- **C** power cuts: a process that writes sales and tickets is killed at random moments (the database stays sound and every sale has its ticket and
  loan); the upload process is killed at random moments and the next upload finishes the job.
- **D** the boards: the terminal restarts while a fill runs and records the result with its saved tickets and remainder; a board loses power in the
  middle of a fill; the converter link drops in the middle of a fill.
- At the end the clean-up removes the uploaded old records and a download does not bring them back.

Notes from writing them: Qt sends a request once more when the connection closes before an answer, so the proxy rules drop several attempts and
are removed before the retry; the supply screen's `heightForWidth` word-wrap caused a layout overlap (fixed in 6.5).

## restore: the web app's database is restored from a dump

`run.ps1 restore`: the terminal uploads, the server dumps, the terminal does more work and uploads it, the server's database is replaced by the dump (so the later work is gone) and `backup:restored` is run, then the terminal downloads: it learns about the restore, marks what it uploaded after the dump as not sent, uploads it again, and `verify.php` finds every record on the server. (`run.ps1` gives the web app the PC's time zone: the restore marker is a time of day.)

## cards: the ledger of a card

`run.ps1 cards` (`Cards.inc`, `cards_step.php`): the terminal registers a card, tops it up twice (40 and 10 kg) and fills with it (20 kg) on a simulated board, uploads; the web app makes the ledger entries from the card sales and the supplement; the terminal downloads and its History list changes from "not confirmed yet" entries to the web app's entries; then the web app tops the card up (5 kg) and deletes the 10 kg top-up of the terminal (a reversal entry), and the terminal downloads again. `verify.php cards` checks that the web app and the terminal hold the same five entries with the running balances 40, 50, 30, 35 and 25, that the deleted sale is still a row, and that the ledger agrees with the records that are left.

## 9.3 backup and restore rehearsal

`php e2e\backup_rehearsal.php [source database]` (after an end-to-end run, which leaves `gasstation_e2e` with data). It copies the source into scratch databases `gasstation_rehearsal_live`, `_restore` and `_backup` and rehearses the daily dump and its restore, the retention of old dumps, the backup run (export + clearing up to a date: tank stock unchanged, debts and loans kept), restoring the safety dump, and the import on the backup server (refused on a live one, merge, repeated import). Dumps and exports go to `web/storage/app/rehearsal/`, not to the real backup folders.

Open point: after restoring a dump the terminals do not send again what they uploaded since that dump (they keep sent records only `purge_days`).
