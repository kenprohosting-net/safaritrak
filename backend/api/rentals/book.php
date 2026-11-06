<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$vehicleId = (int) ($input['vehicle_id'] ?? 0);
$pickupInput = st_input_string($input, 'pickup_at');
$returnInput = st_input_string($input, 'return_at');
$note = trim(st_input_string($input, 'note'));
$pickup = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $pickupInput);
$pickupErrors = DateTimeImmutable::getLastErrors();
$pickupValid = $pickup && $pickup->format('Y-m-d\TH:i') === $pickupInput
    && ($pickupErrors === false || ($pickupErrors['warning_count'] === 0 && $pickupErrors['error_count'] === 0));
$return = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $returnInput);
$returnErrors = DateTimeImmutable::getLastErrors();
$returnValid = $return && $return->format('Y-m-d\TH:i') === $returnInput
    && ($returnErrors === false || ($returnErrors['warning_count'] === 0 && $returnErrors['error_count'] === 0));
$now = new DateTimeImmutable();
if ($vehicleId < 1 || !$pickupValid || !$returnValid || $pickup <= $now || $return <= $pickup || strlen($note) > 500) {
    st_json_error('Choose a vehicle, a future pickup time, a later return time, and a note of at most 500 characters.', 422);
}

$db = safaritrak_db();
$db->beginTransaction();
try {
    $vehicleStmt = $db->prepare(
        'SELECT v.id, v.daily_rate, v.currency
         FROM rental_vehicles v
         JOIN organizations o ON o.id = v.organization_id
         JOIN organization_profiles p ON p.organization_id = o.id
         WHERE v.id = ? AND v.status = "available" AND o.is_suspended = 0
           AND p.account_type = "car_hire" AND p.review_status = "approved"
         FOR UPDATE'
    );
    $vehicleStmt->execute([$vehicleId]);
    $vehicle = $vehicleStmt->fetch(PDO::FETCH_ASSOC);
    if (!$vehicle) {
        $db->rollBack();
        st_json_error('This vehicle is no longer available for booking.', 409);
    }
    $overlapStmt = $db->prepare(
        'SELECT id FROM rental_bookings
         WHERE vehicle_id = ? AND status = "approved"
           AND pickup_at < ? AND return_at > ? LIMIT 1'
    );
    $overlapStmt->execute([
        $vehicleId,
        $return->format('Y-m-d H:i:s'),
        $pickup->format('Y-m-d H:i:s'),
    ]);
    if ($overlapStmt->fetch()) {
        $db->rollBack();
        st_json_error('Those dates overlap another request. Choose different dates.', 409);
    }
    $db->prepare(
        'INSERT INTO rental_bookings (vehicle_id, customer_id, pickup_at, return_at, quoted_daily_rate, customer_note)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $vehicleId,
        $userId,
        $pickup->format('Y-m-d H:i:s'),
        $return->format('Y-m-d H:i:s'),
        $vehicle['daily_rate'],
        $note === '' ? null : mb_substr($note, 0, 500),
    ]);
    $bookingId = (int) $db->lastInsertId();
    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $error;
}

st_json_ok(['booking_id' => $bookingId, 'status' => 'requested']);
