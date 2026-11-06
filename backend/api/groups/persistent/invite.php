<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/app-url.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$groupId = (int) ($input['group_id'] ?? 0);
$email = strtolower(trim(st_input_string($input, 'email')));
if ($groupId < 1 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    st_json_error('Choose a group and enter a valid email address, or leave email blank for a shareable link.', 422);
}

$db = safaritrak_db();
$membershipStmt = $db->prepare(
    'SELECT g.organization_id, gm.role
     FROM persistent_groups g
     JOIN persistent_group_members gm ON gm.group_id = g.id AND gm.user_id = ?
     WHERE g.id = ? AND g.status = "active"
       AND gm.role IN ("owner", "admin")
       AND (
         g.organization_id IS NULL
         OR (
           EXISTS (
             SELECT 1 FROM organizations o
             JOIN organization_profiles p ON p.organization_id = o.id
             WHERE o.id = g.organization_id AND o.is_suspended = 0 AND p.review_status = "approved"
           )
           AND (
             EXISTS (
               SELECT 1 FROM organization_travelers ot
               WHERE ot.organization_id = g.organization_id AND ot.user_id = gm.user_id AND ot.status = "active"
             )
             OR EXISTS (
               SELECT 1 FROM organization_admins oa
               WHERE oa.organization_id = g.organization_id AND oa.user_id = gm.user_id
             )
           )
         )
       )'
);
$membershipStmt->execute([$userId, $groupId]);
$group = $membershipStmt->fetch(PDO::FETCH_ASSOC);
if (!$group) {
    st_json_error('Only group owners and admins can invite members.', 403);
}
if ($group['organization_id'] !== null && $email !== '') {
    $orgMemberStmt = $db->prepare(
        'SELECT 1 FROM organization_travelers ot JOIN users u ON u.id = ot.user_id
         WHERE ot.organization_id = ? AND u.email = ? AND ot.status = "active"'
    );
    $orgMemberStmt->execute([(int) $group['organization_id'], $email]);
    if (!$orgMemberStmt->fetchColumn()) {
        st_json_error('Only approved members of this organization can join its groups.', 403);
    }
}

$token = bin2hex(random_bytes(32));
$expiresAt = gmdate('Y-m-d H:i:s', time() + 7 * 24 * 60 * 60);
$db->prepare(
    'INSERT INTO persistent_group_invites (group_id, created_by, invited_email, token_hash, expires_at)
     VALUES (?, ?, ?, ?, ?)'
)->execute([$groupId, $userId, $email === '' ? null : $email, hash('sha256', $token), $expiresAt]);

st_json_ok([
    'invite_url' => st_app_url() . '/group-invite.php?token=' . urlencode($token),
    'expires_at' => $expiresAt,
]);
