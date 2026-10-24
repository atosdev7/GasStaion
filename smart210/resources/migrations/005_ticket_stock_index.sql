-- The tickets of one fuel type and spec that are in stock, lowest barcode first (the Ticket sale screen asks for the first of them, or for a run of them).
-- Without this the database reads every unsold ticket of the fuel type (100000 of them in a big stock) to find the few it needs.
CREATE INDEX tickets_stock ON tickets (fuel_type, spec, status, barcode);
