<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$token = st_input_string(st_input(), 'token');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    st_json_error('This organization invitation is invalid.', 422);
}

$db = safaritrak_db();
$db->beginTransaction();
try {
    $inviteStmt = $db->prepare(
        'SELECT i.id, i.organization_id, i.invited_email, i.member_type, o.name AS organization_name
         FROM organization_invites i
         JOIN organizations o ON o.id = i.organization_id
         JOIN organization_profiles p ON p.organization_id = o.id
         WHERE i.token_hash = ? AND i.expires_at > UTC_TIMESTAMP() AND i.accepted_at IS NULL
           AND p.account_type = "school" AND p.review_status = "approved" AND o.is_suspended = 0
         FOR UPDATE'
    );
    $inviteStmt->execute([hash('sha256', $token)]);
    $invite = $inviteStmt->fetch(PDO::FETCH_ASSOC);
    if (!$invite) {
        $db->rollBack();
        st_json_error('This organization invitation is invalid or expired.', 404);
    }

    $userStmt = $db->prepare('SELECT email FROM users WHERE id = ?');
    $userStmt->execute([$userId]);
    $email = strtolower((string) $userStmt->fetchColumn());
    if ($invite['invited_email'] !== null && strtolower($invite['invited_email']) !== $email) {
        $db->rollBack();
        st_json_error('This invitation was sent to a different email address.', 403);
    }

    $requestStmt = $db->prepare(
        'INSERT INTO organization_membership_requests (organization_id, user_id, invite_id, member_type)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           invite_id = VALUES(invite_id),
           member_type = VALUES(member_type),
           status = "pending",
           reviewed_by = NULL,
           reviewed_at = NULL'
    );
    $requestStmt->execute([
        (int) $invite['organization_id'],
        $userId,
        (int) $invite['id'],
        $invite['member_type'],
    ]);
    $db->prepare('UPDATE organization_invites SET accepted_at = UTC_TIMESTAMP() WHERE id = ? AND accepted_at IS NULL')
        ->execute([(int) $invite['id']]);
    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $error;
}

st_json_ok([
    'status' => 'pending',
    'organization' => $invite['organization_name'],
    'message' => 'Your request was sent. Organization admins must approve access before they can see your journeys.',
]);
