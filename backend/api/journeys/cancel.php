<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$journeyId = (int) ($input['journey_id'] ?? 0);

$db = safaritrak_db();

$stmt = $db->prepare('UPDATE journeys SET status = "cancelled", ended_at = NOW() WHERE id = ? AND user_id = ? AND status = "active"');
$stmt->execute([$journeyId, $userId]);

if ($stmt->rowCount() === 0) {
    st_json_error('That journey could not be cancelled.', 404);
}

st_json_ok();
