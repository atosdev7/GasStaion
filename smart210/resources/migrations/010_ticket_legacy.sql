-- The press tickets published before the web app's go-live date and sold on paper before it (config `ticket_legacy`): no row is kept for them, only their
-- ranges. A ticket with no row whose barcode is inside a range is a ticket sold before go-live; its row is made when it is used (TicketLegacy).
-- lo / hi are the barcode numbers (sign + code + serial as one number) of the first and the last ticket of the range; kg is x100.
CREATE TABLE ticket_legacy (
    lo        INTEGER NOT NULL,
    hi        INTEGER NOT NULL,
    fuel_type TEXT NOT NULL,
    spec      INTEGER,
    kg        INTEGER NOT NULL,
    day       TEXT NOT NULL            -- the day of the publish: the day the ticket counts as published and sold
);
CREATE INDEX ticket_legacy_lo ON ticket_legacy (lo);
