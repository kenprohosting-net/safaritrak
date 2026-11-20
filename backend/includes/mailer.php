<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/app-url.php';
require_once __DIR__ . '/rate-limit.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function st_gmail_configuration(): array|false|null {
    $names = [
        'GOOGLE_CLIENT_ID' => 'client_id',
        'GOOGLE_CLIENT_SECRET' => 'client_secret',
        'GOOGLE_REDIRECT_URI' => 'redirect_uri',
        'GOOGLE_REFRESH_TOKEN' => 'refresh_token',
        'GMAIL_SENDER_EMAIL' => 'sender_email',
    ];
    $values = [];
    foreach ($names as $environmentName => $key) {
        $values[$key] = trim(getenv($environmentName) ?: '');
    }

    if (count(array_filter($values, static fn (string $value): bool => $value !== '')) === 0) {
        return null;
    }

    $missing = [];
    foreach ($names as $environmentName => $key) {
        if ($values[$key] === '') {
            $missing[] = $environmentName;
        }
    }
    if ($missing !== []) {
        error_log('SafariTrak Gmail API error: incomplete OAuth configuration; missing ' . implode(', ', $missing) . '.');
        return false;
    }
    if (!filter_var($values['sender_email'], FILTER_VALIDATE_EMAIL)) {
        error_log('SafariTrak Gmail API error: GMAIL_SENDER_EMAIL is not a valid email address.');
        return false;
    }

    return $values;
}

function st_gmail_build_raw_message(
    string $fromEmail,
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody
): string {
    $mail = new PHPMailer(true);
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->setFrom($fromEmail, 'SafariTrak');
    $mail->addAddress($toEmail, $toName);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;
    $mail->AltBody = $textBody !== '' ? $textBody : trim(strip_tags($htmlBody));
    if (!$mail->preSend()) {
        throw new RuntimeException('Could not build the transactional email message.');
    }

    return rtrim(strtr(base64_encode($mail->getSentMIMEMessage()), '+/', '-_'), '=');
}

function st_gmail_post_form(string $url, array $fields): ?array {
    if (!function_exists('curl_init')) {
        error_log('SafariTrak Gmail API error: the PHP cURL extension is required.');
        return null;
    }

    $handle = curl_init($url);
    if ($handle === false) {
        error_log('SafariTrak Gmail API error: could not initialize an HTTPS request.');
        return null;
    }
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $errorNumber = curl_errno($handle);
    $failed = $response === false;
    curl_close($handle);
    if ($failed) {
        error_log('SafariTrak Gmail API error: OAuth HTTPS request failed (cURL ' . $errorNumber . ').');
        return null;
    }

    return ['status' => $status, 'body' => (string) $response];
}

function st_gmail_access_token(array $config): ?string {
    static $cachedToken = null;
    static $cachedUntil = 0;
    if (is_string($cachedToken) && $cachedUntil > time() + 30) {
        return $cachedToken;
    }

    $response = st_gmail_post_form('https://oauth2.googleapis.com/token', [
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'refresh_token' => $config['refresh_token'],
        'grant_type' => 'refresh_token',
    ]);
    if ($response === null) {
        return null;
    }

    $payload = json_decode($response['body'], true);
    if ($response['status'] !== 200 || !is_array($payload)
        || !isset($payload['access_token'], $payload['expires_in'])
        || !is_string($payload['access_token']) || (int) $payload['expires_in'] < 1) {
        $errorCode = is_array($payload) && is_string($payload['error'] ?? null)
            ? preg_replace('/[^a-zA-Z0-9_-]/', '', $payload['error'])
            : 'invalid_response';
        error_log('SafariTrak Gmail API error: OAuth token refresh failed (HTTP '
            . $response['status'] . ', ' . ($errorCode ?: 'invalid_response') . ').');
        return null;
    }

    $cachedToken = $payload['access_token'];
    $cachedUntil = time() + (int) $payload['expires_in'];
    return $cachedToken;
}

function st_gmail_send_message(array $config, string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): bool {
    try {
        $rawMessage = st_gmail_build_raw_message(
            $config['sender_email'],
            $toEmail,
            $toName,
            $subject,
            $htmlBody,
            $textBody
        );
    } catch (Throwable $exception) {
        error_log('SafariTrak Gmail API error: could not prepare a transactional email message.');
        return false;
    }

    $accessToken = st_gmail_access_token($config);
    if ($accessToken === null) {
        return false;
    }
    if (!function_exists('curl_init')) {
        error_log('SafariTrak Gmail API error: the PHP cURL extension is required.');
        return false;
    }

    $handle = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    if ($handle === false) {
        error_log('SafariTrak Gmail API error: could not initialize an HTTPS request.');
        return false;
    }
    try {
        $requestBody = json_encode(['raw' => $rawMessage], JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        curl_close($handle);
        error_log('SafariTrak Gmail API error: message encoding failed.');
        return false;
    }
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $requestBody,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $responseBody = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $errorNumber = curl_errno($handle);
    $failed = $responseBody === false;
    curl_close($handle);
    if ($failed) {
        error_log('SafariTrak Gmail API error: message send request failed (cURL ' . $errorNumber . ').');
        return false;
    }
    if ($status < 200 || $status >= 300) {
        $payload = json_decode((string) $responseBody, true);
        $reason = is_array($payload) && is_string($payload['error']['status'] ?? null)
            ? preg_replace('/[^a-zA-Z0-9_-]/', '', $payload['error']['status'])
            : 'api_error';
        error_log('SafariTrak Gmail API error: message send failed (HTTP ' . $status . ', ' . ($reason ?: 'api_error') . ').');
        return false;
    }

    return true;
}

function st_smtp_send_message(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): bool {
    $username = trim(getenv('SAFARITRAK_MAIL_USERNAME') ?: '');
    $password = getenv('SAFARITRAK_MAIL_PASSWORD') ?: '';
    if ($username === '' || $password === '') {
        error_log('SafariTrak mail error: SMTP username and password are not configured.');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = getenv('SAFARITRAK_MAIL_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->Port = (int) (getenv('SAFARITRAK_MAIL_PORT') ?: 587);
        $mail->SMTPSecure = $mail->Port === 465
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom(
            getenv('SAFARITRAK_MAIL_FROM') ?: $mail->Username,
            getenv('SAFARITRAK_MAIL_FROM_NAME') ?: 'SafariTrak'
        );
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : trim(strip_tags($htmlBody));
        $mail->send();
        return true;
    } catch (Exception $exception) {
        error_log('SafariTrak SMTP mail error: message delivery failed.');
        return false;
    }
}

function st_send_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('SafariTrak mail error: recipient email address is invalid.');
        return false;
    }

    $config = st_gmail_configuration();
    if ($config === false) {
        return false;
    }
    $sender = is_array($config)
        ? $config['sender_email']
        : (getenv('SAFARITRAK_MAIL_FROM') ?: getenv('SAFARITRAK_MAIL_USERNAME') ?: 'smtp-sender');
    try {
        $db = safaritrak_db();
        $globalRetry = st_rate_limit_consume($db, 'mail-global', $sender, 400, 86400);
        $recipientRetry = st_rate_limit_consume($db, 'mail-recipient', strtolower($toEmail), 5, 3600);
    } catch (Throwable $exception) {
        error_log('SafariTrak mail error: delivery rate-limit check failed.');
        return false;
    }
    if ($globalRetry > 0 || $recipientRetry > 0) {
        error_log('SafariTrak mail error: transactional email rate limit reached.');
        return false;
    }

    if (is_array($config)) {
        return st_gmail_send_message($config, $toEmail, $toName, $subject, $htmlBody, $textBody);
    }

    return st_smtp_send_message($toEmail, $toName, $subject, $htmlBody, $textBody);
}

function st_email_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function st_email_layout(string $preheader, string $content): string {
    return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light only"><title>SafariTrak</title></head>'
        . '<body style="margin:0;padding:24px 12px;background:#f3f4f8;font-family:Arial,Helvetica,sans-serif;color:#0F172A">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . st_email_escape($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#fff;border:1px solid #E2E8F0;border-radius:18px;overflow:hidden">'
        . '<tr><td style="padding:24px 28px;background:#0C101C;color:#fff">'
        . '<div style="font-size:20px;font-weight:700;letter-spacing:.2px">SafariTrak</div>'
        . '<div style="margin-top:5px;color:#94A3B8;font-size:12px">Your journey. Safer together.</div>'
        . '</td></tr><tr><td style="padding:30px 28px;line-height:1.6;font-size:15px">' . $content . '</td></tr>'
        . '<tr><td style="padding:18px 28px;border-top:1px solid #E2E8F0;color:#64748B;font-size:12px;line-height:1.5">'
        . 'This is a transactional message from SafariTrak. If you did not expect it, you can ignore it.'
        . '</td></tr></table></td></tr></table></body></html>';
}

function st_email_button(string $label, string $url): string {
    return '<p style="margin:26px 0;text-align:center"><a href="' . st_email_escape($url)
        . '" style="display:inline-block;padding:13px 22px;border-radius:10px;background:#F59E0B;color:#111827;font-weight:700;text-decoration:none">'
        . st_email_escape($label) . '</a></p>';
}

function st_send_verification_email(array $user, string $token, array $continuation = []): bool {
    $query = ['token' => $token];
    foreach (['group_invite', 'org_invite'] as $key) {
        if (isset($continuation[$key]) && is_string($continuation[$key])
            && preg_match('/^[a-f0-9]{64}$/', $continuation[$key])) {
            $query[$key] = $continuation[$key];
            break;
        }
    }
    $link = st_app_url() . '/verify-email.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $content = '<h1 style="margin:0 0 16px;font-size:24px;color:#0F172A">Confirm your email</h1>'
        . '<p>Hi ' . st_email_escape((string) $user['full_name']) . ',</p>'
        . '<p>Thanks for signing up for SafariTrak. Confirm your email address to activate your account.</p>'
        . st_email_button('Verify my email', $link)
        . '<p style="color:#6b6877;font-size:13px">This link expires in 24 hours. If you did not create an account, you can ignore this email.</p>'
        . '<p style="color:#6b6877;font-size:12px;word-break:break-all">Or paste this link into your browser:<br>' . st_email_escape($link) . '</p>';

    return st_send_mail(
        (string) $user['email'],
        (string) $user['full_name'],
        'Verify your SafariTrak account',
        st_email_layout('Confirm your email address to activate your SafariTrak account.', $content),
        "Hi {$user['full_name']},\n\nVerify your SafariTrak account using this link:\n{$link}\n\nThis link expires in 24 hours."
    );
}

function st_send_welcome_email(array $user): bool {
    $link = st_app_url() . '/index.php';
    $content = '<h1 style="margin:0 0 16px;font-size:24px;color:#0F172A">Welcome to SafariTrak, '
        . st_email_escape((string) $user['full_name']) . '!</h1>'
        . '<p>Your email is verified and your account is ready.</p>'
        . '<p>Add a trusted contact and start a journey when you are ready.</p>'
        . st_email_button('Open SafariTrak', $link);
    return st_send_mail(
        (string) $user['email'],
        (string) $user['full_name'],
        'Welcome to SafariTrak',
        st_email_layout('Your SafariTrak account is ready.', $content),
        "Welcome to SafariTrak, {$user['full_name']}!\n\nYour account is ready. Open SafariTrak: {$link}"
    );
}

function st_send_password_reset_email(array $user, string $token): bool {
    $link = st_app_url() . '/reset-password.php?' . http_build_query(['token' => $token], '', '&', PHP_QUERY_RFC3986);
    $content = '<h1 style="margin:0 0 16px;font-size:24px;color:#0F172A">Reset your password</h1>'
        . '<p>Hi ' . st_email_escape((string) $user['full_name']) . ',</p>'
        . '<p>We received a request to reset your SafariTrak password. This link expires in 30 minutes and can be used once.</p>'
        . st_email_button('Reset password', $link)
        . '<p>If you did not request a reset, ignore this message. Your password will not change.</p>'
        . '<p style="color:#6b6877;font-size:12px;word-break:break-all">Reset link:<br>' . st_email_escape($link) . '</p>';
    return st_send_mail(
        (string) $user['email'],
        (string) $user['full_name'],
        'Reset your SafariTrak password',
        st_email_layout('Use the secure link to reset your SafariTrak password.', $content),
        "Hi {$user['full_name']},\n\nReset your SafariTrak password using this one-time link (expires in 30 minutes):\n{$link}\n\nIf you did not request this, ignore this email."
    );
}

function st_send_password_changed_email(array $user): bool {
    $link = st_app_url() . '/login.php';
    $content = '<h1 style="margin:0 0 16px;font-size:24px;color:#0F172A">Your password was changed</h1>'
        . '<p>Hi ' . st_email_escape((string) $user['full_name']) . ', your SafariTrak password was just changed.</p>'
        . '<p>If you did not make this change, reset your password immediately and contact support.</p>'
        . st_email_button('Go to SafariTrak', $link);
    return st_send_mail(
        (string) $user['email'],
        (string) $user['full_name'],
        'Your SafariTrak password was changed',
        st_email_layout('Your SafariTrak account password was changed.', $content),
        "Hi {$user['full_name']},\n\nYour SafariTrak password was changed. If you did not make this change, reset it immediately: {$link}"
    );
}

function st_send_trusted_contact_invitation_email(
    string $recipientEmail,
    string $recipientName,
    string $inviterName,
    string $inviteUrl
): bool {
    $content = '<h1 style="margin:0 0 16px;font-size:24px;color:#0F172A">Trusted-contact invitation</h1>'
        . '<p>' . st_email_escape($inviterName) . ' invited you to connect as a trusted contact on SafariTrak.</p>'
        . '<p>A trusted-contact relationship is created only after you sign up and accept. Location sharing remains user-controlled.</p>'
        . st_email_button('Review invitation', $inviteUrl)
        . '<p>This invitation expires in seven days.</p>';
    return st_send_mail(
        $recipientEmail,
        $recipientName,
        'SafariTrak trusted-contact invitation',
        st_email_layout($inviterName . ' invited you to connect on SafariTrak.', $content),
        "{$inviterName} invited you to connect as a trusted contact on SafariTrak.\nReview the invitation: {$inviteUrl}\n\nIt expires in seven days. You must sign up and accept."
    );
}

function st_send_group_invitation_email(
    string $recipientEmail,
    string $groupName,
    string $inviterName,
    string $inviteUrl
): bool {
    $content = '<h1 style="margin:0 0 16px;font-size:24px;color:#0F172A">You are invited to a SafariTrak group</h1>'
        . '<p>' . st_email_escape($inviterName) . ' invited you to join <strong>' . st_email_escape($groupName) . '</strong>.</p>'
        . '<p>Review the invitation before joining. Group membership does not automatically share your location.</p>'
        . st_email_button('Review group invitation', $inviteUrl)
        . '<p>This invitation expires in seven days.</p>';
    return st_send_mail(
        $recipientEmail,
        '',
        'Invitation to join ' . $groupName . ' on SafariTrak',
        st_email_layout('You have a SafariTrak group invitation.', $content),
        "{$inviterName} invited you to join {$groupName} on SafariTrak.\nReview the invitation: {$inviteUrl}\n\nIt expires in seven days. Group membership does not automatically share your location."
    );
}
