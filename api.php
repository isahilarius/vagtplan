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

// VAGTPLAN START

// Kontroller om nødvendige parametre er med i URL'en
if ($endpoint === 'group') {
    if (!isset($_GET['stationId']) || !isset($_GET['date'])) {
        http_response_code(400); 
        echo json_encode(['error' => 'stationId og date (YYYY-MM-DD) mangler']);
        exit;
    }

    // Hent og konverter parametre
    $stationId = intval($_GET['stationId']); 
    $date = $_GET['date']; 
    $anchor = DateTime::createFromFormat('Y-m-d', $date); 
    if (!$anchor) {
        http_response_code(400); 
        echo json_encode(['error' => 'Ugyldigt datoformat. Brug YYYY-MM-DD']);
        exit;
    }

    // Hent stationen fra databasen
    $stmt = $db->prepare('SELECT * FROM stations WHERE id = :id');
    $stmt->bindValue(':id', $stationId, SQLITE3_INTEGER);
    $station = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$station) {
        http_response_code(404); 
        echo json_encode(['error' => 'Station ikke fundet']);
        exit;
    }

    // Hent data fra stationen
    $stationName = $station['name'];
    $rotationAll = explode('_', $station['rotation']); 
    $mandagRot = str_split($rotationAll[0] ?? 'AB'); 
    $fredagRot = str_split($rotationAll[1] ?? 'ABCD'); 
    $holidayDelay = intval($station['holiday_delay']) === 1; 

    // Kontroller om stationen har ferie
    $qv = $db->prepare("SELECT 1 FROM vacations WHERE station_id = :sid AND start_date <= :d AND end_date >= :d LIMIT 1");
    $qv->bindValue(':sid', $stationId, SQLITE3_INTEGER);
    $qv->bindValue(':d', $date, SQLITE3_TEXT);
    if ($qv->execute()->fetchArray()) {
        echo json_encode([
            'station' => $stationName,
            'group' => 'ALLE', 
            'reason' => 'Ferieperiode – alle kaldt ind'
        ]);
        exit;
    }

    // Hent helligdage fra databasen
    $holidays = [];
    $resH = $db->query("SELECT date FROM holidays");
    while ($h = $resH->fetchArray(SQLITE3_ASSOC)) $holidays[] = $h['date'];

    // Beregn antal uger siden fast basisdato
    $baseMonday = new DateTime('2025-01-06'); 
    $diffDays = (int)$baseMonday->diff($anchor)->format('%a'); 
    $weeksPassed = intdiv($diffDays, 7); 

    // Find ugedag
    $wday = $anchor->format('l'); 

    // Kontroller om det er helligdag
    $isHoliday = in_array($anchor->format('Y-m-d'), $holidays); 

    // Beregn hvilken gruppe der skal kaldes
    if ($isHoliday && $wday === 'Monday' && $holidayDelay) {
        $group = $fredagRot[($weeksPassed - 1) % count($fredagRot)];
        $reason = "Helligdag – samme hold (skift i morgen)";
    } elseif ($wday === 'Monday') {
        $group = $mandagRot[$weeksPassed % count($mandagRot)];
        $reason = "Mandagsskift";
    } elseif ($wday === 'Friday') {
        $group = $fredagRot[$weeksPassed % count($fredagRot)];
        $reason = "Fredagsskift";
    } elseif (in_array($wday, ['Saturday', 'Sunday'])) {
        $group = $fredagRot[$weeksPassed % count($fredagRot)];
        $reason = "Weekend – samme hold";
    } else {
        $group = $mandagRot[$weeksPassed % count($mandagRot)];
        $reason = "Hverdag – samme hold";
    }

    // Returner resultat som JSON
    echo json_encode([
        'station' => $stationName,
        'group' => $group,
        'reason' => $reason
    ]);
    exit;
}

// VAGTPLAN SLUT



// ADMIN START

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

// ADMIN SLUT


http_response_code(404);
echo json_encode(['error' => 'Ukendt endpoint']);
?>
