<?php
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/session.php';
st_start_session();
$token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
$group = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = safaritrak_db()->prepare(
        'SELECT g.id, g.name, g.description, i.invited_email, o.name AS organization_name
         FROM persistent_group_invites i
         JOIN persistent_groups g ON g.id = i.group_id
         LEFT JOIN organizations o ON o.id = g.organization_id
         WHERE i.token_hash = ? AND i.expires_at > UTC_TIMESTAMP()
           AND i.accepted_at IS NULL AND i.revoked_at IS NULL AND g.status = "active"
         LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$loggedIn = !empty($_SESSION['user_id']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>SafariTrak | Group invitation</title>
  <link rel="stylesheet" href="style.css"><link rel="stylesheet" href="auth-theme.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
  <main class="login-card group-invite-card">
    <div class="brand-mark"><i class="fa-solid fa-people-group"></i><span>SafariTrak Groups</span></div>
    <?php if (!$group): ?>
      <h1 class="title">Invitation unavailable</h1><p class="subtitle">This group invitation is invalid, expired, or has already been used.</p>
      <p class="signup-text"><a href="index.php">Go to SafariTrak</a></p>
    <?php else: ?>
      <h1 class="title">Join <?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="subtitle"><?= htmlspecialchars($group['description'] ?: 'Join the group to share trip plans and chat with its members.', ENT_QUOTES, 'UTF-8') ?></p>
      <?php if ($group['organization_name']): ?><p class="auth-invite-notice">This is a <?= htmlspecialchars($group['organization_name'], ENT_QUOTES, 'UTF-8') ?> group. Organization membership must already be approved.</p><?php endif; ?>
      <?php if (!$loggedIn): ?>
        <p class="auth-invite-notice">Sign in or create an account to continue. New accounts return here after email verification.</p>
        <a class="login-btn auth-action-link" href="login.php?group_invite=<?= rawurlencode($token) ?>">Sign in</a>
        <p class="signup-text">New to SafariTrak? <a href="signup.php?group_invite=<?= rawurlencode($token) ?>">Create an individual account</a>.</p>
      <?php else: ?>
        <button class="login-btn" type="button" id="acceptGroupInvite" data-token="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">Join group</button>
        <p id="groupInviteStatus" class="signup-text" role="status" aria-live="polite"></p>
      <?php endif; ?>
    <?php endif; ?>
  </main>
  <script src="auth.js"></script><script src="auth-theme.js"></script>
  <script>
    document.getElementById('acceptGroupInvite')?.addEventListener('click', async event => {
      const button = event.currentTarget;
      const status = document.getElementById('groupInviteStatus');
      button.disabled = true;
      status.textContent = 'Joining group…';
      try {
        const response = await fetch('backend/api/groups/persistent/accept.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ token: button.dataset.token }),
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Could not join this group.');
        location.href = `groups.php?id=${encodeURIComponent(payload.group_id)}`;
      } catch (error) {
        status.textContent = error.message || 'Could not join this group. Refresh and try again.';
        button.disabled = false;
      }
    });
  </script>
</body>
</html>
