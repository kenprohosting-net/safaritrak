<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$contactId = (int) ($input['contact_id'] ?? 0);
$action = $input['action'] ?? '';

if (!in_array($action, ['confirm', 'decline'], true)) {
    st_json_error('That action is not recognized.');
}

$db = safaritrak_db();

$stmt = $db->prepare('SELECT id, owner_id, contact_user_id, status FROM trusted_contacts WHERE id = ? AND contact_user_id = ?');
$stmt->execute([$contactId, $userId]);
$row = $stmt->fetch();

if (!$row) {
    st_json_error('That request could not be found.', 404);
}

if ($row['status'] !== 'pending') {
    st_json_error('That request has already been handled.');
}

if ($action === 'decline') {
    $db->prepare('UPDATE trusted_contacts SET status = "declined" WHERE id = ?')->execute([$contactId]);
    st_json_ok(['status' => 'declined']);
}

$db->prepare('UPDATE trusted_contacts SET status = "confirmed" WHERE id = ?')->execute([$contactId]);

$ownerId = (int) $row['owner_id'];

$reciprocal = $db->prepare('SELECT id FROM trusted_contacts WHERE owner_id = ? AND contact_user_id = ?');
$reciprocal->execute([$userId, $ownerId]);
$reciprocalRow = $reciprocal->fetch();

$namesStmt = $db->prepare('SELECT id, full_name, phone FROM users WHERE id IN (?, ?)');
$namesStmt->execute([$userId, $ownerId]);
$names = [];
foreach ($namesStmt->fetchAll() as $u) {
    $names[$u['id']] = $u;
}

if ($reciprocalRow) {
    $db->prepare('UPDATE trusted_contacts SET status = "confirmed", contact_user_id = ? WHERE id = ?')
        ->execute([$ownerId, $reciprocalRow['id']]);
} else {
    $db->prepare(
        'INSERT INTO trusted_contacts (owner_id, contact_user_id, invite_name, invite_phone, status)
         VALUES (?, ?, ?, ?, "confirmed")'
    )->execute([$userId, $ownerId, $names[$ownerId]['full_name'], $names[$ownerId]['phone']]);
}

st_notify(
    $ownerId,
    'contact_request',
    $names[$userId]['full_name'] . ' accepted your trusted contact request',
    'You can now share journeys and see each other as trusted contacts.',
    null,
    $userId
);

st_json_ok(['status' => 'confirmed']);
