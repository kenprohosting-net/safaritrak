<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
$userId = st_require_login();
$db = safaritrak_db();

$contactsStmt = $db->prepare(
    'SELECT tc.id, tc.invite_name, tc.invite_phone, tc.relationship, tc.status,
            tc.share_live_location, tc.journey_alerts, tc.sos_alerts, tc.contact_user_id,
            u.full_name AS linked_name, u.avatar_path AS linked_avatar
     FROM trusted_contacts tc
     LEFT JOIN users u ON u.id = tc.contact_user_id
     WHERE tc.owner_id = ?
     ORDER BY FIELD(tc.status, "confirmed", "pending", "declined"), tc.created_at DESC'
);
$contactsStmt->execute([$userId]);
$contacts = $contactsStmt->fetchAll();

foreach ($contacts as &$contact) {
    $contact['display_name'] = $contact['linked_name'] ?: $contact['invite_name'];
    unset($contact['linked_name']);
}
unset($contact);

$incomingStmt = $db->prepare(
    'SELECT tc.id, tc.relationship, tc.created_at, u.full_name AS owner_name, u.avatar_path AS owner_avatar
     FROM trusted_contacts tc
     JOIN users u ON u.id = tc.owner_id
     WHERE tc.contact_user_id = ? AND tc.status = "pending"
     ORDER BY tc.created_at DESC'
);
$incomingStmt->execute([$userId]);
$incoming = $incomingStmt->fetchAll();

st_json_ok(['contacts' => $contacts, 'incoming' => $incoming]);
