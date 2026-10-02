<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

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
$targetUserId = (int) ($input['user_id'] ?? 0);
$action = $input['action'] ?? '';

if (!in_array($action, ['suspend', 'reactivate'], true)) {
    st_json_error('That action is not recognized.');
}

if ($targetUserId === $userId) {
    st_json_error('You cannot suspend your own account.');
}

$targetStmt = $db->prepare('SELECT id FROM users WHERE id = ?');
$targetStmt->execute([$targetUserId]);
if (!$targetStmt->fetch()) {
    st_json_error('That user could not be found.', 404);
}

$newStatus = $action === 'suspend' ? 1 : 0;
$db->prepare('UPDATE users SET is_suspended = ? WHERE id = ?')->execute([$newStatus, $targetUserId]);

if ($action === 'suspend') {
    $db->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$targetUserId]);
}

st_json_ok(['status' => $newStatus ? 'suspended' : 'active']);
