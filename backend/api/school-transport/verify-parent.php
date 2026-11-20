<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/school-transport.php';

st_start_session();
st_require_method('POST');
$adminId = st_require_login();
$input = st_input();
$db = safaritrak_db();
$access = st_school_transport_require_admin($db, $adminId);

$identifier = trim(st_input_string($input, 'parent_identifier'));
$studentId = filter_var($input['student_user_id'] ?? null, FILTER_VALIDATE_INT);
$busId = filter_var($input['bus_id'] ?? null, FILTER_VALIDATE_INT);
$grade = trim(st_input_string($input, 'student_grade'));
if ($identifier === '' || mb_strlen($identifier) > 160 || !$studentId || !$busId
    || mb_strlen($grade) > 40
) {
    st_json_error('Enter a parent email or phone, student, bus, and a valid grade.', 422);
}

$relationshipStmt = $db->prepare(
    'SELECT guardian.id AS parent_id, child.id AS student_id, child.full_name AS student_name
     FROM users guardian
     JOIN organization_travelers guardian_member
       ON guardian_member.organization_id = ? AND guardian_member.user_id = guardian.id
      AND guardian_member.status = "active" AND guardian_member.member_type = "guardian"
     JOIN organization_child_access ca
       ON ca.organization_id = guardian_member.organization_id
      AND ca.guardian_user_id = guardian.id AND ca.child_user_id = ? AND ca.status = "active"
     JOIN organization_travelers student_member
       ON student_member.organization_id = ca.organization_id
      AND student_member.user_id = ca.child_user_id
      AND student_member.status = "active" AND student_member.member_type = "student"
     JOIN users child ON child.id = ca.child_user_id
     WHERE (LOWER(guardian.email) = LOWER(?) OR guardian.phone = ?)
       AND guardian.is_suspended = 0
     LIMIT 1'
);
$relationshipStmt->execute([
    (int) $access['school_id'],
    (int) $studentId,
    $identifier,
    $identifier,
]);
$relationship = $relationshipStmt->fetch(PDO::FETCH_ASSOC);
if (!$relationship) {
    st_json_error('No accepted, active guardian relationship matches that account and student.', 404);
}

$busStmt = $db->prepare(
    'SELECT id FROM school_buses
     WHERE id = ? AND school_id = ? AND status = "active" LIMIT 1'
);
$busStmt->execute([(int) $busId, (int) $access['school_id']]);
if (!$busStmt->fetchColumn()) {
    st_json_error('Choose an active bus belonging to your school.', 422);
}

$db->prepare(
    'INSERT INTO school_verifications
        (user_id, school_id, role, student_user_id, student_name, student_grade,
         assigned_bus_id, verification_status, verified_by, verified_at)
     VALUES (?, ?, "verified_parent", ?, ?, ?, ?, "verified", ?, UTC_TIMESTAMP())
     ON DUPLICATE KEY UPDATE
       student_name = VALUES(student_name),
       student_grade = VALUES(student_grade),
       assigned_bus_id = VALUES(assigned_bus_id),
       verification_status = "verified",
       verified_by = VALUES(verified_by),
       verified_at = VALUES(verified_at)'
)->execute([
    (int) $relationship['parent_id'],
    (int) $access['school_id'],
    (int) $relationship['student_id'],
    $relationship['student_name'],
    $grade !== '' ? $grade : null,
    (int) $busId,
    $adminId,
]);

st_json_ok(['message' => 'The parent is verified and linked to this bus.']);
