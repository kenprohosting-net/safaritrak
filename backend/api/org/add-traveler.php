<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$phone = preg_replace('/\D/', '', $input['phone'] ?? '');

if (strlen($phone) < 9) {
    st_json_error('Enter a valid phone number.');
}

$db = safaritrak_db();

$adminStmt = $db->prepare('SELECT organization_id FROM organization_admins WHERE user_id = ?');
$adminStmt->execute([$userId]);
$orgId = $adminStmt->fetchColumn();

if (!$orgId) {
    st_json_error('You do not manage an organization.', 403);
}

$travelerStmt = $db->prepare('SELECT id, full_name FROM users WHERE phone = ?');
$travelerStmt->execute([$phone]);
$traveler = $travelerStmt->fetch();

if (!$traveler) {
    st_json_error('No SafariTrak account was found with that phone number yet.');
}

if ((int) $traveler['id'] === $userId) {
    st_json_error('You cannot add yourself as a traveler.');
}

$existingStmt = $db->prepare('SELECT id, status FROM organization_travelers WHERE organization_id = ? AND user_id = ?');
$existingStmt->execute([$orgId, $traveler['id']]);
$existing = $existingStmt->fetch();

if ($existing && $existing['status'] === 'active') {
    st_json_error('This person is already part of your organization.');
}

if ($existing) {
    $db->prepare('UPDATE organization_travelers SET status = "active", joined_at = NOW() WHERE id = ?')->execute([$existing['id']]);
} else {
    $db->prepare('INSERT INTO organization_travelers (organization_id, user_id, status) VALUES (?, ?, "active")')
        ->execute([$orgId, $traveler['id']]);
}

$orgNameStmt = $db->prepare('SELECT name FROM organizations WHERE id = ?');
$orgNameStmt->execute([$orgId]);
$orgName = $orgNameStmt->fetchColumn();

st_notify(
    (int) $traveler['id'],
    'group_invite',
    'You were added to ' . $orgName,
    'An organization admin can now see your journeys and safety activity.',
    null,
    $userId
);

st_json_ok(['traveler_id' => (int) $traveler['id'], 'full_name' => $traveler['full_name']]);
