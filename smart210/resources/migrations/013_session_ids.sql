-- A supply session has an id made by the terminal (the next number, see SupplyQueue::begin). The id goes to the board with START, the board keeps it with the
-- result and sends it back with the result and with ACK_RESULT, so a fill is known by it and not by a counter of the board (a board that was replaced or reset
-- starts its own counter again). The supply keeps the id: the tickets of a session are found through it (SupplyQueue::ticketsOfSession).
-- supply_sessions.result_id and supplements.board_result_id are the old board counters: they stay in the rows that have them and are not written any more.
-- add-column-if-missing: supply_sessions session_id INTEGER NOT NULL DEFAULT 0
-- add-column-if-missing: supplements session_id INTEGER
DROP INDEX IF EXISTS supplements_board_result;
CREATE UNIQUE INDEX IF NOT EXISTS supplements_session ON supplements (session_id) WHERE session_id IS NOT NULL;
