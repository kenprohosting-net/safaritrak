<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/mailer.php';

st_require_method('POST');

$input = st_input();
$contact = trim($input['contact'] ?? '');

if ($contact === '') {
    st_json_error('Enter your email or phone number.', 422);
}

$db = safaritrak_db();

$stmt = $db->prepare('SELECT id, full_name, email FROM users WHERE email = ? OR phone = ?');
$stmt->execute([$contact, preg_replace('/\D/', '', $contact)]);
$user = $stmt->fetch();

$response = ['message' => 'If that account exists, a reset link has been sent.'];

if ($user) {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + (30 * 60));

    $insertStmt = $db->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
    );
    $insertStmt->execute([$user['id'], $tokenHash, $expiresAt]);

    st_send_reset_password_email($user, $token);

    if (getenv('SAFARITRAK_DEV_MODE') === '1') {
        $response['dev_reset_link'] = 'reset-password.php?token=' . $token;
    }
}

st_json_ok($response);
