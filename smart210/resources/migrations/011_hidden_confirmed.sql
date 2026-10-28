-- A card's top-ups and its old supplies are never removed from the terminal (the balance and the history of the card are made of them), but once the accountant
-- has confirmed them on the web app they are hidden: the Review screen and the checkout do not list a record with hidden = 1 (Purger::removeConfirmed).
ALTER TABLE sales ADD COLUMN hidden INTEGER NOT NULL DEFAULT 0;
ALTER TABLE supplements ADD COLUMN hidden INTEGER NOT NULL DEFAULT 0;
