<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/school-transport.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$adminId = st_require_login();
$input = st_input();
$verificationId = filter_var($input['verification_id'] ?? null, FILTER_VALIDATE_INT);
$tripId = filter_var($input['trip_id'] ?? null, FILTER_VALIDATE_INT);
$status = st_input_string($input, 'status');
$note = trim(st_input_string($input, 'note'));
if (!$verificationId || !$tripId
    || !in_array($status, ['on_board', 'approaching', 'dropped_off'], true)
    || mb_strlen($note) > 300
) {
    st_json_error('Choose a valid student trip status.', 422);
}

$db = safaritrak_db();
$access = st_school_transport_require_admin($db, $adminId);
$studentStmt = $db->prepare(
    'SELECT sv.user_id, sv.student_name
     FROM school_verifications sv
     JOIN organization_child_access ca
       ON ca.organization_id = sv.school_id
      AND ca.guardian_user_id = sv.user_id
      AND ca.child_user_id = sv.student_user_id
      AND ca.status = "active"
     JOIN school_bus_trips trip ON trip.id = ?
     JOIN school_buses bus ON bus.id = trip.bus_id
     JOIN organization_travelers guardian
       ON guardian.organization_id = sv.school_id AND guardian.user_id = sv.user_id
      AND guardian.status = "active" AND guardian.member_type = "guardian"
     JOIN organization_travelers pupil
       ON pupil.organization_id = sv.school_id AND pupil.user_id = sv.student_user_id
      AND pupil.status = "active" AND pupil.member_type = "student"
     WHERE sv.id = ? AND sv.school_id = ? AND sv.assigned_bus_id = bus.id
       AND sv.role = "verified_parent" AND sv.verification_status = "verified"
       AND trip.status = "en_route" AND bus.school_id = ?
     LIMIT 1'
);
$studentStmt->execute([
    (int) $tripId,
    (int) $verificationId,
    (int) $access['school_id'],
    (int) $access['school_id'],
]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    st_json_error('That student is not assigned to this active school trip.', 404);
}

$db->prepare(
    'INSERT INTO school_student_trip_status
        (trip_id, verification_id, status, status_note, updated_by)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE status = VALUES(status), status_note = VALUES(status_note),
       updated_by = VALUES(updated_by), updated_at = UTC_TIMESTAMP()'
)->execute([
    (int) $tripId,
    (int) $verificationId,
    $status,
    $note !== '' ? $note : null,
    $adminId,
]);

$statusText = match ($status) {
    'approaching' => 'The bus is approaching the drop-off.',
    'dropped_off' => 'Your child has been marked as dropped off.',
    default => 'Your child is on board the bus.',
};
st_notify(
    (int) $student['user_id'],
    'school_transport_broadcast',
    'Student transport status updated',
    $note !== '' ? $note : $statusText,
    null,
    $adminId
);

st_json_ok(['status' => $status]);
