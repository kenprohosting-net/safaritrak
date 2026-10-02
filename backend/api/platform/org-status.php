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
$orgId = (int) ($input['organization_id'] ?? 0);
$action = $input['action'] ?? '';

if (!in_array($action, ['suspend', 'reactivate'], true)) {
    st_json_error('That action is not recognized.');
}

$orgStmt = $db->prepare('SELECT id FROM organizations WHERE id = ?');
$orgStmt->execute([$orgId]);
if (!$orgStmt->fetch()) {
    st_json_error('That organization could not be found.', 404);
}

$newStatus = $action === 'suspend' ? 1 : 0;
$db->prepare('UPDATE organizations SET is_suspended = ? WHERE id = ?')->execute([$newStatus, $orgId]);

st_json_ok(['status' => $newStatus ? 'suspended' : 'active']);
