-- A free payment card (the web app sets it, the manager or the admin): its top-up may be for nothing, a price of 0.
ALTER TABLE cards ADD COLUMN free_payment INTEGER NOT NULL DEFAULT 0;
