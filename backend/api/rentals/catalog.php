<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('GET');
$userId = st_require_login();
$db = safaritrak_db();
$vehiclesStmt = $db->query(
    'SELECT v.id, v.organization_id, v.make, v.model, v.year, v.category, v.seats,
            v.daily_rate, v.currency, v.description, o.name AS organization_name
     FROM rental_vehicles v
     JOIN organizations o ON o.id = v.organization_id
     JOIN organization_profiles p ON p.organization_id = o.id
     WHERE v.status = "available" AND o.is_suspended = 0 AND p.review_status = "approved"
       AND p.account_type = "car_hire"
     ORDER BY o.name, v.daily_rate, v.make, v.model'
);
$bookingsStmt = $db->prepare(
    'SELECT b.id, b.pickup_at, b.return_at, b.quoted_daily_rate, b.status, b.customer_note,
            v.make, v.model, v.currency, o.name AS organization_name
     FROM rental_bookings b
     JOIN rental_vehicles v ON v.id = b.vehicle_id
     JOIN organizations o ON o.id = v.organization_id
     WHERE b.customer_id = ?
     ORDER BY b.created_at DESC LIMIT 30'
);
$bookingsStmt->execute([$userId]);
st_json_ok([
    'vehicles' => $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC),
    'bookings' => $bookingsStmt->fetchAll(PDO::FETCH_ASSOC),
]);
