<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/mailer.php';

st_require_method('POST');

$input = st_input();
$email = trim($input['email'] ?? '');

// Always return the same message whether or not the account exists, so
// this endpoint can't be used to probe which emails are registered.
$response = ['message' => 'If that account exists and still needs verifying, a new link has been sent.'];

if ($email !== '') {
    $db = safaritrak_db();
    $stmt = $db->prepare('SELECT id, full_name, username, email FROM users WHERE email = ? AND email_verified_at IS NULL');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60));

        $db->prepare('INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
            ->execute([$user['id'], $tokenHash, $expiresAt]);

        st_send_verification_email($user, $token);

        if (getenv('SAFARITRAK_DEV_MODE') === '1') {
            $response['dev_verification_link'] = 'verify-email.php?token=' . $token;
        }
    }
}

st_json_ok($response);
