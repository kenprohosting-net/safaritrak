<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SafariTrak | Forgot Password</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

  <div class="login-card">
    <div class="brand-mark">
      <i class="fa-solid fa-route"></i>
      <span>SafariTrak</span>
    </div>
    <h1 class="title">Reset password</h1>
    <p class="subtitle">Enter the email or phone number on your account and we will send you a reset link</p>

    <form class="login-form" id="forgotForm" novalidate>
      <div class="input-group">
        <input type="text" id="resetContact" placeholder="Email or phone number" required>
        <i class="fa-regular fa-envelope input-icon"></i>
      </div>
      <p class="field-error" id="resetContactError">Enter your email or phone number</p>

      <button type="submit" class="login-btn">Send reset link</button>
    </form>

    <p class="signup-text">
      Remembered your password? <a href="login.php">Back to login</a>
    </p>
  </div>

  <script src="auth.js"></script>
</body>
</html>
