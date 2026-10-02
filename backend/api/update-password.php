<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';

st_start_session();
st_require_method('POST');

if (empty($_SESSION['user_id'])) {
    st_json_error('Unauthorized access.', 401);
}

$userId = (int) $_SESSION['user_id'];
$input = st_input();

$currentPassword = (string) ($input['current_password'] ?? '');
$newPassword = (string) ($input['new_password'] ?? '');
$confirmPassword = (string) ($input['confirm_password'] ?? '');

$errors = [];

if ($currentPassword === '') {
    $errors['current_password'] = 'Enter your current password.';
}

if (strlen($newPassword) < 6) {
    $errors['new_password'] = 'New password must be at least 6 characters.';
}

if ($confirmPassword !== '' && $newPassword !== $confirmPassword) {
    $errors['confirm_password'] = 'Passwords do not match.';
}

if (!empty($errors)) {
    st_json_error('Please fix the errors below.', 422, ['errors' => $errors]);
}

$db = safaritrak_db();

$stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
    st_json_error('Your current password is incorrect.', 422, [
        'errors' => ['current_password' => 'Incorrect current password.'],
    ]);
}

if (password_verify($newPassword, $user['password_hash'])) {
    st_json_error('Choose a password different from your current one.', 422, [
        'errors' => ['new_password' => 'Choose a different password.'],
    ]);
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$updateStmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$updateStmt->execute([$newHash, $userId]);

st_json_ok(['message' => 'Your password has been updated.']);