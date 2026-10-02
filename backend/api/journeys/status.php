<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');

$userId = st_require_login();
$input = st_input();

$journeyId = isset($input['journey_id'])
    ? (int) $input['journey_id']
    : 0;

$action = strtolower(
    trim($input['action'] ?? '')
);

if ($journeyId <= 0) {
    st_json_error(
        'Invalid journey ID.',
        422
    );
}

if (!in_array($action, ['end', 'cancel'], true)) {
    st_json_error(
        'Invalid journey action.',
        422
    );
}

$db = safaritrak_db();

$journeyStmt = $db->prepare(
    'SELECT id, user_id, status, started_at, distance_km
     FROM journeys
     WHERE id = ?
     AND user_id = ?
     LIMIT 1'
);

$journeyStmt->execute([
    $journeyId,
    $userId
]);

$journey = $journeyStmt->fetch(PDO::FETCH_ASSOC);

if (!$journey) {
    st_json_error(
        'Journey not found.',
        404
    );
}

if ($journey['status'] !== 'active') {
    st_json_error(
        'This journey is no longer active.',
        409
    );
}

if ($action === 'end') {

    $status = 'completed';

} else {

    $status = 'cancelled';
}

$columns = $db->query(
    'SHOW COLUMNS FROM journeys'
)->fetchAll(PDO::FETCH_COLUMN);

$availableColumns = array_flip($columns);

$updates = [
    'status = ?'
];

$params = [
    $status
];

if (isset($availableColumns['ended_at'])) {

    $updates[] = 'ended_at = NOW()';
}

if (isset($availableColumns['completed_at']) && $action === 'end') {

    $updates[] = 'completed_at = NOW()';
}

if (isset($availableColumns['cancelled_at']) && $action === 'cancel') {

    $updates[] = 'cancelled_at = NOW()';
}

if (isset($availableColumns['updated_at'])) {

    $updates[] = 'updated_at = NOW()';
}

$sql = 'UPDATE journeys
        SET ' . implode(', ', $updates) . '
        WHERE id = ?
        AND user_id = ?
        AND status = "active"';

$params[] = $journeyId;
$params[] = $userId;

$update = $db->prepare($sql);
$update->execute($params);

if ($update->rowCount() === 0) {
    st_json_error(
        'The journey could not be updated.',
        409
    );
}

st_json_ok([
    'journey_id' => $journeyId,
    'status' => $status,
    'action' => $action
]);