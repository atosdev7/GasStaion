-- The order the web app made the entries of a card ledger in (two entries of the same second keep it): the History list goes by the time, then this.
ALTER TABLE card_transactions ADD COLUMN seq INTEGER NOT NULL DEFAULT 0;
