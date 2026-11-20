<?php

function st_school_transport_verification(PDO $db, int $userId): ?array {
    $stmt = $db->prepare(
        'SELECT sv.id, sv.user_id, sv.school_id, sv.role, sv.student_user_id,
                sv.student_name, sv.student_grade, sv.assigned_bus_id,
                sv.home_dropoff_label, sv.home_dropoff_lat, sv.home_dropoff_lng,
                o.name AS school_name, student.full_name AS current_student_name
         FROM school_verifications sv
         JOIN organizations o ON o.id = sv.school_id AND o.is_suspended = 0
         JOIN organization_profiles p ON p.organization_id = o.id
           AND p.account_type = "school" AND p.review_status = "approved"
         LEFT JOIN users student ON student.id = sv.student_user_id
         WHERE sv.user_id = ? AND sv.verification_status = "verified"
           AND (
             (
               sv.role = "school_admin" AND (
                 EXISTS (
                   SELECT 1 FROM organization_admins oa
                   WHERE oa.organization_id = sv.school_id AND oa.user_id = sv.user_id
                 )
                 OR EXISTS (
                   SELECT 1 FROM organization_travelers staff
                   WHERE staff.organization_id = sv.school_id AND staff.user_id = sv.user_id
                     AND staff.status = "active" AND staff.member_type = "staff"
                 )
               )
             )
             OR (
               sv.role = "verified_parent" AND sv.student_user_id IS NOT NULL
               AND EXISTS (
                 SELECT 1
                 FROM organization_child_access ca
                 JOIN organization_travelers guardian
                   ON guardian.organization_id = ca.organization_id
                  AND guardian.user_id = ca.guardian_user_id
                  AND guardian.status = "active" AND guardian.member_type = "guardian"
                 JOIN organization_travelers pupil
                   ON pupil.organization_id = ca.organization_id
                  AND pupil.user_id = ca.child_user_id
                  AND pupil.status = "active" AND pupil.member_type = "student"
                 WHERE ca.organization_id = sv.school_id
                   AND ca.guardian_user_id = sv.user_id
                   AND ca.child_user_id = sv.student_user_id
                   AND ca.status = "active"
               )
             )
           )
         ORDER BY (sv.role = "school_admin") DESC, sv.id ASC
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);
    return $verification ?: null;
}

function st_school_transport_require_admin(PDO $db, int $userId): array {
    $verification = st_school_transport_verification($db, $userId);
    if (!$verification || $verification['role'] !== 'school_admin') {
        st_json_error('Verified school transport staff access is required.', 403);
    }

    return $verification;
}

function st_school_transport_parse_coordinate(mixed $value, float $minimum, float $maximum): ?float {
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        return null;
    }

    $coordinate = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($coordinate === false || !is_finite((float) $coordinate)
        || $coordinate < $minimum || $coordinate > $maximum
    ) {
        return null;
    }

    return (float) $coordinate;
}
