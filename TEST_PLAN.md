# Full test plan: web app, terminals and boards (written 2026-11-04)

The web app database `gasstation` and the dev terminal were **emptied on 2026-11-04** (a fresh install: only the `admin` user, the `Individual` customer and 3 default settings; backup of the old data in `web/storage/app/reset-backup-2026-11-04/`). This plan builds the station again from nothing, runs one business day by role, and then runs a week.

It is a manual test (people at the screens) that uses the same data and rules as the automatic ones: the unit tests, the end-to-end scenarios (`e2e/README.md`: basic, offline, restore, cards, cardsmany, day) and the backup rehearsal already cover the code paths; this plan checks **the whole system working together the way the staff will use it**, and finds what the automatic tests cannot (screens, printing, habits, the clock, wrong inputs).

| Part | What | Who |
|---|---|---|
| 0 | Before you start | tester |
| 1 | Initialize the data of the web app (and the first run of the terminal) | admin, manager |
| 2 | One business day by role | cashier, assistant, accountant |
| 3 | The week: seven business days | all |
| 4 | Pass criteria, defect log, going back to the start | tester |

---

## 0. Before you start

### 0.1 The parts

| Part | How |
|---|---|
| Web app | `http://localhost:8123` (the `gasstation-web` launch configuration) or Apache on port 80 of XAMPP; database `gasstation` on MariaDB (XAMPP, PHP 7.4.33). Sign in as `admin` (password in `README.md`). |
| Terminal (cashier) | The Smart210 program: on the board, or the PC build (`smart210/build/.../debug/smart210.exe`, `config.ini` next to it, `boards=true` to listen for the boards). Data in `data/smart210.db`; its settings in `device.ini`. Roles allowed to sign in: cashier only. |
| Boards | `simulator/` plays the RS-485 converter and six boards (addresses 1 to 6) by the same protocol; the terminal listens on the converter port (Machine Config, default 4001). Use it to lift and hang the gun, press the keys, set the flow, cut the power. With a real board and dispenser the same steps are the bench test (work plan 6.6). |
| Printer | The network printer of the terminal (Machine Config), or "Check printer" with a test host; look at the tickets on paper at least once (title, footer, barcode). |
| Clock | One PC clock serves the web app and the terminal. See 3.0 for how the days of the week are made. |

### 0.2 Rules the testers must know

- **Business day** = 9:00 to 9:00. A record made at 02:00 belongs to the day before. Records made within 30 minutes of 9:00 are marked "near 9:00: check the date" on the Review screen.
- **Tolerance:** the terminal and the web have none. The board has 0.03 kg: a fill that ends at most 30 g short of the preset is the preset; further short, the rest is a **remainder ticket**.
- **Tickets:** the first digit of a ticket number is the fuel (1 gasoline, 2 diesel); the second is the kind (press ticket, spot sale, self use, other use, ticket loan, card, remainder).
- **Money:** a terminal sale is always paid in full (a debt is made on the web only).
- **Roles on the web:** cashier (looks at his own sales, the supplies and the loans), assistant (ticket sales, debt payments, loan settlements, ticket lookup, edit/return/delete sales), accountant (everything of the assistant plus fuel prices, inbounds and outbounds, costs, the daily confirmation, reports, cards), manager (the accountant plus users and the un-confirm), admin (everything, go-live, opening balances).
- **After the upload** a record can still be changed on the terminal (comment, date): it goes up again. After the **confirmation** on the web it cannot be deleted; only the accountant or manager can take the confirmation back.

### 0.3 Record the results

Every table below has a **Result** column: write P (pass), F (fail) or N (not run), and the defect number from 4.2 for an F. Keep one copy of this file per run (`TEST_RUN_<date>.md`). Take a screenshot of every F.

### 0.4 The size of a business day

A business day of this station is **100 to 120 spot sales, 1 to 3 card sales (top-ups) and 180 to 250 supplies**, besides the ticket sales, the debt payments and the loans. Every simulated day of part 3 stays inside these ranges (3.1). The records of part 2 are the **checked** records inside a full day: they are made by hand and followed to the end; the rest of the day is filler of the same kinds.

- **Spot sales** (100 to 120): typed on the terminal's Spot sale tab, about 20 seconds each, so 40 minutes by hand; a mix of 60% diesel and 40% gasoline, 1 to 40 kg, mostly *Sale* with a few *Self use*, *Other* and *Ticket loan* (about 1 in 12).
- **Card sales** (1 to 3): top-ups, 20 to 100 kg, each followed by a card fill on a nozzle.
- **Supplies** (180 to 250): scan tickets at a nozzle, lift the gun, fill: about 2 minutes by hand, so 6 to 8 hours of one person's time a day. They need the boards (the simulator) and the ticket stock: about 2 tickets a supply, so 400 to 500 of the 47,700 tickets a day. Mix: 5 nozzles (1 and 2 gasoline, 3 and 4 diesel auto, 5 diesel manual), about 85% filled to the preset, 10% stopped short (a remainder ticket), 5% with a remainder ticket used again.
- **Fuel:** at 30 kg a supply the day takes about 6,000 kg out of the tanks (4,000 diesel, 2,000 gasoline), so the week takes about 43 tonnes (about 28 diesel and 14 gasoline). The tanks start full (T2 40 t, T1 25 t) and last the week; to test the limits take a tank low with outbounds on Day 5 and deliver once in the middle of the week, into a tank that is partly full (L4).

**The volume is made by a generator, not by hand.** Typing 200 supplies a day is not a realistic test of anything but patience. `e2e.exe devrun` (Dev.inc) already runs a working day on the terminal classes with the simulated boards, but only with 100 spot sales and a few dozen supplies: it has to be given the three counts (spot, card, supplies) and the mix above before it can run the days of this plan. Until then, run the checked records by hand and the filler with the generator as it is, and write the day's counts down in the ledger (3.9).
---

## 1. Initialize the data of the web app

Do this in order; the later steps use the earlier ones. All values are test values: change the names if you like, keep the numbers (the totals in part 2 depend on them).

### 1.1 Staff

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 1.1.1 | admin | Sign in as `admin`; **Users**: change the admin password | The new password works; the old one does not | |
| 1.1.2 | admin | Users: add `manager1` (manager), `acc1` (accountant), `asst1` (assistant), `cash1` and `cash2` (cashier) | Five users listed with their roles; each can sign in on the web | |
| 1.1.3 | admin | Sign in as each user: look at the menu | Each sees only the menus of his role (section 0.2); the cashier sees no Ticket Management; the assistant sees no Fuel Types, Tanks or Reports | |
| 1.1.4 | admin | Try to open a forbidden URL as the cashier (for example `/users`) | no access (403) | |

### 1.2 Fuel, tanks, nozzles, devices

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 1.2.1 | accountant | **Fuel Types**: `A` Gasoline, price 820, density 0.7400; `D` Diesel, price 750, density 0.8300 | Two fuel types; the prices are the default prices of a sale | |
| 1.2.2 | admin | **Tanks**: `T1` Gasoline (A), capacity 30,000 kg; `T2` Diesel (D), capacity 40,000 kg | Two tanks, stock 0 | |
| 1.2.3 | admin | **Nozzles**: 1 and 2 gasoline on T1; 3, 4 and 5 diesel on T2 (leave 6 out on purpose) | Five nozzles; each has its tank | |
| 1.2.4 | admin | **POS devices**: add the terminal with the IP address it calls from (127.0.0.1 for the PC build); set its PIN if the form asks for one | The device is listed; a call from another address is refused | |
| 1.2.5 | manager | **Customers**: `Acme Transport`, `Blue Taxi` and `North Farm` (credit customers, with a phone number); the built-in `Individual` stays | Three customers + Individual | |

### 1.3 The old tickets (before go-live): the history of the blank tickets received and published

The station has been selling press tickets for a year, so the web app starts with the **history of the blank tickets received (ticket inbounds)** and of the **tickets published** from them, all dated before go-live: 50,000 blank tickets received in 40 deliveries over the 12 months before 2026-10-31, 47,700 of them published (the rest is blank stock). Do this **before** the go-live date is set (1.4.1), and with the web app running (the `gasstation-web` launch configuration, port 8123).

```
php e2e\oldtickets.php --end=2026-10-31          (add --dry-run to see the 40 batches first)
```

It signs in as `admin` and uses the Ticket Inbound and Ticket Publish pages, so the rules and the audit log are the real ones. It stops without doing anything if the database already has ticket inbounds or tickets. The numbers are fixed, so every fresh install gets the same history.

| Kind (fuel : size) | Received | Published | Blank stock left |
|---|---|---|---|
| Diesel 5 kg | 8,000 | 7,620 | 380 |
| Diesel 10 kg | 12,000 | 11,620 | 380 |
| Diesel 15 kg | 4,000 | 3,750 | 250 |
| Diesel 50 kg | 1,000 | 870 | 130 |
| Gasoline 5 kg | 6,000 | 5,750 | 250 |
| Gasoline 10 kg | 14,000 | 13,470 | 530 |
| Gasoline 15 kg | 4,000 | 3,750 | 250 |
| Gasoline 50 kg | 1,000 | 870 | 130 |
| **Total** | **50,000** | **47,700** | **2,300** |

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 1.3.1 | admin | Run the script (above) | "88 checks, 0 failed"; 40 inbounds, 47,700 ticket rows | |
| 1.3.2 | assistant | **Ticket Management > Blank ticket inbound**: look at the history, filter by a month | 40 records between 2025-11-24 and 2026-10-04, each with a printing cost per ticket and a note (printer, order number); no record is dated after 2026-10-31 | |
| 1.3.3 | assistant | **Ticket Publish**: look at the history | 40 batches (the newest of each kind only partly published), each two days after its inbound; the serials of a kind follow one another without a gap | |
| 1.3.4 | assistant | **Summary Tickets** (Ticket Management) | The table above: received, published, blank stock for the 8 kinds; every published ticket is "in stock" (nothing sold or used) | |
| 1.3.5 | assistant | **Ticket Lookup**: a ticket of the first batch (diesel 10 kg, serial 1) and one of the last (gasoline 10 kg, serial 13470) | Both found, status in stock, with the publish batch and date | |
| 1.3.6 | accountant | **Daily Confirmation > Ticket stock**: look at an old date, e.g. 2026-01-07 | The old inbound and publish are listed as not confirmed (the history is not confirmed: decide whether the accountant confirms the old dates now or leaves them; write it down) | |
### 1.4 Opening balances and go-live (the day the system starts)

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 1.4.1 | admin | **Go-live**: set the go-live date to the first business day of the test | Saved; the page says that 40 publishes are dated before go-live and that 47,700 of their tickets have a record (in the stock) | |
| 1.4.2 | admin | Opening tank stock: T1 gasoline 20,000.00 kg at cost 600; T2 diesel 30,000.00 kg at cost 550, dated the day before go-live | **Tank Stock Summary** shows T1 20,000.00 and T2 30,000.00; the cost layers show one layer each | |
| 1.4.3 | admin | Opening debts: `Acme Transport` 60,000.00 (dated 10 days before go-live), `North Farm` 12,000.00 (3 days before) | **Debts > Summary** shows 72,000.00; **Aging** puts them in the right columns | |
| 1.4.4 | admin | Opening ticket loans: `Blue Taxi` 40.00 kg diesel (one loan, not settled) | Appears under **Ticket Loans** | |
| 1.4.5 | admin | Opening remainders: 2 remainder tickets (diesel 3.50 kg and gasoline 1.20 kg) | Listed; usable on the terminal as tickets | |
| 1.4.6 | admin | Old paper tickets: write down 10 diesel 10 kg tickets of the old history (serials 11,611 to 11,620) as the ones customers hold, sold on paper | They are in the stock; when one is scanned at a nozzle on the terminal it is accepted and becomes *supplied* | |
| 1.4.7 | admin | **Lock the opening balances** | Every opening entry becomes read-only; the banner says "locked since ..."; the cashier / accountant cannot change them | |

### 1.5 Stock the station

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 1.5.1 | assistant | **Ticket Management > Blank ticket stock**: look at what is left of the old history | 2,300 blank tickets (the table in 1.3) | |
| 1.5.2 | assistant | **Ticket Publish**: publish 200 diesel 10 kg and 200 gasoline 10 kg, dated the day before go-live (the form proposes the next serials: 11,621 and 13,471) | Success message with the serial range; the tickets are "in stock"; blank stock goes down by the same (diesel 10 kg 180 left, gasoline 10 kg 330) | |
| 1.5.3 | accountant | **Fuel Inbound**: diesel 10,000.00 kg at cost 560 into T2 (supplier invoice DN-001) and gasoline 5,000.00 kg at cost 610 into T1 | **Tank Stock Summary**: T2 40,000.00 kg (full: capacity 40,000), T1 25,000.00 kg | |
| 1.5.4 | accountant | Try an inbound of 1 kg more into T2 | Refused, or a clear warning that the tank would be over its capacity (write down which) | |

Baseline of the web app after part 1: T1 25,000.00 kg, T2 40,000.00 kg; tickets in stock (A / D) **24,040 / 24,060**: gasoline 5,750 + 13,470 + 3,750 + 870 + 200 = 24,040; diesel 7,620 + 11,620 + 3,750 + 870 + 200 = 24,060; blank stock 1,900; debts 72,000.00; one open ticket loan 40.00 kg. **Take a dump now** (`mysqldump -uroot gasstation > baseline-web.sql`) so that every later run can start from here (4.3).
### 1.6 First run of the terminal

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 1.6.1 | cashier | Start the terminal with an empty `data` folder | The server-address page opens | |
| 1.6.2 | cashier | Enter the server address; press **Ping** | "Connected" with the server time, time zone and offset | |
| 1.6.3 | cashier | Sign in as `cash1` (the cashier registers the device) | The login succeeds; the first sync starts and finishes ("Waiting for upload: 0") | |
| 1.6.4 | cashier | Look at the downloaded config: Sales page prices; ticket stock | Gasoline 820, diesel 750; the published tickets are available | |
| 1.6.5 | admin | On the terminal: Settings > PIN > **Machine Config**: set the printer; **add nozzles 1 to 5** with their fuel and tank; press Update and Read on each | Each board answers ("Verified"); nozzle 6 is not in the list; the table shows Online | |
| 1.6.6 | admin | Set nozzle 5 to **manual** mode (Update) | It is no longer polled ("Not polled"); the web app receives the nozzles on the next upload | |
| 1.6.7 | admin | Check the footer clock against the web server time; check the terminal clock guard (see 3.0) | They agree within a minute | |

**Exit of part 1:** every row passes; the baseline dump exists.

---

## 2. One business day, by role

Use **Day 1** of the week plan (3.1). The times are the order, not the clock. Roles work at the same time where the table says "parallel".

### 2.1 The script of the day

The numbers are fixed so that the totals can be checked at the end (2.5). `kg` are net kg. These are the checked records inside a full day (0.4: 100 to 120 spot sales, 1 to 3 card sales, 180 to 250 supplies): the rest of the day is made by the generator and is counted in the ledger, not followed one by one.

| # | Who | Where | Action | Expected | Result |
|---|---|---|---|---|---|
| **Morning** | | | | | |
| 2.1.1 | cashier | terminal | Sign in as `cash1`; look at the footer (waiting 0, connected); wait for or press the download | The day's header shows the business day; the download finishes | |
| 2.1.2 | cashier | terminal, Sales > Spot sale | **S1** diesel 10.00 kg, use *Sale*, Individual, cash | Amount 7,500.00; a ticket prints (diesel, letter D); the sale is in the list | |
| 2.1.3 | cashier | terminal | **S2** gasoline 5.00 kg, use *Sale* | Amount 4,100.00; letter A on the ticket | |
| 2.1.4 | cashier | terminal | **S3** diesel 2.00 kg use *Self use*; **S4** gasoline 1.00 kg use *Other*; **S5** diesel 3.00 kg use *Ticket loan* for `Blue Taxi` | Each sale prints its own ticket kind; S5 creates a ticket loan of 3.00 kg for Blue Taxi | |
| 2.1.5 | cashier | terminal, Sales > Ticket sale | **T1** `Acme Transport`: 6 diesel tickets of 10 kg (60.00 kg), receipt on | Amount 45,000.00, paid in full; the 6 tickets are marked sold | |
| 2.1.6 | cashier | terminal, Sales > Ticket sale | **T2** `Individual`: 2 gasoline tickets of 10 kg (20.00 kg), receipt **off** | Amount 16,400.00; the sale has no receipt (see 2.4 for F8) | |
| **Supplies** | | | | | |
| 2.1.7 | cashier + simulator | terminal, Supply | **P1** nozzle 3 (diesel, auto): press the select key on board 3 (or Select on screen); scan four of the diesel tickets of T1 (4 x 10 kg = 40 kg); lift the gun on the simulator; wait for the fill | Preset 40.00 kg; the board starts when the gun is up; the fill ends at 40.00 kg (dispensed = preset); no remainder | |
| 2.1.8 | cashier + simulator | terminal, Supply | **P2** nozzle 1 (gasoline, auto): scan the 2 gasoline tickets of T2 (2 x 10 kg = 20 kg); lift the gun; **hang the gun at about 18.50 kg** | The fill ends after 3 s on the hook; result 18.50 kg; a **remainder ticket of 1.50 kg** prints | |
| 2.1.9 | cashier + simulator | terminal, Supply | **P3** nozzle 1 again: scan the remainder ticket of P2 (1.50 kg) and 1 gasoline ticket of 10 kg (preset 11.50 kg); fill to the preset | The remainder ticket is accepted as a ticket; the preset is the sum; no new remainder | |
| 2.1.10 | cashier + simulator | boards | **P4** nozzle 5 (manual): type 15 on the keyboard, press Set; lift the gun; fill | Keyboard LCD: `H` then the typed text; after Set: kg and litres show the preset (litres by the density); fuel counts up from zero; at the end the displays **keep 15.00 kg** until the next preset; the terminal shows the dispenser, but records nothing | |
| 2.1.11 | cashier | terminal, Manual supply | Type in the reading of P4: nozzle 5, 15.00 kg, litres by the density, with two 10 kg diesel tickets (20 kg) | The supply is saved with its tickets; a **remainder of 5.00 kg** prints | |
| 2.1.12 | cashier | terminal, Review | Look at the Supplies tab | One total line + a line for **Gasoline** and one for **Diesel**; remainders counted | |
| **Cards** | | | | | |
| 2.1.13 | cashier | terminal, Cards | Register a card (UID, holder, PIN); top it up with 50.00 kg diesel (cash, 37,500.00) | The balance is 50.00 kg; a top-up record exists | |
| 2.1.14 | cashier + simulator | terminal, Cards + Supply | Read the card: the dispenser shows the **balance**; make a card fill with two 10 kg diesel tickets on nozzle 3 | The card pays for the tickets; the unused part goes back to the card; no paper remainder; the card ledger shows top-up and fill | |
| **Loans** | | | | | |
| 2.1.15 | cashier | terminal, Ticket debt | Settle the open loan of Blue Taxi (40.00 kg opening + 3.00 kg from S5) with 2 diesel tickets of 10 kg | The loan's settled amount is 20.00 kg; a ticket cannot settle twice | |
| **Check and upload** | | | | | |
| 2.1.16 | cashier | terminal, Review | Pick today: look at Sales, Supplies and Ticket Debt tabs and their totals | The sales totals match 2.5; **Card sales** has its own line; every record has its time and type | |
| 2.1.17 | cashier | terminal, Review | Edit the comment of S1 ("first sale"); try to move one record to a date 5 days back | The comment is saved and the record waits for the upload again; the 5-day move asks and is refused beyond the limit | |
| 2.1.18 | cashier | terminal, Review | Press **Upload data** | "Uploaded N records"; waiting 0; no refusal | |
| **Web, parallel to the morning** | | | | | |
| 2.1.19 | assistant | web, Sales > Ticket sales | **W1** `Acme Transport`: 10 diesel tickets of 10 kg (100 kg), amount 75,000.00, paid 30,000.00 | A debt of 45,000.00 is made; the tickets are sold | |
| 2.1.20 | assistant | web, Debts > Payments | Pay 15,000.00 for Acme Transport | The debt of this sale is 30,000.00; the customer's total debt goes down | |
| 2.1.21 | assistant | web, Ticket Management > Ticket Lookup | Look up a ticket used in P1 | Status *supplied*, with the supply and the date | |
| 2.1.22 | assistant | web, Sales | Return one ticket of W1 (a return entry); try to delete a sale made yesterday | The return is an entry, not a delete; the delete obeys the rules (only own, unconfirmed) | |
| 2.1.23 | accountant | web, Fuel Inbound/Outbound | Fuel outbound by hand: gasoline 100.00 kg (reason: lab sample) from T1 | T1 goes down by 100.00 kg; the outbound has a cost | |
| **After the upload** | | | | | |
| 2.1.24 | accountant | web, Sync Status | Look at the terminal's last upload | Today, no rejected rows, no conflicts | |
| 2.1.25 | accountant | web | Open **Sales Management**, **Supplements**, **Ticket Management**: find the terminal's records | Every record of 2.1.2 to 2.1.18 is there with the same weight, amount, type and ticket; the cashier column shows `cash1` | |

### 2.2 The next morning: the accountant's daily confirmation

Do this after the terminal has made its checkout at 9:00 (or move the clock past 9:00, see 3.0) and has uploaded.

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 2.2.1 | accountant | **Daily Confirmation > Overview**: pick Day 1 | Counts of unconfirmed records per page; the money of the day (sales, payments, credit) | |
| 2.2.2 | accountant | **Sales**: check the list against the cashier's paper / screen; tick and confirm | The records are confirmed; they can no longer be deleted | |
| 2.2.3 | accountant | **Supplements**: check the 5 supplies (P1, P2, P3, the manual P4 and the card fill); confirm | Each supply shows its tickets, remainder and its fuel outbound; confirming a supply confirms its outbound | |
| 2.2.4 | accountant | **Ticket stock**, **Fuel stock**, **Debts**: check against 2.5; confirm | The numbers equal the reconciliation of 2.5 | |
| 2.2.5 | accountant | Try to delete a confirmed sale as the assistant | Refused | |
| 2.2.6 | manager | Take one confirmation back (un-confirm); edit the comment; confirm again | Allowed for the manager and the accountant; the audit log has both lines | |
| 2.2.7 | accountant | After everything for Day 1 is confirmed | The backup dump is queued once the day is over and the terminal has uploaded since (Backup page); the dump file appears | |

### 2.3 The end-of-day reports (accountant)

| # | Action | Expected | Result |
|---|---|---|---|
| 2.3.1 | **Reports**: daily sales, supplies, ticket stock, tank stock | The same numbers as 2.5 | |
| 2.3.2 | **Debts > Summary, Aging, Statement** for Acme Transport | Opening 60,000.00 (aging by date) + W1 45,000.00 − 15,000.00 payment = 90,000.00 | |
| 2.3.3 | **Cards > Summary, Card Sales, Top Ups** | One card; top-up 50.00 kg; one fill; balance equals 2.1.13 − the ticket paid + the part that came back | |
| 2.3.4 | **Audit Log** | Every action of the day is there with who and when | |

### 2.4 Hidden shortcuts and side checks

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 2.4.1 | cashier | Review > Sales: press **F8** | The sales without a receipt (T2) disappear from the list, the line "1 sales without a receipt are not listed" shows; press F8 again: they return. The change is in the change log | |
| 2.4.2 | cashier | On the Settings pages press F8 | Nothing happens | |
| 2.4.3 | cashier | In a text field press **Right Alt** | The footer letter changes between A and 가; Korean letters are typed; a PIN field stays English | |
| 2.4.4 | cashier | Press the **Total** key on the dispenser keyboard (idle) | kg and litres show the board's total (whole kg over the two displays) for 5 s | |
| 2.4.5 | admin | Machine Config > nozzle 1 > **Reset total** | Asks first; the log has the value that was cleared; refused while a fill runs | |

### 2.5 Reconciliation of Day 1 (the numbers that must agree)

| What | Expected |
|---|---|
| Spot sales (S1 to S5) | 5 records; sale amounts 7,500.00 + 4,100.00 (+ the amounts the screen gives to the self / other / loan uses) |
| Ticket sales on the terminal | T1 45,000.00 (60 kg), T2 16,400.00 (20 kg) |
| Counts of the day | Spot sales 100 to 120; card sales 1 to 3; supplies 180 to 250 (Day 1: 104 / 2 / 196) |
| Ticket sales on the web | W1 75,000.00; paid 30,000.00 + 15,000.00 payment; debt left 30,000.00 |
| Supplies | P1 40.00 kg, P2 18.50 kg (remainder 1.50 kg), P3 = preset, manual 15.00 kg (remainder 5.00 kg), card fill = its preset |
| Diesel tank T2 | 40,000.00 − 40.00 (P1) − 15.00 (manual) − card fill diesel = 39,945.00 − card fill |
| Gasoline tank T1 | 25,000.00 − 18.50 (P2) − P3 − 100.00 (outbound) |
| Tickets | used tickets are *supplied*; sold-not-used are *sold*; remainder tickets are new and in stock; the totals on **Ticket stock** add up |
| Debts | Acme Transport 90,000.00; North Farm 12,000.00; total 102,000.00 |
| Card | balance = 50.00 − the paid ticket kg + the unused part |
| Terminal vs web | `Sync Status`: uploaded, no rejects; every terminal record found on the web with the same values |

**Exit of part 2:** all rows pass or have a defect number; the reconciliation is exact (to the hundredth of a kg and of money).

---

## 3. The week: seven business days

### 3.0 How to make the days

Two ways; pick one and write it in the run file.

- **A. Real days.** One business day per real day. Slow, but nothing is forced: the 9:00 checkout, the morning confirmation and the backup run as they will in life.
- **B. Compressed.** All days on one PC. Before each simulated day, set the Windows date and time to `08:30` of that day and **never go back** (the terminal's clock guard refuses new sales if its clock is behind its newest record; the web app's backdating limit counts from the server date). Restart nothing; just let the terminal download. After each day set the clock to the next 09:10 to let the checkout, the upload and the morning confirmation run, then to the next 08:30. If the clock went wrong, restore the baseline (4.3) and start again.

In both cases start from the baseline of part 1 and run **Day 1 = 2.1 to 2.3**. Days 2 to 7 below repeat the **daily loop** (3.2) and add one focus each.

### 3.1 The week at a glance

The three numbers of a day are drawn inside the ranges of 0.4; the week is **762 spot sales, 13 card sales and 1,446 supplies**.

| Day | Weekday | Focus | Spot / card / supplies | Special conditions |
|---|---|---|---|---|
| 1 | Mon | The baseline day (part 2) | 104 / 2 / 196 | none |
| 2 | Tue | **Price change, dates, edits** | 118 / 3 / 237 | Diesel price 750 → 760 at 9:00 (accountant); records near 9:00; edit after upload |
| 3 | Wed | **No network** | 107 / 1 / 188 | The terminal loses the server for the whole day; upload next morning |
| 4 | Thu | **Boards and power** | 112 / 2 / 214 | Power cut in a fill; board silent; gun hung and lifted; fault code |
| 5 | Fri | **Volume and limits** | 120 / 3 / 250 | The top of every range; a tank nearly full / nearly empty; ticket stock low; loans and debts ageing |
| 6 | Sat | **Corrections** | 101 / 1 / 181 | Returns, deletes, un-confirm, wrong tickets, a second cashier, a lost card |
| 7 | Sun | **Lowest-volume day and the week's close** | 100 / 1 / 180 | The bottom of every range; the weekly reports, backup and restore rehearsal, reconciliation of the week |

### 3.2 The daily loop (every day)

| # | Who | Action | Expected |
|---|---|---|---|
| L1 | cashier | Sign in; download; check the footer and the clock | connected, waiting 0 |
| L2 | cashier + generator | The day's volume from 3.1 (spot sales, card sales, supplies), with the mix of 0.4; at least one ticket sale, one manual-nozzle supply and one remainder ticket used among them | the counts of the Review screen equal the counts of 3.1; everything prints |
| L3 | assistant | 1 ticket sale on credit, 1 debt payment, 1 ticket lookup, publish tickets when the stock is below 50 | debt and stock numbers move as expected |
| L4 | accountant | A fuel inbound when a tank is below 40% (about 6,000 kg leave the tanks a day, so no delivery is needed before Day 4); an outbound when needed; check prices | tank stock summary equals opening + inbounds − outbounds |
| L5 | cashier | Review: check totals; **Upload data** at the end of the shift | waiting 0 |
| L6 | accountant | Next morning: Daily Confirmation of the finished day; reports; Sync Status | all confirmed; no rejects |
| L7 | accountant | Keep the **running ledger** (3.9): tank stock, tickets, debts, cash | it equals the web summary every morning |

### 3.3 Day 2 (Tue): price change, dates, edits

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 3.3.1 | accountant | Change the diesel price to 760 (Fuel Types) before 9:00 | The terminal gets it on its next download; sales before the change keep 750 | |
| 3.3.2 | cashier | At 08:50 make a spot sale (near 9:00); at 09:05 another | The first is marked "near 9:00: check the date"; the two belong to different days only if the clock is on different sides of 9:00 | |
| 3.3.3 | cashier | Review: move the 08:50 sale to the other day, and back | Asks first; the record goes up again; the date shows on the web | |
| 3.3.4 | cashier | After the upload edit the comment of an uploaded sale | The terminal sends it again; the web shows the new comment only | |
| 3.3.5 | assistant | Edit the date and comment of a ticket sale on the web within the allowed days; try beyond the limit | Allowed in, refused out; an audit line each | |
| 3.3.6 | accountant | A back-dated fuel inbound (yesterday) | Accepted with the `sales.backdate` rights; stock correct | |
| 3.3.7 | cashier | Make a ticket sale at the new price (760) and a supply with tickets sold at the old price | Both work; weights follow the tickets, amounts follow the sale | |

### 3.4 Day 3 (Wed): no network

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 3.4.1 | tester | Cut the terminal from the server (stop Apache or block the address) at 09:00 | The footer shows not connected; the cashier can still work | |
| 3.4.2 | cashier | Do the whole daily loop L1 to L5 offline: sales, ticket sales, supplies, remainders, a card top-up | Everything works and prints; "Waiting for upload" grows; an upload and a download fail with a clear message and lose nothing | |
| 3.4.3 | cashier | Past 9:00 next day: the automatic checkout stamps the finished day | The records have their date | |
| 3.4.4 | tester | Return the network | The terminal uploads the finished day by itself ("nothing waits" is reported to the server) | |
| 3.4.5 | accountant | Sync Status | Shows the lag (last upload) and no conflict; the confirmation of Day 3 can start | |
| 3.4.6 | assistant | During the outage keep selling tickets on the web | The tickets sold on the web are downloaded by the terminal after the return; a ticket sold on the web cannot be sold again on the terminal | |

### 3.5 Day 4 (Thu): boards and power

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 3.5.1 | simulator | Cut the power of board 3 in the middle of an auto fill; restore it | The board comes back with the **result of the interrupted fill** (reason interrupted); the terminal records it with its tickets and remainder | |
| 3.5.2 | simulator | Same on the manual nozzle 5 | The fill is dropped, nothing reported; the displays are zero after the restart | |
| 3.5.3 | tester | Kill the terminal program in the middle of a fill and start it again | The fill's result is recorded once; no duplicate on the web | |
| 3.5.4 | simulator | Make board 2 silent | The nozzle shows Offline; no command goes to it; after it answers again it is Online | |
| 3.5.5 | simulator | Drop the converter link in the middle of a fill | The fill finishes on the board; the terminal gets the result afterwards | |
| 3.5.6 | simulator | Hang the gun and lift it again within 3 s; hang it for good | The pump stops and starts with the gun; after 3 s on the hook the fill ends with what came out | |
| 3.5.7 | simulator | End a fill 20 g short of the preset (hang the gun) and another 100 g short | 20 g: result = preset, no remainder; 100 g: a remainder ticket for the rest | |
| 3.5.8 | simulator | Raise a fault (flow sensor) in a fill | The fill ends with reason fault; the fault shows on the Dispensers page; the board needs to be acknowledged | |
| 3.5.9 | cashier | Take the Total key / Reset total of a nozzle after the faults | The total counted every gram (also of the cut fills); the reset is logged | |
| 3.5.10 | tester | Power-cut the terminal PC in the middle of a sale | After the restart the database is sound; every sale has its ticket | |

### 3.6 Day 5 (Fri): volume and limits

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 3.6.1 | cashier + generator | The top of the ranges: 120 spot sales, 3 card sales, 250 supplies over the 5 nozzles, plus 40 ticket sales | The lists stay fast (Review, Sync Status, the web lists); the upload finishes in one go; the totals add up | |
| 3.6.2 | accountant | Bring T2 to within 100 kg of full; try an inbound that goes over | Refused or warned (write down which) | |
| 3.6.3 | accountant | Take T1 down to a low level with outbounds; sell a supply that needs more than the tank holds | Written down: does the stock go below zero, or is the supply refused? The Tank Stock Summary and the backup treat a level below zero (a row per cost) | |
| 3.6.4 | assistant | Sell tickets until the stock is below the warning; publish more | The blank stock and the published stock follow | |
| 3.6.5 | assistant | Settle loans with tickets (partly and fully); reverse a settlement | The loan's settled kg is the sum of the settlements | |
| 3.6.6 | accountant | **Debts > Aging** after the week's days passed | The old opening debt moves to an older column; payments are applied to the right debts | |
| 3.6.7 | accountant | Cost per kg: check the cost shown on a sale and a supply (the cost layers) | The cost follows the layers (opening 550 first, then 560); the tank stock summary values the stock by layers | |

### 3.7 Day 6 (Sat): corrections

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 3.7.1 | assistant | Return a whole ticket sale; delete a sale made by mistake today (own, unconfirmed) | The return is an entry; the delete works only before the confirmation | |
| 3.7.2 | accountant | Return a sale of an older confirmed day | The return is dated today; the confirmed record stays | |
| 3.7.3 | manager | Take the confirmation of Day 5 back; correct; confirm again | The corrected figures reach the reports | |
| 3.7.4 | cashier | Try to sell a ticket that was already sold / supplied / restricted | Refused with the reason | |
| 3.7.5 | accountant | **Restrict** a ticket (lost) and scan it at the terminal | The terminal refuses it after the next download | |
| 3.7.6 | cash2 | A second cashier signs in on the same terminal; makes sales | His sales carry his name; `cash1` sees his own list on the web only | |
| 3.7.7 | cashier | Lost card: the manager blocks it on the web; scan it at the terminal | The terminal refuses the blocked card after the download; a new card can be registered | |
| 3.7.8 | cashier | Card top-up with a wrong amount; the manager deletes the card sale | Only the manager (not the accountant) can delete it | |
| 3.7.9 | accountant | A sale with a wrong customer: edit on the web | Sales edit allows date, comment, receipt only; a wrong customer needs a return and a new sale | |

### 3.8 Day 7 (Sun): lowest-volume day and the close of the week

| # | Who | Action | Expected | Result |
|---|---|---|---|---|
| 3.8.1 | cashier + generator | The bottom of the ranges: 100 spot sales, 1 card sale, 180 supplies; upload | Normal | |
| 3.8.2 | accountant | Confirm all days of the week; look at the **Overview** counts | Every date shows 0 unconfirmed | |
| 3.8.3 | accountant | Weekly reports: sales by day and fuel, supplies by nozzle, tank stock, ticket stock, debts, cards | The sum of the seven daily figures equals the weekly figure | |
| 3.8.4 | admin | **Backup** page: the dump of each confirmed day exists; the retention keeps the newest | Files present; sizes grow with the data | |
| 3.8.5 | admin | Restore the newest dump into a scratch database (`gasstation_check`) and compare row counts with `gasstation` | Equal | |
| 3.8.6 | admin | `php e2e\backup_rehearsal.php gasstation` | The rehearsal passes | |
| 3.8.7 | admin | Run the automatic suites on the same PC: `e2e\run.ps1` (all) and the terminal and firmware unit tests | All pass (the e2e uses its own database and never touches `gasstation`) | |
| 3.8.8 | admin | Last: **Go-live**: lock the opening balances again if they were opened; check **Audit Log** for the week | Every change has who/when | |

### 3.9 The running ledger (keep it every day)

One sheet, one line per day. The web app's own summaries must equal it each morning.

| Day | Spot / card / supplies | T1 gasoline kg | T2 diesel kg | Press tickets in stock (A / D) | Sold not used | Cash in (sales + payments + top-ups) | Debts total | Card balances kg | Result |
|---|---|---|---|---|---|---|---|---|---|
| baseline | - | 25,000.00 | 40,000.00 | 24,040 / 24,060 | 0 | 0 | 72,000.00 | 0 | |
| 1 | | | | | | | | | |
| 2 | | | | | | | | | |
| 3 | | | | | | | | | |
| 4 | | | | | | | | | |
| 5 | | | | | | | | | |
| 6 | | | | | | | | | |
| 7 | | | | | | | | | |

Formulas: tank = previous + inbounds − supplies − outbounds; tickets in stock = published − sold on the web − sold on the terminal + remainders made − used; debts = previous + new credit − payments − returns; cash = sales paid + payments + top-ups − refunds.

---

## 4. Pass criteria, defects, starting again

### 4.1 The plan passes when

- Every row of parts 1 and 2 passes, and the reconciliation 2.5 is exact.
- The seven days of part 3 run, each day's ledger line equals the web summary, and the weekly reports equal the sum of the days.
- No record is lost, doubled or changed by an upload, a power cut, a network cut or a restore.
- No role can do what its row in 0.2 forbids, and every change is in the audit log.
- The tickets print legibly (title, footer, barcode), and the displays and keys of a dispenser behave as in `firmware/WORKFLOW.md` (with the simulator; with a real board it is also the bench test 6.6).

### 4.2 Defect log

| No | Day / step | What happened | Expected | Role | Screenshot | Status |
|---|---|---|---|---|---|---|
| D1 | | | | | | |

### 4.3 Starting again

1. Stop the terminal. Delete its `data` folder (and `device.ini` for a new terminal).
2. Restore the web app: `mysql -uroot -e "DROP DATABASE gasstation; CREATE DATABASE gasstation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"` and `mysql -uroot gasstation < baseline-web.sql`.
3. Set the clock back to the start (only here, never during a run).
4. Run 1.6 again (first run of the terminal). The old ticket history is part of the baseline dump, so it need not be generated again; after a fresh install run 1.3 first.

A full fresh install instead: `php artisan migrate:fresh --seed --force` in `web/` (this deletes everything; the old data was saved on 2026-11-04 in `web/storage/app/reset-backup-2026-11-04/`).
