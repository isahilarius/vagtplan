-- base.sql: opret tabeller og testdata
CREATE TABLE IF NOT EXISTS stations (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  rotation TEXT NOT NULL,      -- nu "AB_ABCD" for mandag A/B og fredag A/B/C/D
  switch_day TEXT NOT NULL,    -- standardiseret til "Monday"
  holiday_delay INTEGER NOT NULL DEFAULT 0,  -- 1 = udskyd mandags-skift hvis helligdag
  summer_override INTEGER NOT NULL DEFAULT 0 -- 1 = alle kaldt i ferieperioder
);

CREATE TABLE IF NOT EXISTS holidays (
  date TEXT PRIMARY KEY, -- 'YYYY-MM-DD'
  description TEXT
);

CREATE TABLE IF NOT EXISTS vacations (
  id INTEGER PRIMARY KEY,
  start_date TEXT NOT NULL,   -- 'YYYY-MM-DD'
  end_date TEXT NOT NULL,     -- 'YYYY-MM-DD'
  station_id INTEGER NOT NULL, -- reference til stations.id
  FOREIGN KEY (station_id) REFERENCES stations(id)
);

-- Ryd eksisterende data
DELETE FROM stations;
DELETE FROM holidays;
DELETE FROM vacations;



-- Indsæt brandstationer med rotation AB_ABCD og standard switch_day = Monday
INSERT INTO stations (name, rotation, switch_day, holiday_delay) VALUES
('Christianshavn', 'AB_ABCD', 'Monday', 1),
('Fælledvej', 'AB_ABCD', 'Monday', 1),
('Tomsgården', 'AB_ABCD', 'Monday', 1),
('Vesterbro', 'AB_ABCD', 'Monday', 1),
('Østerbro', 'AB_ABCD', 'Monday', 1),
('Hovedbrandstationen', 'AB_ABCD', 'Monday', 1),
('Frederiksberg', 'AB_ABCD', 'Monday', 1),
('Hvidovre', 'AB_ABCD', 'Monday', 1),
('Glostrup', 'AB_ABCD', 'Monday', 1),
('Store Magleby', 'AB_ABCD', 'Monday', 1),
('Dragør', 'AB_ABCD', 'Monday', 1);

-- Indsæt helligdage (2025)
INSERT INTO holidays (date, description) VALUES
('2025-04-21', '2. påskedag'),
('2025-06-09', '2. pinsedag'),
('2026-04-06', '2. påskedag'),
('2026-05-25', '2. pinsedag');

-- Indsæt sommerferie
INSERT INTO vacations (start_date, end_date, station_id) VALUES
('2026-07-01', '2026-08-10', 7);
