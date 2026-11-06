<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';
require_once __DIR__ . '/../../includes/geo.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();

$startLabel = trim($input['start_label'] ?? '');
$endLabel = trim($input['end_label'] ?? '');
function st_journey_coordinate(array $input, string $key, float $minimum, float $maximum): ?float {
    if (!array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
        return null;
    }
    if (!is_scalar($input[$key]) || !is_numeric($input[$key])) {
        st_json_error('Journey coordinates must be valid numbers.', 422, ['errors' => [$key => 'Choose a valid location.']]);
    }
    $coordinate = (float) $input[$key];
    if (!is_finite($coordinate) || $coordinate < $minimum || $coordinate > $maximum) {
        st_json_error('Journey coordinates are outside the valid range.', 422, ['errors' => [$key => 'Choose a valid location.']]);
    }
    return $coordinate;
}

$startLat = st_journey_coordinate($input, 'start_lat', -90, 90);
$startLng = st_journey_coordinate($input, 'start_lng', -180, 180);
$endLat = st_journey_coordinate($input, 'end_lat', -90, 90);
$endLng = st_journey_coordinate($input, 'end_lng', -180, 180);
$transportMode = $input['transport_mode'] ?? 'car';
$note = trim($input['note'] ?? '');
$plannedDeparture = null;
$routeDeviationAlert = !empty($input['route_deviation_alert']) ? 1 : 0;
$shareWith = is_array($input['share_with'] ?? null) ? $input['share_with'] : [];

$errors = [];

if ($startLabel === '') {
    $errors['start_label'] = 'Enter your starting point.';
}
if ($endLabel === '') {
    $errors['end_label'] = 'Enter your destination.';
}
if (($startLat === null) !== ($startLng === null)) {
    $errors['start_lat'] = 'Choose both coordinates for the starting point.';
}
if (($endLat === null) !== ($endLng === null)) {
    $errors['end_lat'] = 'Choose both coordinates for the destination.';
}
if (!empty($input['planned_departure_at'])) {
    $rawDeparture = $input['planned_departure_at'];
    $departure = is_string($rawDeparture)
        ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $rawDeparture)
        : false;
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$departure || ($dateErrors && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
        $errors['planned_departure_at'] = 'Choose a valid departure date and time.';
    } elseif ($departure <= new DateTimeImmutable('now')) {
        $errors['planned_departure_at'] = 'Scheduled trips must have a future departure time.';
    } elseif ($departure > new DateTimeImmutable('+30 days')) {
        $errors['planned_departure_at'] = 'Trips can be scheduled up to 30 days ahead.';
    } else {
        $plannedDeparture = $departure->format('Y-m-d H:i:s');
    }
}

$allowedModes = ['car', 'bus', 'motorbike', 'walking'];
if (!in_array($transportMode, $allowedModes, true)) {
    $transportMode = 'car';
}

if (!empty($errors)) {
    st_json_error('Please fix the highlighted fields.', 422, ['errors' => $errors]);
}

$db = safaritrak_db();

$existingActive = $db->prepare('SELECT id FROM journeys WHERE user_id = ? AND status = "active"');
$existingActive->execute([$userId]);
if ($existingActive->fetch()) {
    st_json_error('You already have a journey in progress. End it before starting a new one.');
}

$distanceKm = st_distance_km($startLat, $startLng, $endLat, $endLng);

$insert = $db->prepare(
    'INSERT INTO journeys (user_id, start_label, start_lat, start_lng, end_label, end_lat, end_lng,
        transport_mode, note, distance_km, status, route_deviation_alert, planned_departure_at, started_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
);
$journeyStatus = $plannedDeparture === null ? 'active' : 'planned';
$insert->execute([
    $userId, $startLabel, $startLat, $startLng, $endLabel, $endLat, $endLng,
    $transportMode, $note ?: null, $distanceKm, $journeyStatus, $routeDeviationAlert, $plannedDeparture,
]);

$journeyId = (int) $db->lastInsertId();

if (!empty($shareWith)) {
    $placeholders = implode(',', array_fill(0, count($shareWith), '?'));
    $validContacts = $db->prepare(
        "SELECT id, contact_user_id, invite_name FROM trusted_contacts
         WHERE owner_id = ? AND status = 'confirmed' AND id IN ($placeholders)"
    );
    $validContacts->execute(array_merge([$userId], $shareWith));
    $contacts = $validContacts->fetchAll();

    $shareInsert = $db->prepare('INSERT IGNORE INTO journey_shares (journey_id, trusted_contact_id) VALUES (?, ?)');
    $ownerStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
    $ownerStmt->execute([$userId]);
    $ownerName = $ownerStmt->fetchColumn();

    foreach ($contacts as $contact) {
        $shareInsert->execute([$journeyId, $contact['id']]);

        if ($contact['contact_user_id'] && $journeyStatus === 'active') {
            st_notify(
                (int) $contact['contact_user_id'],
                'location_share',
                $ownerName . ' started a journey they are sharing with you',
                $startLabel . ' to ' . $endLabel,
                $journeyId,
                $userId
            );
        }
    }
}

st_json_ok([
    'journey_id' => $journeyId,
    'status' => $journeyStatus,
    'redirect' => $journeyStatus === 'active' ? 'my-journeys.php?selected=' . $journeyId : 'my-journeys.php',
]);
