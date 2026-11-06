<?php

require_once __DIR__ . '/../backend/includes/session.php';

$checks = 0;
$passed = [];
$expect = static function (bool $condition, string $message) use (&$checks, &$passed): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
    $passed[] = $message;
};

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec(
    'CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        username TEXT NOT NULL,
        is_suspended INTEGER NOT NULL DEFAULT 0,
        email_verified_at TEXT NULL
    )'
);
$db->exec(
    'CREATE TABLE sessions (
        id TEXT PRIMARY KEY,
        user_id INTEGER NOT NULL,
        validator_hash TEXT NOT NULL,
        user_agent TEXT NULL,
        ip_address TEXT NULL,
        last_active_at TEXT NOT NULL,
        expires_at TEXT NOT NULL
    )'
);
$db->exec(
    "INSERT INTO users (id, username, is_suspended, email_verified_at)
     VALUES (1, 'test-user', 0, '2026-01-01 00:00:00')"
);

$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'SafariTrak integration test';
$user = ['id' => 1, 'username' => 'test-user'];

st_start_session($db);
$anonymousSessionId = session_id();
$_SESSION['_csrf_token'] = 'pre-login-token';
st_login_user($user, false, $db);
$sessionId = session_id();
$expect($sessionId !== $anonymousSessionId, 'sign-in rotates the PHP session identifier');
$expect(!isset($_SESSION['_csrf_token']), 'sign-in invalidates the pre-authentication CSRF token');
$expect($_SESSION['user_id'] === 1, 'sign-in establishes the authenticated session');

$readSession = $db->prepare('SELECT * FROM sessions WHERE id = ?');
$readSession->execute([$sessionId]);
$record = $readSession->fetch();
$expectedValidatorHash = hash('sha256', $_SESSION['_session_validator']);
$expect(is_array($record), 'sign-in persists a session record in the database');
$expect(hash_equals($record['validator_hash'], $expectedValidatorHash), 'database stores only a hash of the session validator');
$normalLifetime = strtotime($record['expires_at']) - time();
$expect($normalLifetime > 11 * 60 * 60 && $normalLifetime <= 12 * 60 * 60, 'ordinary sessions have a twelve-hour absolute expiry');

st_validate_session($db, true);
$expect($_SESSION['user_id'] === 1, 'a valid database-backed session remains authenticated');

$oldLastActive = date('Y-m-d H:i:s', time() - (31 * 60));
$updateActivity = $db->prepare('UPDATE sessions SET last_active_at = ? WHERE id = ?');
$updateActivity->execute([$oldLastActive, $sessionId]);
st_validate_session($db, true);
$expect(empty($_SESSION['user_id']), 'an idle session expires after thirty minutes');
$expect((int) $db->query('SELECT COUNT(*) FROM sessions')->fetchColumn() === 0, 'expired session records are revoked');

st_start_session($db);
st_login_user($user, true, $db);
$rememberSessionId = session_id();
$readSession->execute([$rememberSessionId]);
$rememberRecord = $readSession->fetch();
$rememberLifetime = strtotime($rememberRecord['expires_at']) - time();
$expect($_SESSION['_remember_me'] === true, 'remember-me state is stored server-side');
$expect($rememberLifetime > 29 * 24 * 60 * 60 && $rememberLifetime <= 30 * 24 * 60 * 60, 'remembered sessions have a thirty-day absolute expiry');

$oldRememberActivity = date('Y-m-d H:i:s', time() - (8 * 24 * 60 * 60));
$updateActivity->execute([$oldRememberActivity, $rememberSessionId]);
st_validate_session($db, true);
$expect(empty($_SESSION['user_id']), 'remembered sessions expire after seven inactive days');

st_start_session($db);
st_login_user($user, false, $db);
$revokedSessionId = session_id();
$db->prepare('DELETE FROM sessions WHERE id = ?')->execute([$revokedSessionId]);
st_validate_session($db, true);
$expect(empty($_SESSION['user_id']), 'server-side session revocation removes access');

st_start_session($db);
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'test-user';
$legacySessionId = session_id();
st_validate_session($db, true);
$expect(session_id() !== $legacySessionId, 'legacy authenticated sessions are rotated during upgrade');
$expect(isset($_SESSION['_session_validator']), 'legacy session upgrades receive a new validator');

st_logout($db);
$expect(session_status() === PHP_SESSION_NONE, 'logout destroys the PHP session');
$expect((int) $db->query('SELECT COUNT(*) FROM sessions')->fetchColumn() === 0, 'logout removes the persistent session record');

st_start_session($db);
st_login_user($user, false, $db);
$expect(st_require_login() === 1, 'authenticated API requests resolve the signed-in user');
$expect(session_status() === PHP_SESSION_NONE, 'authenticated API requests release the PHP session lock after reading the user');

st_start_session($db);
st_login_user($user, false, $db);
$expiredSessionId = session_id();
$expireRecord = $db->prepare('UPDATE sessions SET expires_at = ? WHERE id = ?');
$expireRecord->execute(['2000-01-01 00:00:00', $expiredSessionId]);
st_validate_session($db, true);
$expect(empty($_SESSION['user_id']), 'absolute expiry revokes an authenticated session');
$expect((int) $db->query('SELECT COUNT(*) FROM sessions')->fetchColumn() === 0, 'absolute expiry removes the server-side session');

foreach ($passed as $message) {
    echo "PASS {$message}\n";
}
echo "Completed {$checks} database-backed session checks.\n";
