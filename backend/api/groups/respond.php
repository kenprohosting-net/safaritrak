<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$memberId = (int) ($input['member_id'] ?? 0);
$action = $input['action'] ?? '';

if (!in_array($action, ['confirm', 'decline', 'leave'], true)) {
    st_json_error('That action is not recognized.');
}

$db = safaritrak_db();

$stmt = $db->prepare(
    'SELECT gm.id, gm.status, gm.group_journey_id, gj.organizer_id, gj.title
     FROM group_members gm
     JOIN group_journeys gj ON gj.id = gm.group_journey_id
     WHERE gm.id = ? AND gm.user_id = ?'
);
$stmt->execute([$memberId, $userId]);
$member = $stmt->fetch();

if (!$member) {
    st_json_error('That membership could not be found.', 404);
}

if (in_array($action, ['confirm', 'decline'], true) && $member['status'] !== 'invited') {
    st_json_error('That invitation has already been handled.');
}

if ($action === 'leave' && $member['status'] !== 'confirmed') {
    st_json_error('You are not currently part of this group.');
}

if ($action === 'leave' && (int) $member['organizer_id'] === $userId) {
    st_json_error('You organized this trip. Cancel it instead of leaving.');
}

$newStatus = $action === 'confirm' ? 'confirmed' : 'declined';
$db->prepare('UPDATE group_members SET status = ? WHERE id = ?')->execute([$newStatus, $memberId]);

$nameStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
$nameStmt->execute([$userId]);
$myName = $nameStmt->fetchColumn();

$verb = $action === 'confirm' ? 'joined' : ($action === 'leave' ? 'left' : 'declined');
st_notify(
    (int) $member['organizer_id'],
    'group_invite',
    $myName . ' ' . $verb . ' ' . $member['title'],
    null,
    null,
    $userId
);

st_json_ok(['status' => $newStatus]);
