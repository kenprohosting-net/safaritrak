<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');

$userId = st_require_login();

$db = safaritrak_db();

$journeyId = isset($_POST['journey_id'])
    ? (int) $_POST['journey_id']
    : 0;

$latitude = isset($_POST['latitude'])
    ? (float) $_POST['latitude']
    : null;

$longitude = isset($_POST['longitude'])
    ? (float) $_POST['longitude']
    : null;

if (
    $journeyId <= 0 ||
    $latitude === null ||
    $longitude === null ||
    !is_finite($latitude) ||
    !is_finite($longitude) ||
    $latitude < -90 ||
    $latitude > 90 ||
    $longitude < -180 ||
    $longitude > 180
) {
    header('Content-Type: application/json; charset=utf-8');

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid location data.'
    ]);

    exit;
}

$journeyStmt = $db->prepare(
    'SELECT id, status
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

    header('Content-Type: application/json; charset=utf-8');

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Journey not found.'
    ]);

    exit;
}

if ($journey['status'] !== 'active') {

    header('Content-Type: application/json; charset=utf-8');

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'This journey is not active.'
    ]);

    exit;
}

$insertStmt = $db->prepare(
    'INSERT INTO journey_locations
        (journey_id, latitude, longitude, recorded_at)
     VALUES
        (?, ?, ?, NOW())'
);

$insertStmt->execute([
    $journeyId,
    $latitude,
    $longitude
]);

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success' => true,
    'message' => 'Location saved.',
    'journey_id' => $journeyId,
    'latitude' => $latitude,
    'longitude' => $longitude
]);

exit;