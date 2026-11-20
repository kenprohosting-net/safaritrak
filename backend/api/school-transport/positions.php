<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/rate-limit.php';
require_once __DIR__ . '/../../includes/school-transport.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$tripId = filter_var($input['trip_id'] ?? null, FILTER_VALIDATE_INT);
$lat = st_school_transport_parse_coordinate($input['lat'] ?? null, -90, 90);
$lng = st_school_transport_parse_coordinate($input['lng'] ?? null, -180, 180);
$speed = st_school_transport_parse_coordinate($input['speed_kmh'] ?? null, 0, 300);
$accuracy = st_school_transport_parse_coordinate($input['accuracy_meters'] ?? null, 0, 100000);
if (!$tripId || $lat === null || $lng === null) {
    st_json_error('The live location payload is invalid.', 422);
}

$db = safaritrak_db();
$access = st_school_transport_require_admin($db, $userId);
$retryAfter = st_rate_limit_consume($db, 'school-bus-position-user', (string) $userId, 30, 60);
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    st_json_error('Location updates are arriving too quickly. Wait a moment and retry.', 429);
}

$tripStmt = $db->prepare(
    'SELECT bus.driver_user_id
     FROM school_bus_trips trip
     JOIN school_buses bus ON bus.id = trip.bus_id
     WHERE trip.id = ? AND bus.school_id = ?
       AND trip.status = "en_route" AND trip.broadcast_active = 1
     LIMIT 1'
);
$tripStmt->execute([(int) $tripId, (int) $access['school_id']]);
$driverUserId = $tripStmt->fetchColumn();
if ($driverUserId === false) {
    st_json_error('That live bus trip is no longer available.', 404);
}
if ($driverUserId !== null && (int) $driverUserId !== $userId) {
    st_json_error('Only the assigned driver can update this bus location.', 403);
}

$db->prepare(
    'INSERT INTO school_bus_positions
        (trip_id, lat, lng, speed_kmh, accuracy_meters)
     VALUES (?, ?, ?, ?, ?)'
)->execute([
    (int) $tripId,
    $lat,
    $lng,
    $speed,
    $accuracy,
]);

st_json_ok(['recorded' => true]);
