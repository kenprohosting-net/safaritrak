<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/rate-limit.php';

st_start_session();
st_require_method('GET');
$userId = st_require_login();
session_write_close();

$readCoordinate = static function (string $key, float $minimum, float $maximum): ?float {
    $raw = $_GET[$key] ?? null;
    if (!is_string($raw) && !is_int($raw) && !is_float($raw)) {
        return null;
    }

    $value = filter_var($raw, FILTER_VALIDATE_FLOAT);
    if ($value === false || !is_finite((float) $value) || $value < $minimum || $value > $maximum) {
        return null;
    }

    return (float) $value;
};

$startLat = $readCoordinate('start_lat', -90, 90);
$startLng = $readCoordinate('start_lng', -180, 180);
$endLat = $readCoordinate('end_lat', -90, 90);
$endLng = $readCoordinate('end_lng', -180, 180);
if ($startLat === null || $startLng === null || $endLat === null || $endLng === null) {
    st_json_error('Choose valid starting and destination locations.', 422);
}

$db = safaritrak_db();
$retryAfter = st_rate_limit_consume($db, 'journey-directions-user', (string) $userId, 40, 60);
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    st_json_error('Too many route requests. Wait a moment and try again.', 429);
}

$coordinates = implode(';', [
    $startLng . ',' . $startLat,
    $endLng . ',' . $endLat,
]);
$providers = [
    'https://router.project-osrm.org/route/v1/driving/' . $coordinates
        . '?overview=full&geometries=geojson&steps=false',
    'https://routing.openstreetmap.de/routed-car/route/v1/driving/' . $coordinates
        . '?overview=full&geometries=geojson&steps=false',
];
$sawNoRoute = false;

foreach ($providers as $providerUrl) {
    $curl = curl_init($providerUrl);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_HTTPHEADER => ['User-Agent: SafariTrak/1.0 (route preview)'],
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false || $status !== 200) {
        error_log('SafariTrak route provider failed: ' . $curlError . ' (HTTP ' . $status . ')');
        continue;
    }

    $payload = json_decode($response, true);
    if (!is_array($payload)) {
        error_log('SafariTrak route provider returned invalid JSON.');
        continue;
    }
    if (($payload['code'] ?? null) === 'NoRoute') {
        $sawNoRoute = true;
        continue;
    }

    $route = $payload['routes'][0] ?? null;
    $routeCoordinates = $route['geometry']['coordinates'] ?? null;
    if (($payload['code'] ?? null) !== 'Ok'
        || !is_array($route)
        || !is_numeric($route['distance'] ?? null)
        || !is_numeric($route['duration'] ?? null)
        || !is_array($routeCoordinates)
        || count($routeCoordinates) < 2
    ) {
        error_log('SafariTrak route provider returned an incomplete route.');
        continue;
    }

    foreach ($routeCoordinates as $point) {
        if (!is_array($point) || count($point) < 2
            || !is_numeric($point[0]) || !is_numeric($point[1])
            || !is_finite((float) $point[0]) || !is_finite((float) $point[1])
            || (float) $point[0] < -180 || (float) $point[0] > 180
            || (float) $point[1] < -90 || (float) $point[1] > 90
        ) {
            error_log('SafariTrak route provider returned invalid route coordinates.');
            continue 2;
        }
    }

    st_json_ok([
        'route' => [
            'distance_meters' => (float) $route['distance'],
            'duration_seconds' => (float) $route['duration'],
            'coordinates' => $routeCoordinates,
        ],
    ]);
}

if ($sawNoRoute) {
    st_json_error('No drivable road route connects those locations. Choose nearby road-accessible places.', 422);
}

st_json_error('The route services are temporarily unavailable. Please retry in a moment.', 502);
