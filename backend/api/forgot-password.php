<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rate-limit.php';

st_require_method('POST');

$input = st_input();
$contact = trim(st_input_string($input, 'contact'));

if ($contact === '') {
    st_json_error('Enter your email or phone number.', 422);
}

$db = safaritrak_db();
$retryAfter = st_rate_limit_consume(
    $db,
    'password-reset-ip',
    st_client_address(),
    st_local_development_rate_limit_maximum(5),
    3600
);
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    st_json_error('Too many reset requests. Wait before trying again.', 429);
}

$stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR phone = ?');
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

    if (getenv('SAFARITRAK_DEV_MODE') === '1') {
        $response['dev_reset_link'] = 'reset-password.php?token=' . $token;
    }
}

st_json_ok($response);
