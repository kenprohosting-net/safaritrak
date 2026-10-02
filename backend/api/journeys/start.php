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
$startLat = isset($input['start_lat']) && $input['start_lat'] !== '' ? (float) $input['start_lat'] : null;
$startLng = isset($input['start_lng']) && $input['start_lng'] !== '' ? (float) $input['start_lng'] : null;
$endLat = isset($input['end_lat']) && $input['end_lat'] !== '' ? (float) $input['end_lat'] : null;
$endLng = isset($input['end_lng']) && $input['end_lng'] !== '' ? (float) $input['end_lng'] : null;
$transportMode = $input['transport_mode'] ?? 'car';
$note = trim($input['note'] ?? '');
$plannedDeparture = !empty($input['planned_departure_at']) ? $input['planned_departure_at'] : null;
$routeDeviationAlert = !empty($input['route_deviation_alert']) ? 1 : 0;
$shareWith = is_array($input['share_with'] ?? null) ? $input['share_with'] : [];

$errors = [];

if ($startLabel === '') {
    $errors['start_label'] = 'Enter your starting point.';
}
if ($endLabel === '') {
    $errors['end_label'] = 'Enter your destination.';
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
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active", ?, ?, NOW())'
);
$insert->execute([
    $userId, $startLabel, $startLat, $startLng, $endLabel, $endLat, $endLng,
    $transportMode, $note ?: null, $distanceKm, $routeDeviationAlert, $plannedDeparture,
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

        if ($contact['contact_user_id']) {
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

st_json_ok(['journey_id' => $journeyId, 'redirect' => 'live-tracking.php']);
