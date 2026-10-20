-- The schema of the Smart210 database. A migration is one numbered .sql file; the number is the database's
-- PRAGMA user_version once it has run. Never edit a migration that has shipped to a terminal: add the next number.
--
-- Written for SQLite 3.8.4, the version inside Qt 5.3.2: no UPSERT (ON CONFLICT ... DO UPDATE), no RETURNING, no
-- RENAME COLUMN or DROP COLUMN, no window functions. Use INSERT OR REPLACE, and rebuild a table to change it.
--
-- Conventions
--   * Decimals are INTEGERS, so sums are exact: kg, money and litres are stored x100 (12.50 is 1250), density x10000
--     (0.5400 is 5400). The server sends and expects strings such as "12.50".
--   * A ticket barcode is its 16-digit number as an INTEGER (leading zeros are dropped, add them back to 16 digits to
--     print or send). It fits easily in 64 bits.
--   * Times are 'YYYY-MM-DD HH:MM:SS' in the station's local time, dates are 'YYYY-MM-DD', like the API.
--   * Records the Smart210 creates have a UUID text id and a `synced` flag: 0 until the cashier has uploaded them
--     (`uploaded_at` says when). A pulled row that is still unsynced locally must not be overwritten by a pull.
--   * The column names and their order follow the web app's tables: the key, what identifies the row, the references,
--     the facts, who (user_id), the dates, then the sync flags. `sale_date`, `supply_date` and `remainder_date` (and `date` of
--     loans, settlements and checkouts) are the day of the daily checkout; they stay empty until the checkout stamps them.

-- ---------------------------------------------------------------------------------------------------------------------
-- Settings and configuration

CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

-- Small config, replaced whole by every config pull.
CREATE TABLE fuel_types (
    fuel_type TEXT PRIMARY KEY,
    name      TEXT NOT NULL,
    price     INTEGER NOT NULL,
    density   INTEGER
);

CREATE TABLE tanks (
    id          INTEGER PRIMARY KEY,
    name        TEXT NOT NULL,
    fuel_type   TEXT NOT NULL,
    capacity_kg INTEGER,
    stock_kg    INTEGER NOT NULL
);

-- The nozzles are the boards 1 to 6 and each board holds its own config: the terminal keeps what the board last reported.
--   mode:    0 auto, 1 manual (the board's mode)
--   density: kg per litre x 10000, as the board reports it (empty while the board has none)
--   seen_at: when the board last answered with its config (empty for a nozzle that never did)
-- tank_id 0: no tank picked yet. A nozzle made or changed here waits for the upload (synced = 0); a deleted nozzle stays (deleted = 1).
CREATE TABLE nozzles (
    id            INTEGER PRIMARY KEY,
    number        INTEGER NOT NULL,
    tank_id       INTEGER NOT NULL,
    fuel_type     TEXT NOT NULL,
    board_address INTEGER,
    mode          INTEGER NOT NULL DEFAULT 0,
    density       INTEGER,
    seen_at       TEXT,
    synced        INTEGER NOT NULL DEFAULT 1,
    deleted       INTEGER NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX nozzles_number_live ON nozzles (number) WHERE deleted = 0;
CREATE INDEX nozzles_unsynced ON nozzles (synced) WHERE synced = 0;

-- What the technician sets on the terminal itself (Settings > Machine Config, behind the device PIN), kept apart from the
-- nozzles table. pulses_per_liter: the flow meter's constant of one nozzle, x100 like the other decimals (12.50 pulses is 1250).
CREATE TABLE nozzle_config (
    nozzle_id        INTEGER PRIMARY KEY,
    pulses_per_liter INTEGER NOT NULL
);

-- Every change made on the Machine Config page: when, by which cashier, what, and the old and new value.
CREATE TABLE config_changes (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    changed_at TEXT NOT NULL,
    user_id    INTEGER,
    what       TEXT NOT NULL,
    old_value  TEXT,
    new_value  TEXT
);

-- ---------------------------------------------------------------------------------------------------------------------
-- Pulled from the web app

-- Deleted rows stay (deleted = 1) because sales still point at them.
CREATE TABLE users (
    id       INTEGER PRIMARY KEY,
    username TEXT NOT NULL,
    name     TEXT NOT NULL,
    role     TEXT NOT NULL,
    pos      INTEGER NOT NULL DEFAULT 0,
    active   INTEGER NOT NULL DEFAULT 1,
    pos_salt TEXT,
    pos_hash TEXT,
    deleted  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE customers (
    id      INTEGER PRIMARY KEY,
    name    TEXT NOT NULL,
    phone   TEXT,
    is_self INTEGER NOT NULL DEFAULT 0,
    deleted INTEGER NOT NULL DEFAULT 0
);

-- ---------------------------------------------------------------------------------------------------------------------
-- Tickets

-- Pulled, and changed here when a ticket is sold or supplied (synced = 0 until uploaded). The Smart210 may also create
-- tickets with the reserved codes 21-25 (a spot sale's ticket).
CREATE TABLE tickets (
    barcode       INTEGER PRIMARY KEY,
    fuel_type     TEXT NOT NULL,
    spec          INTEGER,
    amount_kg     INTEGER NOT NULL,
    status        INTEGER NOT NULL,
    sale_id       TEXT,
    sale_date     TEXT,
    supplement_id TEXT,
    supply_date   TEXT,
    synced        INTEGER NOT NULL DEFAULT 1,
    uploaded_at   TEXT
);
CREATE INDEX tickets_status ON tickets (status, fuel_type);
CREATE INDEX tickets_sale_id ON tickets (sale_id);
CREATE INDEX tickets_unsynced ON tickets (synced) WHERE synced = 0;

-- Pulled (loans made on the web) and created here by a ticket_loan sale. settled_amount_kg is owned by the server and
-- also raised locally when a settlement is entered, until the next pull brings the server's total.
CREATE TABLE ticket_loans (
    id                TEXT PRIMARY KEY,
    loaned_at         TEXT NOT NULL,
    date              TEXT,
    customer_id       INTEGER NOT NULL,
    fuel_type         TEXT NOT NULL,
    amount_kg         INTEGER NOT NULL,
    settled_amount_kg INTEGER NOT NULL DEFAULT 0,
    sale_id           TEXT,
    comment           TEXT,
    user_id           INTEGER,
    deleted           INTEGER NOT NULL DEFAULT 0,
    synced            INTEGER NOT NULL DEFAULT 1,
    uploaded_at       TEXT
);
CREATE INDEX ticket_loans_customer ON ticket_loans (customer_id);
CREATE INDEX ticket_loans_unsynced ON ticket_loans (synced) WHERE synced = 0;

-- A ticket handed back against a loan.
CREATE TABLE ticket_loan_settlements (
    id             TEXT PRIMARY KEY,
    loan_id        TEXT NOT NULL,
    ticket_barcode INTEGER NOT NULL,
    amount_kg      INTEGER NOT NULL,
    settled_at     TEXT NOT NULL,
    date           TEXT,
    comment        TEXT,
    user_id        INTEGER NOT NULL,
    synced         INTEGER NOT NULL DEFAULT 0,
    uploaded_at    TEXT
);
CREATE INDEX ticket_loan_settlements_loan ON ticket_loan_settlements (loan_id);
CREATE INDEX ticket_loan_settlements_unsynced ON ticket_loan_settlements (synced) WHERE synced = 0;

-- ---------------------------------------------------------------------------------------------------------------------
-- Cards

-- RFID fuel cards. The card is only an id (`uid`); what it holds is a balance in kg x100 of its one fuel type.
--   Registered here by the cashier (synced = 0 until uploaded) and pulled from the web app, where the manager and the accountant unlock a
--   card, block a lost one and reset a PIN. The PIN is only kept as a salted SHA-256 (`pin_salt`, `pin_hash`), checked on the terminal.
--   `balance_kg` is the balance as this terminal sees it now: the web app's figure at the last pull plus what was topped up here and
--   taken here since, so it is right before anything is uploaded; a top-up or a supply changes it in the same transaction.
--   `failed_attempts` counts the wrong PINs; the 5th locks the card (status 'locked'). status: active, locked, blocked.
CREATE TABLE cards (
    id              TEXT PRIMARY KEY,
    uid             TEXT NOT NULL,
    holder_name     TEXT NOT NULL,
    holder_phone    TEXT,
    fuel_type       TEXT NOT NULL,
    pin_salt        TEXT NOT NULL,
    pin_hash        TEXT NOT NULL,
    failed_attempts INTEGER NOT NULL DEFAULT 0,
    status          TEXT NOT NULL DEFAULT 'active',
    balance_kg      INTEGER NOT NULL DEFAULT 0,
    deleted         INTEGER NOT NULL DEFAULT 0,
    synced          INTEGER NOT NULL DEFAULT 1,
    uploaded_at     TEXT
);
CREATE UNIQUE INDEX cards_uid ON cards (uid);
CREATE INDEX cards_unsynced ON cards (synced) WHERE synced = 0;

-- ---------------------------------------------------------------------------------------------------------------------
-- Records made here, uploaded by the cashier

-- deleted_at is a delete made before the upload: such a row is never sent. sale_type spot, ticket or card (a top-up: card_id).
CREATE TABLE sales (
    id             TEXT PRIMARY KEY,
    created_at     TEXT NOT NULL,
    updated_at     TEXT,
    customer_id    INTEGER,
    card_id        TEXT,
    sale_type      TEXT NOT NULL,
    fuel_type      TEXT NOT NULL,
    price          INTEGER NOT NULL,
    amount_kg      INTEGER NOT NULL,
    payment_method TEXT NOT NULL,
    total_amount   INTEGER NOT NULL,
    paid_amount    INTEGER NOT NULL,
    unpaid_amount  INTEGER NOT NULL DEFAULT 0,
    receipt        TEXT,
    usage          TEXT NOT NULL,
    comment        TEXT,
    sale_date      TEXT,
    user_id        INTEGER NOT NULL,
    deleted_at     TEXT,
    synced         INTEGER NOT NULL DEFAULT 0,
    uploaded_at    TEXT
);
CREATE INDEX sales_sale_date ON sales (sale_date);
CREATE INDEX sales_created_at ON sales (created_at);
CREATE INDEX sales_unsynced ON sales (synced) WHERE synced = 0;

-- The lines of a ticket sale as the cashier entered them (a range of serials of one spec); they travel with the sale.
CREATE TABLE sale_items (
    id           TEXT PRIMARY KEY,
    sale_id      TEXT NOT NULL,
    spec         INTEGER NOT NULL,
    start_serial INTEGER NOT NULL,
    end_serial   INTEGER NOT NULL,
    quantity     INTEGER NOT NULL,
    amount_kg    INTEGER NOT NULL
);
CREATE INDEX sale_items_sale ON sale_items (sale_id);

-- A fill. mode: auto (the terminal set the preset) or manual (typed at the dispenser, or a reading typed in by hand).
-- A fill paid with a card has its card_id and no tickets. density is kg per litre x10000. board_result_id is the board's
-- result number (a fill is recorded once).
CREATE TABLE supplements (
    id              TEXT PRIMARY KEY,
    fuel_type       TEXT NOT NULL,
    nozzle_id       INTEGER NOT NULL,
    card_id         TEXT,
    supply_date     TEXT,
    supplied_at     TEXT NOT NULL,
    mode            TEXT NOT NULL DEFAULT 'auto',
    preset_kg       INTEGER NOT NULL,
    density         INTEGER,
    out_amount_lt   INTEGER NOT NULL,
    out_amount_kg   INTEGER NOT NULL,
    comment         TEXT,
    board_result_id INTEGER,
    synced          INTEGER NOT NULL DEFAULT 0,
    uploaded_at     TEXT
);
CREATE UNIQUE INDEX supplements_board_result ON supplements (nozzle_id, board_result_id) WHERE board_result_id IS NOT NULL;
CREATE INDEX supplements_supply_date ON supplements (supply_date);
CREATE INDEX supplements_unsynced ON supplements (synced) WHERE synced = 0;
CREATE INDEX supplements_card ON supplements (card_id) WHERE card_id IS NOT NULL;

-- The rest of a fill that gave less than its tickets covered: a new ticket for the customer. printed_at: its ticket was printed.
CREATE TABLE remainders (
    id             TEXT PRIMARY KEY,
    supplement_id  TEXT NOT NULL,
    ticket_barcode INTEGER NOT NULL,
    fuel_type      TEXT NOT NULL,
    amount_kg      INTEGER NOT NULL,
    remainder_date TEXT,
    created_at     TEXT NOT NULL,
    printed_at     TEXT,
    synced         INTEGER NOT NULL DEFAULT 0,
    uploaded_at    TEXT
);
CREATE INDEX remainders_supplement ON remainders (supplement_id);
CREATE INDEX remainders_unsynced ON remainders (synced) WHERE synced = 0;

-- ---------------------------------------------------------------------------------------------------------------------
-- A fill in progress

-- The tickets scanned for a nozzle, in the order they came (a ticket is in one queue only).
CREATE TABLE supply_queue (
    barcode       INTEGER PRIMARY KEY,
    nozzle_number INTEGER NOT NULL,
    amount_kg     INTEGER NOT NULL,
    position      INTEGER NOT NULL,
    added_at      TEXT NOT NULL
);
CREATE INDEX supply_queue_nozzle ON supply_queue (nozzle_number, position);

-- A nozzle whose fill was started: saved before the preset is sent, so a restart knows it; its queue is locked until the result is
-- recorded. card_id: the fill is paid with a card, not with tickets.
CREATE TABLE supply_sessions (
    nozzle_number INTEGER PRIMARY KEY,
    card_id       TEXT,
    preset_kg     INTEGER NOT NULL,
    result_id     INTEGER NOT NULL DEFAULT 0,
    started_at    TEXT NOT NULL
);

-- ---------------------------------------------------------------------------------------------------------------------
-- Checkout and upload bookkeeping

-- The checkout log: every time records were checked out (stamped with their business day, 9 AM to 9 AM). One line per run and
-- business day. mode: manual (the cashier pressed Checkout), auto (9 AM, or the catch-up at start) or upload (the upload checks
-- out what was still open first). user_id is empty for an automatic run.
CREATE TABLE checkouts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    date         TEXT NOT NULL,
    mode         TEXT NOT NULL,
    user_id      INTEGER,
    performed_at TEXT NOT NULL,
    records      INTEGER NOT NULL
);
CREATE INDEX checkouts_date ON checkouts (date);

-- Why the web app refused a row at the last upload, so the cashier can see it. One line per row, replaced by the next
-- refusal of the same row and removed when the row is finally accepted.
CREATE TABLE upload_errors (
    table_name TEXT NOT NULL,
    row_key    TEXT NOT NULL,
    message    TEXT NOT NULL,
    created_at TEXT NOT NULL,
    PRIMARY KEY (table_name, row_key)
);
