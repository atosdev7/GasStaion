-- A ticket made for a card (the cashier asks for fuel with the card: the card pays with its kg and a ticket is printed for the supplier to scan,
-- like the ticket of a spot sale). card_id: the card that paid for it; the ticket has no sale.
ALTER TABLE tickets ADD COLUMN card_id TEXT;
CREATE INDEX tickets_card ON tickets (card_id) WHERE card_id IS NOT NULL;
