<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/rate-limit.php';
require_once __DIR__ . '/../../includes/app-url.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$action = st_input_string($input, 'action');
if (!in_array($action, ['create', 'revoke'], true)) {
    st_json_error('Choose whether to create or revoke the link.', 422);
}

$db = safaritrak_db();
if ($action === 'revoke') {
    $db->prepare(
        'UPDATE journey_share_links SET revoked_at = UTC_TIMESTAMP()
         WHERE owner_id = ? AND revoked_at IS NULL'
    )->execute([$userId]);
    st_json_ok(['message' => 'Live-location links have been revoked.']);
}

$requestedJourneyId = filter_var($input['journey_id'] ?? null, FILTER_VALIDATE_INT);
$journeyQuery = $requestedJourneyId
    ? $db->prepare(
        'SELECT id, status, planned_departure_at FROM journeys WHERE id = ? AND user_id = ? LIMIT 1'
    )
    : $db->prepare(
        'SELECT id, status, planned_departure_at FROM journeys
         WHERE user_id = ? AND status = "active" ORDER BY started_at DESC LIMIT 1'
    );
$journeyQuery->execute($requestedJourneyId ? [$requestedJourneyId, $userId] : [$userId]);
$journey = $journeyQuery->fetch(PDO::FETCH_ASSOC);
if (!$journey || !in_array($journey['status'], ['active', 'planned'], true)) {
    st_json_error('Choose an active or scheduled trip before creating a share link.', 409);
}

$retryAfter = st_rate_limit_consume($db, 'journey-share-link-user', (string) $userId, 5, 3600);
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    st_json_error('Too many live links were created. Wait before trying again.', 429);
}
$journeyId = (int) $journey['id'];

$token = bin2hex(random_bytes(32));
$db->beginTransaction();
try {
    $db->prepare(
        'UPDATE journey_share_links SET revoked_at = UTC_TIMESTAMP()
         WHERE owner_id = ? AND revoked_at IS NULL'
    )->execute([$userId]);
    $expiresAtTimestamp = time() + 3600;
    if ($journey['status'] === 'planned' && !empty($journey['planned_departure_at'])) {
        $departureTimestamp = strtotime($journey['planned_departure_at']);
        if ($departureTimestamp !== false) {
            $expiresAtTimestamp = max($expiresAtTimestamp, $departureTimestamp + 3600);
        }
    }
    $expiresAt = gmdate('Y-m-d H:i:s', $expiresAtTimestamp);
    $db->prepare(
        'INSERT INTO journey_share_links (journey_id, owner_id, token_hash, expires_at)
         VALUES (?, ?, ?, ?)'
    )->execute([(int) $journeyId, $userId, hash('sha256', $token), $expiresAt]);
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

st_json_ok([
    'share_url' => st_app_url() . '/shared-location.php?token=' . urlencode($token),
    'expires_at' => $expiresAt,
    'trip_status' => $journey['status'],
]);
