# Gas Station Management System

Four parts: a Laravel web app (`web/`), a Qt app on the Smart210 cashier terminal, STM32 firmware per nozzle, and an offline backup server (same Laravel code). See [WORK_PLAN.md](WORK_PLAN.md) for what is open and [WORK_PLAN_DONE.md](WORK_PLAN_DONE.md) for the finished tasks.

Also: `PROTOCOL.md` (the nozzle board protocol) and `simulator/` (a PC program that plays the converter and the six nozzle boards, see its README).

## Run the web project

The web app is in `web/`: Laravel 6 on PHP 7.4.33 (XAMPP, Apache + MariaDB 10.4 as services), AdminLTE 3 (bundled locally, no internet needed). The station browsers are Google Chrome 86, so the pages use no newer script or style features.

Uses whatever is already on the machine — no separate database server or PHP install needed:

- **XAMPP 7.4.33** (`D:\xampp`): its PHP 7.4.33 runs the app (Laravel 6 does not run on PHP 8), its MariaDB 10.4 holds the databases (`gasstation`, `gasstation_test` for the tests, `gasstation_e2e` for the end-to-end tests), its Apache serves the pages. `e2e/apache/gasstation.conf` has the virtual hosts of the app (include it in `httpd.conf` to serve it from the Apache service); `e2e/apache/httpd-test.conf` starts a second Apache of the same XAMPP on ports 8123 (app) and 8124 (end-to-end tests) without touching the service.

### Requirements

- **PHP 7.4** (the XAMPP one) with the extensions `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `curl`, `gd`, `zip`, `xml`, `tokenizer`, `ctype`, `json`. Make sure `php -v` shows 7.4 in the terminal you use (put `D:\xampp\php` first on `PATH`).
- **Composer**.
- **MariaDB running** (start it from the XAMPP control panel, or `mysqld` directly if it's not already up).

### First-time setup

Run these in the `web` folder.

```bash
composer install
copy .env.example .env
php artisan key:generate
```

In `.env`, set the database (the defaults below match a local XAMPP MariaDB):

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gasstation
DB_USERNAME=root
DB_PASSWORD=
```

Create the database, then build the tables and the starting data:

```bash
mysql -u root -e "CREATE DATABASE gasstation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed
```

The seeder creates the `admin` user, the built-in `Individual` customer and the default device settings.

### Start the server

```bash
php artisan serve
```

Open <http://127.0.0.1:8000> and sign in:

| Username | Password |
|---|---|
| `admin` | `admin123` |

Change the admin password after the first sign-in (Users screen). Add the other users there too: admin, manager, accountant, assistant and cashier.

The web app is in English only (a language can be added in `web/config/locales.php` and `web/resources/lang/<code>/`).

### Run the tests

The tests use their own database, so your data is not touched:

```bash
mysql -u root -e "CREATE DATABASE gasstation_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php vendor/phpunit/phpunit/phpunit
```

### Smart210 API

The Smart210 talks to the web app over JSON under `/api`. Every reply has a `status` (`ok` or `error`; an error also has a `message`). ids and counts are JSON numbers, flags are `true`/`false`, missing values are `null`, decimals (kg, money) are strings such as `"12.50"`, and times are `YYYY-MM-DD HH:MM:SS` in the station's local time.

A Smart210 is recognised by the **IP address** it calls from. The admin adds each terminal on the **Devices** screen with a unique address (give the terminal a fixed address or a reservation on the router), and only the addresses of registered, non-deleted devices may use the API, except `ping`. A call from any other address is answered `403` with `this IP address is not registered`. There is no token and no sign-in call: the cashier login is checked on the device against the users it downloaded.

| Call | Registered address needed | Purpose |
|---|---|---|
| `GET /api/ping` | no | Test the connection, get the server time (`server_time` in the station's zone, `server_time_utc`, the zone's `timezone` name and its `utc_offset_seconds` now: the same four come with `GET /api/device` and `GET /api/pull/config`), and `registered` (is the caller's address a device), `device_id`, `device_name` and `ip` |
| `GET /api/device` | yes | Check that the address is registered, get the device settings |
| `GET /api/pull/config` | yes | Small config, always sent whole: `fuel_types` (name, price, density), `tanks` (with `stock_kg`), `device` (PIN salt and hash, `purge_days`), and what the device needs to number a ticket of its own: `ticket_sign`, `ticket_legacy` (the ranges of the publishes dated before the go-live date: `code`, `from`, `to`, `fuel_type`, `spec`, `kg`, `date`; empty while no go-live date is set), `pos_codes` (`fuel`: the first digit of the code of a POS ticket per fuel type, `type`: the second digit per kind of ticket) and `ticket_serial_floor` (per 2-digit code, the highest serial the web app holds in the device range). Replace the local copy with it |
| `GET /api/pull/{table}?limit=` | yes | One page of rows not yet synced, for `users`, `customers`, `tickets` or `ticket_loans` (default 200, at most 500). Returns `rows`, `more` and `server_time`. A deleted row comes with `"deleted": true` |
| `POST /api/pull/{table}/ack` | yes | After storing a page, send `{"keys": [...], "pulled_at": "<that page's server_time>"}`. Rows the web changed since the page was served stay unsynced and come again. Returns `acked` |
| `POST /api/sync/begin` | yes | A device with an empty database (new, or its board was replaced) calls this before its first sync: every pull table goes back to unsynced and the device counts as not set up |
| `POST /api/sync/initial-done` | yes | The Smart210 has pulled everything once |
| `POST /api/push/{table}` | yes | Send `{"rows": [...]}` (at most 200) for `nozzles`, `sales`, `supplements`, `remainders`, `ticket_loans`, `ticket_loan_settlements` or `tickets`, in that order. A row is stored by its key (`id`; `barcode` for tickets; `number` for nozzles), so sending it again changes nothing. A cashier's change of the comment or the date of a sale or supplement after its upload is pushed again and stored; if the accountant has confirmed the record it is refused with the reason `confirmed by the accountant`. Supplements, ticket loans and loan settlements also carry a `comment`. Nozzles are set up on the Smart210 and pushed as `{number, tank_id, board_address, deleted}` (the device is the caller, the fuel type is the tank's); a supplement names its nozzle by `nozzle_number` Returns `saved` (keys to mark synced) and `rejected` (each with `key` and `error`; keep those rows and fix or report them, they are also logged in `sync_errors`) |

What the server does with pushed rows:

- **Sales** are always paid in full (`unpaid_amount` 0; debts are made on the web). The server fills `cost` itself.
- **Supplements:** the device is the one registered for the caller's address, and `amount_kg` may not exceed `preset_kg` (the Smart210's 0.05 kg tolerance only ever favours the station). A **remainder** needs its supplement to be pushed first. When every ticket of a supply is a ticket one card paid for, the unused part is not a remainder ticket: the web app gives it back to the card as a `refund` entry of the card's ledger (a leftover of 0.05 kg or less stays with the station). The Overview of the daily confirmation lists the supplies whose tickets, preset and remainder do not agree; the upload accepts them, since the fuel came out.
- **Ticket loans:** the server owns `settled_amount_kg`, summing the pushed settlements (and any made on the web), and sends the new total back with the next pull. A settlement needs its loan pushed first.
- **Tickets:** a device reports only `sold` or `supplied`, and a ticket only moves forward. A device may create tickets only with a POS code (the first digit is the fuel type, 1 gasoline or 2 diesel, and it must be the ticket's own; the second the kind: 1 spot sale, 2 self use, 3 other use, 4 ticket loan, 5 card, 9 remainder; a card ticket carries `card_id`) and a serial above `ticket_serial_base` (from `GET /api/pull/config`; the web app numbers below it). A supplied ticket needs no supplement when it was surrendered for a ticket loan. If the web already knows better (the ticket was finished or restricted meanwhile) the change is logged in `sync_errors` as a conflict and the row still counts as saved, so the device does not retry forever. Fuel that was dispensed always wins over a restriction. **Tickets sold on paper before go-live:** a ticket with no row whose barcode is inside a publish dated before the go-live date (Config for Admin > Go-live) is a press ticket sold before go-live; the report of its use (`status` supplied) makes its row, sold on the day of its publish, and goes on as for any sold ticket (`LegacyTickets`). A barcode in no such range is refused as before.
- **While a backup runs,** pull, ack, push and `initial-done` answer `503` with `{"status": "busy"}` and `Retry-After: 30`: keep the data and ask again.

How the Smart210 uses it: it works offline almost all the time. It downloads (pull) by itself whenever the server is reachable, but it never uploads by itself: the cashier checks the day's records at check time and presses an **Upload data** button, which pushes everything not uploaded yet. A record deleted on the device before the upload is never sent. After the upload the device offers no delete: the web corrects it.

Staff see rejected rows and conflicts, and when each Smart210 last uploaded (a download does not count; older than `sync.stale_hours`, 30, is flagged), on the **Sync Status** screen (menu: Config for Admin).

Pulling a table: ask for a page, store it, ack it, and repeat until `more` is `false`. A page that is not acked is simply sent again. `sync/begin` puts every table back to unsynced, except tickets that are already supplied or restricted, which a new device never needs.

### Backups and restoring the database

- **The dump:** when a date is fully confirmed (the accountant's morning review), a dump of the database is queued (`RunBackupDump`; `backup:dump`), but only once the business day is over and every terminal has uploaded since (a terminal that has not may still hold records of the day); until then the page says why, and the next confirmation of the day makes it. The nightly 02:00 job is only a safety net: it dumps when there is no dump from the last 20 hours. Dumps are `storage/app/backups/daily/*.sql.gz`, the last 3 days are kept (copy them to another disk!). Needs the scheduled task that runs `php artisan schedule:run` every minute for the safety net.
- **Setting up the backup server (once, at go-live):** (1) finish the setup on the live server first: the opening balances (tank stock, debts, ticket loans, old paper tickets, opening remainders) and the go-live date; (2) then copy the whole site and its database to the backup server (a dump of the live database restored there, and the `web/` folder); (3) in its `.env` set `BACKUP_IS_BACKUP_SERVER=true` and its own database name, and delete the devices that were copied (the terminals talk to the live server only). The backup server then has the opening balances with the same ids as the live server, which is what the payments and settlements made against them need, because an export does not carry the opening balances. After that, `php artisan backup:import <zip>` after every backup. Do not add or change an opening balance on the live server after the copy: it would not reach the backup server; if it happens, `backup:import` says how many payments or settlements point at an opening debt or loan the backup server does not have.
- **Restoring:** (1) stop the web app, restore the newest dump into the database (`mysql ... < dump.sql`, after unzipping), start it; (2) enter again what only the web app held since the dump (users and customers first: they get their ids from the database); (3) **last**, run `php artisan backup:restored` (it takes the time of the newest dump; `--since="Y-m-d H:i:s"` gives it by hand). The Smart210s see the restore at their next download: each marks everything it uploaded after that time (minus 5 minutes) as not sent, says so in the download message, and the cashier presses **Upload data**. What is already on the server stays as it is (records are stored by their key). The terminals keep sent records `purge_days` (3), so this works for a dump up to about 3 days old. The terminal and the web app must use the same local time (the station's).
- A restore never brings back web-only work done after the dump; that is entered again by hand (step 2).

### Logs

- `storage/logs/laravel.log`: application errors.
- `storage/logs/audit-YYYY-MM-DD.log`: one audit file per day (`DATETIME - WHO - ACTION - DETAIL`), kept 90 days.

### Troubleshooting

- **`php` is the wrong version:** put the XAMPP PHP folder first in the `PATH` (for example `set PATH=D:\xampp\php;%PATH%` on Windows).
- **`Error on delete of ...\xampp\tmp\#sql... (Errcode: 13)` from MariaDB, or `ZipArchive::close(): Renaming temporary file failed`:** a virus scanner or indexer holds a new file on the drive for a moment. The backup retries both; if it keeps happening, exclude `D:\xampp\tmp`, `D:\xampp\mysql\data` and the project folder from the scanner, or point `tmpdir` in `D:\xampp\mysql\bin\my.ini` to a folder that is not scanned and restart the `mysql` service.
- **Windows: "rename ... Permission denied" while running artisan or composer:** antivirus is briefly locking a file. Run the command again.
- **MariaDB stops during `migrate`:** some XAMPP MariaDB builds crash on `ALTER TABLE ... ADD PRIMARY KEY`. The migrations already avoid it by creating those tables with raw SQL, so keep that style for any new table that needs a non-auto-increment primary key.
