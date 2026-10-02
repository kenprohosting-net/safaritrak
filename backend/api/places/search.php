<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/geo.php';

st_start_session();
st_require_method('GET');
$userId = st_require_login();



$allowedCategories = ['all', 'hospital', 'police', 'fuel', 'hotel', 'restaurant'];
$category = $_GET['category'] ?? 'all';
if (!in_array($category, $allowedCategories, true)) {
    $category = 'all';
}

$query = trim((string) ($_GET['q'] ?? ''));


$lat = isset($_GET['lat']) && $_GET['lat'] !== '' ? (float) $_GET['lat'] : -1.2833;
$lng = isset($_GET['lng']) && $_GET['lng'] !== '' ? (float) $_GET['lng'] : 36.8167;

$radius = isset($_GET['radius']) ? (int) $_GET['radius'] : 20000;
$radius = max(1000, min(100000, $radius));



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


function st_places_safe_distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
    return st_distance_km($lat1, $lng1, $lat2, $lng2) ?? 0.0;
}

function st_places_address(array $tags): string {
    $parts = array_filter([
        $tags['addr:housenumber'] ?? null,
        $tags['addr:street'] ?? null,
        $tags['addr:city'] ?? $tags['addr:suburb'] ?? null,
    ]);
    return $parts ? implode(', ', $parts) : 'Address not available';
}


function st_places_curl(string $url, array $headers = [], ?string $postBody = null, int $timeoutSeconds = 20): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($postBody !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['data' => $postBody]));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log(sprintf(
            'SafariTrak places lookup failed: url=%s http_code=%s curl_errno=%s curl_error=%s',
            $url,
            $httpCode,
            $curlErrno,
            $curlError ?: 'none'
        ));
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log('SafariTrak places lookup returned invalid JSON from: ' . $url);
        return null;
    }

    return $decoded;
}

$userAgent = 'SafariTrak/1.0 (contact: support@safaritrak.app)';


if ($query !== '') {
    $deltaDeg = $radius / 111000; // rough metres-to-degrees conversion
    $viewbox = ($lng - $deltaDeg) . ',' . ($lat + $deltaDeg) . ',' . ($lng + $deltaDeg) . ',' . ($lat - $deltaDeg);

    $url = 'https://nominatim.openstreetmap.org/search'
        . '?format=jsonv2&addressdetails=1&extratags=1&limit=25'
        . '&bounded=1&viewbox=' . urlencode($viewbox)
        . '&q=' . urlencode($query);

    $results = st_places_curl($url, ['User-Agent: ' . $userAgent], null, 20);

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
        $rClass = $r['class'] ?? null;
        $rType = $r['type'] ?? null;
        $inferredCategory = st_places_category_from_tags([
            'amenity' => $rClass === 'amenity' ? $rType : null,
            'tourism' => $rClass === 'tourism' ? $rType : null,
        ]);
        [$hoursLabel, $is24hr] = st_places_hours_label($tags);

        $places[] = [
            'name' => $r['name'] ?? explode(',', $r['display_name'])[0],
            'category' => $inferredCategory,
            'lat' => $placeLat,
            'lng' => $placeLng,
            'distance_km' => st_places_safe_distance_km($lat, $lng, $placeLat, $placeLng),
            'address' => $r['display_name'] ?? 'Address not available',
            'hours' => $hoursLabel,
            'is_24hr' => $is24hr,
        ];
    }

    if ($category !== 'all') {
        $filtered = array_values(array_filter($places, fn($p) => $p['category'] === $category));
     
        if (!empty($filtered)) {
            $places = $filtered;
        }
    }

    usort($places, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

    st_json_ok(['places' => array_slice($places, 0, 30), 'mode' => 'search']);
    exit;
}


$categoryTags = [
    'hospital' => ['amenity', 'hospital'],
    'police' => ['amenity', 'police'],
    'fuel' => ['amenity', 'fuel'],
    'hotel' => ['tourism', 'hotel'],
    'restaurant' => ['amenity', 'restaurant'],
];

$targets = $category === 'all' ? $categoryTags : [$category => $categoryTags[$category]];

$effectiveRadius = $radius;
if ($category === 'all') {
    $effectiveRadius = min($radius, 7000);
}

$clauses = [];
foreach ($targets as [$key, $value]) {
    $clauses[] = 'node["' . $key . '"="' . $value . '"](around:' . $effectiveRadius . ',' . $lat . ',' . $lng . ');';
    $clauses[] = 'way["' . $key . '"="' . $value . '"](around:' . $effectiveRadius . ',' . $lat . ',' . $lng . ');';
}

$ql = '[out:json][timeout:25];(' . implode('', $clauses) . ');out center 80;';


$overpassEndpoints = [
    'https://overpass-api.de/api/interpreter',
    'https://overpass.kumi.systems/api/interpreter',
];


$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
$cacheKey = 'overpass_' . $category . '_' . round($lat, 3) . '_' . round($lng, 3) . '_' . $effectiveRadius;
$cacheFile = $cacheDir . '/' . md5($cacheKey) . '.json';
$cacheTtlSeconds = 900; // 15 minutes

$result = null;
if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtlSeconds)) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached) && isset($cached['elements'])) {
        $result = $cached;
    }
}

if ($result === null) {
    foreach ($overpassEndpoints as $endpoint) {
        $attempt = st_places_curl($endpoint, ['User-Agent: ' . $userAgent], $ql, 35);
        if ($attempt !== null && isset($attempt['elements'])) {
            $result = $attempt;
            break;
        }
    }

    if ($result !== null) {
        @file_put_contents($cacheFile, json_encode($result));
    } elseif (is_file($cacheFile)) {
        // Overpass is down/degraded right now — a slightly stale result
        // beats a hard error.
        $stale = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($stale) && isset($stale['elements'])) {
            $result = $stale;
        }
    }
}

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
        'distance_km' => st_places_safe_distance_km($lat, $lng, (float) $placeLat, (float) $placeLng),
        'address' => st_places_address($tags),
        'hours' => $hoursLabel,
        'is_24hr' => $is24hr,
    ];
}

usort($places, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

st_json_ok(['places' => array_slice($places, 0, 30), 'mode' => 'nearby']);
exit;