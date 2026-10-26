-- How many times a ticket was printed (every successful send to the printer: the first print and each Print again) and when it was last printed.
-- Uploaded with the ticket; the web app keeps the larger count.
ALTER TABLE tickets ADD COLUMN print_count INTEGER NOT NULL DEFAULT 0;
ALTER TABLE tickets ADD COLUMN last_printed_at TEXT;
