<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/geo.php';

st_start_session();
st_require_method('POST');

$userId = st_require_login();
$input = st_input();

$journeyId = isset($input['journey_id'])
    ? (int) $input['journey_id']
    : 0;

$lat = isset($input['latitude'])
    ? (float) $input['latitude']
    : null;

$lng = isset($input['longitude'])
    ? (float) $input['longitude']
    : null;

$speedKmh = isset($input['speed_kmh']) &&
            $input['speed_kmh'] !== '' &&
            $input['speed_kmh'] !== null
    ? (float) $input['speed_kmh']
    : null;

if ($journeyId <= 0) {
    st_json_error('Invalid journey ID.', 422);
}

if (
    $lat === null ||
    $lng === null ||
    !is_finite($lat) ||
    !is_finite($lng)
) {
    st_json_error('Valid GPS coordinates are required.', 422);
}

if (
    $lat < -90 ||
    $lat > 90 ||
    $lng < -180 ||
    $lng > 180
) {
    st_json_error('Invalid GPS coordinates.', 422);
}

$db = safaritrak_db();

$journeyStmt = $db->prepare(
    'SELECT
        id,
        status,
        start_lat,
        start_lng,
        end_lat,
        end_lng,
        transport_mode
     FROM journeys
     WHERE id = ?
     AND user_id = ?
     LIMIT 1'
);

$journeyStmt->execute([
    $journeyId,
    $userId
]);

$journey = $journeyStmt->fetch(PDO::FETCH_ASSOC);

if (!$journey) {
    st_json_error('Journey not found.', 404);
}

if ($journey['status'] !== 'active') {
    st_json_error(
        'This journey is no longer active.',
        409
    );
}

$lastStmt = $db->prepare(
    'SELECT
        lat,
        lng
     FROM journey_positions
     WHERE journey_id = ?
     ORDER BY recorded_at DESC, id DESC
     LIMIT 1'
);

$lastStmt->execute([
    $journeyId
]);

$lastPosition = $lastStmt->fetch(PDO::FETCH_ASSOC);

$distanceFromPrevious = 0;

if ($lastPosition) {

    $previousLat =
        (float) $lastPosition['lat'];

    $previousLng =
        (float) $lastPosition['lng'];

    $distanceFromPrevious = st_distance_km(
        $previousLat,
        $previousLng,
        $lat,
        $lng
    );

    if (
        $distanceFromPrevious === null ||
        $distanceFromPrevious < 0
    ) {
        $distanceFromPrevious = 0;
    }
}

$insert = $db->prepare(
    'INSERT INTO journey_positions
    (
        journey_id,
        lat,
        lng,
        speed_kmh,
        recorded_at
    )
    VALUES (?, ?, ?, ?, NOW())'
);

$insert->execute([
    $journeyId,
    $lat,
    $lng,
    $speedKmh
]);

$positionsStmt = $db->prepare(
    'SELECT
        lat,
        lng
     FROM journey_positions
     WHERE journey_id = ?
     ORDER BY recorded_at ASC, id ASC'
);

$positionsStmt->execute([
    $journeyId
]);

$positions =
    $positionsStmt->fetchAll(PDO::FETCH_ASSOC);

$coveredDistance = 0;

$previousLat = null;
$previousLng = null;

foreach ($positions as $position) {

    $currentLat =
        (float) $position['lat'];

    $currentLng =
        (float) $position['lng'];

    if (
        $previousLat !== null &&
        $previousLng !== null
    ) {

        $segmentDistance = st_distance_km(
            $previousLat,
            $previousLng,
            $currentLat,
            $currentLng
        );

        if (
            $segmentDistance !== null &&
            $segmentDistance >= 0
        ) {
            $coveredDistance +=
                $segmentDistance;
        }
    }

    $previousLat = $currentLat;
    $previousLng = $currentLng;
}

$remainingDistance = null;

if (
    $journey['end_lat'] !== null &&
    $journey['end_lng'] !== null
) {

    $remainingDistance = st_distance_km(
        $lat,
        $lng,
        (float) $journey['end_lat'],
        (float) $journey['end_lng']
    );

    if (
        $remainingDistance !== null &&
        $remainingDistance < 0
    ) {
        $remainingDistance = 0;
    }
}

$averageSpeeds = [
    'car' => 60,
    'bus' => 45,
    'motorbike' => 55,
    'walking' => 5
];

$transportMode =
    $journey['transport_mode'] ?? 'car';

$estimatedSpeed =
    $averageSpeeds[$transportMode] ?? 50;

$etaMinutes = null;

if (
    $remainingDistance !== null &&
    $remainingDistance >= 0 &&
    $estimatedSpeed > 0
) {

    $etaMinutes = (int) round(
        ($remainingDistance / $estimatedSpeed) * 60
    );
}

$eta = '--';

if ($etaMinutes !== null) {

    if ($etaMinutes <= 0) {

        $eta = 'Arriving';

    } elseif ($etaMinutes < 60) {

        $eta = $etaMinutes . ' min';

    } else {

        $hours = floor($etaMinutes / 60);
        $minutes = $etaMinutes % 60;

        $eta = $hours . ' hr';

        if ($minutes > 0) {
            $eta .= ' ' . $minutes . ' min';
        }
    }
}

$columns = $db->query(
    'SHOW COLUMNS FROM journeys'
)->fetchAll(PDO::FETCH_COLUMN);

$availableColumns = array_flip($columns);

$updates = [];
$params = [];

if (isset($availableColumns['current_lat'])) {
    $updates[] = 'current_lat = ?';
    $params[] = $lat;
}

if (isset($availableColumns['current_lng'])) {
    $updates[] = 'current_lng = ?';
    $params[] = $lng;
}

if (isset($availableColumns['covered_distance_km'])) {
    $updates[] = 'covered_distance_km = ?';
    $params[] = $coveredDistance;
}

if (isset($availableColumns['remaining_distance_km'])) {
    $updates[] = 'remaining_distance_km = ?';
    $params[] = $remainingDistance;
}

if (isset($availableColumns['eta_minutes'])) {
    $updates[] = 'eta_minutes = ?';
    $params[] = $etaMinutes;
}

if (isset($availableColumns['last_location_at'])) {
    $updates[] = 'last_location_at = NOW()';
}

if (isset($availableColumns['updated_at'])) {
    $updates[] = 'updated_at = NOW()';
}

if (!empty($updates)) {

    $params[] = $journeyId;
    $params[] = $userId;

    $updateSql =
        'UPDATE journeys
         SET ' .
        implode(', ', $updates) .
        '
         WHERE id = ?
         AND user_id = ?
         AND status = "active"';

    $updateStmt =
        $db->prepare($updateSql);

    $updateStmt->execute($params);
}

st_json_ok([
    'journey_id' => $journeyId,
    'status' => 'active',
    'latitude' => $lat,
    'longitude' => $lng,
    'speed_kmh' => $speedKmh,
    'covered_km' => round($coveredDistance, 2),
    'distance_km' => $remainingDistance !== null
        ? round($remainingDistance, 2)
        : null,
    'eta_minutes' => $etaMinutes,
    'eta' => $eta
]);