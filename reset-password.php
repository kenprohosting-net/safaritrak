<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SafariTrak | Reset Password</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

  <div class="login-card">
    <div class="brand-mark">
      <i class="fa-solid fa-route"></i>
      <span>SafariTrak</span>
    </div>
    <h1 class="title">Set a new password</h1>
    <p class="subtitle">Choose a new password for your SafariTrak account</p>

    <form class="login-form" id="resetForm" novalidate>
      <div class="input-group">
        <input type="password" id="newPassword" placeholder="New password" required>
        <button type="button" class="input-icon" id="toggleNewPassword" aria-label="Show password">
          <i class="fa-regular fa-eye-slash"></i>
        </button>
      </div>
      <p class="field-error" id="newPasswordError">Password must be at least 6 characters</p>

      <div class="input-group">
        <input type="password" id="confirmPassword" placeholder="Confirm new password" required>
        <button type="button" class="input-icon" id="toggleConfirmPassword" aria-label="Show password">
          <i class="fa-regular fa-eye-slash"></i>
        </button>
      </div>
      <p class="field-error" id="confirmPasswordError">Passwords do not match</p>

      <button type="submit" class="login-btn">Update password</button>
    </form>

    <p class="signup-text">
      Changed your mind? <a href="login.php">Back to login</a>
    </p>
  </div>

  <script src="auth.js"></script>
</body>
</html>
