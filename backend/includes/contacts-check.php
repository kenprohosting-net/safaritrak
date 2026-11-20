<?php

function st_are_confirmed_contacts(PDO $db, int $userA, int $userB): bool {
    $stmt = $db->prepare(
        'SELECT id FROM trusted_contacts
         WHERE owner_id = ? AND contact_user_id = ? AND status = "confirmed" LIMIT 1'
    );
    $stmt->execute([$userA, $userB]);
    return (bool) $stmt->fetch();
}

function st_can_message(PDO $db, int $userA, int $userB): bool {
    if ($userA < 1 || $userB < 1 || $userA === $userB) {
        return false;
    }
    if (st_are_confirmed_contacts($db, $userA, $userB)) {
        return true;
    }

    $stmt = $db->prepare(
        'SELECT 1
         FROM organization_admins oa
         JOIN organizations o ON o.id = oa.organization_id AND o.is_suspended = 0
         JOIN organization_profiles p ON p.organization_id = o.id AND p.review_status = "approved"
         JOIN organization_travelers ot ON ot.organization_id = o.id AND ot.status = "active"
         WHERE (oa.user_id = ? AND ot.user_id = ?)
            OR (oa.user_id = ? AND ot.user_id = ?)
         LIMIT 1'
    );
    $stmt->execute([$userA, $userB, $userB, $userA]);
    if ($stmt->fetchColumn()) {
        return true;
    }

    $rentalStmt = $db->prepare(
        'SELECT 1
         FROM rental_bookings b
         JOIN rental_vehicles v ON v.id = b.vehicle_id
         JOIN organization_admins oa ON oa.organization_id = v.organization_id
         WHERE b.status IN ("requested", "responded", "approved", "completed")
           AND ((b.customer_id = ? AND oa.user_id = ?)
             OR (b.customer_id = ? AND oa.user_id = ?))
         LIMIT 1'
    );
    $rentalStmt->execute([$userA, $userB, $userB, $userA]);
    if ($rentalStmt->fetchColumn()) {
        return true;
    }

    $schoolTransportStmt = $db->prepare(
        'SELECT 1
         FROM school_verifications parent_verification
         JOIN organizations o ON o.id = parent_verification.school_id AND o.is_suspended = 0
         JOIN organization_profiles p ON p.organization_id = o.id
           AND p.account_type = "school" AND p.review_status = "approved"
         JOIN organization_travelers guardian
           ON guardian.organization_id = parent_verification.school_id
          AND guardian.user_id = parent_verification.user_id
          AND guardian.status = "active" AND guardian.member_type = "guardian"
         JOIN organization_travelers student
           ON student.organization_id = parent_verification.school_id
          AND student.user_id = parent_verification.student_user_id
          AND student.status = "active" AND student.member_type = "student"
         JOIN organization_child_access ca
           ON ca.organization_id = parent_verification.school_id
          AND ca.guardian_user_id = parent_verification.user_id
          AND ca.child_user_id = parent_verification.student_user_id
          AND ca.status = "active"
         JOIN school_buses bus ON bus.id = parent_verification.assigned_bus_id
         JOIN school_bus_trips trip
           ON trip.bus_id = bus.id AND trip.status = "en_route" AND trip.broadcast_active = 1
         WHERE parent_verification.role = "verified_parent"
           AND parent_verification.verification_status = "verified"
           AND (
             (parent_verification.user_id = ? AND bus.driver_user_id = ?)
             OR (parent_verification.user_id = ? AND bus.driver_user_id = ?)
           )
         LIMIT 1'
    );
    $schoolTransportStmt->execute([$userA, $userB, $userB, $userA]);
    return (bool) $schoolTransportStmt->fetchColumn();
}
