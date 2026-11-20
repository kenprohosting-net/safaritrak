<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/school-transport.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$db = safaritrak_db();
$access = st_school_transport_require_admin($db, $userId);

$readText = static function (string $key, int $maximum, bool $required = true) use ($input): string {
    $value = $input[$key] ?? '';
    if (!is_string($value)) {
        st_json_error('Enter valid bus details.', 422);
    }
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value) > $maximum) {
        st_json_error('Complete the bus details using the indicated limits.', 422);
    }
    return $value;
};

$busNumber = $readText('bus_number', 32);
$routeName = $readText('route_name', 120);
$driverName = $readText('driver_name', 120);
$driverPhone = $readText('driver_phone', 20, false);
$originName = $readText('origin_name', 120);
$originLat = st_school_transport_parse_coordinate($input['origin_lat'] ?? null, -90, 90);
$originLng = st_school_transport_parse_coordinate($input['origin_lng'] ?? null, -180, 180);
if ($originLat === null || $originLng === null) {
    st_json_error('Enter valid school origin coordinates.', 422);
}
if ($driverPhone !== '' && !preg_match('/^\+?[0-9 ()-]{6,20}$/', $driverPhone)) {
    st_json_error('Enter a valid driver phone number.', 422);
}

$stops = $input['stops'] ?? null;
if (!is_array($stops) || count($stops) < 1 || count($stops) > 20) {
    st_json_error('Add between 1 and 20 route stops.', 422);
}
$validatedStops = [];
foreach ($stops as $index => $stop) {
    if (!is_array($stop)) {
        st_json_error('Enter valid route stop details.', 422);
    }
    $name = $stop['name'] ?? null;
    $type = $stop['type'] ?? 'waypoint';
    if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 120
        || !is_string($type) || !in_array($type, ['pickup', 'dropoff', 'waypoint'], true)
    ) {
        st_json_error('Each route stop needs a name and a valid type.', 422);
    }
    $lat = st_school_transport_parse_coordinate($stop['lat'] ?? null, -90, 90);
    $lng = st_school_transport_parse_coordinate($stop['lng'] ?? null, -180, 180);
    if ($lat === null || $lng === null) {
        st_json_error('Enter valid coordinates for each route stop.', 422);
    }
    $validatedStops[] = [
        'name' => trim($name),
        'type' => $type,
        'lat' => $lat,
        'lng' => $lng,
        'order' => $index + 1,
    ];
}

$driverUserId = null;
$driverIdentifier = $readText('driver_identifier', 160, false);
if ($driverIdentifier !== '') {
    $driverStmt = $db->prepare(
        'SELECT u.id
         FROM users u
         JOIN organization_travelers staff
           ON staff.organization_id = ? AND staff.user_id = u.id
          AND staff.status = "active" AND staff.member_type = "staff"
         WHERE u.is_suspended = 0 AND (LOWER(u.email) = LOWER(?) OR u.phone = ?)
         LIMIT 1'
    );
    $driverStmt->execute([(int) $access['school_id'], $driverIdentifier, $driverIdentifier]);
    $driverUserId = $driverStmt->fetchColumn();
    if (!$driverUserId) {
        st_json_error('The driver account must already be an active staff member of this school.', 422);
    }
}

$duplicateStmt = $db->prepare(
    'SELECT 1 FROM school_buses WHERE school_id = ? AND bus_number = ? LIMIT 1'
);
$duplicateStmt->execute([(int) $access['school_id'], $busNumber]);
if ($duplicateStmt->fetchColumn()) {
    st_json_error('That bus number is already registered for this school.', 409);
}

$db->beginTransaction();
try {
    $db->prepare(
        'INSERT INTO school_transport_profiles
            (school_id, origin_name, origin_lat, origin_lng, updated_by)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           origin_name = VALUES(origin_name),
           origin_lat = VALUES(origin_lat),
           origin_lng = VALUES(origin_lng),
           updated_by = VALUES(updated_by)'
    )->execute([
        (int) $access['school_id'],
        $originName,
        $originLat,
        $originLng,
        $userId,
    ]);

    $busInsert = $db->prepare(
        'INSERT INTO school_buses
            (school_id, bus_number, route_name, driver_user_id, driver_name, driver_phone, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $busInsert->execute([
        (int) $access['school_id'],
        $busNumber,
        $routeName,
        $driverUserId ? (int) $driverUserId : null,
        $driverName,
        $driverPhone !== '' ? $driverPhone : null,
        $userId,
    ]);
    $busId = (int) $db->lastInsertId();

    $stopInsert = $db->prepare(
        'INSERT INTO school_bus_stops
            (bus_id, stop_name, lat, lng, stop_order, stop_type)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($validatedStops as $stop) {
        $stopInsert->execute([
            $busId,
            $stop['name'],
            $stop['lat'],
            $stop['lng'],
            $stop['order'],
            $stop['type'],
        ]);
    }

    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

st_json_ok(['bus_id' => $busId]);
