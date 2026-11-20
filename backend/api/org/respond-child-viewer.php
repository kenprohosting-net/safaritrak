<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$guardianId = st_require_login();
$input = st_input();
$accessId = filter_var($input['access_id'] ?? null, FILTER_VALIDATE_INT);
$action = st_input_string($input, 'action');
if (!$accessId || !in_array($action, ['accept', 'decline', 'revoke'], true)) {
    st_json_error('Choose a valid school invitation action.', 422);
}

$db = safaritrak_db();
$db->beginTransaction();
try {
    $accessStmt = $db->prepare(
        'SELECT ca.id, ca.organization_id, ca.child_user_id, ca.status,
                child.full_name AS child_name, o.name AS organization_name,
                ca.invited_by
         FROM organization_child_access ca
         JOIN users child ON child.id = ca.child_user_id
         JOIN organizations o ON o.id = ca.organization_id AND o.is_suspended = 0
         JOIN organization_profiles p ON p.organization_id = o.id
             AND p.account_type = "school" AND p.review_status = "approved"
         JOIN organization_travelers student
           ON student.organization_id = ca.organization_id
          AND student.user_id = ca.child_user_id
          AND student.status = "active" AND student.member_type = "student"
         JOIN organization_travelers guardian
           ON guardian.organization_id = ca.organization_id
          AND guardian.user_id = ca.guardian_user_id
          AND guardian.status = "active" AND guardian.member_type = "guardian"
         WHERE ca.id = ? AND ca.guardian_user_id = ? AND ca.status = ?
         FOR UPDATE'
    );
    $expectedStatus = $action === 'revoke' ? 'active' : 'pending';
    $accessStmt->execute([$accessId, $guardianId, $expectedStatus]);
    $access = $accessStmt->fetch(PDO::FETCH_ASSOC);
    if (!$access) {
        $db->rollBack();
        st_json_error('This invitation is no longer available.', 404);
    }

    $status = $action === 'accept' ? 'active' : 'revoked';
    $db->prepare(
        'UPDATE organization_child_access SET status = ?, responded_at = NOW() WHERE id = ?'
    )->execute([$status, $accessId]);
    if ($action === 'accept') {
        $db->prepare(
            'INSERT INTO school_verifications
                (user_id, school_id, role, student_user_id, student_name,
                 verification_status, verified_by, verified_at)
             VALUES (?, ?, "verified_parent", ?, ?, "verified", ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
               student_name = VALUES(student_name),
               verification_status = "verified",
               verified_by = VALUES(verified_by),
               verified_at = VALUES(verified_at)'
        )->execute([
            $guardianId,
            (int) $access['organization_id'],
            (int) $access['child_user_id'],
            $access['child_name'],
            (int) $access['invited_by'],
        ]);
    } elseif ($action === 'revoke') {
        $db->prepare(
            'UPDATE school_verifications
             SET verification_status = "revoked"
             WHERE user_id = ? AND school_id = ? AND student_user_id = ?
               AND role = "verified_parent"'
        )->execute([$guardianId, (int) $access['organization_id'], (int) $access['child_user_id']]);
    }
    if ($action === 'accept' || $action === 'revoke') {
        st_notify(
            (int) $access['invited_by'],
            'school_trip_access',
            $action === 'accept'
                ? 'A guardian accepted the school trip invitation'
                : 'A guardian ended student trip access',
            $access['child_name'] . ' · ' . $access['organization_name'],
            null,
            $guardianId
        );
    }
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

st_json_ok(['status' => $status]);
