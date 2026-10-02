<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$name = trim($input['name'] ?? '');
$phone = trim($input['phone'] ?? '');
$relationship = trim($input['relationship'] ?? '');

$errors = [];

if ($name === '') {
    $errors['name'] = 'Enter their full name.';
}

$phoneDigits = preg_replace('/\D/', '', $phone);
if (strlen($phoneDigits) < 9) {
    $errors['phone'] = 'Enter a valid phone number.';
}

if (!empty($errors)) {
    st_json_error('Please fix the highlighted fields.', 422, ['errors' => $errors]);
}

$db = safaritrak_db();

$selfCheck = $db->prepare('SELECT phone FROM users WHERE id = ?');
$selfCheck->execute([$userId]);
if ($selfCheck->fetchColumn() === $phoneDigits) {
    st_json_error('You cannot add your own phone number as a trusted contact.');
}

$dupeCheck = $db->prepare('SELECT id FROM trusted_contacts WHERE owner_id = ? AND invite_phone = ? AND status != "declined"');
$dupeCheck->execute([$userId, $phoneDigits]);
if ($dupeCheck->fetch()) {
    st_json_error('You have already added this phone number as a trusted contact.');
}

$matchStmt = $db->prepare('SELECT id, full_name, discoverable_by_phone FROM users WHERE phone = ?');
$matchStmt->execute([$phoneDigits]);
$matchedUser = $matchStmt->fetch();

$contactUserId = ($matchedUser && $matchedUser['discoverable_by_phone']) ? (int) $matchedUser['id'] : null;

$insert = $db->prepare(
    'INSERT INTO trusted_contacts (owner_id, contact_user_id, invite_name, invite_phone, relationship, status)
     VALUES (?, ?, ?, ?, ?, "pending")'
);
$insert->execute([$userId, $contactUserId, $name, $phoneDigits, $relationship ?: null]);

if ($contactUserId) {
    $ownerStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
    $ownerStmt->execute([$userId]);
    $ownerName = $ownerStmt->fetchColumn();

    st_notify(
        $contactUserId,
        'contact_request',
        $ownerName . ' wants to add you as a trusted contact',
        'Confirm this to let them see your journeys and location when you choose to share.',
        null,
        $userId
    );
}

st_json_ok(['id' => (int) $db->lastInsertId(), 'linked' => $contactUserId !== null]);
