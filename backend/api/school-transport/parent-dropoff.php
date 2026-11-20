<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/school-transport.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$label = trim(st_input_string($input, 'label'));
$routeConsent = $input['route_service_consent'] ?? false;
$lat = st_school_transport_parse_coordinate($input['lat'] ?? null, -90, 90);
$lng = st_school_transport_parse_coordinate($input['lng'] ?? null, -180, 180);
if ($label === '') {
    $label = 'Home drop-off';
}
if (mb_strlen($label) > 120 || $lat === null || $lng === null || !is_bool($routeConsent)) {
    st_json_error('Choose a valid drop-off label and location.', 422);
}

$db = safaritrak_db();
$verification = st_school_transport_verification($db, $userId);
if (!$verification || $verification['role'] !== 'verified_parent') {
    st_json_error('Verified parent access is required to set a drop-off location.', 403);
}

$db->prepare(
    'UPDATE school_verifications
     SET home_dropoff_label = ?, home_dropoff_lat = ?, home_dropoff_lng = ?,
         route_service_consent = ?,
         route_service_consent_at = CASE WHEN ? = 1 THEN UTC_TIMESTAMP() ELSE NULL END
     WHERE id = ? AND user_id = ? AND role = "verified_parent"
       AND verification_status = "verified"'
)->execute([
    $label,
    $lat,
    $lng,
    $routeConsent ? 1 : 0,
    $routeConsent ? 1 : 0,
    (int) $verification['id'],
    $userId,
]);

st_json_ok([
    'label' => $label,
    'lat' => $lat,
    'lng' => $lng,
    'route_service_consent' => $routeConsent,
]);
