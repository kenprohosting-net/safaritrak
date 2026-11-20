<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/geo.php';

st_start_session();
$userId = st_require_login();
$db = safaritrak_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $groupId = filter_var($_GET['group_id'] ?? null, FILTER_VALIDATE_INT);
    if ($groupId === false || $groupId < 1) {
        st_json_error('Choose a group.', 422);
    }
} else {
    st_require_method('POST');
    $input = st_input();
    $groupId = filter_var($input['group_id'] ?? null, FILTER_VALIDATE_INT);
    $action = st_input_string($input, 'action');
    if ($groupId === false || $groupId < 1 || !in_array($action, ['share', 'stop'], true)) {
        st_json_error('Choose a group and a valid location-sharing action.', 422);
    }
}

$membershipStmt = $db->prepare(
    'SELECT gm.sharing_location
     FROM persistent_group_members gm
     JOIN persistent_groups g ON g.id = gm.group_id
     WHERE gm.group_id = ? AND gm.user_id = ? AND g.status = "active"
       AND (
         g.organization_id IS NULL
         OR (
           EXISTS (
             SELECT 1 FROM organizations o
             JOIN organization_profiles p ON p.organization_id = o.id
             WHERE o.id = g.organization_id AND o.is_suspended = 0 AND p.review_status = "approved"
           )
           AND (
             EXISTS (
               SELECT 1 FROM organization_travelers ot
               WHERE ot.organization_id = g.organization_id AND ot.user_id = gm.user_id AND ot.status = "active"
             )
             OR EXISTS (
               SELECT 1 FROM organization_admins oa
               WHERE oa.organization_id = g.organization_id AND oa.user_id = gm.user_id
             )
           )
         )
       )'
);
$membershipStmt->execute([$groupId, $userId]);
$membership = $membershipStmt->fetch(PDO::FETCH_ASSOC);
if (!$membership) {
    st_json_error('You must be an active member of this group to view or share locations.', 403);
}

if ($method === 'POST') {
    if ($action === 'share') {
        $lat = st_parse_coordinate($input['lat'] ?? null, -90, 90);
        $lng = st_parse_coordinate($input['lng'] ?? null, -180, 180);
        if ($lat === null || $lng === null) {
            st_json_error('A valid location is required to share with this group.', 422);
        }
        $updateStmt = $db->prepare(
            'UPDATE persistent_group_members
             SET sharing_location = 1, last_lat = ?, last_lng = ?, location_updated_at = UTC_TIMESTAMP()
             WHERE group_id = ? AND user_id = ?'
        );
        $updateStmt->execute([$lat, $lng, $groupId, $userId]);
        st_json_ok(['sharing' => true]);
    }

    $db->prepare(
        'UPDATE persistent_group_members
         SET sharing_location = 0, last_lat = NULL, last_lng = NULL, location_updated_at = NULL
         WHERE group_id = ? AND user_id = ?'
    )->execute([$groupId, $userId]);
    st_json_ok(['sharing' => false]);
}

$locationsStmt = $db->prepare(
    'SELECT gm.user_id, u.full_name, u.username, gm.last_lat, gm.last_lng, gm.location_updated_at
     FROM persistent_group_members gm
     JOIN users u ON u.id = gm.user_id
     WHERE gm.group_id = ? AND gm.sharing_location = 1
       AND gm.last_lat IS NOT NULL AND gm.last_lng IS NOT NULL
       AND gm.location_updated_at >= UTC_TIMESTAMP() - INTERVAL 2 MINUTE
     ORDER BY u.full_name, gm.user_id'
);
$locationsStmt->execute([$groupId]);
$locations = [];
foreach ($locationsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $lat = st_parse_coordinate($row['last_lat'], -90, 90);
    $lng = st_parse_coordinate($row['last_lng'], -180, 180);
    if ($lat === null || $lng === null) {
        continue;
    }
    $locations[] = [
        'user_id' => (int) $row['user_id'],
        'name' => (string) ($row['full_name'] ?: '@' . $row['username']),
        'lat' => $lat,
        'lng' => $lng,
        'updated_at' => (string) $row['location_updated_at'],
        'is_mine' => (int) $row['user_id'] === $userId,
    ];
}

st_json_ok([
    'sharing' => (bool) $membership['sharing_location'],
    'locations' => $locations,
]);
