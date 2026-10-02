<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$db = safaritrak_db();

$myRoleStmt = $db->prepare('SELECT role FROM platform_admins WHERE user_id = ?');
$myRoleStmt->execute([$userId]);
$myRole = $myRoleStmt->fetchColumn();

if ($myRole !== 'owner') {
    st_json_error('Only the platform owner can remove admins.', 403);
}

$input = st_input();
$adminId = (int) ($input['admin_id'] ?? 0);

$targetStmt = $db->prepare('SELECT id, user_id, role FROM platform_admins WHERE id = ?');
$targetStmt->execute([$adminId]);
$target = $targetStmt->fetch();

if (!$target) {
    st_json_error('That admin could not be found.', 404);
}

if ((int) $target['user_id'] === $userId) {
    st_json_error('You cannot remove yourself. Ask another owner to do this.');
}

if ($target['role'] === 'owner') {
    st_json_error('Owners cannot be removed from here.');
}

$db->prepare('DELETE FROM platform_admins WHERE id = ?')->execute([$adminId]);

st_json_ok();
