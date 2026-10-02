<?php

require __DIR__ . '/backend/includes/session.php';
require __DIR__ . '/backend/config/database.php';
require __DIR__ . '/backend/includes/helpers.php';
require __DIR__ . '/backend/includes/notify.php';
require __DIR__ . '/backend/includes/mailer.php';

st_start_session();

$token = trim($_GET['token'] ?? '');
$db = safaritrak_db();

$status = 'invalid'; // invalid | expired | already | success
$user = null;

if ($token !== '') {
    $tokenHash = hash('sha256', $token);

    $stmt = $db->prepare('SELECT id, user_id, expires_at, used_at FROM email_verifications WHERE token_hash = ?');
    $stmt->execute([$tokenHash]);
    $record = $stmt->fetch();

    if ($record) {
        $userStmt = $db->prepare('SELECT id, full_name, username, email, email_verified_at FROM users WHERE id = ?');
        $userStmt->execute([$record['user_id']]);
        $user = $userStmt->fetch();

        if (!$user) {
            $status = 'invalid';
        } elseif ($user['email_verified_at'] || $record['used_at']) {
            $status = 'already';
        } elseif (strtotime($record['expires_at']) < time()) {
            $status = 'expired';
        } else {
            $db->beginTransaction();
            $db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$user['id']]);
            $db->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = ?')->execute([$record['id']]);
            $db->commit();

            st_notify(
                (int) $user['id'],
                'welcome',
                'Welcome to SafariTrak, ' . $user['full_name'] . '!',
                'Your email is verified. Add a trusted contact and start your first journey to stay safe on the road.'
            );

            st_send_welcome_email($user);
            st_login_user($user, false);

            $status = 'success';
        }
    }
}

$copy = [
    'success' => ['title' => 'Email verified', 'text' => 'Your account is active. Taking you to your dashboard...', 'redirect' => 'index.php'],
    'already' => ['title' => 'Already verified', 'text' => 'This email is already verified — you can log in as usual.', 'redirect' => null],
    'expired' => ['title' => 'Link expired', 'text' => 'This verification link has expired. Request a new one from the login screen.', 'redirect' => null],
    'invalid' => ['title' => 'Invalid link', 'text' => 'This verification link is not valid. Request a new one from the login screen.', 'redirect' => null],
][$status];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SafariTrak | Verify Email</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

  <div class="login-card">
    <div class="brand-mark">
      <i class="fa-solid fa-route"></i>
      <span>SafariTrak</span>
    </div>
    <h1 class="title"><?= htmlspecialchars($copy['title']) ?></h1>
    <p class="subtitle"><?= htmlspecialchars($copy['text']) ?></p>

    <?php if ($status === 'success'): ?>
      <a class="login-btn" style="display:block;text-align:center;text-decoration:none" href="index.php">Go to dashboard</a>
    <?php else: ?>
      <a class="login-btn" style="display:block;text-align:center;text-decoration:none" href="login.php">Back to login</a>
    <?php endif; ?>
  </div>

  <?php if ($status === 'success'): ?>
  <script>
    setTimeout(function () { window.location.href = 'index.php'; }, 2500);
  </script>
  <?php endif; ?>

</body>
</html>
