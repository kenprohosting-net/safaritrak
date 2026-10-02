<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$groupId = (int) ($input['group_id'] ?? 0);

$db = safaritrak_db();

$stmt = $db->prepare('SELECT id, title, organizer_id, status FROM group_journeys WHERE id = ? AND organizer_id = ?');
$stmt->execute([$groupId, $userId]);
$group = $stmt->fetch();

if (!$group) {
    st_json_error('That group journey could not be found.', 404);
}

if ($group['status'] !== 'upcoming') {
    st_json_error('This trip cannot be started from its current status.');
}

$db->prepare('UPDATE group_journeys SET status = "active" WHERE id = ?')->execute([$groupId]);

$membersStmt = $db->prepare('SELECT user_id FROM group_members WHERE group_journey_id = ? AND status = "confirmed" AND user_id != ?');
$membersStmt->execute([$groupId, $userId]);

foreach ($membersStmt->fetchAll() as $m) {
    st_notify((int) $m['user_id'], 'group_invite', $group['title'] . ' has started', 'Live tracking is now on for this trip.', null, $userId);
}

st_json_ok();
