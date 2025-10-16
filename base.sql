-- Oprettelse af tabeller
CREATE TABLE IF NOT EXISTS stations (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  rotation TEXT NOT NULL,      
  holiday_delay INTEGER NOT NULL DEFAULT 0
);


CREATE TABLE IF NOT EXISTS holidays (
  date TEXT PRIMARY KEY, 
  description TEXT
);

CREATE TABLE IF NOT EXISTS vacations (
  id INTEGER PRIMARY KEY,
  start_date TEXT NOT NULL,  
  end_date TEXT NOT NULL,   
  station_id INTEGER NOT NULL, 
  FOREIGN KEY (station_id) REFERENCES stations(id)
);

-- Ryd eksisterende data
DELETE FROM stations;
DELETE FROM holidays;
DELETE FROM vacations;


-- Indsæt stationer 
INSERT INTO stations (name, rotation, holiday_delay) VALUES
('Christianshavn', 'AB_ABCD', 1),
('Fælledvej', 'AB_ABCD', 1),
('Tomsgården', 'AB_ABCD', 1),
('Vesterbro', 'AB_ABCD', 1),
('Østerbro', 'AB_ABCD', 1),
('Hovedbrandstationen', 'AB_ABCD', 1),
('Frederiksberg', 'AB_ABCD', 1),
('Hvidovre', 'AB_ABCD', 1),
('Glostrup', 'AB_ABCD', 1),
('Store Magleby', 'AB_ABCD', 1),
('Dragør', 'AB_ABCD', 1);

-- Indsæt helligdage 
INSERT INTO holidays (date, description) VALUES
('2025-04-21', '2. påskedag'),
('2025-06-09', '2. pinsedag'),
('2026-04-06', '2. påskedag'),
('2026-05-25', '2. pinsedag');

-- Indsæt ferieperioder
INSERT INTO vacations (start_date, end_date, station_id) VALUES
('2026-07-01', '2026-08-10', 7);
