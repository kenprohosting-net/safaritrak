<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/geo.php';

st_start_session();
st_require_method('GET');

$userId = st_require_login();

$journeyId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($journeyId <= 0) {
    st_json_error(
        'Invalid journey ID.',
        422
    );
}

$db = safaritrak_db();

$journeyStmt = $db->prepare(
    'SELECT *
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
    st_json_error(
        'Journey not found.',
        404
    );
}

$transportMode =
    $journey['transport_mode'] ?? 'car';

$speeds = [
    'car' => 60,
    'bus' => 45,
    'motorbike' => 55,
    'walking' => 5
];

$speed =
    $speeds[$transportMode] ?? 50;

$currentLat = null;
$currentLng = null;

if (isset($journey['current_lat'])) {
    $currentLat =
        $journey['current_lat'] !== null
            ? (float) $journey['current_lat']
            : null;
}

if (isset($journey['current_lng'])) {
    $currentLng =
        $journey['current_lng'] !== null
            ? (float) $journey['current_lng']
            : null;
}

$destinationLat =
    isset($journey['end_lat'])
        ? (float) $journey['end_lat']
        : null;

$destinationLng =
    isset($journey['end_lng'])
        ? (float) $journey['end_lng']
        : null;

$remainingDistance = null;

if (
    $currentLat !== null &&
    $currentLng !== null &&
    $destinationLat !== null &&
    $destinationLng !== null
) {

    $remainingDistance = st_distance_km(
        $currentLat,
        $currentLng,
        $destinationLat,
        $destinationLng
    );
}

if (
    $remainingDistance === null &&
    isset($journey['distance_km']) &&
    $journey['distance_km'] !== null
) {

    $remainingDistance =
        (float) $journey['distance_km'];
}

$etaMinutes = null;

if (
    $remainingDistance !== null &&
    $remainingDistance >= 0 &&
    $speed > 0
) {

    $etaMinutes = (int) round(
        ($remainingDistance / $speed) * 60
    );
}

$etaText = '--';

if ($etaMinutes !== null) {

    if ($etaMinutes < 1) {
        $etaText = 'Arriving';
    } elseif ($etaMinutes < 60) {
        $etaText =
            $etaMinutes . ' min';
    } else {

        $hours =
            floor($etaMinutes / 60);

        $minutes =
            $etaMinutes % 60;

        $etaText =
            $hours . ' hr';

        if ($minutes > 0) {
            $etaText .=
                ' ' . $minutes . ' min';
        }
    }
}

$expectedArrival = null;

if (
    $etaMinutes !== null &&
    $journey['status'] === 'active'
) {

    $expectedArrival = date(
        'Y-m-d H:i:s',
        time() + ($etaMinutes * 60)
    );
}

$shareStmt = $db->prepare(
    'SELECT COUNT(*)
     FROM journey_shares
     WHERE journey_id = ?'
);

$shareStmt->execute([
    $journeyId
]);

$shareCount =
    (int) $shareStmt->fetchColumn();

$statusLabel = [
    'active' => 'In progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
];

$journey['status_label'] =
    $statusLabel[$journey['status'] ?? '']
    ?? ucfirst(
        $journey['status'] ?? 'unknown'
    );

$journey['share_count'] =
    $shareCount;

$journey['remaining_distance_km'] =
    $remainingDistance !== null
        ? round($remainingDistance, 2)
        : null;

$journey['eta_minutes'] =
    $etaMinutes;

$journey['eta'] =
    $etaText;

$journey['expected_arrival'] =
    $expectedArrival;

$journey['current_lat'] =
    $currentLat;

$journey['current_lng'] =
    $currentLng;

$journey['destination_lat'] =
    $destinationLat;

$journey['destination_lng'] =
    $destinationLng;

$journey['transport_speed_kmh'] =
    $speed;

st_json_ok([
    'journey' => $journey
]);