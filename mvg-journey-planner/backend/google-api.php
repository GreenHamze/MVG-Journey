<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$API_KEY = 'YOUR_API_KEY_HERE';

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ===================
// ACTION: Get Route
// ===================
if ($action === 'route') {
    $origin = isset($_GET['origin']) ? $_GET['origin'] : 'Garching Forschungszentrum';
    $destination = isset($_GET['destination']) ? $_GET['destination'] : 'Marienplatz, Muenchen';
    
    $url = 'https://maps.googleapis.com/maps/api/directions/json?' . http_build_query([
        'origin' => $origin,
        'destination' => $destination,
        'mode' => 'transit',
        'language' => 'en',
        'key' => $API_KEY
    ]);
    
    $response = file_get_contents($url);
    echo $response;
    exit;
}

// ===================
// ACTION: Get Nearby Places (New API)
// ===================
if ($action === 'places') {
    $location = isset($_GET['location']) ? $_GET['location'] : '48.1351,11.5583';
    $type = isset($_GET['type']) ? $_GET['type'] : 'supermarket';
    $radius = isset($_GET['radius']) ? (int)$_GET['radius'] : 500;
    
    $coords = explode(',', $location);
    $lat = (float)$coords[0];
    $lng = (float)$coords[1];
    
    $typeMapping = [
        'supermarket' => ['supermarket', 'grocery_store'],
        'restaurant' => ['restaurant'],
        'bar' => ['bar', 'night_club'],
        'cafe' => ['cafe', 'coffee_shop']
    ];
    
    $includedTypes = isset($typeMapping[$type]) ? $typeMapping[$type] : [$type];
    
    $requestBody = [
        'includedTypes' => $includedTypes,
        'maxResultCount' => 10,
        'locationRestriction' => [
            'circle' => [
                'center' => [
                    'latitude' => $lat,
                    'longitude' => $lng
                ],
                'radius' => $radius
            ]
        ]
    ];
    
    $url = 'https://places.googleapis.com/v1/places:searchNearby';
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nX-Goog-Api-Key: " . $API_KEY . "\r\nX-Goog-FieldMask: places.displayName,places.formattedAddress,places.location,places.currentOpeningHours,places.rating,places.id",
            'content' => json_encode($requestBody)
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo json_encode(['error' => 'API request failed']);
        exit;
    }
    
    echo $response;
    exit;
}

// ===================
// ACTION: Geocode
// ===================
if ($action === 'geocode') {
    $address = isset($_GET['address']) ? $_GET['address'] : '';
    
    if (!$address) {
        echo json_encode(['error' => 'Missing address']);
        exit;
    }
    
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
        'address' => $address,
        'key' => $API_KEY
    ]);
    
    $response = file_get_contents($url);
    echo $response;
    exit;
}

// ===================
// No valid action
// ===================
echo json_encode([
    'error' => 'Invalid action',
    'valid_actions' => ['route', 'places', 'geocode']
]);
?>
```
