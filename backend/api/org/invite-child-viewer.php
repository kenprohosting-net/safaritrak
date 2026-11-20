<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$adminId = st_require_login();
$input = st_input();
$childId = filter_var($input['child_user_id'] ?? null, FILTER_VALIDATE_INT);
$guardianId = filter_var($input['guardian_user_id'] ?? null, FILTER_VALIDATE_INT);
if (!$childId || !$guardianId || $childId === $guardianId) {
    st_json_error('Choose a student and a different guardian account.', 422);
}

$db = safaritrak_db();
$orgStmt = $db->prepare(
    'SELECT o.id, o.name, p.account_type, p.review_status
     FROM organization_admins oa
     JOIN organizations o ON o.id = oa.organization_id AND o.is_suspended = 0
     JOIN organization_profiles p ON p.organization_id = o.id
     WHERE oa.user_id = ? LIMIT 1'
);
$orgStmt->execute([$adminId]);
$organization = $orgStmt->fetch(PDO::FETCH_ASSOC);
if (!$organization || $organization['account_type'] !== 'school' || $organization['review_status'] !== 'approved') {
    st_json_error('Only administrators of an approved school can invite a guardian to view a trip.', 403);
}

$membersStmt = $db->prepare(
    'SELECT child_user.full_name AS child_name, guardian_user.full_name AS guardian_name
     FROM organization_travelers child_member
     JOIN organization_travelers guardian_member
       ON guardian_member.organization_id = child_member.organization_id
      AND guardian_member.user_id = ? AND guardian_member.status = "active"
      AND guardian_member.member_type = "guardian"
     JOIN users child_user ON child_user.id = child_member.user_id
     JOIN users guardian_user ON guardian_user.id = guardian_member.user_id
     WHERE child_member.organization_id = ? AND child_member.user_id = ?
       AND child_member.status = "active" AND child_member.member_type = "student"
     LIMIT 1'
);
$membersStmt->execute([$guardianId, (int) $organization['id'], $childId]);
$members = $membersStmt->fetch(PDO::FETCH_ASSOC);
if (!$members) {
    st_json_error('The selected accounts must be an active student and guardian in this school.', 422);
}

$db->beginTransaction();
try {
    $existingStmt = $db->prepare(
        'SELECT id, status FROM organization_child_access
         WHERE organization_id = ? AND child_user_id = ? AND guardian_user_id = ? FOR UPDATE'
    );
    $existingStmt->execute([(int) $organization['id'], $childId, $guardianId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing && in_array($existing['status'], ['pending', 'active'], true)) {
        $db->rollBack();
        st_json_error('This guardian already has a pending or active invitation for that student.', 409);
    }

    if ($existing) {
        $db->prepare(
            'UPDATE organization_child_access
             SET status = "pending", invited_by = ?, created_at = NOW(), responded_at = NULL
             WHERE id = ?'
        )->execute([$adminId, (int) $existing['id']]);
    } else {
        $db->prepare(
            'INSERT INTO organization_child_access
                (organization_id, child_user_id, guardian_user_id, invited_by)
             VALUES (?, ?, ?, ?)'
        )->execute([(int) $organization['id'], $childId, $guardianId, $adminId]);
    }

    st_notify(
        $guardianId,
        'school_trip_access',
        'A school invited you to view a student’s trips',
        $members['child_name'] . ' · ' . $organization['name'],
        null,
        $adminId
    );
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

st_json_ok(['message' => 'Guardian invitation sent. They must accept before any trip details are visible.']);
