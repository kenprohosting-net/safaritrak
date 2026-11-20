<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$db = safaritrak_db();

$bootstrapToken = getenv('SAFARITRAK_PLATFORM_ADMIN_BOOTSTRAP_TOKEN');
if (!is_string($bootstrapToken) || strlen($bootstrapToken) < 32) {
    st_json_error('Platform owner setup is not enabled. Contact the site operator.', 503);
}

$input = st_input();
$providedToken = st_input_string($input, 'bootstrap_token');
if ($providedToken === '' || !hash_equals($bootstrapToken, $providedToken)) {
    st_json_error('The platform setup key is invalid.', 403);
}

$lockName = 'safaritrak_platform_admin_bootstrap';
$lockStmt = $db->prepare('SELECT GET_LOCK(?, 10)');
$lockStmt->execute([$lockName]);
if ((int) $lockStmt->fetchColumn() !== 1) {
    st_json_error('Platform owner setup is busy. Try again shortly.', 503);
}

$claimError = null;
try {
    $countStmt = $db->query('SELECT COUNT(*) FROM platform_admins');
    if ((int) $countStmt->fetchColumn() > 0) {
        $claimError = 'SafariTrak already has a platform owner. Ask an existing platform admin to add you.';
    } else {
        $db->prepare('INSERT INTO platform_admins (user_id, role) VALUES (?, "owner")')->execute([$userId]);
    }
} finally {
    $releaseStmt = $db->prepare('SELECT RELEASE_LOCK(?)');
    $releaseStmt->execute([$lockName]);
}

if ($claimError !== null) {
    st_json_error($claimError, 403);
}

st_json_ok();
