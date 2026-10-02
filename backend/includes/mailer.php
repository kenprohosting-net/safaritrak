<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function st_send_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = '', int $priority = 3): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = getenv('SAFARITRAK_MAIL_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SAFARITRAK_MAIL_USERNAME') ?: '';
        $mail->Password = getenv('SAFARITRAK_MAIL_PASSWORD') ?: '';
        $mail->Port = (int) (getenv('SAFARITRAK_MAIL_PORT') ?: 587);
        $mail->SMTPSecure = $mail->Port === 465
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Priority = max(1, min(5, $priority));

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

function st_app_url(): string {
    $base = getenv('SAFARITRAK_APP_URL');
    if ($base) {
        return rtrim($base, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $apiRoot = '/backend/api/';
    $apiRootPosition = strpos($scriptPath, $apiRoot);
    $appPath = $apiRootPosition === false
        ? rtrim(str_replace('\\', '/', dirname($scriptPath)), '/')
        : substr($scriptPath, 0, $apiRootPosition);

    return $scheme . '://' . $host . $appPath;
}

function st_mail_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function st_send_verification_email(array $user, string $token): bool {
    $link = st_app_url() . '/verify-email.php?token=' . urlencode($token);
    $safeLink = st_mail_escape($link);
    $safeName = st_mail_escape((string) ($user['full_name'] ?? ''));

    $html = '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto">
            <h2 style="color:#1b6e4a">Confirm your email</h2>
            <p>Hi ' . $safeName . ',</p>
            <p>Thanks for signing up for SafariTrak. Please confirm your email address to activate your account.</p>
            <p style="text-align:center;margin:28px 0">
                <a href="' . $safeLink . '" style="background:#1b6e4a;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block">Verify my email</a>
            </p>
            <p style="color:#666;font-size:13px">Or paste this link into your browser:<br>' . $safeLink . '</p>
            <p style="color:#666;font-size:13px">This link expires in 24 hours. If you did not create a SafariTrak account, you can ignore this email.</p>
        </div>
    ';

    return st_send_mail((string) ($user['email'] ?? ''), (string) ($user['full_name'] ?? ''), 'Verify your SafariTrak account', $html);
}

function st_send_reset_password_email(array $user, string $token): bool {
    $link = st_app_url() . '/reset-password.php?token=' . urlencode($token);
    $safeLink = st_mail_escape($link);
    $safeName = st_mail_escape((string) ($user['full_name'] ?? ''));

    $html = '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto">
            <h2 style="color:#1b6e4a">Reset your password</h2>
            <p>Hi ' . $safeName . ',</p>
            <p>We received a request to reset your SafariTrak password.</p>
            <p style="text-align:center;margin:28px 0">
                <a href="' . $safeLink . '" style="background:#1b6e4a;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block">Reset my password</a>
            </p>
            <p style="color:#666;font-size:13px">Or paste this link into your browser:<br>' . $safeLink . '</p>
            <p style="color:#666;font-size:13px">This link expires in 30 minutes. If you did not request a reset, you can ignore this email.</p>
        </div>
    ';

    return st_send_mail((string) ($user['email'] ?? ''), (string) ($user['full_name'] ?? ''), 'Reset your SafariTrak password', $html);
}

function st_send_welcome_email(array $user): bool {
    $link = st_mail_escape(st_app_url() . '/index.php');
    $safeName = st_mail_escape((string) ($user['full_name'] ?? ''));
    $html = '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto">
            <h2 style="color:#1b6e4a">Welcome to SafariTrak, ' . $safeName . '!</h2>
            <p>Your email is verified and your account is ready to go.</p>
            <p>Next step: add a trusted contact and start your first journey so someone always knows where you are.</p>
            <p style="text-align:center;margin:28px 0">
                <a href="' . $link . '" style="background:#1b6e4a;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block">Open SafariTrak</a>
            </p>
        </div>
    ';

    return st_send_mail((string) ($user['email'] ?? ''), (string) ($user['full_name'] ?? ''), 'Welcome to SafariTrak', $html);
}

function st_send_group_invite_email(string $inviterName, string $targetEmail, string $targetName, string $groupName, string $token): bool {
    $link = st_mail_escape(st_app_url() . '/index.php?invite_token=' . urlencode($token));
    $safeInviterName = st_mail_escape($inviterName);
    $safeTargetName = st_mail_escape($targetName);
    $safeGroupName = st_mail_escape($groupName);

    $html = '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto">
            <h2 style="color:#1b6e4a">Group journey invitation</h2>
            <p>Hi ' . $safeTargetName . ',</p>
            <p>' . $safeInviterName . ' invited you to join the SafariTrak group journey <strong>' . $safeGroupName . '</strong>.</p>
            <p style="text-align:center;margin:28px 0">
                <a href="' . $link . '" style="background:#1b6e4a;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block">View invitation</a>
            </p>
            <p style="color:#666;font-size:13px">Or paste this link into your browser:<br>' . $link . '</p>
        </div>
    ';

    return st_send_mail($targetEmail, $targetName, 'SafariTrak group journey invitation', $html);
}

function st_send_sos_alert_email(array $contact, array $traveler, string $journeyId): bool {
    $contactEmail = (string) ($contact['email'] ?? '');
    $contactName = (string) ($contact['full_name'] ?? $contact['name'] ?? 'Trusted contact');
    $travelerName = (string) ($traveler['full_name'] ?? $traveler['name'] ?? 'A SafariTrak traveler');
    $link = st_mail_escape(st_app_url() . '/view-shared.php?id=' . urlencode($journeyId));
    $safeContactName = st_mail_escape($contactName);
    $safeTravelerName = st_mail_escape($travelerName);

    $html = '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;border-top:6px solid #b42318;padding-top:16px">
            <h2 style="color:#b42318">Emergency SOS alert</h2>
            <p>Hi ' . $safeContactName . ',</p>
            <p><strong>' . $safeTravelerName . ' has triggered an SOS alert and may need immediate help.</strong></p>
            <p>Open the live tracking view now to check their location and journey status.</p>
            <p style="text-align:center;margin:28px 0">
                <a href="' . $link . '" style="background:#b42318;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block">Open live tracking</a>
            </p>
            <p style="color:#666;font-size:13px">Or paste this link into your browser:<br>' . $link . '</p>
        </div>
    ';

    $text = 'SOS ALERT: ' . $travelerName . ' may need immediate help. Live tracking: ' . html_entity_decode($link, ENT_QUOTES, 'UTF-8');

    return st_send_mail($contactEmail, $contactName, 'URGENT: SafariTrak SOS alert', $html, $text, 1);
}
