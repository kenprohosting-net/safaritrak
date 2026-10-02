<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$db = safaritrak_db();

$adminStmt = $db->prepare('SELECT id FROM platform_admins WHERE user_id = ?');
$adminStmt->execute([$userId]);
if (!$adminStmt->fetch()) {
    st_json_error('You do not have platform admin access.', 403);
}

$input = st_input();
$alertId = (int) ($input['alert_id'] ?? 0);

$alertStmt = $db->prepare('SELECT id, user_id FROM sos_alerts WHERE id = ? AND status = "active"');
$alertStmt->execute([$alertId]);
$alert = $alertStmt->fetch();

if (!$alert) {
    st_json_error('That alert could not be found or is already resolved.', 404);
}

$db->prepare('UPDATE sos_alerts SET status = "resolved", resolved_by = ?, resolved_at = NOW() WHERE id = ?')
    ->execute([$userId, $alertId]);

$travelerId = (int) $alert['user_id'];

st_notify($travelerId, 'sos_alert', 'Your SOS alert was resolved by SafariTrak support', null, null, $userId);

$contactsStmt = $db->prepare(
    'SELECT contact_user_id FROM trusted_contacts
     WHERE owner_id = ? AND status = "confirmed" AND sos_alerts = 1 AND contact_user_id IS NOT NULL'
);
$contactsStmt->execute([$travelerId]);

$travelerNameStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
$travelerNameStmt->execute([$travelerId]);
$travelerName = $travelerNameStmt->fetchColumn();

foreach ($contactsStmt->fetchAll() as $c) {
    st_notify((int) $c['contact_user_id'], 'sos_alert', $travelerName . ' is safe now', 'Resolved by SafariTrak support.', null, $travelerId);
}

st_json_ok();
