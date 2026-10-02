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

$db = safaritrak_db();

$stmt = $db->prepare(
    'SELECT gm.id, gm.user_id, gm.status, gj.organizer_id, gj.title
     FROM group_members gm
     JOIN group_journeys gj ON gj.id = gm.group_journey_id
     WHERE gm.id = ?'
);
$stmt->execute([$memberId]);
$member = $stmt->fetch();

if (!$member || (int) $member['organizer_id'] !== $userId) {
    st_json_error('That member could not be found.', 404);
}

if ((int) $member['user_id'] === $userId) {
    st_json_error('You cannot remove yourself as the organizer. Cancel the trip instead.');
}

$db->prepare('DELETE FROM group_members WHERE id = ?')->execute([$memberId]);

if ($member['user_id']) {
    st_notify(
        (int) $member['user_id'],
        'group_invite',
        'You were removed from ' . $member['title'],
        null,
        null,
        $userId
    );
}

st_json_ok();
