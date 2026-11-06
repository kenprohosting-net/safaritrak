<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/rate-limit.php';

st_start_session();
st_require_method('GET');
$token = (string) ($_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    st_json_error('This live-location link is invalid or expired.', 404);
}

$db = safaritrak_db();
$retryAfter = st_rate_limit_consume($db, 'public-journey-share-ip', st_client_address(), 240, 3600);
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    st_json_error('This link has received too many location requests. Try again later.', 429);
}

$stmt = $db->prepare(
    'SELECT j.id, j.status, j.start_label, j.start_lat, j.start_lng,
            j.end_label, j.end_lat, j.end_lng, j.planned_departure_at, l.expires_at
     FROM journey_share_links l
     JOIN journeys j ON j.id = l.journey_id
     WHERE l.token_hash = ? AND l.owner_id = j.user_id
       AND l.revoked_at IS NULL AND l.expires_at > UTC_TIMESTAMP()
       AND j.status IN ("active", "planned")
     LIMIT 1'
);
$stmt->execute([hash('sha256', $token)]);
$journey = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$journey) {
    st_json_error('This live-location link has expired, was revoked, or the journey has ended.', 404);
}

$location = null;
if ($journey['status'] === 'active') {
    $positionStmt = $db->prepare(
        'SELECT lat, lng, recorded_at FROM journey_positions
         WHERE journey_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 1'
    );
    $positionStmt->execute([(int) $journey['id']]);
    $position = $positionStmt->fetch(PDO::FETCH_ASSOC);
    if ($position) {
        $location = [
            'lat' => (float) $position['lat'],
            'lng' => (float) $position['lng'],
            'recorded_at' => (new DateTimeImmutable($position['recorded_at']))->format(DATE_ATOM),
        ];
    }
}

st_json_ok([
    'journey' => [
        'status' => $journey['status'],
        'start_label' => $journey['start_label'],
        'start_lat' => $journey['start_lat'] === null ? null : (float) $journey['start_lat'],
        'start_lng' => $journey['start_lng'] === null ? null : (float) $journey['start_lng'],
        'end_label' => $journey['end_label'],
        'end_lat' => $journey['end_lat'] === null ? null : (float) $journey['end_lat'],
        'end_lng' => $journey['end_lng'] === null ? null : (float) $journey['end_lng'],
        'planned_departure_at' => $journey['planned_departure_at'],
        'expires_at' => $journey['expires_at'],
        'location' => $location,
    ],
]);
