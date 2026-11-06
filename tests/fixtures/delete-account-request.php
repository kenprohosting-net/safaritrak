<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$userId = filter_var(getenv('SAFARITRAK_TEST_DELETE_USER_ID'), FILTER_VALIDATE_INT);
$password = getenv('SAFARITRAK_TEST_DELETE_PASSWORD');
if (!is_int($userId) || !is_string($password)) {
    fwrite(STDERR, "Missing disposable account-deletion test input.\n");
    exit(2);
}

require_once __DIR__ . '/../../backend/config/database.php';
$db = safaritrak_db();
$sessionId = bin2hex(random_bytes(24));
$validator = bin2hex(random_bytes(32));
$csrfToken = bin2hex(random_bytes(32));

$insertSession = $db->prepare(
    'INSERT INTO sessions (id, user_id, validator_hash, last_active_at, expires_at)
     VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))'
);
$insertSession->execute([$sessionId, $userId, hash('sha256', $validator)]);

session_id($sessionId);
session_start();
$_SESSION['user_id'] = $userId;
$_SESSION['_session_validator'] = $validator;
$_SESSION['_csrf_token'] = $csrfToken;
$_SESSION['_remember_me'] = false;
session_write_close();

register_shutdown_function(static function () use ($db, $sessionId): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    $db->prepare('DELETE FROM sessions WHERE id = ?')->execute([$sessionId]);
});

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfToken;
$_POST = ['password' => $password];

require __DIR__ . '/../../backend/api/delete-account.php';
