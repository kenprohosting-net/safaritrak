<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/geo.php';

st_start_session();
st_require_method('GET');
$userId = st_require_login();

$journeyId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$journeyId || $journeyId < 1) {
    st_json_error('A valid journey ID is required.', 422);
}

$db = safaritrak_db();
$journeyStmt = $db->prepare(
    'SELECT j.id, j.user_id, j.start_label, j.start_lat, j.start_lng,
            j.end_label, j.end_lat, j.end_lng, j.distance_km, j.covered_km,
            j.transport_mode, j.status, j.started_at, j.ended_at,
            j.route_deviation_alert, u.full_name AS owner_name, u.avatar_path AS owner_avatar_path
     FROM journeys j
     JOIN users u ON u.id = j.user_id
     WHERE j.id = ?
       AND (
           j.user_id = ?
           OR (
               j.status = "active"
               AND EXISTS (
                   SELECT 1
                   FROM journey_shares js
                   JOIN trusted_contacts tc ON tc.id = js.trusted_contact_id
                   WHERE js.journey_id = j.id
                     AND tc.contact_user_id = ?
                     AND tc.status = "confirmed"
                     AND tc.share_live_location = 1
               )
           )
       )
     LIMIT 1'
);
$journeyStmt->execute([$journeyId, $userId, $userId]);
$journey = $journeyStmt->fetch();

if (!$journey) {
    st_json_error('That journey is not available to your account.', 404);
}

$positionStmt = $db->prepare(
    'SELECT id, lat, lng, speed_kmh, recorded_at
     FROM (
         SELECT id, lat, lng, speed_kmh, recorded_at
         FROM journey_positions
         WHERE journey_id = ?
         ORDER BY recorded_at DESC, id DESC
         LIMIT 600
     ) recent_positions
     ORDER BY recorded_at ASC, id ASC'
);
$positionStmt->execute([$journeyId]);
$positions = $positionStmt->fetchAll();

$formatTimestamp = static function (?string $timestamp): ?string {
    if ($timestamp === null) {
        return null;
    }

    return (new DateTimeImmutable($timestamp, new DateTimeZone(date_default_timezone_get())))
        ->format(DATE_ATOM);
};

$route = [];
$current = null;
$latestSpeed = null;
$latestTimestamp = null;
foreach ($positions as $position) {
    $point = [
        'lat' => (float) $position['lat'],
        'lng' => (float) $position['lng'],
        'speed_kmh' => $position['speed_kmh'] !== null ? (float) $position['speed_kmh'] : null,
        'recorded_at' => $formatTimestamp($position['recorded_at']),
    ];
    $route[] = $point;
    $current = $point;
}

if ($current !== null) {
    $latestSpeed = $current['speed_kmh'];
    $latestTimestamp = $current['recorded_at'];
}

$start = $journey['start_lat'] !== null && $journey['start_lng'] !== null
    ? ['lat' => (float) $journey['start_lat'], 'lng' => (float) $journey['start_lng']]
    : null;
$destination = $journey['end_lat'] !== null && $journey['end_lng'] !== null
    ? ['lat' => (float) $journey['end_lat'], 'lng' => (float) $journey['end_lng']]
    : null;

$remainingDistance = null;
if ($current !== null && $destination !== null) {
    $remainingDistance = st_distance_km(
        $current['lat'],
        $current['lng'],
        $destination['lat'],
        $destination['lng']
    );
} elseif ($start !== null && $destination !== null) {
    $remainingDistance = st_distance_km(
        $start['lat'],
        $start['lng'],
        $destination['lat'],
        $destination['lng']
    );
}

$modeSpeeds = ['walking' => 5.0, 'motorbike' => 35.0, 'bus' => 30.0, 'car' => 50.0];
$estimatedSpeed = $latestSpeed !== null && $latestSpeed > 0
    ? $latestSpeed
    : ($modeSpeeds[$journey['transport_mode']] ?? 30.0);
$etaMinutes = $remainingDistance !== null
    ? (int) ceil(($remainingDistance / $estimatedSpeed) * 60)
    : null;

$avatarUrl = null;
$avatarPath = $journey['owner_avatar_path'] ?? '';
if (
    is_string($avatarPath)
    && preg_match('#^uploads/[A-Za-z0-9_./-]+$#', $avatarPath)
    && !preg_match('#(^|/)\.\.(/|$)#', $avatarPath)
) {
    $avatarFile = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $avatarPath);
    if (is_file($avatarFile)) {
        $avatarUrl = $avatarPath . '?v=' . (string) filemtime($avatarFile);
    }
}

st_json_ok([
    'trip' => [
        'id' => (int) $journey['id'],
        'owner_id' => (int) $journey['user_id'],
        'owner_name' => $journey['owner_name'],
        'owner_avatar_url' => $avatarUrl,
        'start_label' => $journey['start_label'],
        'start' => $start,
        'destination_label' => $journey['end_label'],
        'destination' => $destination,
        'distance_km' => $journey['distance_km'] !== null ? (float) $journey['distance_km'] : null,
        'remaining_distance_km' => $remainingDistance,
        'covered_km' => (float) $journey['covered_km'],
        'transport_mode' => $journey['transport_mode'],
        'status' => $journey['status'],
        'started_at' => $formatTimestamp($journey['started_at']),
        'ended_at' => $formatTimestamp($journey['ended_at']),
        'current' => $current,
        'route' => $route,
        'route_truncated' => count($positions) === 600,
        'speed_kmh' => $latestSpeed,
        'effective_speed_kmh' => $estimatedSpeed,
        'eta_minutes' => $etaMinutes,
        'last_location_at' => $latestTimestamp,
    ],
]);
