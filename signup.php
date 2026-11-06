<?php
$contactInviteToken = isset($_GET['invite']) && is_string($_GET['invite'])
    && preg_match('/^[a-f0-9]{64}$/', $_GET['invite'])
        ? $_GET['invite']
        : '';
$groupInviteToken = isset($_GET['group_invite']) && is_string($_GET['group_invite'])
    && preg_match('/^[a-f0-9]{64}$/', $_GET['group_invite'])
        ? $_GET['group_invite']
        : '';
$organizationInviteToken = isset($_GET['org_invite']) && is_string($_GET['org_invite'])
    && preg_match('/^[a-f0-9]{64}$/', $_GET['org_invite'])
        ? $_GET['org_invite']
        : '';
$contactInviteName = '';
$contactInviteValid = false;
if ($contactInviteToken !== '') {
    require_once __DIR__ . '/backend/config/database.php';
    $inviteStmt = safaritrak_db()->prepare(
        'SELECT i.invite_name, u.full_name AS inviter_name
         FROM trusted_contact_invites i
         JOIN users u ON u.id = i.owner_id
         WHERE i.token_hash = ? AND i.expires_at > UTC_TIMESTAMP()
           AND i.accepted_at IS NULL AND i.claimed_user_id IS NULL
         LIMIT 1'
    );
    $inviteStmt->execute([hash('sha256', $contactInviteToken)]);
    $invite = $inviteStmt->fetch(PDO::FETCH_ASSOC);
    if ($invite) {
        $contactInviteName = (string) $invite['inviter_name'];
        $contactInviteValid = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SafariTrak | Sign Up</title>
  <link rel="stylesheet" href="style.css?v=14">
  <link rel="stylesheet" href="auth-theme.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

  <div class="login-card">
    <div class="brand-mark">
      <i class="fa-solid fa-route"></i>
      <span>SafariTrak</span>
    </div>
    <h1 class="title">Join SafariTrak</h1>
    <p class="subtitle">Create an account to start tracking your journeys</p>
    <?php if ($contactInviteValid): ?>
      <p class="auth-invite-notice">You were invited by <?= htmlspecialchars($contactInviteName, ENT_QUOTES, 'UTF-8') ?>. After signing up and verifying your email, accept their trusted-contact request.</p>
    <?php elseif (isset($_GET['invite'])): ?>
      <p class="auth-invite-notice" role="alert">This invitation link is invalid or expired.</p>
    <?php endif; ?>
    <?php if ($groupInviteToken !== ''): ?>
      <p class="auth-invite-notice">After verifying your email, you will return to the group invitation.</p>
    <?php elseif ($organizationInviteToken !== ''): ?>
      <p class="auth-invite-notice">After verifying your email, you will return to the school invitation.</p>
    <?php endif; ?>
    <p class="field-error" id="signupInviteError" role="alert"></p>

    <form class="login-form" id="signupForm" novalidate>
      <input type="hidden" id="contactInviteToken" value="<?= $contactInviteValid ? htmlspecialchars($contactInviteToken, ENT_QUOTES, 'UTF-8') : '' ?>">
      <input type="hidden" id="groupInviteToken" value="<?= htmlspecialchars($groupInviteToken, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" id="organizationInviteToken" value="<?= htmlspecialchars($organizationInviteToken, ENT_QUOTES, 'UTF-8') ?>">
      <div class="input-group">
        <input type="text" id="fullName" placeholder="Full Name" required>
        <i class="fa-regular fa-id-card input-icon"></i>
      </div>
      <p class="field-error" id="fullNameError">Enter your full name</p>

      <div class="input-group">
        <input type="text" id="signupUsername" placeholder="Username" required>
        <i class="fa-regular fa-user input-icon"></i>
      </div>
      <p class="field-error" id="signupUsernameError">Enter a username</p>

      <div class="input-group">
        <input type="email" id="email" placeholder="Email Address" required>
        <i class="fa-regular fa-envelope input-icon"></i>
      </div>
      <p class="field-error" id="emailError">Enter a valid email address</p>

      <div class="input-group">
        <input type="tel" id="signupPhone" placeholder="Phone Number, e.g. 0712 345 678" required>
        <i class="fa-solid fa-phone input-icon"></i>
      </div>
      <p class="field-error" id="signupPhoneError">Enter a valid phone number</p>

      <div class="input-group">
        <input type="password" id="signupPassword" placeholder="At least 12 characters" minlength="12" maxlength="1024" autocomplete="new-password" required>
        <button type="button" class="input-icon no-border" id="toggleSignupPassword" aria-label="Show password">
          <i class="fa-regular fa-eye-slash"></i>
        </button>
      </div>
      <p class="field-error" id="signupPasswordError">Password must be at least 12 characters and no more than 1024 bytes</p>

      <div class="auth-row">
        <div class="remember-me">
          <input type="checkbox" id="terms" required>
          <label for="terms">I agree to the <a href="terms.html" target="_blank">Terms &amp; Conditions</a> and <a href="privacy.html" target="_blank">Privacy Policy</a></label>
        </div>
      </div>
      <p class="field-error" id="termsError">You need to accept the terms to continue</p>

      <button type="submit" class="login-btn">Create Account</button>
    </form>

    <p class="signup-text">
      Already have an account? <a href="login.php">Login</a>
    </p>
    <p class="signup-text">
      Signing up for a business or Sacco? <a href="org-signup.php">Register your organization</a>
    </p>
  </div>

  <script src="auth.js"></script>
  <script src="auth-theme.js"></script>
</body>
</html>
