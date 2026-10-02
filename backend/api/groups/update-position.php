<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$groupId = (int) ($input['group_id'] ?? 0);
$lat = isset($input['lat']) ? (float) $input['lat'] : null;
$lng = isset($input['lng']) ? (float) $input['lng'] : null;

if ($lat === null || $lng === null) {
    st_json_error('A location is required.');
}

$db = safaritrak_db();

$stmt = $db->prepare(
    'SELECT gm.id FROM group_members gm
     JOIN group_journeys gj ON gj.id = gm.group_journey_id
     WHERE gm.group_journey_id = ? AND gm.user_id = ? AND gm.status = "confirmed" AND gj.status = "active"'
);
$stmt->execute([$groupId, $userId]);

if (!$stmt->fetch()) {
    st_json_error('You are not an active confirmed member of this trip.', 403);
}

$db->prepare('UPDATE group_members SET last_lat = ?, last_lng = ? WHERE group_journey_id = ? AND user_id = ?')
    ->execute([$lat, $lng, $groupId, $userId]);

st_json_ok();
