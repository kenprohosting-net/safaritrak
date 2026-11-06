<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$action = st_input_string($input, 'action');
$db = safaritrak_db();
$orgStmt = $db->prepare(
    'SELECT oa.organization_id, p.account_type, p.review_status, o.is_suspended
     FROM organization_admins oa
     JOIN organizations o ON o.id = oa.organization_id
     JOIN organization_profiles p ON p.organization_id = oa.organization_id
     WHERE oa.user_id = ? LIMIT 1'
);
$orgStmt->execute([$userId]);
$org = $orgStmt->fetch(PDO::FETCH_ASSOC);
if (!$org || $org['account_type'] !== 'car_hire' || $org['review_status'] !== 'approved' || (int) $org['is_suspended'] === 1) {
    st_json_error('An approved car hire organization account is required.', 403);
}

if ($action === 'add_vehicle') {
    $make = trim(st_input_string($input, 'make'));
    $model = trim(st_input_string($input, 'model'));
    $category = trim(st_input_string($input, 'category'));
    $registration = strtoupper(trim(st_input_string($input, 'registration_number')));
    $year = (int) ($input['year'] ?? 0);
    $seats = (int) ($input['seats'] ?? 0);
    $rate = filter_var($input['daily_rate'] ?? null, FILTER_VALIDATE_FLOAT);
    $description = trim(st_input_string($input, 'description'));
    $currency = strtoupper(trim(st_input_string($input, 'currency') ?: 'KES'));
    if ($make === '' || mb_strlen($make) > 80 || $model === '' || mb_strlen($model) > 100
        || $category === '' || mb_strlen($category) > 60
        || !preg_match('/^[A-Z0-9 -]{2,32}$/', $registration)
        || $seats < 1 || $seats > 99 || $rate === false || $rate <= 0 || $rate > 10000000
        || !preg_match('/^[A-Z]{3}$/', $currency) || mb_strlen($description) > 500
        || ($year !== 0 && ($year < 1900 || $year > ((int) date('Y') + 1)))) {
        st_json_error('Check the vehicle details, registration, seats, rate, and currency.', 422);
    }
    try {
        $db->prepare(
            'INSERT INTO rental_vehicles
             (organization_id, make, model, year, category, seats, daily_rate, currency, registration_number, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) $org['organization_id'], $make, $model, $year ?: null, $category, $seats,
            $rate, $currency, $registration, $description === '' ? null : $description,
        ]);
    } catch (PDOException $error) {
        if ((string) $error->getCode() === '23000') {
            st_json_error('That registration number is already in your fleet.', 409);
        }
        throw $error;
    }
    st_json_ok(['vehicle_id' => (int) $db->lastInsertId()]);
}

if ($action === 'vehicle_status') {
    $vehicleId = (int) ($input['vehicle_id'] ?? 0);
    $status = st_input_string($input, 'status');
    if ($vehicleId < 1 || !in_array($status, ['available', 'unavailable', 'maintenance'], true)) {
        st_json_error('Choose a valid vehicle and fleet status.', 422);
    }
    $update = $db->prepare('UPDATE rental_vehicles SET status = ? WHERE id = ? AND organization_id = ?');
    $update->execute([$status, $vehicleId, (int) $org['organization_id']]);
    $exists = $db->prepare('SELECT 1 FROM rental_vehicles WHERE id = ? AND organization_id = ?');
    $exists->execute([$vehicleId, (int) $org['organization_id']]);
    if (!$exists->fetchColumn()) {
        st_json_error('That vehicle could not be found or its status is unchanged.', 404);
    }
    st_json_ok(['status' => $status]);
}

if ($action === 'booking_status') {
    $bookingId = (int) ($input['booking_id'] ?? 0);
    $status = st_input_string($input, 'status');
    if ($bookingId < 1 || !in_array($status, ['approved', 'rejected'], true)) {
        st_json_error('Choose a booking and approve or reject it.', 422);
    }
    $db->beginTransaction();
    $booking = $db->prepare(
        'SELECT b.id, b.vehicle_id, b.pickup_at, b.return_at
         FROM rental_bookings b
         JOIN rental_vehicles v ON v.id = b.vehicle_id
         WHERE b.id = ? AND v.organization_id = ? AND b.status = "requested"
         FOR UPDATE'
    );
    $booking->execute([$bookingId, (int) $org['organization_id']]);
    $request = $booking->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        $db->rollBack();
        st_json_error('That booking is no longer awaiting review.', 404);
    }
    $db->prepare('SELECT id FROM rental_vehicles WHERE id = ? FOR UPDATE')->execute([(int) $request['vehicle_id']]);
    if ($status === 'approved') {
        $overlap = $db->prepare(
            'SELECT id FROM rental_bookings
             WHERE vehicle_id = ? AND id <> ? AND status = "approved"
               AND pickup_at < ? AND return_at > ? LIMIT 1'
        );
        $overlap->execute([
            (int) $request['vehicle_id'], $bookingId, $request['return_at'], $request['pickup_at'],
        ]);
        if ($overlap->fetch()) {
            $db->rollBack();
            st_json_error('Another booking has since been approved for those dates.', 409);
        }
    }
    $db->prepare('UPDATE rental_bookings SET status = ? WHERE id = ?')->execute([$status, $bookingId]);
    $db->commit();
    st_json_ok(['status' => $status]);
}

st_json_error('That fleet action is not recognized.', 422);
