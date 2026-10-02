<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$contactId = (int) ($input['contact_id'] ?? 0);
$field = $input['field'] ?? '';
$value = !empty($input['value']) ? 1 : 0;

$allowedFields = ['share_live_location', 'journey_alerts', 'sos_alerts'];
if (!in_array($field, $allowedFields, true)) {
    st_json_error('That setting is not recognized.');
}

$db = safaritrak_db();

$stmt = $db->prepare("UPDATE trusted_contacts SET {$field} = ? WHERE id = ? AND owner_id = ?");
$stmt->execute([$value, $contactId, $userId]);

if ($stmt->rowCount() === 0) {
    $checkStmt = $db->prepare('SELECT id FROM trusted_contacts WHERE id = ? AND owner_id = ?');
    $checkStmt->execute([$contactId, $userId]);
    if (!$checkStmt->fetch()) {
        st_json_error('That contact could not be found.', 404);
    }
}

st_json_ok();
