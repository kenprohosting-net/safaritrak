<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$token = trim(st_input_string($input, 'token'));
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    st_json_error('This group invitation is invalid or expired.', 404);
}

$db = safaritrak_db();
$db->beginTransaction();
try {
    $inviteStmt = $db->prepare(
        'SELECT i.id, i.group_id, i.invited_email, g.organization_id
         FROM persistent_group_invites i
         JOIN persistent_groups g ON g.id = i.group_id
         WHERE i.token_hash = ? AND i.expires_at > UTC_TIMESTAMP()
           AND i.accepted_at IS NULL AND i.revoked_at IS NULL AND g.status = "active"
         FOR UPDATE'
    );
    $inviteStmt->execute([hash('sha256', $token)]);
    $invite = $inviteStmt->fetch(PDO::FETCH_ASSOC);
    if (!$invite) {
        $db->rollBack();
        st_json_error('This group invitation is invalid, expired, or already used.', 404);
    }

    $userStmt = $db->prepare('SELECT email FROM users WHERE id = ?');
    $userStmt->execute([$userId]);
    $email = $userStmt->fetchColumn();
    if ($invite['invited_email'] !== null && strcasecmp((string) $invite['invited_email'], (string) $email) !== 0) {
        $db->rollBack();
        st_json_error('Sign in with the email address this invitation was sent to.', 403);
    }

    if ($invite['organization_id'] !== null) {
        $orgMemberStmt = $db->prepare(
            'SELECT 1 FROM organization_travelers ot
             JOIN organizations o ON o.id = ot.organization_id AND o.is_suspended = 0
             JOIN organization_profiles p ON p.organization_id = o.id AND p.review_status = "approved"
             WHERE ot.organization_id = ? AND ot.user_id = ? AND ot.status = "active"'
        );
        $orgMemberStmt->execute([(int) $invite['organization_id'], $userId]);
        if (!$orgMemberStmt->fetchColumn()) {
            $db->rollBack();
            st_json_error('Organization groups are limited to approved organization members.', 403);
        }
    }

    $db->prepare(
        'INSERT INTO persistent_group_members (group_id, user_id, role)
         VALUES (?, ?, "member")
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)'
    )->execute([(int) $invite['group_id'], $userId]);
    $db->prepare(
        'UPDATE persistent_group_invites SET accepted_by = ?, accepted_at = UTC_TIMESTAMP() WHERE id = ?'
    )->execute([$userId, (int) $invite['id']]);
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

st_json_ok(['group_id' => (int) $invite['group_id']]);
