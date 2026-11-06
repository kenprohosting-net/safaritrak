<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/geo.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$journeyId = (int) ($input['journey_id'] ?? 0);
$lat = isset($input['lat']) ? (float) $input['lat'] : null;
$lng = isset($input['lng']) ? (float) $input['lng'] : null;
$speedKmh = isset($input['speed_kmh']) && $input['speed_kmh'] !== '' ? (float) $input['speed_kmh'] : null;

if ($lat === null || $lng === null) {
    st_json_error('A location is required.');
}

$db = safaritrak_db();

$journeyStmt = $db->prepare('SELECT id, distance_km, covered_km FROM journeys WHERE id = ? AND user_id = ? AND status = "active"');
$journeyStmt->execute([$journeyId, $userId]);
$journey = $journeyStmt->fetch();

if (!$journey) {
    st_json_error('That journey is not active.', 404);
}

// Fetch the most recent coordinate before inserting the new point
$lastPointStmt = $db->prepare('SELECT lat, lng FROM journey_positions WHERE journey_id = ? ORDER BY id DESC LIMIT 1');
$lastPointStmt->execute([$journeyId]);
$lastPoint = $lastPointStmt->fetch();

// Insert the new position ping
$insertStmt = $db->prepare('INSERT INTO journey_positions (journey_id, lat, lng, speed_kmh) VALUES (?, ?, ?, ?)');
$insertStmt->execute([$journeyId, $lat, $lng, $speedKmh]);

// Incremental distance calculation (O(1) complexity)
$currentCovered = (float) ($journey['covered_km'] ?? 0.0);

if ($lastPoint) {
    $legKm = st_distance_km(
        (float) $lastPoint['lat'],
        (float) $lastPoint['lng'],
        $lat,
        $lng
    );
    if ($legKm !== null && $legKm > 0) {
        $currentCovered += $legKm;

        $updateCoveredStmt = $db->prepare('UPDATE journeys SET covered_km = ? WHERE id = ?');
        $updateCoveredStmt->execute([$currentCovered, $journeyId]);
    }
}

st_json_ok([
    'covered_km' => round($currentCovered, 2),
    'total_km' => $journey['distance_km'] !== null ? (float) $journey['distance_km'] : null,
]);