<?php
require_once __DIR__ . '/backend/includes/session.php';
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/helpers.php';

st_start_session();

$db = safaritrak_db();

// If user is already logged in, determine their dynamic role from the DB tables and redirect
if (!empty($_SESSION['user_id'])) {

    $paStmt = $db->prepare('SELECT id FROM platform_admins WHERE user_id = ? LIMIT 1');
    $paStmt->execute([$_SESSION['user_id']]);
    if ($paStmt->fetch()) {
        $_SESSION['role'] = 'platform_admin';
        header('Location: admin-dashboard.php');
        exit();
    }

    $oaStmt = $db->prepare('SELECT organization_id FROM organization_admins WHERE user_id = ? LIMIT 1');
    $oaStmt->execute([$_SESSION['user_id']]);
    $orgAdminData = $oaStmt->fetch();
    if ($orgAdminData) {
        $_SESSION['role'] = 'org_admin';
        $_SESSION['organization_id'] = (int) $orgAdminData['organization_id'];
        header('Location: org-dashboard.php');
        exit();
    }

    $_SESSION['role'] = 'user';
    header('Location: index.php');
    exit();
}

$error = '';
$usernameInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = trim($_POST['username'] ?? '');
    $passwordInput = $_POST['password'] ?? '';

    if (empty($usernameInput) || empty($passwordInput)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$usernameInput, $usernameInput]);
        $user = $stmt->fetch();

        if ($user && password_verify($passwordInput, $user['password_hash'])) {
            if ((int) ($user['is_suspended'] ?? 0) === 1) {
                $error = 'Your account has been suspended. Please contact support.';
            } elseif (empty($user['email_verified_at'])) {
                $error = 'Please verify your email before logging in. Check your inbox for the verification link, or sign up again to request a new one.';
            } else {
                $_SESSION['user_id'] = (int) $user['id'];

                // Check Platform Admin status
                $paStmt = $db->prepare('SELECT id FROM platform_admins WHERE user_id = ? LIMIT 1');
                $paStmt->execute([$user['id']]);
                $isPlatformAdmin = (bool) $paStmt->fetch();

                // Check Organization Admin status
                $oaStmt = $db->prepare('SELECT organization_id FROM organization_admins WHERE user_id = ? LIMIT 1');
                $oaStmt->execute([$user['id']]);
                $orgAdminData = $oaStmt->fetch();

                if ($isPlatformAdmin) {
                    $_SESSION['role'] = 'platform_admin';
                    header('Location: admin-dashboard.php');
                } elseif ($orgAdminData) {
                    $_SESSION['role'] = 'org_admin';
                    $_SESSION['organization_id'] = (int) $orgAdminData['organization_id'];
                    header('Location: org-dashboard.php');
                } else {
                    $_SESSION['role'] = 'user';
                    header('Location: index.php');
                }
                exit();
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SafariTrak | Login</title>
  <link rel="stylesheet" href="style.css?v=16">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

  <div class="login-card">
    <div class="brand-mark">
      <i class="fa-solid fa-route"></i>
      <span>SafariTrak</span>
    </div>
    <h1 class="title">Welcome back</h1>
    <p class="subtitle">Log in to continue your journey</p>

    <?php if (!empty($error)): ?>
      <div class="field-error" style="display: block; margin-bottom: 15px; text-align: center;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form class="login-form" id="loginForm" method="POST" action="login.php" novalidate>
      <div class="input-group">
        <input type="text" name="username" id="loginUsername" placeholder="Username or Email" value="<?= htmlspecialchars($usernameInput) ?>" required>
        <i class="fa-regular fa-user input-icon"></i>
      </div>
      <p class="field-error" id="loginUsernameError">Enter your username</p>

      <div class="input-group">
        <input type="password" name="password" id="loginPassword" placeholder="Password" required>
        <button type="button" class="input-icon" id="toggleLoginPassword" aria-label="Show password">
          <i class="fa-regular fa-eye-slash"></i>
        </button>
      </div>
      <p class="field-error" id="loginPasswordError">Enter your password</p>

      <div class="auth-row">
        <div class="remember-me">
          <input type="checkbox" id="remember" name="remember" checked>
          <label for="remember">Remember me</label>
        </div>
        <a class="forgot-link" href="forgot-password.php">Forgot password?</a>
      </div>

      <button type="submit" class="login-btn">Login</button>
    </form>

    <p class="signup-text">
      Don't have an account? <a href="signup.php">Sign up</a>
    </p>
    <p class="signup-text">
      Managing an organization? <a href="org-signup.php">Register it here</a>
    </p>
  </div>

  <script src="auth.js"></script>
</body>
</html>