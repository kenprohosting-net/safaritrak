<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rate-limit.php';

st_require_method('POST');

$input = st_input();

$identifier = trim(st_input_string($input, 'username'));
$password = st_input_string($input, 'password');
$remember = !empty($input['remember']);

if ($identifier === '' || $password === '') {
    st_json_error('Enter your username and password.', 422);
}

$db = safaritrak_db();
$clientAddress = st_client_address();
$rateLimitKey = strtolower($identifier);
$retryAfter = st_rate_limit_consume($db, 'login-ip', $clientAddress, 20, 900);
if ($retryAfter === 0) {
    $retryAfter = st_rate_limit_consume($db, 'login-identifier', $rateLimitKey, 8, 900);
}
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    st_json_error('Too many sign-in attempts. Wait before trying again.', 429);
}

$phoneIdentifier = preg_replace('/\D/', '', $identifier) ?? '';
$stmt = $db->prepare('SELECT id, full_name, username, email, password_hash, is_suspended, email_verified_at FROM users WHERE username = ? OR email = ? OR phone = ?');
$stmt->execute([$identifier, $identifier, $phoneIdentifier]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    st_json_error('That username or password is not right.', 401);
}

st_rate_limit_clear($db, 'login-identifier', $rateLimitKey);

if ((int) $user['is_suspended'] === 1) {
    st_json_error('This account has been suspended. Contact SafariTrak support for help.', 403);
}

if (!$user['email_verified_at']) {
    st_json_error(
        'Please verify your email before logging in. Check your inbox for the verification link, or request a new one.',
        403,
        ['unverified' => true, 'email' => $user['email']]
    );
}

st_login_user($user, $remember, $db);

// Role priority matches login.php's fallback path and auth-guard.php:
// platform_admin > org_admin > traveler.
$paStmt = $db->prepare('SELECT id FROM platform_admins WHERE user_id = ? LIMIT 1');
$paStmt->execute([$user['id']]);
$isPlatformAdmin = (bool) $paStmt->fetch();

$oaStmt = $db->prepare('SELECT organization_id FROM organization_admins WHERE user_id = ? LIMIT 1');
$oaStmt->execute([$user['id']]);
$orgAdminData = $oaStmt->fetch();

if ($isPlatformAdmin) {
    $_SESSION['role'] = 'platform_admin';
    $userRole = 'platform_admin';
    $redirect = 'admin-dashboard.php';
} elseif ($orgAdminData) {
    $_SESSION['role'] = 'org_admin';
    $_SESSION['organization_id'] = (int) $orgAdminData['organization_id'];
    $userRole = 'org_admin';
    $redirect = 'org-dashboard.php';
} else {
    $_SESSION['role'] = 'traveler';
    $userRole = 'traveler';
    $redirect = 'index.php';
}

st_json_ok([
    'redirect' => $redirect,
    'user' => [
        'full_name' => $user['full_name'],
        'username'  => $user['username'],
        'role'      => $userRole,
    ],
]);