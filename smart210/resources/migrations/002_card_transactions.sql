-- The ledger of the cards (the web app card_transactions, pulled): every top-up and fill of a card with the running balance after it. Amounts are x100.
-- Read only here: the web app makes the entries (from the sales and supplements the terminal uploads) and works the balances out.
CREATE TABLE card_transactions (
    id           TEXT PRIMARY KEY,
    card_id      TEXT NOT NULL,
    type         TEXT NOT NULL,        -- topup, fill or reversal
    kg           INTEGER NOT NULL,     -- signed: + in, - out
    balance_kg   INTEGER NOT NULL,     -- the balance of the card after this entry
    price        INTEGER,
    total_amount INTEGER,
    source_id    TEXT NOT NULL,        -- the sale (top-up) or the supplement (fill) it came from
    occurred_at  TEXT NOT NULL,
    comment      TEXT
);
CREATE INDEX card_transactions_card ON card_transactions (card_id, occurred_at);
CREATE INDEX card_transactions_source ON card_transactions (type, source_id);
