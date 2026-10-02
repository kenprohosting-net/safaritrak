<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/mailer.php';

st_require_method('POST');

$input = st_input();

$fullName = trim($input['full_name'] ?? '');
$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$password = (string) ($input['password'] ?? '');
$terms = !empty($input['terms']);

$errors = [];

if ($fullName === '') {
    $errors['full_name'] = 'Enter your full name.';
}

if (!preg_match('/^[a-zA-Z0-9_.]{3,40}$/', $username)) {
    $errors['username'] = 'Username must be 3 to 40 characters, letters, numbers, dots or underscores only.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
}

$phoneDigits = preg_replace('/\D/', '', $phone);
if (strlen($phoneDigits) < 9) {
    $errors['phone'] = 'Enter a valid phone number.';
}

if (strlen($password) < 6) {
    $errors['password'] = 'Password must be at least 6 characters.';
}

if (!$terms) {
    $errors['terms'] = 'You need to accept the Terms and Privacy Policy.';
}

if (!empty($errors)) {
    st_json_error('Please fix the highlighted fields.', 422, ['errors' => $errors]);
}

$db = safaritrak_db();

$checkStmt = $db->prepare('SELECT id, username, email, phone FROM users WHERE username = ? OR email = ? OR phone = ?');
$checkStmt->execute([$username, $email, $phoneDigits]);
$existing = $checkStmt->fetch();

if ($existing) {
    if (strcasecmp($existing['username'], $username) === 0) {
        st_json_error('That username is already taken.', 409, ['errors' => ['username' => 'That username is already taken.']]);
    }
    if (strcasecmp($existing['email'], $email) === 0) {
        st_json_error('An account with that email already exists.', 409, ['errors' => ['email' => 'An account with that email already exists.']]);
    }
    st_json_error('An account with that phone number already exists.', 409, ['errors' => ['phone' => 'An account with that phone number already exists.']]);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$insertStmt = $db->prepare(
    'INSERT INTO users (full_name, username, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)'
);
$insertStmt->execute([$fullName, $username, $email, $phoneDigits, $passwordHash]);

$userId = (int) $db->lastInsertId();

$linkStmt = $db->prepare(
    'UPDATE trusted_contacts SET contact_user_id = ? WHERE invite_phone = ? AND contact_user_id IS NULL'
);
$linkStmt->execute([$userId, $phoneDigits]);

// Every account now needs a verified email before it can log in, so we do
// NOT call st_login_user() here. Instead: generate a token, mirroring the
// password_resets pattern, and mail the link.
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60));

$verifyStmt = $db->prepare(
    'INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
);
$verifyStmt->execute([$userId, $tokenHash, $expiresAt]);

$userStmt = $db->prepare('SELECT id, full_name, username, email FROM users WHERE id = ?');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

st_send_verification_email($user, $token);

$response = [
    'need_verification' => true,
    'message' => 'Almost there — we sent a verification link to ' . $email . '. Check your inbox to activate your account.',
];

if (getenv('SAFARITRAK_DEV_MODE') === '1') {
    $response['dev_verification_link'] = 'verify-email.php?token=' . $token;
}

st_json_ok($response);
