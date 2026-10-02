<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$travelerRowId = (int) ($input['traveler_id'] ?? 0);
$action = $input['action'] ?? '';

if (!in_array($action, ['deactivate', 'reactivate'], true)) {
    st_json_error('That action is not recognized.');
}

$db = safaritrak_db();

$adminStmt = $db->prepare('SELECT organization_id, o.name AS org_name FROM organization_admins oa JOIN organizations o ON o.id = oa.organization_id WHERE oa.user_id = ?');
$adminStmt->execute([$userId]);
$admin = $adminStmt->fetch();

if (!$admin) {
    st_json_error('You do not manage an organization.', 403);
}

$stmt = $db->prepare('SELECT id, user_id, status FROM organization_travelers WHERE id = ? AND organization_id = ?');
$stmt->execute([$travelerRowId, $admin['organization_id']]);
$traveler = $stmt->fetch();

if (!$traveler) {
    st_json_error('That traveler could not be found.', 404);
}

$newStatus = $action === 'deactivate' ? 'deactivated' : 'active';
$db->prepare('UPDATE organization_travelers SET status = ? WHERE id = ?')->execute([$newStatus, $travelerRowId]);

st_notify(
    (int) $traveler['user_id'],
    'group_invite',
    $action === 'deactivate' ? 'Your access to ' . $admin['org_name'] . ' was removed' : 'You were re-added to ' . $admin['org_name'],
    null,
    null,
    $userId
);

st_json_ok(['status' => $newStatus]);
