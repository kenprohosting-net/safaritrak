<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/consent.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();

if (empty($input['terms']) || empty($input['privacy'])) {
    st_json_error('Review and accept both the Terms and Privacy Policy.', 422);
}

$db = safaritrak_db();
$db->beginTransaction();
try {
    st_record_consent($db, $userId);
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

st_json_ok(['message' => 'Your acceptance of the current Terms and Privacy Policy has been recorded.']);
