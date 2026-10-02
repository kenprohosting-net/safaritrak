<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/session.php';

st_require_method('POST');

$input = st_input();

$identifier = trim($input['username'] ?? '');
$password = (string) ($input['password'] ?? '');
$remember = !empty($input['remember']);

if ($identifier === '' || $password === '') {
    st_json_error('Enter your username and password.', 422);
}

$db = safaritrak_db();

$stmt = $db->prepare('SELECT id, full_name, username, email, password_hash, is_suspended, email_verified_at FROM users WHERE username = ? OR email = ? OR phone = ?');
$stmt->execute([$identifier, $identifier, preg_replace('/\D/', '', $identifier)]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    st_json_error('That username or password is not right.', 401);
}

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

st_login_user($user, $remember);

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
    $_SESSION['role'] = 'user';
    $userRole = 'user';
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