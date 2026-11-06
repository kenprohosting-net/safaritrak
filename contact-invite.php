<?php

require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/session.php';

st_start_session();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
$validToken = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$invite = null;
$canAccept = false;
$notice = 'This invitation link is invalid, expired, or already used.';

if ($validToken) {
    $stmt = safaritrak_db()->prepare(
        'SELECT i.owner_id, i.invite_name, i.relationship, i.recipient_email,
                i.claimed_user_id, u.full_name AS inviter_name
         FROM trusted_contact_invites i
         JOIN users u ON u.id = i.owner_id
         WHERE i.token_hash = ? AND i.expires_at > UTC_TIMESTAMP() AND i.accepted_at IS NULL
           AND (i.claimed_user_id IS NULL OR i.claimed_user_id = ?)
         LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token), $userId]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($invite) {
        if ($userId > 0 && $invite['recipient_email'] !== null) {
            $emailStmt = safaritrak_db()->prepare('SELECT email FROM users WHERE id = ?');
            $emailStmt->execute([$userId]);
            $userEmail = (string) $emailStmt->fetchColumn();
            if (strcasecmp($userEmail, (string) $invite['recipient_email']) !== 0) {
                $notice = 'Sign in with the email address this invitation was sent to.';
                $invite = null;
            } else {
                $canAccept = true;
            }
        } elseif ($userId > 0) {
            $canAccept = true;
        } else {
            $notice = 'Create an account to accept this invitation. The trusted-contact connection is not created until you sign up and accept.';
        }
    }
}

?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>SafariTrak | Trusted-contact invitation</title>
  <link rel="stylesheet" href="style.css?v=14">
  <link rel="stylesheet" href="auth-theme.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
  <main class="login-card">
    <div class="brand-mark"><i class="fa-solid fa-route"></i><span>SafariTrak</span></div>
    <?php if ($invite): ?>
      <h1 class="title">Trusted-contact invitation</h1>
      <p class="subtitle"><?= htmlspecialchars($invite['inviter_name'], ENT_QUOTES, 'UTF-8') ?> invited you to connect so you can share journeys when you choose.</p>
      <?php if ($canAccept): ?>
        <button type="button" class="login-btn" id="acceptInviteBtn" data-token="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">Accept invitation</button>
      <?php else: ?>
        <a class="login-btn auth-action-link" href="signup.php?invite=<?= urlencode($token) ?>">Sign up to accept</a>
        <p class="signup-text">Already have an account? <a href="login.php">Sign in</a>, then reopen this invitation link.</p>
      <?php endif; ?>
    <?php else: ?>
      <h1 class="title">Invitation unavailable</h1>
      <p class="subtitle" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p>
      <?php if ($userId > 0): ?><a class="login-btn auth-action-link" href="trusted-contacts.php">Go to trusted contacts</a><?php else: ?><a class="login-btn auth-action-link" href="login.php">Sign in</a><?php endif; ?>
    <?php endif; ?>
    <p id="inviteStatus" class="subtitle" role="status" aria-live="polite"></p>
  </main>
  <script src="auth.js"></script>
  <script>
    document.getElementById('acceptInviteBtn')?.addEventListener('click', async event => {
      const button = event.currentTarget;
      const status = document.getElementById('inviteStatus');
      button.disabled = true;
      status.textContent = 'Accepting invitation…';
      try {
        const response = await fetch('backend/api/contacts/accept-invite.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ token: button.dataset.token }),
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Could not accept this invitation.');
        window.location.href = data.redirect || 'trusted-contacts.php';
      } catch (error) {
        status.textContent = error.message || 'Could not accept this invitation. Please try again.';
        button.disabled = false;
      }
    });
  </script>
</body>
</html>
