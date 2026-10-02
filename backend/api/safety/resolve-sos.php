<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$alertId = (int) ($input['alert_id'] ?? 0);

$db = safaritrak_db();

$stmt = $db->prepare('SELECT id FROM sos_alerts WHERE id = ? AND user_id = ? AND status = "active"');
$stmt->execute([$alertId, $userId]);
if (!$stmt->fetch()) {
    st_json_error('That alert could not be found.', 404);
}

$db->prepare('UPDATE sos_alerts SET status = "resolved", resolved_by = ?, resolved_at = NOW() WHERE id = ?')
    ->execute([$userId, $alertId]);

$ownerStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
$ownerStmt->execute([$userId]);
$ownerName = $ownerStmt->fetchColumn();

$contactsStmt = $db->prepare(
    'SELECT contact_user_id FROM trusted_contacts
     WHERE owner_id = ? AND status = "confirmed" AND sos_alerts = 1 AND contact_user_id IS NOT NULL'
);
$contactsStmt->execute([$userId]);

foreach ($contactsStmt->fetchAll() as $contact) {
    st_notify(
        (int) $contact['contact_user_id'],
        'sos_alert',
        $ownerName . ' is safe now',
        'The earlier SOS alert has been resolved.',
        null,
        $userId
    );
}

st_json_ok();