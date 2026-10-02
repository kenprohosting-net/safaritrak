<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
st_start_session();
st_require_method('POST');

if (empty($_SESSION['user_id'])) {
    st_json_error('Unauthorized access.', 401);
}

$userId = (int) $_SESSION['user_id'];
$input = st_input();

$fullName = trim((string) ($input['full_name'] ?? ''));
$username = trim((string) ($input['username'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$phoneRaw = trim((string) ($input['phone'] ?? ''));
$homeAddress = trim((string) ($input['home_address'] ?? ''));

$errors = [];

if ($fullName === '' || mb_strlen($fullName) > 120) {
    $errors['full_name'] = 'Enter your full name.';
}

if ($username === '' || !preg_match('/^[a-zA-Z0-9_.]{3,40}$/', $username)) {
    $errors['username'] = 'Username must be 3-40 characters: letters, numbers, "." or "_" only.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
    $errors['email'] = 'Enter a valid email address.';
}

$phoneDigits = st_clean_phone($phoneRaw);
if (!st_valid_kenyan_phone($phoneDigits)) {
    $errors['phone'] = 'Enter a valid phone number, e.g. 0712 345 678.';
} else {
    $phoneDigits = st_normalize_phone($phoneDigits);
}

if (mb_strlen($homeAddress) > 255) {
    $errors['home_address'] = 'Home address is too long.';
}

if (!empty($errors)) {
    st_json_error('Please fix the errors below.', 422, ['errors' => $errors]);
}

$db = safaritrak_db();

// Uniqueness checks against every other account.
$dupStmt = $db->prepare(
    'SELECT username, email, phone FROM users WHERE (username = ? OR email = ? OR phone = ?) AND id != ?'
);
$dupStmt->execute([$username, $email, $phoneDigits, $userId]);
while ($row = $dupStmt->fetch()) {
    if ($row['username'] === $username) {
        $errors['username'] = 'That username is already taken.';
    }
    if ($row['email'] === $email) {
        $errors['email'] = 'That email is already in use.';
    }
    if ($row['phone'] === $phoneDigits) {
        $errors['phone'] = 'That phone number is already in use.';
    }
}

if (!empty($errors)) {
    st_json_error('Please fix the errors below.', 422, ['errors' => $errors]);
}

$updateStmt = $db->prepare(
    'UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, home_address = ? WHERE id = ?'
);
$updateStmt->execute([
    $fullName,
    $username,
    $email,
    $phoneDigits,
    $homeAddress !== '' ? $homeAddress : null,
    $userId,
]);

$_SESSION['username'] = $username;

st_json_ok([
    'message' => 'Your profile has been updated.',
    'user' => [
        'full_name' => $fullName,
        'username' => $username,
        'email' => $email,
        'phone' => $phoneDigits,
        'phone_display' => st_display_phone($phoneDigits),
        'home_address' => $homeAddress,
    ],
]);
