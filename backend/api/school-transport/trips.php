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
$action = st_input_string($input, 'action');
$busId = filter_var($input['bus_id'] ?? null, FILTER_VALIDATE_INT);
if (!$busId || !in_array($action, ['start', 'broadcast', 'pause', 'arrived', 'note'], true)) {
    st_json_error('Choose a valid bus trip action.', 422);
}

$db = safaritrak_db();
$access = st_school_transport_require_admin($db, $adminId);
$busStmt = $db->prepare(
    'SELECT id, bus_number, route_name, driver_user_id FROM school_buses
     WHERE id = ? AND school_id = ? AND status = "active" LIMIT 1'
);
$busStmt->execute([(int) $busId, (int) $access['school_id']]);
$bus = $busStmt->fetch(PDO::FETCH_ASSOC);
if (!$bus) {
    st_json_error('That active bus does not belong to your school.', 404);
}

$parentsStmt = $db->prepare(
    'SELECT DISTINCT sv.id, sv.user_id, sv.student_user_id
     FROM school_verifications sv
     JOIN organization_travelers guardian
       ON guardian.organization_id = sv.school_id AND guardian.user_id = sv.user_id
      AND guardian.status = "active" AND guardian.member_type = "guardian"
     JOIN organization_travelers student
       ON student.organization_id = sv.school_id AND student.user_id = sv.student_user_id
      AND student.status = "active" AND student.member_type = "student"
     JOIN organization_child_access ca
       ON ca.organization_id = sv.school_id
      AND ca.guardian_user_id = sv.user_id
      AND ca.child_user_id = sv.student_user_id
      AND ca.status = "active"
     WHERE sv.school_id = ? AND sv.assigned_bus_id = ?
       AND sv.role = "verified_parent" AND sv.verification_status = "verified"'
);
$parentsStmt->execute([(int) $access['school_id'], (int) $bus['id']]);
$parents = $parentsStmt->fetchAll(PDO::FETCH_ASSOC);

if ($action === 'start') {
    if ($bus['driver_user_id'] !== null && (int) $bus['driver_user_id'] !== $adminId) {
        st_json_error('Only the driver assigned to this bus can start its GPS broadcast.', 403);
    }
    $lat = st_school_transport_parse_coordinate($input['lat'] ?? null, -90, 90);
    $lng = st_school_transport_parse_coordinate($input['lng'] ?? null, -180, 180);
    if ($lat === null || $lng === null) {
        st_json_error('Allow location access to start and share the live trip.', 422);
    }

    $db->beginTransaction();
    try {
        $busLock = $db->prepare(
            'SELECT id FROM school_buses WHERE id = ? AND school_id = ? FOR UPDATE'
        );
        $busLock->execute([(int) $bus['id'], (int) $access['school_id']]);
        if (!$busLock->fetchColumn()) {
            $db->rollBack();
            st_json_error('That bus is no longer available.', 404);
        }
        $activeStmt = $db->prepare(
            'SELECT id FROM school_bus_trips
             WHERE bus_id = ? AND status = "en_route"
             ORDER BY started_at DESC, id DESC LIMIT 1 FOR UPDATE'
        );
        $activeStmt->execute([(int) $bus['id']]);
        if ($activeStmt->fetchColumn()) {
            $db->rollBack();
            st_json_error('This bus already has an active trip.', 409);
        }

        $db->prepare(
            'INSERT INTO school_bus_trips
                (bus_id, started_by, status, broadcast_active, broadcast_started_at)
             VALUES (?, ?, "en_route", 1, UTC_TIMESTAMP())'
        )->execute([(int) $bus['id'], $adminId]);
        $tripId = (int) $db->lastInsertId();
        $db->prepare(
            'INSERT INTO school_bus_positions (trip_id, lat, lng) VALUES (?, ?, ?)'
        )->execute([$tripId, $lat, $lng]);

        $statusInsert = $db->prepare(
            'INSERT INTO school_student_trip_status
                (trip_id, verification_id, status, updated_by)
             VALUES (?, ?, "on_board", ?)
             ON DUPLICATE KEY UPDATE status = "on_board", status_note = NULL,
               updated_by = VALUES(updated_by), updated_at = UTC_TIMESTAMP()'
        );
        foreach ($parents as $parent) {
            $statusInsert->execute([$tripId, (int) $parent['id'], $adminId]);
            st_notify(
                (int) $parent['user_id'],
                'school_transport_broadcast',
                'Your child’s bus is on the way',
                'Bus ' . $bus['bus_number'] . ' · ' . $bus['route_name'],
                null,
                $adminId
            );
        }
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }

    st_json_ok(['trip_id' => $tripId, 'broadcast_active' => true]);
}

$tripStmt = $db->prepare(
    'SELECT id, broadcast_active, status
     FROM school_bus_trips
     WHERE bus_id = ? AND status = "en_route"
     ORDER BY started_at DESC, id DESC LIMIT 1'
);
$tripStmt->execute([(int) $bus['id']]);
$trip = $tripStmt->fetch(PDO::FETCH_ASSOC);
if (!$trip) {
    st_json_error('Start the bus trip before changing its live broadcast.', 409);
}

if ($action === 'note') {
    $note = trim(st_input_string($input, 'note'));
    if ($note === '' || mb_strlen($note) > 500) {
        st_json_error('Enter a trip update of up to 500 characters.', 422);
    }
    $db->prepare('UPDATE school_bus_trips SET driver_note = ? WHERE id = ?')
        ->execute([$note, (int) $trip['id']]);
    foreach ($parents as $parent) {
        st_notify(
            (int) $parent['user_id'],
            'school_transport_broadcast',
            'A school bus update was shared',
            $note,
            null,
            $adminId
        );
    }
    st_json_ok(['message' => 'The trip update was shared with assigned parents.']);
}

if ($action === 'arrived') {
    $db->prepare(
        'UPDATE school_bus_trips
         SET status = "arrived", broadcast_active = 0, completed_at = UTC_TIMESTAMP()
         WHERE id = ?'
    )->execute([(int) $trip['id']]);
    foreach ($parents as $parent) {
        st_notify(
            (int) $parent['user_id'],
            'school_transport_broadcast',
            'The school bus has arrived',
            'Bus ' . $bus['bus_number'] . ' has ended its live trip.',
            null,
            $adminId
        );
    }
    st_json_ok(['status' => 'arrived', 'broadcast_active' => false]);
}

$enableBroadcast = $action === 'broadcast';
$wasActive = (bool) $trip['broadcast_active'];
$db->prepare(
    'UPDATE school_bus_trips
     SET broadcast_active = ?, broadcast_started_at = CASE
       WHEN ? = 1 THEN UTC_TIMESTAMP() ELSE broadcast_started_at END
     WHERE id = ?'
)->execute([$enableBroadcast ? 1 : 0, $enableBroadcast ? 1 : 0, (int) $trip['id']]);

if ($enableBroadcast && !$wasActive) {
    foreach ($parents as $parent) {
        st_notify(
            (int) $parent['user_id'],
            'school_transport_broadcast',
            'Live school bus location is available',
            'Bus ' . $bus['bus_number'] . ' · ' . $bus['route_name'],
            null,
            $adminId
        );
    }
}

st_json_ok(['broadcast_active' => $enableBroadcast]);
