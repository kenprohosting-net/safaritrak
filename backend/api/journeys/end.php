<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$journeyId = (int) ($input['journey_id'] ?? 0);

$db = safaritrak_db();

$stmt = $db->prepare('SELECT id, start_label, end_label, status FROM journeys WHERE id = ? AND user_id = ?');
$stmt->execute([$journeyId, $userId]);
$journey = $stmt->fetch();

if (!$journey) {
    st_json_error('That journey could not be found.', 404);
}

if ($journey['status'] !== 'active') {
    st_json_error('That journey is not currently active.');
}

$db->prepare('UPDATE journeys SET status = "completed", ended_at = NOW() WHERE id = ?')->execute([$journeyId]);

$ownerStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
$ownerStmt->execute([$userId]);
$ownerName = $ownerStmt->fetchColumn();

$sharedStmt = $db->prepare(
    'SELECT tc.contact_user_id FROM journey_shares js
     JOIN trusted_contacts tc ON tc.id = js.trusted_contact_id
     WHERE js.journey_id = ? AND tc.contact_user_id IS NOT NULL'
);
$sharedStmt->execute([$journeyId]);

foreach ($sharedStmt->fetchAll() as $row) {
    st_notify(
        (int) $row['contact_user_id'],
        'journey_completed',
        $ownerName . ' arrived safely',
        $journey['start_label'] . ' to ' . $journey['end_label'],
        $journeyId,
        $userId
    );
}

st_json_ok();
