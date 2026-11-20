<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/password-policy.php';
require_once __DIR__ . '/../includes/mailer.php';

st_require_method('POST');

$input = st_input();
$token = trim(st_input_string($input, 'token'));
$newPassword = st_input_string($input, 'password');

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    st_json_error('This reset link is missing its token.', 422);
}

$passwordError = st_password_policy_error($newPassword);
if ($passwordError !== null) {
    st_json_error($passwordError, 422);
}

$tokenHash = hash('sha256', $token);
$db = safaritrak_db();

$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'SELECT pr.id, pr.user_id, u.full_name, u.email
         FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ? AND pr.expires_at > UTC_TIMESTAMP() AND pr.used_at IS NULL
         FOR UPDATE'
    );
    $stmt->execute([$tokenHash]);
    $reset = $stmt->fetch();

    if (!$reset) {
        $db->rollBack();
        st_json_error('This reset link is invalid or has expired. Request a new one.', 400);
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $markUsedStmt = $db->prepare(
        'UPDATE password_resets SET used_at = UTC_TIMESTAMP()
         WHERE id = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()'
    );
    $markUsedStmt->execute([$reset['id']]);
    if ($markUsedStmt->rowCount() !== 1) {
        $db->rollBack();
        st_json_error('This reset link is invalid or has expired. Request a new one.', 400);
    }

    $updateStmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $updateStmt->execute([$passwordHash, $reset['user_id']]);

    $invalidateSessionsStmt = $db->prepare('DELETE FROM sessions WHERE user_id = ?');
    $invalidateSessionsStmt->execute([$reset['user_id']]);
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

st_send_password_changed_email([
    'full_name' => (string) $reset['full_name'],
    'email' => (string) $reset['email'],
]);

st_json_ok(['redirect' => 'login.php']);
