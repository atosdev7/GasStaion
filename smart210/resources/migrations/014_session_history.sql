-- The sessions of the supplies are a history (the user, 2026-11-01): session_id is the primary key, a session keeps when it started and ended and how it ended, and
-- one that is over is removed after the purge days (Purger), like the records it made. status: started (the fill is saved and waits for its result; a nozzle has at most
-- one), completed (its result was recorded), cancelled (it did not run: the board refused START, or a new session of the nozzle took its place).
-- SQLite cannot change a primary key, so the table is made again. The sessions of before the session ids (session_id 0) belong to fills of an old board and are not kept: the
-- queue of their nozzle stays and is not locked any more.
CREATE TABLE supply_sessions_new (
    session_id    INTEGER PRIMARY KEY,
    nozzle_number INTEGER NOT NULL,
    card_id       TEXT,
    preset_kg     INTEGER NOT NULL,
    started_at    TEXT NOT NULL,
    ended_at      TEXT,
    status        TEXT NOT NULL DEFAULT 'started'
);
INSERT INTO supply_sessions_new (session_id, nozzle_number, card_id, preset_kg, started_at, ended_at, status)
    SELECT session_id, nozzle_number, card_id, preset_kg, started_at, NULL, 'started' FROM supply_sessions WHERE session_id > 0;
DROP TABLE supply_sessions;
ALTER TABLE supply_sessions_new RENAME TO supply_sessions;
CREATE INDEX supply_sessions_nozzle ON supply_sessions (nozzle_number, status);
