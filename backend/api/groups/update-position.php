<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/geo.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$groupId = (int) ($input['group_id'] ?? 0);
$lat = st_parse_coordinate($input['lat'] ?? null, -90, 90);
$lng = st_parse_coordinate($input['lng'] ?? null, -180, 180);
$speed = isset($input['speed_kmh']) ? filter_var($input['speed_kmh'], FILTER_VALIDATE_FLOAT) : null;
$heading = isset($input['heading']) ? filter_var($input['heading'], FILTER_VALIDATE_FLOAT) : null;
$accuracy = isset($input['accuracy']) ? filter_var($input['accuracy'], FILTER_VALIDATE_FLOAT) : null;

if ($groupId < 1 || $lat === null || $lng === null) {
    st_json_error('Enter a valid trip and location.', 422);
}
if (($speed !== null && ($speed === false || $speed < 0 || $speed > 500))
    || ($heading !== null && ($heading === false || $heading < 0 || $heading >= 360))
    || ($accuracy !== null && ($accuracy === false || $accuracy < 0 || $accuracy > 100000))) {
    st_json_error('Location sensor details are invalid.', 422);
}

$db = safaritrak_db();

$stmt = $db->prepare(
    'SELECT gm.id FROM group_members gm
     JOIN group_journeys gj ON gj.id = gm.group_journey_id
     WHERE gm.group_journey_id = ? AND gm.user_id = ? AND gm.status = "confirmed" AND gj.status = "active"'
);
$stmt->execute([$groupId, $userId]);

$memberId = $stmt->fetchColumn();
if ($memberId === false) {
    st_json_error('You are not an active confirmed member of this trip.', 403);
}
$memberId = (int) $memberId;
$db->beginTransaction();
try {
    $db->prepare('UPDATE group_members SET last_lat = ?, last_lng = ? WHERE id = ?')
        ->execute([$lat, $lng, $memberId]);
    $db->prepare(
        'INSERT INTO group_member_positions
            (group_journey_id, group_member_id, user_id, lat, lng, speed_kmh, heading, accuracy)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            lat = VALUES(lat), lng = VALUES(lng), speed_kmh = VALUES(speed_kmh),
            heading = VALUES(heading), accuracy = VALUES(accuracy), updated_at = NOW()'
    )->execute([$groupId, $memberId, $userId, $lat, $lng, $speed, $heading, $accuracy]);
    $db->prepare(
        'INSERT INTO group_member_route_points (group_journey_id, group_member_id, user_id, lat, lng)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$groupId, $memberId, $userId, $lat, $lng]);
    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $error;
}

st_json_ok();
