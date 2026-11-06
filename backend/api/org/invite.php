<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/app-url.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$email = strtolower(trim(st_input_string($input, 'email')));
$memberType = st_input_string($input, 'member_type') ?: 'student';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    st_json_error('Enter a valid email address or leave it blank for a shareable link.', 422);
}
if (!in_array($memberType, ['student', 'guardian', 'staff'], true)) {
    st_json_error('Choose student, guardian, or staff for the invitation.', 422);
}

$db = safaritrak_db();
$orgStmt = $db->prepare(
    'SELECT o.id, p.account_type, p.review_status
     FROM organization_admins oa
     JOIN organizations o ON o.id = oa.organization_id
     JOIN organization_profiles p ON p.organization_id = o.id
     WHERE oa.user_id = ? AND o.is_suspended = 0 LIMIT 1'
);
$orgStmt->execute([$userId]);
$org = $orgStmt->fetch(PDO::FETCH_ASSOC);
if (!$org) {
    st_json_error('You do not manage an active organization.', 403);
}
if ($org['review_status'] !== 'approved') {
    st_json_error('Your organization must be approved before you can invite members.', 403);
}
if ($org['account_type'] !== 'school') {
    st_json_error('Membership enrollment links are available for school accounts.', 403);
}

$token = bin2hex(random_bytes(32));
$expiresAt = gmdate('Y-m-d H:i:s', time() + 7 * 24 * 60 * 60);
$db->prepare(
    'INSERT INTO organization_invites (organization_id, created_by, invited_email, member_type, token_hash, expires_at)
     VALUES (?, ?, ?, ?, ?, ?)'
)->execute([(int) $org['id'], $userId, $email === '' ? null : $email, $memberType, hash('sha256', $token), $expiresAt]);

st_json_ok([
    'invite_url' => st_app_url() . '/organization-invite.php?token=' . urlencode($token),
    'expires_at' => $expiresAt,
]);
