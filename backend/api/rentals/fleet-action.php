<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

function st_rental_optional_amount(array $input, string $key): ?float {
    if (!isset($input[$key]) || $input[$key] === '') {
        return null;
    }
    $value = filter_var($input[$key], FILTER_VALIDATE_FLOAT);
    if ($value === false || $value < 0 || $value > 10000000) {
        st_json_error('Check the rental pricing fields.', 422);
    }
    return (float) $value;
}

function st_rental_optional_integer(array $input, string $key, int $minimum, int $maximum): ?int {
    if (!isset($input[$key]) || $input[$key] === '') {
        return null;
    }
    $value = filter_var($input[$key], FILTER_VALIDATE_INT);
    if ($value === false || $value < $minimum || $value > $maximum) {
        st_json_error('Check the vehicle detail fields.', 422);
    }
    return (int) $value;
}

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
    $pickupLat = isset($input['pickup_lat']) && $input['pickup_lat'] !== '' ? filter_var($input['pickup_lat'], FILTER_VALIDATE_FLOAT) : null;
    $pickupLng = isset($input['pickup_lng']) && $input['pickup_lng'] !== '' ? filter_var($input['pickup_lng'], FILTER_VALIDATE_FLOAT) : null;
    $quotationRequired = filter_var($input['quotation_required'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $rate = $quotationRequired && ($input['daily_rate'] ?? '') === ''
        ? null
        : filter_var($input['daily_rate'] ?? null, FILTER_VALIDATE_FLOAT);
    $description = trim(st_input_string($input, 'description'));
    $currency = strtoupper(trim(st_input_string($input, 'currency') ?: 'KES'));
    if ($make === '' || mb_strlen($make) > 80 || $model === '' || mb_strlen($model) > 100
        || $category === '' || mb_strlen($category) > 60
        || !preg_match('/^[A-Z0-9 -]{2,32}$/', $registration)
        || $seats < 1 || $seats > 99 || ($rate !== null && ($rate === false || $rate <= 0 || $rate > 10000000))
        || ($rate === null && !$quotationRequired)
        || (($pickupLat === null) !== ($pickupLng === null))
        || $pickupLat === false || $pickupLng === false
        || ($pickupLat !== null && ($pickupLat < -90 || $pickupLat > 90 || $pickupLng < -180 || $pickupLng > 180))
        || !preg_match('/^[A-Z]{3}$/', $currency) || mb_strlen($description) > 500
        || ($year !== 0 && ($year < 1900 || $year > ((int) date('Y') + 1)))) {
        st_json_error('Check the vehicle details, registration, seats, rate, and currency.', 422);
    }
    $transmission = st_input_string($input, 'transmission');
    $fuelType = st_input_string($input, 'fuel_type');
    $driverOption = st_input_string($input, 'driver_option') ?: 'both';
    if (($transmission !== '' && !in_array($transmission, ['automatic', 'manual'], true))
        || ($fuelType !== '' && !in_array($fuelType, ['petrol', 'diesel', 'hybrid', 'electric'], true))
        || !in_array($driverOption, ['self_drive', 'chauffeur', 'both'], true)) {
        st_json_error('Choose a valid transmission, fuel type, and driver option.', 422);
    }
    $serviceOptions = [
        'self_drive_car_hire', 'chauffeur_car_hire', 'airport_transfers', 'corporate_transport',
        'wedding_transport', 'safari_tourism_transport', 'long_term_hire', 'short_term_hire',
        'event_transport', 'school_transport', 'one_way_rentals',
    ];
    $services = $input['services'] ?? [];
    if (!is_array($services) || count($services) > count($serviceOptions)
        || count(array_filter($services, static fn($service): bool => !is_string($service) || !in_array($service, $serviceOptions, true))) > 0) {
        st_json_error('Choose valid services for this vehicle.', 422);
    }
    $vehicleDetails = [
        'transmission' => $transmission === '' ? null : $transmission,
        'fuel_type' => $fuelType === '' ? null : $fuelType,
        'air_conditioning' => isset($input['air_conditioning']) ? (int) filter_var($input['air_conditioning'], FILTER_VALIDATE_BOOLEAN) : null,
        'luggage_capacity' => st_rental_optional_integer($input, 'luggage_capacity', 0, 99),
        'weekly_rate' => st_rental_optional_amount($input, 'weekly_rate'),
        'monthly_rate' => st_rental_optional_amount($input, 'monthly_rate'),
        'chauffeur_rate' => st_rental_optional_amount($input, 'chauffeur_rate'),
        'self_drive_rate' => st_rental_optional_amount($input, 'self_drive_rate'),
        'security_deposit' => st_rental_optional_amount($input, 'security_deposit'),
        'extra_mileage_charge' => st_rental_optional_amount($input, 'extra_mileage_charge'),
        'fuel_policy' => trim(st_input_string($input, 'fuel_policy')) ?: null,
        'additional_charges' => trim(st_input_string($input, 'additional_charges')) ?: null,
        'negotiable' => (int) filter_var($input['negotiable'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'quotation_required' => (int) $quotationRequired,
        'driver_option' => $driverOption,
        'services' => json_encode(array_values(array_unique($services)), JSON_THROW_ON_ERROR),
        'minimum_driver_age' => st_rental_optional_integer($input, 'minimum_driver_age', 16, 99),
        'valid_licence_required' => (int) filter_var($input['valid_licence_required'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'minimum_driving_experience' => st_rental_optional_integer($input, 'minimum_driving_experience', 0, 60),
        'identification_requirements' => trim(st_input_string($input, 'identification_requirements')) ?: null,
        'booking_notice' => trim(st_input_string($input, 'booking_notice')) ?: null,
        'minimum_rental_days' => st_rental_optional_integer($input, 'minimum_rental_days', 1, 3650),
        'maximum_rental_days' => st_rental_optional_integer($input, 'maximum_rental_days', 1, 3650),
        'fuel_return_policy' => trim(st_input_string($input, 'fuel_return_policy')) ?: null,
        'mileage_restrictions' => trim(st_input_string($input, 'mileage_restrictions')) ?: null,
        'late_return_policy' => trim(st_input_string($input, 'late_return_policy')) ?: null,
        'cancellation_policy' => trim(st_input_string($input, 'cancellation_policy')) ?: null,
        'refund_policy' => trim(st_input_string($input, 'refund_policy')) ?: null,
        'cross_border_available' => (int) filter_var($input['cross_border_available'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ];
    foreach (['fuel_policy' => 500, 'additional_charges' => 1000, 'identification_requirements' => 500,
        'booking_notice' => 200, 'fuel_return_policy' => 500, 'mileage_restrictions' => 500,
        'late_return_policy' => 500, 'cancellation_policy' => 1000, 'refund_policy' => 1000] as $field => $limit) {
        if ($vehicleDetails[$field] !== null && mb_strlen($vehicleDetails[$field]) > $limit) {
            st_json_error('One or more vehicle policy details are too long.', 422);
        }
    }
    if ($vehicleDetails['minimum_rental_days'] !== null && $vehicleDetails['maximum_rental_days'] !== null
        && $vehicleDetails['minimum_rental_days'] > $vehicleDetails['maximum_rental_days']) {
        st_json_error('Minimum rental duration cannot exceed maximum duration.', 422);
    }
    $photosByType = [];
    foreach (['exterior_photos' => 'exterior', 'interior_photos' => 'interior'] as $field => $type) {
        $photos = $input[$field] ?? [];
        if (!is_array($photos) || count($photos) > 10) {
            st_json_error('Add up to 10 valid exterior and interior photo URLs each.', 422);
        }
        foreach (array_values(array_unique($photos)) as $index => $photo) {
            if (!is_string($photo) || filter_var($photo, FILTER_VALIDATE_URL) === false
                || strtolower((string) parse_url($photo, PHP_URL_SCHEME)) !== 'https') {
                st_json_error('Vehicle photos must use secure HTTPS URLs.', 422);
            }
            $photosByType[] = [$type, $photo, $index];
        }
    }

    $db->beginTransaction();
    try {
        $db->prepare(
            'INSERT INTO rental_vehicles
             (organization_id, make, model, year, category, seats, daily_rate, currency, registration_number, description, pickup_lat, pickup_lng)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) $org['organization_id'], $make, $model, $year ?: null, $category, $seats,
            $rate, $currency, $registration, $description === '' ? null : $description, $pickupLat, $pickupLng,
        ]);
        $vehicleId = (int) $db->lastInsertId();
        $columns = array_keys($vehicleDetails);
        $db->prepare(
            'INSERT INTO rental_vehicle_details (vehicle_id, ' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', array_fill(0, count($columns) + 1, '?')) . ')'
        )->execute(array_merge([$vehicleId], array_values($vehicleDetails)));
        foreach ($photosByType as [$type, $photo, $index]) {
            $db->prepare(
                'INSERT INTO rental_vehicle_photos (vehicle_id, photo_type, photo_url, sort_order) VALUES (?, ?, ?, ?)'
            )->execute([$vehicleId, $type, $photo, $index]);
        }
        $db->commit();
    } catch (PDOException $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ((string) $error->getCode() === '23000') {
            st_json_error('That registration number is already in your fleet.', 409);
        }
        throw $error;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
    st_json_ok(['vehicle_id' => $vehicleId]);
}

if ($action === 'save_company_profile') {
    $textLimits = [
        'logo_url' => 500, 'cover_photo_url' => 500, 'description' => 2000, 'business_email' => 160,
        'official_phone' => 32, 'whatsapp' => 32, 'website' => 500, 'social_links' => 2000,
        'office_location' => 500, 'town' => 120, 'regions_served' => 1000, 'operating_hours' => 500,
        'coverage_description' => 1000, 'pickup_locations' => 1000, 'identification_requirements' => 500,
        'booking_notice' => 200, 'fuel_return_policy' => 500, 'mileage_restrictions' => 500,
        'late_return_policy' => 500, 'cancellation_policy' => 1000, 'refund_policy' => 1000,
        'emergency_number' => 32, 'emergency_arrangements' => 1000, 'response_information' => 500,
    ];
    $profile = [];
    foreach ($textLimits as $field => $limit) {
        $value = trim(st_input_string($input, $field));
        if (mb_strlen($value) > $limit) {
            st_json_error('One or more company profile fields are too long.', 422);
        }
        $profile[$field] = $value === '' ? null : $value;
    }
    if ($profile['business_email'] !== null && !filter_var($profile['business_email'], FILTER_VALIDATE_EMAIL)) {
        st_json_error('Enter a valid business email address.', 422);
    }
    foreach (['logo_url', 'cover_photo_url', 'website'] as $urlField) {
        if ($profile[$urlField] !== null && (filter_var($profile[$urlField], FILTER_VALIDATE_URL) === false
            || strtolower((string) parse_url($profile[$urlField], PHP_URL_SCHEME)) !== 'https')) {
            st_json_error('Company website and image URLs must use HTTPS.', 422);
        }
    }
    $year = st_rental_optional_integer($input, 'established_year', 1800, (int) date('Y'));
    $officeLat = isset($input['office_lat']) && $input['office_lat'] !== '' ? filter_var($input['office_lat'], FILTER_VALIDATE_FLOAT) : null;
    $officeLng = isset($input['office_lng']) && $input['office_lng'] !== '' ? filter_var($input['office_lng'], FILTER_VALIDATE_FLOAT) : null;
    if (($officeLat === null) !== ($officeLng === null) || $officeLat === false || $officeLng === false
        || ($officeLat !== null && ($officeLat < -90 || $officeLat > 90 || $officeLng < -180 || $officeLng > 180))) {
        st_json_error('Enter both valid map coordinates for your office or leave both blank.', 422);
    }
    $serviceOptions = [
        'self_drive_car_hire', 'chauffeur_car_hire', 'airport_transfers', 'corporate_transport',
        'wedding_transport', 'safari_tourism_transport', 'long_term_hire', 'short_term_hire',
        'event_transport', 'school_transport', 'one_way_rentals',
    ];
    $services = $input['services'] ?? [];
    if (!is_array($services) || array_diff($services, $serviceOptions)) {
        st_json_error('Choose valid company services.', 422);
    }
    $profile['established_year'] = $year;
    $profile['office_lat'] = $officeLat;
    $profile['office_lng'] = $officeLng;
    $profile['services'] = json_encode(array_values(array_unique($services)), JSON_THROW_ON_ERROR);
    foreach ([
        'vehicle_delivery', 'airport_pickup', 'valid_licence_required', 'cross_border_available',
        'gps_tracking_available', 'customer_trip_sharing', 'roadside_assistance', 'breakdown_assistance',
        'registered_drivers', 'live_trip_monitoring',
    ] as $field) {
        $profile[$field] = (int) filter_var($input[$field] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
    $profile['minimum_driver_age'] = st_rental_optional_integer($input, 'minimum_driver_age', 16, 99);
    $profile['minimum_driving_experience'] = st_rental_optional_integer($input, 'minimum_driving_experience', 0, 60);
    $profile['minimum_rental_days'] = st_rental_optional_integer($input, 'minimum_rental_days', 1, 3650);
    $profile['maximum_rental_days'] = st_rental_optional_integer($input, 'maximum_rental_days', 1, 3650);
    if ($profile['minimum_rental_days'] !== null && $profile['maximum_rental_days'] !== null
        && $profile['minimum_rental_days'] > $profile['maximum_rental_days']) {
        st_json_error('Minimum rental duration cannot exceed maximum duration.', 422);
    }
    $profile['organization_id'] = (int) $org['organization_id'];
    $columns = array_keys($profile);
    $updates = array_map(static fn(string $column): string => $column . ' = VALUES(' . $column . ')', array_filter(
        $columns,
        static fn(string $column): bool => $column !== 'organization_id'
    ));
    $db->prepare(
        'INSERT INTO rental_company_profiles (' . implode(', ', $columns) . ')
         VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')
         ON DUPLICATE KEY UPDATE ' . implode(', ', $updates)
    )->execute(array_values($profile));
    st_json_ok();
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

if ($action === 'update_vehicle') {
    $vehicleId = (int) ($input['vehicle_id'] ?? 0);
    $status = st_input_string($input, 'status');
    $quotationRequired = filter_var($input['quotation_required'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $dailyRate = $quotationRequired && ($input['daily_rate'] ?? '') === ''
        ? null
        : filter_var($input['daily_rate'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($vehicleId < 1 || !in_array($status, ['available', 'unavailable', 'maintenance'], true)
        || ($dailyRate !== null && ($dailyRate === false || $dailyRate <= 0 || $dailyRate > 10000000))
        || ($dailyRate === null && !$quotationRequired)) {
        st_json_error('Check the vehicle price and availability fields.', 422);
    }
    $update = $db->prepare(
        'UPDATE rental_vehicles SET daily_rate = ?, status = ?
         WHERE id = ? AND organization_id = ?'
    );
    $update->execute([$dailyRate, $status, $vehicleId, (int) $org['organization_id']]);
    $exists = $db->prepare('SELECT 1 FROM rental_vehicles WHERE id = ? AND organization_id = ?');
    $exists->execute([$vehicleId, (int) $org['organization_id']]);
    if (!$exists->fetchColumn()) {
        st_json_error('That vehicle could not be found.', 404);
    }
    $db->prepare(
        'INSERT INTO rental_vehicle_details (vehicle_id, quotation_required)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE quotation_required = VALUES(quotation_required)'
    )->execute([$vehicleId, (int) $quotationRequired]);
    st_json_ok(['status' => $status]);
}

if ($action === 'booking_status') {
    $bookingId = (int) ($input['booking_id'] ?? 0);
    $status = st_input_string($input, 'status');
    if ($bookingId < 1 || !in_array($status, ['approved', 'responded', 'rejected', 'completed'], true)) {
        st_json_error('Choose an enquiry and a supported status update.', 422);
    }
    $db->beginTransaction();
    $booking = $db->prepare(
        'SELECT b.id, b.vehicle_id, b.status, b.pickup_at, b.return_at
         FROM rental_bookings b
         JOIN rental_vehicles v ON v.id = b.vehicle_id
         WHERE b.id = ? AND v.organization_id = ?
         FOR UPDATE'
    );
    $booking->execute([$bookingId, (int) $org['organization_id']]);
    $request = $booking->fetch(PDO::FETCH_ASSOC);
    $validTransition = $request && (
        ($request['status'] === 'requested' && in_array($status, ['approved', 'responded', 'rejected'], true))
        || (in_array($request['status'], ['responded', 'approved'], true) && $status === 'completed')
    );
    if (!$validTransition) {
        $db->rollBack();
        st_json_error('That enquiry cannot be changed to the selected status.', 409);
    }
    $db->prepare('SELECT id FROM rental_vehicles WHERE id = ? FOR UPDATE')->execute([(int) $request['vehicle_id']]);
    if ($status === 'approved') {
        $overlap = $db->prepare(
            'SELECT id FROM rental_bookings
             WHERE vehicle_id = ? AND id <> ? AND status = "approved"
               AND pickup_at < ? AND return_at > ?
             LIMIT 1'
        );
        $overlap->execute([
            (int) $request['vehicle_id'],
            $bookingId,
            $request['return_at'],
            $request['pickup_at'],
        ]);
        if ($overlap->fetchColumn()) {
            $db->rollBack();
            st_json_error('This request overlaps an approved rental. Decline it or contact the customer to choose other dates.', 409);
        }
    }
    $db->prepare('UPDATE rental_bookings SET status = ? WHERE id = ?')->execute([$status, $bookingId]);
    $db->commit();
    st_json_ok(['status' => $status]);
}

st_json_error('That fleet action is not recognized.', 422);
