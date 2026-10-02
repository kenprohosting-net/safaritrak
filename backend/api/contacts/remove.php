<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$contactId = (int) ($input['contact_id'] ?? 0);

$db = safaritrak_db();

$stmt = $db->prepare('DELETE FROM trusted_contacts WHERE id = ? AND owner_id = ?');
$stmt->execute([$contactId, $userId]);

if ($stmt->rowCount() === 0) {
    st_json_error('That contact could not be found.', 404);
}

st_json_ok();
