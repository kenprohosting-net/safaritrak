<?php
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/session.php';
st_start_session();
$token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
$invite = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = safaritrak_db()->prepare(
        'SELECT i.invited_email, i.member_type, o.name AS organization_name
         FROM organization_invites i
         JOIN organizations o ON o.id = i.organization_id
         JOIN organization_profiles p ON p.organization_id = o.id
         WHERE i.token_hash = ? AND i.expires_at > UTC_TIMESTAMP()
           AND p.account_type = "school" AND p.review_status = "approved"
           AND o.is_suspended = 0 LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>SafariTrak | School invitation</title>
  <link rel="stylesheet" href="style.css"><link rel="stylesheet" href="auth-theme.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
  <main class="login-card group-invite-card">
    <div class="brand-mark"><i class="fa-solid fa-school"></i><span>SafariTrak Schools</span></div>
    <?php if (!$invite): ?>
      <h1 class="title">Invitation unavailable</h1><p class="subtitle">This school invitation is invalid or expired.</p>
      <p class="signup-text"><a href="index.php">Go to SafariTrak</a></p>
    <?php else: ?>
      <h1 class="title">Request to join <?= htmlspecialchars($invite['organization_name'], ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="subtitle">This invitation is for a <?= htmlspecialchars($invite['member_type'], ENT_QUOTES, 'UTF-8') ?>. A school administrator must approve your request before journey or location data is shared.</p>
      <?php if (empty($_SESSION['user_id'])): ?>
        <a class="login-btn auth-action-link" href="login.php?org_invite=<?= rawurlencode($token) ?>">Sign in to request access</a>
        <p class="signup-text">New to SafariTrak? <a href="signup.php?org_invite=<?= rawurlencode($token) ?>">Create an individual account</a> and verify your email to continue.</p>
      <?php else: ?>
        <button class="login-btn" type="button" id="requestSchoolMembership" data-token="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">Request to join</button>
        <p id="schoolInviteStatus" class="signup-text" role="status" aria-live="polite"></p>
      <?php endif; ?>
    <?php endif; ?>
  </main>
  <script src="auth.js"></script><script src="auth-theme.js"></script>
  <script>
    document.getElementById('requestSchoolMembership')?.addEventListener('click', async event => {
      const button = event.currentTarget;
      const status = document.getElementById('schoolInviteStatus');
      button.disabled = true;
      status.textContent = 'Sending your request…';
      try {
        const response = await fetch('backend/api/org/request-membership.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ token: button.dataset.token }),
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Could not send your request.');
        button.textContent = 'Request sent';
        status.textContent = payload.message;
      } catch (error) {
        status.textContent = error.message || 'Could not send your request. Try again.';
        button.disabled = false;
      }
    });
  </script>
</body>
</html>
