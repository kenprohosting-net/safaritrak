<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/rate-limit.php';
require_once __DIR__ . '/../../includes/mailer.php';

st_start_session();
st_require_method('POST');
$ownerId = st_require_login();
$input = st_input();
$name = trim(st_input_string($input, 'name'));
$email = trim(st_input_string($input, 'email'));
$relationship = trim(st_input_string($input, 'relationship'));

if ($name === '' || mb_strlen($name) > 120) {
    st_json_error('Enter their name (up to 120 characters).', 422, ['errors' => ['name' => 'Enter a valid name.']]);
}
if ($relationship !== '' && mb_strlen($relationship) > 60) {
    st_json_error('Relationship must be 60 characters or fewer.', 422);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    st_json_error('Enter a valid email address or leave it blank.', 422, ['errors' => ['email' => 'Enter a valid email.']]);
}

$db = safaritrak_db();
$retryAfter = st_rate_limit_consume($db, 'contact-invite-user', (string) $ownerId, 10, 3600);
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    st_json_error('Too many invitations. Wait before creating another link.', 429);
}

$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$expiresAt = gmdate('Y-m-d H:i:s', time() + (7 * 86400));
$insert = $db->prepare(
    'INSERT INTO trusted_contact_invites
        (owner_id, token_hash, invite_name, relationship, recipient_email, expires_at)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$insert->execute([$ownerId, $tokenHash, $name, $relationship ?: null, $email ?: null, $expiresAt]);

$inviteUrl = st_app_url() . '/contact-invite.php?token=' . urlencode($token);
$emailSent = null;
if ($email !== '') {
    $ownerStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
    $ownerStmt->execute([$ownerId]);
    $ownerName = (string) $ownerStmt->fetchColumn();
    $safeOwner = htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8');
    $html = '<p>' . $safeOwner . ' invited you to connect as a trusted contact on SafariTrak.</p>'
        . '<p><a href="' . $safeUrl . '">Review the invitation</a></p>'
        . '<p>This invitation expires in seven days. A trusted-contact relationship is created only after you sign up and accept.</p>';
    $emailSent = st_send_mail(
        $email,
        $name,
        'SafariTrak trusted-contact invitation',
        $html,
        $ownerName . ' invited you to connect as a trusted contact: ' . $inviteUrl
    );
}

st_json_ok([
    'invite_url' => $inviteUrl,
    'expires_at' => $expiresAt,
    'email_sent' => $emailSent,
]);
