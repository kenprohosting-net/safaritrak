<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/rate-limit.php';
require_once __DIR__ . '/../includes/password-policy.php';
require_once __DIR__ . '/../includes/consent.php';

st_require_method('POST');

$input = st_input();

$fullName = trim(st_input_string($input, 'full_name'));
$username = trim(st_input_string($input, 'username'));
$email = trim(st_input_string($input, 'email'));
$phone = trim(st_input_string($input, 'phone'));
$password = st_input_string($input, 'password');
$contactInviteToken = trim(st_input_string($input, 'contact_invite_token'));
$groupInviteToken = trim(st_input_string($input, 'group_invite_token'));
$organizationInviteToken = trim(st_input_string($input, 'org_invite_token'));
$terms = !empty($input['terms']);

$errors = [];

if ($fullName === '') {
    $errors['full_name'] = 'Enter your full name.';
}

if (!preg_match('/^[a-zA-Z0-9_.]{3,40}$/', $username)) {
    $errors['username'] = 'Username must be 3 to 40 characters, letters, numbers, dots or underscores only.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
}

$phoneDigits = preg_replace('/\D/', '', $phone);
if (strlen($phoneDigits) < 9) {
    $errors['phone'] = 'Enter a valid phone number.';
}

$passwordError = st_password_policy_error($password);
if ($passwordError !== null) {
    $errors['password'] = $passwordError;
}

if (!$terms) {
    $errors['terms'] = 'You need to accept the Terms and Privacy Policy.';
}
if ($contactInviteToken !== '' && !preg_match('/^[a-f0-9]{64}$/', $contactInviteToken)) {
    $errors['contact_invite_token'] = 'This invitation link is invalid or expired.';
}
if ($groupInviteToken !== '' && !preg_match('/^[a-f0-9]{64}$/', $groupInviteToken)) {
    $errors['group_invite_token'] = 'This group invitation link is invalid or expired.';
}
if ($organizationInviteToken !== '' && !preg_match('/^[a-f0-9]{64}$/', $organizationInviteToken)) {
    $errors['org_invite_token'] = 'This school invitation link is invalid or expired.';
}
if ($groupInviteToken !== '' && $organizationInviteToken !== '') {
    $errors['group_invite_token'] = 'Continue with one invitation at a time.';
}

if (!empty($errors)) {
    st_json_error('Please fix the highlighted fields.', 422, ['errors' => $errors]);
}

$db = safaritrak_db();
$retryAfter = st_rate_limit_consume(
    $db,
    'register-ip',
    st_client_address(),
    st_local_development_rate_limit_maximum(5),
    3600
);
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    st_json_error('Too many account requests. Wait before trying again.', 429);
}

$checkStmt = $db->prepare('SELECT id, username, email, phone FROM users WHERE username = ? OR email = ? OR phone = ?');
$checkStmt->execute([$username, $email, $phoneDigits]);
$existing = $checkStmt->fetch();

if ($existing) {
    if (strcasecmp($existing['username'], $username) === 0) {
        st_json_error('That username is already taken.', 409, ['errors' => ['username' => 'That username is already taken.']]);
    }
    if (strcasecmp($existing['email'], $email) === 0) {
        st_json_error('An account with that email already exists.', 409, ['errors' => ['email' => 'An account with that email already exists.']]);
    }
    st_json_error('An account with that phone number already exists.', 409, ['errors' => ['phone' => 'An account with that phone number already exists.']]);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$db->beginTransaction();
try {
    $insertStmt = $db->prepare(
        'INSERT INTO users (full_name, username, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)'
    );
    $insertStmt->execute([$fullName, $username, $email, $phoneDigits, $passwordHash]);
    $userId = (int) $db->lastInsertId();

    st_record_consent($db, $userId);

    if ($contactInviteToken !== '') {
        $inviteStmt = $db->prepare(
            'SELECT id, owner_id, invite_name, relationship, recipient_email
             FROM trusted_contact_invites
             WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP()
               AND claimed_user_id IS NULL AND accepted_at IS NULL
             FOR UPDATE'
        );
        $inviteStmt->execute([hash('sha256', $contactInviteToken)]);
        $invite = $inviteStmt->fetch(PDO::FETCH_ASSOC);
        if (!$invite) {
            $db->rollBack();
            st_json_error('This invitation link has expired or has already been used.', 422);
        }
        if ($invite['recipient_email'] !== null && strcasecmp((string) $invite['recipient_email'], $email) !== 0) {
            $db->rollBack();
            st_json_error('Create your account with the email address this invitation was sent to.', 403);
        }
        if ((int) $invite['owner_id'] === $userId) {
            $db->rollBack();
            st_json_error('You cannot accept your own invitation.', 422);
        }
        $db->prepare(
            'INSERT INTO trusted_contacts
                (owner_id, contact_user_id, invite_name, invite_phone, relationship, status)
             VALUES (?, ?, ?, ?, ?, "pending")'
        )->execute([
            (int) $invite['owner_id'],
            $userId,
            $invite['invite_name'],
            $phoneDigits,
            $invite['relationship'],
        ]);
        $db->prepare('UPDATE trusted_contact_invites SET claimed_user_id = ? WHERE id = ?')
            ->execute([$userId, $invite['id']]);
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60));

    $verifyStmt = $db->prepare(
        'INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
    );
    $verifyStmt->execute([$userId, $tokenHash, $expiresAt]);
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

$userStmt = $db->prepare('SELECT id, full_name, username, email FROM users WHERE id = ?');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

$continuation = $groupInviteToken !== ''
    ? ['group_invite' => $groupInviteToken]
    : ($organizationInviteToken !== '' ? ['org_invite' => $organizationInviteToken] : []);
$mailSent = st_send_verification_email($user, $token, $continuation);
$devMode = getenv('SAFARITRAK_DEV_MODE') === '1';

$response = [
    'need_verification' => true,
    'message' => $devMode
        ? 'Your account was created. Local development mode is enabled; use the verification link below to activate it.'
        : ($mailSent
            ? 'Almost there — we sent a verification link to ' . $email . '. Check your inbox to activate your account.'
            : 'Your account was created, but we could not send the verification email. Please use the resend option on the login page or contact support.'),
];

if ($devMode) {
    $response['dev_verification_link'] = 'verify-email.php?' . http_build_query(
        ['token' => $token] + $continuation,
        '',
        '&',
        PHP_QUERY_RFC3986
    );
}

st_json_ok($response);
