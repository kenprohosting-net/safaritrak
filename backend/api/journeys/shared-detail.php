<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/geo.php';

st_start_session();
$userId = st_require_login();

$journeyId = (int) ($_GET['id'] ?? 0);

$db = safaritrak_db();

$isPlatformAdminStmt = $db->prepare('SELECT id FROM platform_admins WHERE user_id = ?');
$isPlatformAdminStmt->execute([$userId]);
$isPlatformAdmin = (bool) $isPlatformAdminStmt->fetch();

if ($isPlatformAdmin) {
    $stmt = $db->prepare(
        'SELECT j.*, u.full_name AS traveler_name, u.avatar_path AS traveler_avatar
         FROM journeys j JOIN users u ON u.id = j.user_id
         WHERE j.id = ?
         LIMIT 1'
    );
    $stmt->execute([$journeyId]);
} else {
    $stmt = $db->prepare(
        'SELECT j.*, u.full_name AS traveler_name, u.avatar_path AS traveler_avatar
         FROM journeys j
         JOIN users u ON u.id = j.user_id
         JOIN journey_shares js ON js.journey_id = j.id
         JOIN trusted_contacts tc ON tc.id = js.trusted_contact_id
         WHERE j.id = ? AND tc.contact_user_id = ? AND tc.status = "confirmed"
         LIMIT 1'
    );
    $stmt->execute([$journeyId, $userId]);
}
$journey = $stmt->fetch();

if (!$journey) {
    st_json_error('This journey was not shared with you, or does not exist.', 403);
}

$positionsStmt = $db->prepare('SELECT lat, lng, speed_kmh, recorded_at FROM journey_positions WHERE journey_id = ? ORDER BY id ASC');
$positionsStmt->execute([$journeyId]);
$positions = $positionsStmt->fetchAll();

$coveredKm = 0.0;
for ($i = 1; $i < count($positions); $i++) {
    $leg = st_distance_km(
        (float) $positions[$i - 1]['lat'],
        (float) $positions[$i - 1]['lng'],
        (float) $positions[$i]['lat'],
        (float) $positions[$i]['lng']
    );
    $coveredKm += $leg ?? 0;
}

st_json_ok([
    'journey' => $journey,
    'positions' => $positions,
    'covered_km' => round($coveredKm, 2),
]);
