<?php

require_once __DIR__ . '/backend/includes/session.php';
require_once __DIR__ . '/backend/includes/response.php';
require_once __DIR__ . '/backend/config/database.php';

st_start_session();
st_require_method('POST');

if (empty($_SESSION['user_id'])) {
    st_json_error('Unauthorized access.', 401);
}

$userId = (int) $_SESSION['user_id'];
$input = st_input();
$password = (string) ($input['password'] ?? '');

if ($password === '') {
    st_json_error('Enter your password to confirm deletion.', 422, [
        'errors' => ['password' => 'Enter your password to confirm.'],
    ]);
}

$db = safaritrak_db();

$stmt = $db->prepare('SELECT password_hash, avatar_path FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    st_json_error('Incorrect password.', 422, ['errors' => ['password' => 'Incorrect password.']]);
}

$deleteStmt = $db->prepare('DELETE FROM users WHERE id = ?');
$deleteStmt->execute([$userId]);

if (!empty($user['avatar_path'])) {
    $avatarFile = __DIR__ . '/' . ltrim($user['avatar_path'], '/');
    if (is_file($avatarFile)) {
        @unlink($avatarFile);
    }
}

session_unset();
session_destroy();

st_json_ok(['redirect' => 'login.php']);