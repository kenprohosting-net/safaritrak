<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$lat = isset($input['lat']) && $input['lat'] !== '' ? (float) $input['lat'] : null;
$lng = isset($input['lng']) && $input['lng'] !== '' ? (float) $input['lng'] : null;

$db = safaritrak_db();

$existing = $db->prepare('SELECT id FROM sos_alerts WHERE user_id = ? AND status = "active"');
$existing->execute([$userId]);
if ($existing->fetch()) {
    st_json_error('You already have an active SOS alert. Resolve it before sending a new one.');
}

$journeyStmt = $db->prepare('SELECT id FROM journeys WHERE user_id = ? AND status = "active" LIMIT 1');
$journeyStmt->execute([$userId]);
$activeJourneyId = $journeyStmt->fetchColumn();

$insert = $db->prepare(
    'INSERT INTO sos_alerts (user_id, journey_id, lat, lng, status) VALUES (?, ?, ?, ?, "active")'
);
$insert->execute([$userId, $activeJourneyId ?: null, $lat, $lng]);
$alertId = (int) $db->lastInsertId();

$ownerStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
$ownerStmt->execute([$userId]);
$ownerName = $ownerStmt->fetchColumn();

$contactsStmt = $db->prepare(
    'SELECT contact_user_id FROM trusted_contacts
     WHERE owner_id = ? AND status = "confirmed" AND sos_alerts = 1 AND contact_user_id IS NOT NULL'
);
$contactsStmt->execute([$userId]);
$contacts = $contactsStmt->fetchAll();

$locationNote = ($lat !== null && $lng !== null)
    ? 'Their last known location has been shared with you.'
    : 'Their location was not available at the time of the alert.';

foreach ($contacts as $contact) {
    st_notify(
        (int) $contact['contact_user_id'],
        'sos_alert',
        $ownerName . ' needs help',
        $locationNote,
        $activeJourneyId ?: null,
        $userId
    );
}

st_json_ok(['alert_id' => $alertId, 'notified_count' => count($contacts)]);