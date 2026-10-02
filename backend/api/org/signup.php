<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/mailer.php';

st_start_session();
st_require_method('POST');

$input = st_input();

$orgName = trim($input['org_name'] ?? '');
$fullName = trim($input['full_name'] ?? '');
$username = trim($input['username'] ?? '');
$email = trim(strtolower($input['email'] ?? ''));
$phone = preg_replace('/\D/', '', $input['phone'] ?? '');
$password = (string) ($input['password'] ?? '');
$terms = !empty($input['terms']);

$errors = [];

if ($orgName === '' || mb_strlen($orgName) > 160) {
    $errors['org_name'] = 'Enter your organization name.';
}
if ($fullName === '') {
    $errors['full_name'] = 'Enter your full name.';
}
if (!preg_match('/^[a-zA-Z0-9_.]{3,40}$/', $username)) {
    $errors['username'] = 'Username must be 3-40 characters, letters, numbers, dots or underscores only.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
}
if (strlen($phone) < 9) {
    $errors['phone'] = 'Enter a valid phone number.';
}
if (strlen($password) < 6) {
    $errors['password'] = 'Password must be at least 6 characters.';
}
if (!$terms) {
    $errors['terms'] = 'You need to accept the terms to continue.';
}

if (!empty($errors)) {
    st_json_error('Please fix the highlighted fields.', 422, ['errors' => $errors]);
}

$db = safaritrak_db();

$dupeStmt = $db->prepare('SELECT username, email, phone FROM users WHERE username = ? OR email = ? OR phone = ?');
$dupeStmt->execute([$username, $email, $phone]);
$dupe = $dupeStmt->fetch();

if ($dupe) {
    if ($dupe['username'] === $username) {
        st_json_error('That username is already taken.', 422, ['errors' => ['username' => 'That username is already taken.']]);
    }
    if ($dupe['email'] === $email) {
        st_json_error('That email is already registered.', 422, ['errors' => ['email' => 'That email is already registered.']]);
    }
    st_json_error('That phone number is already registered.', 422, ['errors' => ['phone' => 'That phone number is already registered.']]);
}

$db->beginTransaction();

try {
    $insertUser = $db->prepare(
        'INSERT INTO users (full_name, username, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)'
    );
    $insertUser->execute([$fullName, $username, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $db->lastInsertId();

    $insertOrg = $db->prepare('INSERT INTO organizations (name) VALUES (?)');
    $insertOrg->execute([$orgName]);
    $orgId = (int) $db->lastInsertId();

    $insertAdmin = $db->prepare('INSERT INTO organization_admins (organization_id, user_id, role) VALUES (?, ?, "owner")');
    $insertAdmin->execute([$orgId, $userId]);

    $linkStmt = $db->prepare('UPDATE trusted_contacts SET contact_user_id = ? WHERE invite_phone = ? AND contact_user_id IS NULL');
    $linkStmt->execute([$userId, $phone]);

    // Same as personal signup: create the org, but hold off on login()
    // until the email is verified.
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60));

    $verifyStmt = $db->prepare(
        'INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
    );
    $verifyStmt->execute([$userId, $tokenHash, $expiresAt]);

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    st_json_error('That organization could not be created. Please try again.', 500);
}

$userStmt = $db->prepare('SELECT id, full_name, username, email FROM users WHERE id = ?');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

st_send_verification_email($user, $token);

$response = [
    'need_verification' => true,
    'message' => 'Almost there — we sent a verification link to ' . $email . '. Verify it to start managing ' . $orgName . '.',
];

if (getenv('SAFARITRAK_DEV_MODE') === '1') {
    $response['dev_verification_link'] = 'verify-email.php?token=' . $token;
}

st_json_ok($response);
