<?php
ob_start();
register_shutdown_function(function () {
    $buffer = ob_get_clean();
    if (preg_match('/\{.*\}\s*$/s', $buffer, $m)) {
        if (trim($buffer) !== trim($m[0])) {
            error_log('[SafariTrak places-search] stripped extra output: ' . trim(str_replace($m[0], '', $buffer)));
        }
        header('Content-Type: application/json');
        echo $m[0];
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        error_log('[SafariTrak places-search] fatal with no JSON produced: ' . trim($buffer));
        echo json_encode(['success' => false, 'message' => 'Unexpected server error. Check php-error.log.']);
    }
});

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
$userId = st_require_login();

/* ---------------- Input ---------------- */

$allowedCategories = ['all', 'hospital', 'police', 'fuel', 'hotel', 'restaurant'];
$category = $_GET['category'] ?? 'all';
if (!in_array($category, $allowedCategories, true)) {
    $category = 'all';
}

$query = trim((string) ($_GET['q'] ?? ''));

// Default to Nairobi CBD if the browser could not provide a location.
$lat = isset($_GET['lat']) && $_GET['lat'] !== '' ? (float) $_GET['lat'] : -1.2833;
$lng = isset($_GET['lng']) && $_GET['lng'] !== '' ? (float) $_GET['lng'] : 36.8167;

$radius = isset($_GET['radius']) ? (int) $_GET['radius'] : 20000;
$radius = max(1000, min(100000, $radius));

/* ---------------- Helpers ---------------- */

function st_places_distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $earthRadius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadius * $c, 1);
}

function st_places_category_from_tags(array $tags): string {
    if (($tags['amenity'] ?? null) === 'hospital') return 'hospital';
    if (($tags['amenity'] ?? null) === 'police') return 'police';
    if (($tags['amenity'] ?? null) === 'fuel') return 'fuel';
    if (($tags['amenity'] ?? null) === 'restaurant') return 'restaurant';
    if (($tags['tourism'] ?? null) === 'hotel') return 'hotel';
    return 'other';
}

function st_places_hours_label(array $tags): array {
    $hours = $tags['opening_hours'] ?? null;
    if ($hours === '24/7') {
        return ['Open 24 hrs', true];
    }
    if ($hours) {
        return [$hours, false];
    }
    return ['Hours not listed', false];
}

function st_places_address(array $tags): string {
    $parts = array_filter([
        $tags['addr:housenumber'] ?? null,
        $tags['addr:street'] ?? null,
        $tags['addr:city'] ?? $tags['addr:suburb'] ?? null,
    ]);
    return $parts ? implode(', ', $parts) : 'Address not available';
}

function st_places_curl(string $url, array $headers = [], ?string $postBody = null): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($postBody !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['data' => $postBody]));
    }
    $response = curl_exec($ch);
    $ok = $response !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);

    if (!$ok) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

// Replace with a real contact address/domain before shipping — Nominatim's
// usage policy requires a genuine identifying User-Agent, not a placeholder.
$userAgent = 'SafariTrak/1.0 (contact: support@safaritrak.app)';

/* ---------------- Free-text search (Nominatim) ---------------- */

if ($query !== '') {
    $deltaDeg = $radius / 111000; // rough metres-to-degrees conversion
    $viewbox = ($lng - $deltaDeg) . ',' . ($lat + $deltaDeg) . ',' . ($lng + $deltaDeg) . ',' . ($lat - $deltaDeg);

    $url = 'https://nominatim.openstreetmap.org/search'
        . '?format=jsonv2&addressdetails=1&extratags=1&limit=25'
        . '&bounded=1&viewbox=' . urlencode($viewbox)
        . '&q=' . urlencode($query);

    $results = st_places_curl($url, ['User-Agent: ' . $userAgent]);

    if ($results === null) {
        st_json_error('Could not reach the places service right now. Please try again.', 502);
        exit;
    }

    $places = [];
    foreach ($results as $r) {
        $placeLat = (float) ($r['lat'] ?? 0);
        $placeLng = (float) ($r['lon'] ?? 0);
        if (!$placeLat || !$placeLng) {
            continue;
        }

        $tags = $r['extratags'] ?? [];
        $inferredCategory = st_places_category_from_tags([
            'amenity' => $r['class'] === 'amenity' ? $r['type'] : null,
            'tourism' => $r['class'] === 'tourism' ? $r['type'] : null,
        ]);
        [$hoursLabel, $is24hr] = st_places_hours_label($tags);

        $places[] = [
            'name' => $r['name'] ?? explode(',', $r['display_name'])[0],
            'category' => $inferredCategory,
            'lat' => $placeLat,
            'lng' => $placeLng,
            'distance_km' => st_places_distance_km($lat, $lng, $placeLat, $placeLng),
            'address' => $r['display_name'] ?? 'Address not available',
            'hours' => $hoursLabel,
            'is_24hr' => $is24hr,
        ];
    }

    if ($category !== 'all') {
        $filtered = array_values(array_filter($places, fn($p) => $p['category'] === $category));
        // If filtering wipes out every text match, show the unfiltered set
        // rather than leaving the user with nothing for a place they named.
        if (!empty($filtered)) {
            $places = $filtered;
        }
    }

    usort($places, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

    st_json_ok(['places' => array_slice($places, 0, 30), 'mode' => 'search']);
    exit;
}

/* ---------------- Category browse (Overpass) ---------------- */

$categoryTags = [
    'hospital' => ['amenity', 'hospital'],
    'police' => ['amenity', 'police'],
    'fuel' => ['amenity', 'fuel'],
    'hotel' => ['tourism', 'hotel'],
    'restaurant' => ['amenity', 'restaurant'],
];

$targets = $category === 'all' ? $categoryTags : [$category => $categoryTags[$category]];

$clauses = [];
foreach ($targets as [$key, $value]) {
    $clauses[] = 'node["' . $key . '"="' . $value . '"](around:' . $radius . ',' . $lat . ',' . $lng . ');';
    $clauses[] = 'way["' . $key . '"="' . $value . '"](around:' . $radius . ',' . $lat . ',' . $lng . ');';
}

$ql = '[out:json][timeout:20];(' . implode('', $clauses) . ');out center;';

$result = st_places_curl('https://overpass-api.de/api/interpreter', ['User-Agent: ' . $userAgent], $ql);

if ($result === null || !isset($result['elements'])) {
    st_json_error('Could not reach the places service right now. Please try again.', 502);
    exit;
}

$places = [];
foreach ($result['elements'] as $el) {
    $tags = $el['tags'] ?? [];
    $placeLat = $el['lat'] ?? ($el['center']['lat'] ?? null);
    $placeLng = $el['lon'] ?? ($el['center']['lon'] ?? null);
    if ($placeLat === null || $placeLng === null) {
        continue;
    }

    $placeCategory = st_places_category_from_tags($tags);
    $name = $tags['name'] ?? ($tags['brand'] ?? ucfirst($placeCategory));
    [$hoursLabel, $is24hr] = st_places_hours_label($tags);

    $places[] = [
        'name' => $name,
        'category' => $placeCategory,
        'lat' => (float) $placeLat,
        'lng' => (float) $placeLng,
        'distance_km' => st_places_distance_km($lat, $lng, (float) $placeLat, (float) $placeLng),
        'address' => st_places_address($tags),
        'hours' => $hoursLabel,
        'is_24hr' => $is24hr,
    ];
}

usort($places, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

st_json_ok(['places' => array_slice($places, 0, 30), 'mode' => 'nearby']);
exit;