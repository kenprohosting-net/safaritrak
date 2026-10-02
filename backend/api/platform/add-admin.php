<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$db = safaritrak_db();

$myRoleStmt = $db->prepare('SELECT role FROM platform_admins WHERE user_id = ?');
$myRoleStmt->execute([$userId]);
$myRole = $myRoleStmt->fetchColumn();

if ($myRole !== 'owner') {
    st_json_error('Only the platform owner can add admins.', 403);
}

$input = st_input();
$identifier = trim($input['identifier'] ?? '');

if ($identifier === '') {
    st_json_error('Enter a username, email or phone number.');
}

$phoneDigits = preg_replace('/\D/', '', $identifier);
$userStmt = $db->prepare('SELECT id, full_name FROM users WHERE username = ? OR email = ? OR (phone = ? AND ? != "")');
$userStmt->execute([$identifier, strtolower($identifier), $phoneDigits, $phoneDigits]);
$target = $userStmt->fetch();

if (!$target) {
    st_json_error('No SafariTrak account matches that.');
}

$existingStmt = $db->prepare('SELECT id FROM platform_admins WHERE user_id = ?');
$existingStmt->execute([$target['id']]);
if ($existingStmt->fetch()) {
    st_json_error('That person is already a platform admin.');
}

$db->prepare('INSERT INTO platform_admins (user_id, role) VALUES (?, "staff")')->execute([$target['id']]);

st_notify((int) $target['id'], 'group_invite', 'You were made a SafariTrak platform admin', 'You now have platform-wide administrative access.', null, $userId);

st_json_ok(['full_name' => $target['full_name']]);
