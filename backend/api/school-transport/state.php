<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/school-transport.php';

st_start_session();
st_require_method('GET');
$userId = st_require_login();
session_write_close();

$db = safaritrak_db();
$verification = st_school_transport_verification($db, $userId);
if (!$verification) {
    st_json_error('Verified school transport access is required.', 403);
}

$schoolId = (int) $verification['school_id'];
$profileStmt = $db->prepare(
    'SELECT origin_name, origin_lat, origin_lng
     FROM school_transport_profiles WHERE school_id = ?'
);
$profileStmt->execute([$schoolId]);
$origin = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: null;
$schoolFeature = $origin ? [[
    'kind' => 'school',
    'label' => $origin['origin_name'],
    'lat' => (float) $origin['origin_lat'],
    'lng' => (float) $origin['origin_lng'],
]] : [];

if ($verification['role'] === 'school_admin') {
    $busStmt = $db->prepare(
        'SELECT b.id, b.bus_number, b.route_name, b.driver_user_id, b.driver_name,
                b.driver_phone, b.status AS bus_status,
                trip.id AS trip_id, trip.status AS trip_status,
                trip.broadcast_active, trip.driver_note, trip.started_at,
                pos.lat AS current_lat, pos.lng AS current_lng, pos.recorded_at AS last_update,
                (
                  SELECT COUNT(DISTINCT sv.student_user_id)
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
                  WHERE sv.assigned_bus_id = b.id AND sv.role = "verified_parent"
                    AND sv.verification_status = "verified"
                ) AS student_count
         FROM school_buses b
         LEFT JOIN school_bus_trips trip
           ON trip.id = (
             SELECT latest.id FROM school_bus_trips latest
             WHERE latest.bus_id = b.id
             ORDER BY latest.started_at DESC, latest.id DESC LIMIT 1
           )
         LEFT JOIN school_bus_positions pos
           ON pos.id = (
             SELECT latest_pos.id FROM school_bus_positions latest_pos
             WHERE latest_pos.trip_id = trip.id
             ORDER BY latest_pos.recorded_at DESC, latest_pos.id DESC LIMIT 1
           )
         WHERE b.school_id = ? AND b.status = "active"
         ORDER BY b.bus_number, b.id'
    );
    $busStmt->execute([$schoolId]);
    $buses = $busStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($buses as &$bus) {
        $bus['id'] = (int) $bus['id'];
        $bus['student_count'] = (int) $bus['student_count'];
        $bus['trip_id'] = $bus['trip_id'] === null ? null : (int) $bus['trip_id'];
        $bus['broadcast_active'] = (bool) $bus['broadcast_active'];
        $bus['current_lat'] = $bus['current_lat'] === null ? null : (float) $bus['current_lat'];
        $bus['current_lng'] = $bus['current_lng'] === null ? null : (float) $bus['current_lng'];
        $stopStmt = $db->prepare(
            'SELECT id, stop_name, lat, lng, stop_order, stop_type
             FROM school_bus_stops WHERE bus_id = ? ORDER BY stop_order, id'
        );
        $stopStmt->execute([$bus['id']]);
        $bus['stops'] = array_map(static function (array $stop): array {
            $stop['id'] = (int) $stop['id'];
            $stop['lat'] = (float) $stop['lat'];
            $stop['lng'] = (float) $stop['lng'];
            return $stop;
        }, $stopStmt->fetchAll(PDO::FETCH_ASSOC));
    }
    unset($bus);

    $hasRequestedBusId = array_key_exists('bus_id', $_GET);
    $requestedBusId = $hasRequestedBusId
        ? filter_var($_GET['bus_id'], FILTER_VALIDATE_INT)
        : null;
    if ($hasRequestedBusId && (!$requestedBusId || (int) $requestedBusId < 1)) {
        st_json_error('Choose a valid school bus.', 422);
    }
    $selectedBus = null;
    foreach ($buses as $bus) {
        if ($requestedBusId && $bus['id'] === (int) $requestedBusId) {
            $selectedBus = $bus;
            break;
        }
    }
    if (!$selectedBus && $buses) {
        if ($hasRequestedBusId) {
            st_json_error('That bus is not available in your school.', 404);
        }
        $selectedBus = $buses[0];
    }

    $roster = [];
    $mapFeatures = $schoolFeature;
    if ($selectedBus) {
        $rosterStmt = $db->prepare(
            'SELECT sv.id AS verification_id, sv.user_id AS parent_user_id,
                    sv.student_user_id, sv.student_name, sv.student_grade,
                    sv.home_dropoff_label, sv.home_dropoff_lat, sv.home_dropoff_lng,
                    sv.route_service_consent,
                    parent.full_name AS parent_name, parent.phone AS parent_phone,
                    child.full_name AS current_student_name,
                    status.status AS trip_student_status, status.status_note,
                    status.updated_at AS status_updated_at
             FROM school_verifications sv
             JOIN users parent ON parent.id = sv.user_id
             JOIN users child ON child.id = sv.student_user_id
             JOIN organization_travelers guardian
               ON guardian.organization_id = sv.school_id AND guardian.user_id = sv.user_id
              AND guardian.status = "active" AND guardian.member_type = "guardian"
             JOIN organization_travelers pupil
               ON pupil.organization_id = sv.school_id AND pupil.user_id = sv.student_user_id
              AND pupil.status = "active" AND pupil.member_type = "student"
             JOIN organization_child_access ca
               ON ca.organization_id = sv.school_id
              AND ca.guardian_user_id = sv.user_id
              AND ca.child_user_id = sv.student_user_id
              AND ca.status = "active"
             LEFT JOIN school_student_trip_status status
               ON status.verification_id = sv.id AND status.trip_id = ?
             WHERE sv.school_id = ? AND sv.assigned_bus_id = ?
               AND sv.role = "verified_parent" AND sv.verification_status = "verified"
             ORDER BY parent.full_name, child.full_name'
        );
        $rosterStmt->execute([
            $selectedBus['trip_id'] ?? 0,
            $schoolId,
            $selectedBus['id'],
        ]);
        $roster = $rosterStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($roster as &$parent) {
            $parent['verification_id'] = (int) $parent['verification_id'];
            $parent['parent_user_id'] = (int) $parent['parent_user_id'];
            $parent['student_user_id'] = (int) $parent['student_user_id'];
            $parent['home_dropoff_lat'] = $parent['home_dropoff_lat'] === null
                ? null : (float) $parent['home_dropoff_lat'];
            $parent['home_dropoff_lng'] = $parent['home_dropoff_lng'] === null
                ? null : (float) $parent['home_dropoff_lng'];
            $parent['route_service_consent'] = (bool) $parent['route_service_consent'];
            $parent['student_name'] = $parent['current_student_name'] ?: $parent['student_name'];
            if ($parent['home_dropoff_lat'] !== null && $parent['home_dropoff_lng'] !== null) {
                $mapFeatures[] = [
                    'kind' => 'student',
                    'label' => $parent['student_name'] . ' · drop-off',
                    'lat' => $parent['home_dropoff_lat'],
                    'lng' => $parent['home_dropoff_lng'],
                ];
            }
        }
        unset($parent);

        foreach ($buses as $bus) {
            if ($bus['current_lat'] !== null && $bus['current_lng'] !== null) {
                $mapFeatures[] = [
                    'kind' => 'bus',
                    'label' => 'Bus ' . $bus['bus_number'],
                    'lat' => $bus['current_lat'],
                    'lng' => $bus['current_lng'],
                ];
            }
        }

        $routeStops = [];
        if ($origin) {
            $routeStops[] = [
                'name' => $origin['origin_name'],
                'lat' => (float) $origin['origin_lat'],
                'lng' => (float) $origin['origin_lng'],
                'kind' => 'school',
            ];
        }
        foreach ($selectedBus['stops'] as $stop) {
            $routeStops[] = [
                'name' => $stop['stop_name'],
                'lat' => $stop['lat'],
                'lng' => $stop['lng'],
                'kind' => $stop['stop_type'],
            ];
            $mapFeatures[] = [
                'kind' => $stop['stop_type'] === 'dropoff' ? 'dropoff' : 'student',
                'label' => $stop['stop_name'],
                'lat' => $stop['lat'],
                'lng' => $stop['lng'],
            ];
        }
        $consentedHomeStops = array_values(array_filter(
            $roster,
            static fn (array $parent): bool => $parent['route_service_consent']
                && $parent['home_dropoff_lat'] !== null
                && $parent['home_dropoff_lng'] !== null
        ));
        foreach (array_slice($consentedHomeStops, 0, max(0, 20 - count($routeStops))) as $parent) {
            $routeStops[] = [
                'name' => $parent['student_name'] . ' home',
                'lat' => $parent['home_dropoff_lat'],
                'lng' => $parent['home_dropoff_lng'],
                'kind' => 'dropoff',
            ];
        }
        if ($routeStops) {
            $mapFeatures[] = [
                'kind' => 'journey',
                'label' => $selectedBus['route_name'],
                'lat' => $routeStops[0]['lat'],
                'lng' => $routeStops[0]['lng'],
                'routeStops' => $routeStops,
                'routeStroke' => '#2DD4BF',
                'routeGlow' => 'rgba(45, 212, 191, 0.85)',
                'routeCasing' => 'rgba(8, 47, 73, 0.92)',
            ];
        }
    }

    st_json_ok([
        'role' => 'school_admin',
        'school' => ['id' => $schoolId, 'name' => $verification['school_name']],
        'origin' => $origin,
        'buses' => $buses,
        'selected_bus' => $selectedBus,
        'roster' => $roster,
        'map_features' => $mapFeatures,
    ]);
}

$verificationId = (int) $verification['id'];
$busId = $verification['assigned_bus_id'] === null ? null : (int) $verification['assigned_bus_id'];
$studentName = $verification['current_student_name'] ?: $verification['student_name'];
$schoolContactStmt = $db->prepare(
    'SELECT user_id FROM organization_admins
     WHERE organization_id = ? ORDER BY (role = "owner") DESC, id LIMIT 1'
);
$schoolContactStmt->execute([$schoolId]);
$schoolContactUserId = $schoolContactStmt->fetchColumn();
$parentState = [
    'role' => 'verified_parent',
    'school' => ['id' => $schoolId, 'name' => $verification['school_name']],
    'school_contact_user_id' => $schoolContactUserId === false ? null : (int) $schoolContactUserId,
    'student' => [
        'name' => $studentName ?: 'Student',
        'grade' => $verification['student_grade'],
        'home_dropoff_label' => $verification['home_dropoff_label'],
        'home_dropoff_lat' => $verification['home_dropoff_lat'] === null
            ? null : (float) $verification['home_dropoff_lat'],
        'home_dropoff_lng' => $verification['home_dropoff_lng'] === null
            ? null : (float) $verification['home_dropoff_lng'],
        'route_service_consent' => (bool) $verification['route_service_consent'],
    ],
    'bus' => null,
    'trip' => null,
    'timeline' => [],
    'map_features' => $schoolFeature,
];

if (!$busId) {
    st_json_ok($parentState);
}

$busStmt = $db->prepare(
    'SELECT b.id, b.bus_number, b.route_name, b.driver_user_id, b.driver_name, b.driver_phone,
            trip.id AS trip_id, trip.status AS trip_status, trip.broadcast_active,
            trip.driver_note, trip.started_at, trip.completed_at,
            pos.lat AS current_lat, pos.lng AS current_lng, pos.recorded_at AS last_update
     FROM school_buses b
     LEFT JOIN school_bus_trips trip
       ON trip.id = (
         SELECT latest.id FROM school_bus_trips latest
         WHERE latest.bus_id = b.id
         ORDER BY latest.started_at DESC, latest.id DESC LIMIT 1
       )
     LEFT JOIN school_bus_positions pos
       ON pos.id = (
         SELECT latest_pos.id FROM school_bus_positions latest_pos
         WHERE latest_pos.trip_id = trip.id
         ORDER BY latest_pos.recorded_at DESC, latest_pos.id DESC LIMIT 1
       )
     WHERE b.id = ? AND b.school_id = ? AND b.status = "active"
     LIMIT 1'
);
$busStmt->execute([$busId, $schoolId]);
$bus = $busStmt->fetch(PDO::FETCH_ASSOC);
if (!$bus) {
    st_json_error('The assigned school bus is no longer available.', 403);
}
$bus['id'] = (int) $bus['id'];
$bus['driver_user_id'] = $bus['driver_user_id'] === null ? null : (int) $bus['driver_user_id'];
$bus['trip_id'] = $bus['trip_id'] === null ? null : (int) $bus['trip_id'];
$bus['broadcast_active'] = (bool) $bus['broadcast_active'];
$bus['current_lat'] = $bus['current_lat'] === null ? null : (float) $bus['current_lat'];
$bus['current_lng'] = $bus['current_lng'] === null ? null : (float) $bus['current_lng'];
$studentCountStmt = $db->prepare(
    'SELECT COUNT(DISTINCT sv.student_user_id)
     FROM school_verifications sv
     JOIN organization_travelers guardian
       ON guardian.organization_id = sv.school_id AND guardian.user_id = sv.user_id
      AND guardian.status = "active" AND guardian.member_type = "guardian"
     JOIN organization_travelers student
       ON student.organization_id = sv.school_id AND student.user_id = sv.student_user_id
      AND student.status = "active" AND student.member_type = "student"
     JOIN organization_child_access ca
       ON ca.organization_id = sv.school_id AND ca.guardian_user_id = sv.user_id
      AND ca.child_user_id = sv.student_user_id AND ca.status = "active"
     WHERE sv.assigned_bus_id = ? AND sv.role = "verified_parent"
       AND sv.verification_status = "verified"'
);
$studentCountStmt->execute([$bus['id']]);
$bus['student_count'] = (int) $studentCountStmt->fetchColumn();

$stopStmt = $db->prepare(
    'SELECT stop_name, lat, lng, stop_order, stop_type
     FROM school_bus_stops WHERE bus_id = ? ORDER BY stop_order, id'
);
$stopStmt->execute([$busId]);
$bus['stops'] = array_map(static function (array $stop): array {
    $stop['lat'] = (float) $stop['lat'];
    $stop['lng'] = (float) $stop['lng'];
    return $stop;
}, $stopStmt->fetchAll(PDO::FETCH_ASSOC));
$parentState['bus'] = $bus;

$trip = null;
if ($bus['trip_id']) {
    $tripStmt = $db->prepare(
        'SELECT id, status, broadcast_active, driver_note, started_at, completed_at
         FROM school_bus_trips WHERE id = ?'
    );
    $tripStmt->execute([$bus['trip_id']]);
    $trip = $tripStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if ($trip) {
    $parentState['trip'] = [
        'id' => (int) $trip['id'],
        'status' => $trip['status'],
        'broadcast_active' => (bool) $trip['broadcast_active'],
        'driver_note' => $trip['driver_note'],
        'started_at' => $trip['started_at'],
        'completed_at' => $trip['completed_at'],
        'last_update' => $trip['broadcast_active'] ? $bus['last_update'] : null,
        'current_lat' => $trip['broadcast_active'] ? $bus['current_lat'] : null,
        'current_lng' => $trip['broadcast_active'] ? $bus['current_lng'] : null,
    ];

    $statusStmt = $db->prepare(
        'SELECT status, status_note, updated_at
         FROM school_student_trip_status
         WHERE trip_id = ? AND verification_id = ?'
    );
    $statusStmt->execute([(int) $trip['id'], $verificationId]);
    $studentStatus = $statusStmt->fetch(PDO::FETCH_ASSOC);
    $parentState['student_status'] = $studentStatus ?: null;
}

$routeStops = [];
if ($origin) {
    $routeStops[] = [
        'name' => $origin['origin_name'],
        'lat' => (float) $origin['origin_lat'],
        'lng' => (float) $origin['origin_lng'],
        'kind' => 'school',
    ];
}
foreach ($bus['stops'] as $stop) {
    $routeStops[] = [
        'name' => $stop['stop_name'],
        'lat' => $stop['lat'],
        'lng' => $stop['lng'],
        'kind' => $stop['stop_type'],
    ];
}
if ($parentState['student']['route_service_consent']
    && $parentState['student']['home_dropoff_lat'] !== null
    && $parentState['student']['home_dropoff_lng'] !== null
) {
    $routeStops[] = [
        'name' => $parentState['student']['home_dropoff_label'] ?: ($studentName . ' home'),
        'lat' => $parentState['student']['home_dropoff_lat'],
        'lng' => $parentState['student']['home_dropoff_lng'],
        'kind' => 'dropoff',
    ];
}
if ($trip && $trip['broadcast_active'] && $bus['current_lat'] !== null && $bus['current_lng'] !== null) {
    $parentState['map_features'][] = [
        'kind' => 'bus',
        'label' => 'Bus ' . $bus['bus_number'] . ' · ' . $studentName . '\'s Bus',
        'lat' => $bus['current_lat'],
        'lng' => $bus['current_lng'],
    ];
}
foreach ($bus['stops'] as $stop) {
    $parentState['map_features'][] = [
        'kind' => $stop['stop_type'] === 'dropoff' ? 'dropoff' : 'student',
        'label' => $stop['stop_name'],
        'lat' => $stop['lat'],
        'lng' => $stop['lng'],
    ];
}
if ($parentState['student']['home_dropoff_lat'] !== null
    && $parentState['student']['home_dropoff_lng'] !== null
) {
    $parentState['map_features'][] = [
        'kind' => 'dropoff',
        'label' => $parentState['student']['home_dropoff_label'] ?: 'Home drop-off',
        'lat' => $parentState['student']['home_dropoff_lat'],
        'lng' => $parentState['student']['home_dropoff_lng'],
    ];
}
if ($routeStops) {
    $parentState['map_features'][] = [
        'kind' => 'journey',
        'label' => $bus['route_name'],
        'lat' => $routeStops[0]['lat'],
        'lng' => $routeStops[0]['lng'],
        'routeStops' => $routeStops,
        'routeStroke' => '#2DD4BF',
        'routeGlow' => 'rgba(45, 212, 191, 0.85)',
        'routeCasing' => 'rgba(8, 47, 73, 0.92)',
    ];
}

st_json_ok($parentState);
