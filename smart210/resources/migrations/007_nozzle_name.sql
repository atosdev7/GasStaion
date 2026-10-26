-- The name of a nozzle: typed on Machine Config (Settings), uploaded with the nozzle, shown in the lists of the nozzles ("Nozzle <number>" while it has none).
ALTER TABLE nozzles ADD COLUMN name TEXT;
