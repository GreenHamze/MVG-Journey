<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Cinema coordinates mapping
$cinemaCoords = [
    'mathaeser' => ['lat' => 48.1401, 'lng' => 11.5584, 'display' => 'Mathäser'],
    'cinemaxx' => ['lat' => 48.1351, 'lng' => 11.5623, 'display' => 'CinemaxX'],
    'royal' => ['lat' => 48.1345, 'lng' => 11.5596, 'display' => 'Royal Filmpalast'],
    'astor' => ['lat' => 48.1423, 'lng' => 11.5782, 'display' => 'Astor Film Lounge'],
    'gloria' => ['lat' => 48.1498, 'lng' => 11.5703, 'display' => 'Gloria Palast'],
    'museum_lichtspiel' => ['lat' => 48.1312, 'lng' => 11.5938, 'display' => 'Museum Lichtspiele'],
    'cinema_muenchen_ov' => ['lat' => 48.1351, 'lng' => 11.5713, 'display' => 'Cinema München'],
    'rio_filmpalast' => ['lat' => 48.1523, 'lng' => 11.5485, 'display' => 'Rio Filmpalast'],
    'cineplex_neufahrn' => ['lat' => 48.3152, 'lng' => 11.6656, 'display' => 'Cineplex Neufahrn'],
    'theatiner' => ['lat' => 48.1420, 'lng' => 11.5770, 'display' => 'Theatiner Filmkunst'],
    'monopol' => ['lat' => 48.1375, 'lng' => 11.5755, 'display' => 'Monopol'],
    'city_atelier' => ['lat' => 48.1364, 'lng' => 11.5768, 'display' => 'City Atelier'],
    'neues_rottmann' => ['lat' => 48.1453, 'lng' => 11.5557, 'display' => 'Neues Rottmann'],
    'werkstattkino' => ['lat' => 48.1318, 'lng' => 11.5825, 'display' => 'Werkstattkino'],
    'cadillac_veranda' => ['lat' => 48.1312, 'lng' => 11.5938, 'display' => 'Cadillac & Veranda'],
    'filmmuseum' => ['lat' => 48.1348, 'lng' => 11.5748, 'display' => 'Filmmuseum München'],
    'arena_filmtheater' => ['lat' => 48.1262, 'lng' => 11.5568, 'display' => 'Arena Filmtheater']
];

// Paths to Alex's files
$selectionPath = '/srv/gruppe/students/ge82bob/public_html/selection.json';
$dbPath = '/srv/gruppe/students/ge82bob/public_html/db/movies.db';

// Read selection.json
if (!file_exists($selectionPath)) {
    echo json_encode(['error' => 'selection.json not found']);
    exit;
}

$selectionJson = file_get_contents($selectionPath);
$selection = json_decode($selectionJson, true);

if (!$selection || !isset($selection['selected_movie']) || !isset($selection['cinemas'])) {
    echo json_encode(['error' => 'Invalid selection.json format']);
    exit;
}

$selectedMovie = $selection['selected_movie'];
$cinemas = $selection['cinemas'];

// Connect to SQLite database
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Build cinema key from display name (lowercase only)
$cinemaKey = strtolower($cinemas[0]);

// Query for the movie at the selected cinema - get most recent scrape
$stmt = $db->prepare("
    SELECT title, showtimes, duration, cinema_key 
    FROM movies 
    WHERE title_normalized LIKE :movie 
    AND cinema_key = :cinema 
    ORDER BY scrape_date DESC 
    LIMIT 1
");

$stmt->execute([
    ':movie' => '%' . str_replace(' ', '%', $selectedMovie) . '%',
    ':cinema' => $cinemaKey
]);

$movie = $stmt->fetch(PDO::FETCH_ASSOC);

// If no result, try without cinema filter to find any showing
if (!$movie) {
    $stmt = $db->prepare("
        SELECT title, showtimes, duration, cinema_key 
        FROM movies 
        WHERE title_normalized LIKE :movie 
        ORDER BY scrape_date DESC 
        LIMIT 1
    ");
    $stmt->execute([
        ':movie' => '%' . str_replace(' ', '%', $selectedMovie) . '%'
    ]);
    $movie = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$movie) {
    echo json_encode(['error' => 'Movie not found in database', 'debug_movie' => $selectedMovie, 'debug_cinema' => $cinemaKey]);
    exit;
}

// Parse showtimes
$showtimes = json_decode($movie['showtimes'], true) ?: [];

// Get duration - if empty, try to find from another cinema
$duration = $movie['duration'];
if (empty($duration)) {
    $stmt = $db->prepare("
        SELECT duration 
        FROM movies 
        WHERE title_normalized LIKE :movie 
        AND duration IS NOT NULL 
        AND duration != ''
        ORDER BY scrape_date DESC 
        LIMIT 1
    ");
    $stmt->execute([
        ':movie' => '%' . str_replace(' ', '%', $selectedMovie) . '%'
    ]);
    $durationRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $duration = $durationRow ? $durationRow['duration'] : null;
}

// Parse duration to integer (e.g., "156 min" -> 156)
$durationMinutes = 120; // default
if ($duration) {
    preg_match('/(\d+)/', $duration, $matches);
    if (isset($matches[1])) {
        $durationMinutes = (int)$matches[1];
    }
}

// Get cinema info
$cinemaKey = $movie['cinema_key'];
$cinemaInfo = isset($cinemaCoords[$cinemaKey]) ? $cinemaCoords[$cinemaKey] : null;

$cinemaDisplay = $cinemaInfo ? $cinemaInfo['display'] : ucfirst($cinemaKey);
$coords = $cinemaInfo ? $cinemaInfo['lat'] . ',' . $cinemaInfo['lng'] : '48.1351,11.5583';

// Build response
$response = [
    'movie_title' => $movie['title'],
    'cinema' => $cinemaDisplay,
    'cinema_key' => $cinemaKey,
    'cinema_coords' => $coords,
    'showtimes' => $showtimes,
    'duration' => $durationMinutes
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
