-- A fill paid with a card (the cashier reads the card and the PIN, sets the kg, the supplier fills; the card is charged what really came out): the kg
-- the cashier set are held on the card from the moment they are set until the result is recorded or the fill is cancelled, so that the same card cannot
-- be filled twice, or fill and make a ticket, with the same kg. One row per nozzle (a nozzle has one fill at a time), one open fill per card.
CREATE TABLE card_holds (
    nozzle_number INTEGER PRIMARY KEY,
    card_id       TEXT NOT NULL,
    preset_kg     INTEGER NOT NULL,
    created_at    TEXT NOT NULL
);
CREATE UNIQUE INDEX card_holds_card ON card_holds (card_id);
