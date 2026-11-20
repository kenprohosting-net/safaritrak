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
            v.pickup_lat, v.pickup_lng,
            v.daily_rate, v.currency, v.description, o.name AS organization_name,
            d.transmission, d.fuel_type, d.air_conditioning, d.luggage_capacity,
            d.weekly_rate, d.monthly_rate, d.chauffeur_rate, d.self_drive_rate,
            d.security_deposit, d.extra_mileage_charge, d.fuel_policy, d.additional_charges,
            d.negotiable, d.quotation_required, d.driver_option, d.services AS vehicle_services,
            d.minimum_driver_age, d.valid_licence_required, d.minimum_driving_experience,
            d.identification_requirements, d.booking_notice, d.minimum_rental_days,
            d.maximum_rental_days, d.fuel_return_policy, d.mileage_restrictions,
            d.late_return_policy, d.cancellation_policy, d.refund_policy, d.cross_border_available,
            cp.logo_url, cp.cover_photo_url, cp.description AS company_description,
            cp.established_year, cp.business_email, cp.official_phone, cp.whatsapp, cp.website,
            cp.social_links, cp.office_location, cp.town, cp.regions_served, cp.operating_hours,
            cp.office_lat, cp.office_lng, cp.coverage_description, cp.pickup_locations,
            cp.vehicle_delivery, cp.airport_pickup, cp.services AS company_services,
            cp.minimum_driver_age AS company_minimum_driver_age,
            cp.valid_licence_required AS company_valid_licence_required,
            cp.minimum_driving_experience AS company_minimum_driving_experience,
            cp.identification_requirements AS company_identification_requirements,
            cp.booking_notice AS company_booking_notice,
            cp.minimum_rental_days AS company_minimum_rental_days,
            cp.maximum_rental_days AS company_maximum_rental_days,
            cp.fuel_return_policy AS company_fuel_return_policy,
            cp.mileage_restrictions AS company_mileage_restrictions,
            cp.late_return_policy AS company_late_return_policy,
            cp.cancellation_policy AS company_cancellation_policy,
            cp.refund_policy AS company_refund_policy,
            cp.cross_border_available AS company_cross_border_available,
            cp.gps_tracking_available, cp.customer_trip_sharing, cp.roadside_assistance,
            cp.emergency_number, cp.breakdown_assistance, cp.registered_drivers,
            cp.live_trip_monitoring, cp.emergency_arrangements, cp.response_information,
            contact.user_id AS contact_user_id,
            (SELECT AVG(rr.rating) FROM rental_reviews rr
             WHERE rr.vehicle_id = v.id AND rr.status = "published") AS rating_average,
            (SELECT COUNT(*) FROM rental_reviews rr
             WHERE rr.vehicle_id = v.id AND rr.status = "published") AS rating_count
     FROM rental_vehicles v
     JOIN organizations o ON o.id = v.organization_id
     JOIN organization_profiles p ON p.organization_id = o.id
     LEFT JOIN rental_vehicle_details d ON d.vehicle_id = v.id
     LEFT JOIN rental_company_profiles cp ON cp.organization_id = v.organization_id
     LEFT JOIN (
       SELECT organization_id, MIN(user_id) AS user_id
       FROM organization_admins GROUP BY organization_id
     ) contact ON contact.organization_id = v.organization_id
     WHERE v.status = "available" AND o.is_suspended = 0 AND p.review_status = "approved"
       AND p.account_type = "car_hire"
     ORDER BY o.name, v.daily_rate IS NULL, v.daily_rate, v.make, v.model
     LIMIT 200'
);
$vehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
$photosByVehicle = [];
if ($vehicles) {
    $vehicleIds = array_map(static fn(array $vehicle): int => (int) $vehicle['id'], $vehicles);
    $photoPlaceholders = implode(',', array_fill(0, count($vehicleIds), '?'));
    $photosStmt = $db->prepare(
        'SELECT vehicle_id, photo_type, photo_url
         FROM rental_vehicle_photos
         WHERE vehicle_id IN (' . $photoPlaceholders . ')
         ORDER BY vehicle_id, photo_type, sort_order, id'
    );
    $photosStmt->execute($vehicleIds);
    foreach ($photosStmt->fetchAll(PDO::FETCH_ASSOC) as $photo) {
        $photosByVehicle[(int) $photo['vehicle_id']][$photo['photo_type']][] = $photo['photo_url'];
    }
}
foreach ($vehicles as &$vehicle) {
    $vehicle['services'] = json_decode((string) ($vehicle['vehicle_services'] ?? '[]'), true) ?: [];
    $vehicle['company_services'] = json_decode((string) ($vehicle['company_services'] ?? '[]'), true) ?: [];
    $vehicle['photos'] = $photosByVehicle[(int) $vehicle['id']] ?? ['exterior' => [], 'interior' => []];
    if ($vehicle['office_lat'] !== null) $vehicle['office_lat'] = (float) $vehicle['office_lat'];
    if ($vehicle['office_lng'] !== null) $vehicle['office_lng'] = (float) $vehicle['office_lng'];
    if ($vehicle['pickup_lat'] !== null) $vehicle['pickup_lat'] = (float) $vehicle['pickup_lat'];
    if ($vehicle['pickup_lng'] !== null) $vehicle['pickup_lng'] = (float) $vehicle['pickup_lng'];
    $vehicle['rating_average'] = $vehicle['rating_average'] === null ? null : (float) $vehicle['rating_average'];
    $vehicle['rating_count'] = (int) $vehicle['rating_count'];
}
unset($vehicle);
$reviewsByVehicle = [];
if ($vehicles) {
    $vehicleIds = array_map(static fn(array $vehicle): int => (int) $vehicle['id'], $vehicles);
    $reviewPlaceholders = implode(',', array_fill(0, count($vehicleIds), '?'));
    $reviewsStmt = $db->prepare(
        'SELECT rr.vehicle_id, rr.rating, rr.review_text, rr.created_at, reviewer.full_name
         FROM rental_reviews rr
         JOIN users reviewer ON reviewer.id = rr.customer_id
         WHERE rr.vehicle_id IN (' . $reviewPlaceholders . ') AND rr.status = "published"
         ORDER BY rr.created_at DESC LIMIT 200'
    );
    $reviewsStmt->execute($vehicleIds);
    foreach ($reviewsStmt->fetchAll(PDO::FETCH_ASSOC) as $review) {
        $reviewsByVehicle[(int) $review['vehicle_id']][] = $review;
    }
}
foreach ($vehicles as &$vehicle) {
    $vehicle['reviews'] = $reviewsByVehicle[(int) $vehicle['id']] ?? [];
}
unset($vehicle);
$bookingsStmt = $db->prepare(
    'SELECT b.id, b.pickup_at, b.return_at, b.quoted_daily_rate, b.status, b.customer_note,
            v.make, v.model, v.currency, o.name AS organization_name,
            contact.user_id AS contact_user_id, rr.rating, rr.review_text
     FROM rental_bookings b
     JOIN rental_vehicles v ON v.id = b.vehicle_id
     JOIN organizations o ON o.id = v.organization_id
     LEFT JOIN rental_reviews rr ON rr.booking_id = b.id AND rr.customer_id = b.customer_id
     LEFT JOIN (
       SELECT organization_id, MIN(user_id) AS user_id
       FROM organization_admins GROUP BY organization_id
     ) contact ON contact.organization_id = v.organization_id
     WHERE b.customer_id = ?
     ORDER BY b.created_at DESC LIMIT 30'
);
$bookingsStmt->execute([$userId]);
st_json_ok([
    'vehicles' => $vehicles,
    'bookings' => $bookingsStmt->fetchAll(PDO::FETCH_ASSOC),
]);
