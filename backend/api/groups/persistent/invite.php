<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/rate-limit.php';
require_once __DIR__ . '/../../../includes/app-url.php';
require_once __DIR__ . '/../../../includes/mailer.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$groupId = (int) ($input['group_id'] ?? 0);
$username = strtolower(trim(st_input_string($input, 'username')));
$email = strtolower(trim(st_input_string($input, 'email')));
if (
    $groupId < 1
    || ($username !== '' && !preg_match('/^[a-zA-Z0-9_.]{3,40}$/', $username))
    || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
    || ($username !== '' && $email !== '')
) {
    st_json_error('Choose a group, then invite a username or enter a valid email address for a share link.', 422);
}

$db = safaritrak_db();
$membershipStmt = $db->prepare(
    'SELECT g.organization_id, g.name, gm.role, u.full_name AS inviter_name
     FROM persistent_groups g
     JOIN persistent_group_members gm ON gm.group_id = g.id AND gm.user_id = ?
     JOIN users u ON u.id = gm.user_id
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
$retryAfter = st_rate_limit_consume($db, 'group-invite-user', (string) $userId, 10, 3600);
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    st_json_error('Too many group invitations. Wait before inviting more members.', 429);
}
$invitee = null;
if ($username !== '') {
    $inviteeStmt = $db->prepare(
        'SELECT id, username, email
         FROM users
         WHERE username = ? AND is_suspended = 0'
    );
    $inviteeStmt->execute([$username]);
    $invitee = $inviteeStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$invitee) {
        st_json_error('No active account was found with that username.', 404);
    }
    if ((int) $invitee['id'] === $userId) {
        st_json_error('You are already a member of this group.', 422);
    }
    $existingMemberStmt = $db->prepare(
        'SELECT 1 FROM persistent_group_members WHERE group_id = ? AND user_id = ?'
    );
    $existingMemberStmt->execute([$groupId, (int) $invitee['id']]);
    if ($existingMemberStmt->fetchColumn()) {
        st_json_error('That user is already a member of this group.', 409);
    }
}
if ($group['organization_id'] !== null && $invitee !== null) {
    $orgMemberStmt = $db->prepare(
        'SELECT 1
         FROM organization_travelers ot
         WHERE ot.organization_id = ? AND ot.user_id = ? AND ot.status = "active"
         UNION
         SELECT 1
         FROM organization_admins oa
         WHERE oa.organization_id = ? AND oa.user_id = ?'
    );
    $orgMemberStmt->execute([
        (int) $group['organization_id'],
        (int) $invitee['id'],
        (int) $group['organization_id'],
        (int) $invitee['id'],
    ]);
    if (!$orgMemberStmt->fetchColumn()) {
        st_json_error('Only approved members of this organization can join its groups.', 403);
    }
}
if ($group['organization_id'] !== null && $invitee === null && $email !== '') {
    $orgEmailStmt = $db->prepare(
        'SELECT 1
         FROM organization_travelers ot
         JOIN users u ON u.id = ot.user_id
         WHERE ot.organization_id = ? AND u.email = ? AND ot.status = "active"
         UNION
         SELECT 1
         FROM organization_admins oa
         JOIN users u ON u.id = oa.user_id
         WHERE oa.organization_id = ? AND u.email = ?'
    );
    $orgEmailStmt->execute([
        (int) $group['organization_id'],
        $email,
        (int) $group['organization_id'],
        $email,
    ]);
    if (!$orgEmailStmt->fetchColumn()) {
        st_json_error('Only approved members of this organization can join its groups.', 403);
    }
}

$token = bin2hex(random_bytes(32));
$expiresAt = gmdate('Y-m-d H:i:s', time() + 7 * 24 * 60 * 60);
$recipientEmail = $invitee !== null ? (string) $invitee['email'] : $email;
$db->prepare(
    'INSERT INTO persistent_group_invites
        (group_id, created_by, invited_email, invited_user_id, token_hash, expires_at)
     VALUES (?, ?, NULL, ?, ?, ?)'
)->execute([
    $groupId,
    $userId,
    $invitee !== null ? (int) $invitee['id'] : null,
    hash('sha256', $token),
    $expiresAt,
]);

$inviteUrl = st_app_url() . '/group-invite.php?token=' . urlencode($token);
$emailSent = null;
if ($recipientEmail !== '') {
    $emailSent = st_send_group_invitation_email(
        $recipientEmail,
        (string) $group['name'],
        (string) $group['inviter_name'],
        $inviteUrl
    );
}

st_json_ok([
    'invite_url' => $inviteUrl,
    'expires_at' => $expiresAt,
    'email_sent' => $emailSent,
    'invited_username' => $invitee['username'] ?? null,
]);
