<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/password-policy.php';

st_start_session();
st_require_method('POST');

if (empty($_SESSION['user_id'])) {
    st_json_error('Unauthorized access.', 401);
}

$userId = (int) $_SESSION['user_id'];
$currentSessionId = session_id();
session_write_close();
$input = st_input();

$currentPassword = st_input_string($input, 'current_password');
$newPassword = st_input_string($input, 'new_password');
$confirmPassword = st_input_string($input, 'confirm_password');

$errors = [];

if ($currentPassword === '') {
    $errors['current_password'] = 'Enter your current password.';
}

$passwordError = st_password_policy_error($newPassword);
if ($passwordError !== null) {
    $errors['new_password'] = $passwordError;
}

if ($newPassword !== $confirmPassword) {
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
$invalidateOtherSessionsStmt = $db->prepare(
    'DELETE FROM sessions WHERE user_id = ? AND id <> ?'
);
$invalidateOtherSessionsStmt->execute([$userId, $currentSessionId]);

st_json_ok(['message' => 'Your password has been updated.']);