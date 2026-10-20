-- A remainder can have a comment (typed on the Remainders screen); it goes up with the remainder.
ALTER TABLE remainders ADD COLUMN comment TEXT;
