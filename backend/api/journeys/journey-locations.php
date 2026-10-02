<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();

st_require_method('GET');

$userId = st_require_login();

$db = safaritrak_db();

$journeyId = isset($_GET['journey_id'])
    ? (int) $_GET['journey_id']
    : 0;

if ($journeyId <= 0) {
    http_response_code(400);

    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'message' => 'Invalid journey ID.'
    ]);

    exit;
}

$journeyStmt = $db->prepare(
    'SELECT j.id,
            j.user_id,
            j.status,
            j.distance_km,
            j.start_lat,
            j.start_lng,
            j.end_lat,
            j.end_lng
     FROM journeys j
     WHERE j.id = ?
     AND (
        j.user_id = ?
        OR EXISTS (
            SELECT 1
            FROM journey_shares js
            JOIN trusted_contacts tc
                ON tc.id = js.trusted_contact_id
            WHERE js.journey_id = j.id
            AND tc.contact_user_id = ?
        )
     )
     LIMIT 1'
);

$journeyStmt->execute([
    $journeyId,
    $userId,
    $userId
]);

$journey = $journeyStmt->fetch(PDO::FETCH_ASSOC);

if (!$journey) {

    http_response_code(403);

    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'message' => 'You are not authorized to view this journey.'
    ]);

    exit;
}

$locationsStmt = $db->prepare(
    'SELECT id,
            latitude,
            longitude,
            recorded_at
     FROM journey_locations
     WHERE journey_id = ?
     AND latitude IS NOT NULL
     AND longitude IS NOT NULL
     ORDER BY recorded_at ASC, id ASC'
);

$locationsStmt->execute([
    $journeyId
]);

$locations = $locationsStmt->fetchAll(PDO::FETCH_ASSOC);

function distance_between_points(
    float $lat1,
    float $lng1,
    float $lat2,
    float $lng2
): float {

    $earthRadius = 6371;

    $lat1 = deg2rad($lat1);
    $lat2 = deg2rad($lat2);

    $latDifference =
        deg2rad($lat2 - $lat1);

    $lngDifference =
        deg2rad($lng2 - $lng1);

    $a =
        sin($latDifference / 2) *
        sin($latDifference / 2) +
        cos($lat1) *
        cos($lat2) *
        sin($lngDifference / 2) *
        sin($lngDifference / 2);

    $c =
        2 *
        atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

    return $earthRadius * $c;
}

$distanceCovered = 0.0;

if (count($locations) > 1) {

    for ($i = 1; $i < count($locations); $i++) {

        $previous =
            $locations[$i - 1];

        $current =
            $locations[$i];

        $distanceCovered +=
            distance_between_points(
                (float) $previous['latitude'],
                (float) $previous['longitude'],
                (float) $current['latitude'],
                (float) $current['longitude']
            );
    }
}

$totalDistance =
    $journey['distance_km'] !== null
        ? (float) $journey['distance_km']
        : 0.0;

if ($totalDistance > 0) {

    $distanceCovered =
        min(
            $distanceCovered,
            $totalDistance
        );
}

if ($journey['status'] === 'completed') {

    if ($totalDistance > 0) {
        $distanceCovered =
            $totalDistance;
    }
}

$distanceRemaining =
    max(
        0,
        $totalDistance - $distanceCovered
    );

$progress = 0;

if ($totalDistance > 0) {

    $progress =
        ($distanceCovered / $totalDistance) * 100;
}

if ($journey['status'] === 'completed') {
    $progress = 100;
}

$currentLocation = null;

if (!empty($locations)) {

    $last =
        $locations[count($locations) - 1];

    $currentLocation = [
        'latitude' =>
            (float) $last['latitude'],
        'longitude' =>
            (float) $last['longitude'],
        'recorded_at' =>
            $last['recorded_at']
    ];
}

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'journey_id' => $journeyId,
    'status' => $journey['status'],
    'total_distance' => round(
        $totalDistance,
        2
    ),
    'distance_covered' => round(
        $distanceCovered,
        2
    ),
    'distance_remaining' => round(
        $distanceRemaining,
        2
    ),
    'progress' => round(
        min(100, max(0, $progress)),
        1
    ),
    'start' => [
        'latitude' =>
            $journey['start_lat'] !== null
                ? (float) $journey['start_lat']
                : null,
        'longitude' =>
            $journey['start_lng'] !== null
                ? (float) $journey['start_lng']
                : null
    ],
    'destination' => [
        'latitude' =>
            $journey['end_lat'] !== null
                ? (float) $journey['end_lat']
                : null,
        'longitude' =>
            $journey['end_lng'] !== null
                ? (float) $journey['end_lng']
                : null
    ],
    'current' => $currentLocation,
    'locations' => $locations
]);

exit;