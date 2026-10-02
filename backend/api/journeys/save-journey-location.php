<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');

$userId = st_require_login();

$db = safaritrak_db();

$rawInput = file_get_contents('php://input');

$jsonInput = json_decode(
    $rawInput,
    true
);

if (is_array($jsonInput)) {
    $input = $jsonInput;
} else {
    $input = $_POST;
}

$journeyId = isset($input['journey_id'])
    ? (int) $input['journey_id']
    : 0;

$latitude = isset($input['latitude'])
    ? (float) $input['latitude']
    : null;

$longitude = isset($input['longitude'])
    ? (float) $input['longitude']
    : null;

if ($journeyId <= 0) {
    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'message' => 'Invalid journey ID.',
        'received_journey_id' => $input['journey_id'] ?? null
    ]);

    exit;
}

if (
    $latitude === null ||
    $longitude === null ||
    !is_finite($latitude) ||
    !is_finite($longitude) ||
    $latitude < -90 ||
    $latitude > 90 ||
    $longitude < -180 ||
    $longitude > 180
) {
    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'message' => 'Invalid GPS coordinates.'
    ]);

    exit;
}

$journeyStmt = $db->prepare(
    'SELECT id, user_id, status
     FROM journeys
     WHERE id = ?
     LIMIT 1'
);

$journeyStmt->execute([
    $journeyId
]);

$journey = $journeyStmt->fetch(PDO::FETCH_ASSOC);

if (!$journey) {
    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'message' => 'Journey does not exist.',
        'journey_id' => $journeyId
    ]);

    exit;
}

if ((int) $journey['user_id'] !== (int) $userId) {
    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'message' => 'You are not the owner of this journey.'
    ]);

    exit;
}

if ($journey['status'] !== 'active') {
    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'message' => 'This journey is not active.',
        'status' => $journey['status']
    ]);

    exit;
}

$locationStmt = $db->prepare(
    'INSERT INTO journey_locations
        (journey_id, latitude, longitude, recorded_at)
     VALUES
        (?, ?, ?, NOW())'
);

$locationStmt->execute([
    $journeyId,
    $latitude,
    $longitude
]);

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'Location saved.',
    'location_id' => (int) $db->lastInsertId(),
    'journey_id' => $journeyId,
    'latitude' => $latitude,
    'longitude' => $longitude
]);

exit;