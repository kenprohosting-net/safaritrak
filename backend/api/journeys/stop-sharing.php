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
$trustedContactId = (int) ($input['trusted_contact_id'] ?? 0);

$db = safaritrak_db();

$journeyStmt = $db->prepare('SELECT id, start_label, end_label FROM journeys WHERE id = ? AND user_id = ?');
$journeyStmt->execute([$journeyId, $userId]);
$journey = $journeyStmt->fetch();

if (!$journey) {
    st_json_error('That journey could not be found.', 404);
}

$deleteStmt = $db->prepare(
    'DELETE js FROM journey_shares js
     JOIN trusted_contacts tc ON tc.id = js.trusted_contact_id
     WHERE js.journey_id = ? AND js.trusted_contact_id = ? AND tc.owner_id = ?'
);
$deleteStmt->execute([$journeyId, $trustedContactId, $userId]);

if ($deleteStmt->rowCount() === 0) {
    st_json_error('That share could not be found.', 404);
}

$contactStmt = $db->prepare('SELECT contact_user_id FROM trusted_contacts WHERE id = ?');
$contactStmt->execute([$trustedContactId]);
$contactUserId = $contactStmt->fetchColumn();

if ($contactUserId) {
    $ownerStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
    $ownerStmt->execute([$userId]);
    $ownerName = $ownerStmt->fetchColumn();

    st_notify(
        (int) $contactUserId,
        'location_share',
        $ownerName . ' stopped sharing a journey with you',
        $journey['start_label'] . ' to ' . $journey['end_label'],
        null,
        $userId
    );
}

st_json_ok();
