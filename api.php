<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

$dbfile = __DIR__ . '/vagtskema.db';
if (!file_exists($dbfile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database vagtskema.db ikke fundet. Kør base.sql først.']);
    exit;
}

$db = new SQLite3($dbfile);
$endpoint = $_GET['endpoint'] ?? '';



##### ADMIN

// Hent alle stationer (til dropdown)
if ($endpoint === 'getStations') {
    $res = $db->query("SELECT id, name FROM stations ORDER BY name ASC");
    $stations = [];
    while ($s = $res->fetchArray(SQLITE3_ASSOC)) {
        $stations[] = $s;
    }
    echo json_encode($stations);
    exit;
}

// Hent alle helligdage
if ($endpoint === 'getHolidays') {
    $stmt = $db->query("SELECT rowid AS id, date, description FROM holidays ORDER BY date ASC");
    $holidays = [];
    while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) {
        $holidays[] = $row;
    }
    echo json_encode($holidays);
    exit;
}

// Tilføj helligdag
if ($endpoint === 'addHoliday' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $date = $data['date'] ?? null;
    $desc = $data['description'] ?? null;

    if ($date && $desc) {
        $stmt = $db->prepare("INSERT INTO holidays (date, description) VALUES (?, ?)");
        $stmt->bindValue(1, $date, SQLITE3_TEXT);
        $stmt->bindValue(2, $desc, SQLITE3_TEXT);
        $stmt->execute();
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Manglende data"]);
    }
    exit;
}

// Slet helligdag
if ($endpoint === 'deleteHoliday' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    if ($id) {
        $stmt = $db->prepare("DELETE FROM holidays WHERE rowid = ?");
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->execute();
        echo json_encode(["status" => "deleted"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Ingen ID"]);
    }
    exit;
}

// Hent alle ferieperioder
if ($endpoint === 'getVacations') {
    $stmt = $db->query("
        SELECT v.id, v.start_date, v.end_date, s.name AS station_name, v.station_id
        FROM vacations v
        LEFT JOIN stations s ON s.id = v.station_id
        ORDER BY v.start_date ASC
    ");
    $vacations = [];
    while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) {
        $vacations[] = $row;
    }
    echo json_encode($vacations);
    exit;
}

// Tilføj ferie
if ($endpoint === 'addVacation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $start = $data['start_date'] ?? null;
    $end = $data['end_date'] ?? null;
    $station_id = $data['station_id'] ?? null;

    if ($start && $end && $station_id) {
        $stmt = $db->prepare("INSERT INTO vacations (start_date, end_date, station_id) VALUES (?, ?, ?)");
        $stmt->bindValue(1, $start, SQLITE3_TEXT);
        $stmt->bindValue(2, $end, SQLITE3_TEXT);
        $stmt->bindValue(3, $station_id, SQLITE3_INTEGER);
        $stmt->execute();
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Manglende data"]);
    }
    exit;
}

// Slet ferie
if ($endpoint === 'deleteVacation' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    if ($id) {
        $stmt = $db->prepare("DELETE FROM vacations WHERE id = ?");
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->execute();
        echo json_encode(["status" => "deleted"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Ingen ID"]);
    }
    exit;
}

##### ADMIN SLUT



if ($endpoint === 'stations') {
    $res = $db->query('SELECT id, name FROM stations');
    $out = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $out[] = $r;
    echo json_encode($out);
    exit;
}

if ($endpoint === 'group') {
    if (!isset($_GET['stationId']) || !isset($_GET['date'])) {
        http_response_code(400);
        echo json_encode(['error' => 'stationId og date (YYYY-MM-DD) kræves']);
        exit;
    }

    $stationId = intval($_GET['stationId']);
    $date = $_GET['date'];
    $anchor = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' 08:00:00');
    if (!$anchor) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldigt datoformat. Brug YYYY-MM-DD']);
        exit;
    }

    // Hent station
    $stmt = $db->prepare('SELECT * FROM stations WHERE id = :id');
    $stmt->bindValue(':id', $stationId, SQLITE3_INTEGER);
    $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$r) {
        http_response_code(404);
        echo json_encode(['error' => 'Station ikke fundet']);
        exit;
    }

    $stationName = $r['name'];
    // Rotation parse
    $rotationAll = explode('_', $r['rotation']);
    $mandagRot = str_split($rotationAll[0] ?? 'AB');
    $fredagRot = str_split($rotationAll[1] ?? 'ABCD');
    $holidayDelay = intval($r['holiday_delay']) === 1;
    $summerOverride = intval($r['summer_override']) === 1;

    // Sommerferie-check
    $qv = $db->prepare("SELECT 1 FROM vacations WHERE station_id = :sid AND start_date <= :d AND end_date >= :d LIMIT 1");
    $qv->bindValue(':sid', $stationId, SQLITE3_INTEGER);
    $qv->bindValue(':d', $date, SQLITE3_TEXT);
    $inVacation = !!$qv->execute()->fetchArray();
    
    if ($inVacation) {
    echo json_encode([
        'station' => $stationName,
        'group' => 'ALLE',
        'reason' => 'Ferieperiode – alle kaldt ind'
    ]);
    exit;
}

    // Helligdage
    $holidays = [];
    $resH = $db->query("SELECT date FROM holidays");
    while ($h = $resH->fetchArray(SQLITE3_ASSOC)) $holidays[] = $h['date'];

    // Simulation fra baseMonday til anchor - vi simulerer dag-for-dag så vi bevarer rækkefølgen korrekt
    $baseMonday = new DateTime('2025-01-06 08:00:00'); // reference start
    if ($anchor < $baseMonday) {
        // Hvis anmodet dato er før base, brug første kendte værdier
        $activeGroup = $mandagRot[0];
        echo json_encode(['station' => $stationName, 'group' => $activeGroup, 'reason' => 'Fallback (før base-dato)']);
        exit;
    }

    $current = clone $baseMonday;
    // Indekser til næste rotationselement
    $mondayIndex = 0;   // næste mandags-indeks der skal tages ved næste mandagsskift
    $fridayIndex = 0;   // næste fredags-indeks
    $lastMondayGroup = null;
    $lastFridayGroup = null;
    $thisWeeksShiftDay = null; // gemmer denne uges skiftedato (kan være Mon/Tue/... afhængigt af helligdage)

    // Vi simulerer op til og med anchor-dato (08:00)
    while ($current->getTimestamp() <= $anchor->getTimestamp()) {
        $wday = $current->format('l');
        $ds = $current->format('Y-m-d');

        // Når vi rammer en mandag, bestem denne uges skiftedag (første ikke-helligdag fra mandag hvis holidayDelay)
        if ($wday === 'Monday') {
            $shiftDay = clone $current;
            if ($holidayDelay) {
                // flyt frem til første ikke-helligdag i ugen (maksimalt til søndag)
                while (in_array($shiftDay->format('Y-m-d'), $holidays) && $shiftDay->format('l') !== 'Sunday') {
                    $shiftDay->modify('+1 day');
                }
            }
            $thisWeeksShiftDay = $shiftDay->format('Y-m-d');
        }

        // Først: hvis det er fredag, anvend fredagsrotation (sker kl. 08)
        if ($wday === 'Friday') {
            $lastFridayGroup = $fredagRot[$fridayIndex % count($fredagRot)];
            $fridayIndex++;
            // Bemærk: lastFridayGroup ændres her og er gyldig for fredag, lørdag og søndag
        }

        // Hvis i dag er denne uges skiftedag (kan være mandag eller udsat dag), så udfør mandagsskiftet (kl. 08)
        if ($thisWeeksShiftDay !== null && $ds === $thisWeeksShiftDay) {
            $lastMondayGroup = $mandagRot[$mondayIndex % count($mandagRot)];
            $mondayIndex++;
            // Hvis skiftedag og samtidig fredag (sjælden), så begge opdateringer er udført ovenfor
        }

        // Bestem aktiv gruppe for denne dag (bruges hvis det er anchor-dato)
        $activeForCurrent = null;
        // Sommer-override er allerede håndteret tidligere, så her normallogik:
        if ($ds === $thisWeeksShiftDay) {
            // På selve skiftedagen gælder netop det nyeste mandags-skift
            $activeForCurrent = $lastMondayGroup;
            $reasonForCurrent = "Nyt hold overtager i dag";
        } elseif ($wday === 'Friday') {
            $activeForCurrent = $lastFridayGroup;
            $reasonForCurrent = "Nyt hold overtager i dag";
        } elseif (in_array($wday, ['Saturday', 'Sunday'])) {
            // Weekend arver fredagens gruppe
            $activeForCurrent = $lastFridayGroup ?? $lastMondayGroup ?? $mandagRot[0];
            $reasonForCurrent = "Weekend – samme hold";
        } elseif ($wday === 'Monday' && $holidayDelay && in_array($ds, $holidays) && $ds !== $thisWeeksShiftDay) {
            // Her: mandag er helligdag OG skift er udskudt til senere i ugen.
            // På selve den hellige mandag skal den gruppe, som var aktiv fredag, være aktiv.
            $activeForCurrent = $lastFridayGroup ?? $lastMondayGroup ?? $mandagRot[0];
            $reasonForCurrent = "Helligdag – samme hold (skift i morgen)";
        } else {
            // Tirsdag-onsdag-torsdag (eller mandag hvis ikke-helligdag men ikke skiftedag) følger sidste mandags-gruppe
            $activeForCurrent = $lastMondayGroup ?? $mandagRot[0];
            $reasonForCurrent = "Hverdag – samme hold";
        }

        // Hvis denne dag er den anmodede dato, returnér resultatet
        if ($ds === $anchor->format('Y-m-d')) {
            // Hvis der ikke er en bestemt gruppe (meget tidligt i simulation), fallback til mandagRot[0]
            if ($activeForCurrent === null) {
                $activeForCurrent = $mandagRot[0];
                $reasonForCurrent = "Fallback";
            }

            echo json_encode([
                'station' => $stationName,
                'group' => $activeForCurrent,
                'reason' => $reasonForCurrent
            ]);
            exit;
        }

        // Gå til næste dag (kl. 08:00)
        $current->modify('+1 day');
    }

    // Fallback (skulle ikke nåes)
    echo json_encode(['station' => $stationName, 'group' => $mandagRot[0], 'reason' => 'Fallback efter simulation']);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Ukendt endpoint']);
?>
