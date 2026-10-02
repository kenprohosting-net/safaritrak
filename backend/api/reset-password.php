<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';

st_require_method('POST');

$input = st_input();
$token = trim($input['token'] ?? '');
$newPassword = (string) ($input['password'] ?? '');

if ($token === '') {
    st_json_error('This reset link is missing its token.', 422);
}

if (strlen($newPassword) < 6) {
    st_json_error('Password must be at least 6 characters.', 422);
}

$tokenHash = hash('sha256', $token);
$db = safaritrak_db();

$stmt = $db->prepare(
    'SELECT id, user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW() AND used_at IS NULL'
);
$stmt->execute([$tokenHash]);
$reset = $stmt->fetch();

if (!$reset) {
    st_json_error('This reset link is invalid or has expired. Request a new one.', 400);
}

$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

$db->beginTransaction();

$updateStmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$updateStmt->execute([$passwordHash, $reset['user_id']]);

$markUsedStmt = $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
$markUsedStmt->execute([$reset['id']]);

$invalidateSessionsStmt = $db->prepare('DELETE FROM sessions WHERE user_id = ?');
$invalidateSessionsStmt->execute([$reset['user_id']]);

$db->commit();

st_json_ok(['redirect' => 'login.php']);
