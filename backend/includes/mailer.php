<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/app-url.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sends an email via Gmail SMTP. Never throws — a mail failure should not
 * block the request that triggered it (e.g. account creation). Returns
 * false and logs the error instead.
 */
function st_send_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
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

        $fromEmail = getenv('SAFARITRAK_MAIL_FROM') ?: $mail->Username;
        $fromName = getenv('SAFARITRAK_MAIL_FROM_NAME') ?: 'SafariTrak';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('SafariTrak mail error: ' . $mail->ErrorInfo);
        return false;
    }
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
    $escapedLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

    $html = '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto">
            <h2 style="color:#1b6e4a">Confirm your email</h2>
            <p>Hi ' . htmlspecialchars($user['full_name']) . ',</p>
            <p>Thanks for signing up for SafariTrak. Please confirm your email address to activate your account.</p>
            <p style="text-align:center;margin:28px 0">
                <a href="' . $escapedLink . '" style="background:#1b6e4a;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block">Verify my email</a>
            </p>
            <p style="color:#666;font-size:13px">Or paste this link into your browser:<br>' . $escapedLink . '</p>
            <p style="color:#666;font-size:13px">This link expires in 24 hours. If you did not create a SafariTrak account, you can ignore this email.</p>
        </div>
    ';

    return st_send_mail(
        $user['email'],
        $user['full_name'],
        'Verify your SafariTrak account',
        $html,
        "Open this link to verify your SafariTrak account:\n" . $link
    );
}

function st_send_welcome_email(array $user): bool {
    $html = '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto">
            <h2 style="color:#1b6e4a">Welcome to SafariTrak, ' . htmlspecialchars($user['full_name']) . '!</h2>
            <p>Your email is verified and your account is ready to go.</p>
            <p>Next step: add a trusted contact and start your first journey so someone always knows where you are.</p>
            <p style="text-align:center;margin:28px 0">
                <a href="' . st_app_url() . '/index.php" style="background:#1b6e4a;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block">Open SafariTrak</a>
            </p>
        </div>
    ';

    return st_send_mail($user['email'], $user['full_name'], 'Welcome to SafariTrak', $html);
}
