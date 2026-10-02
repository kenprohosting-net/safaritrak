<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SafariTrak | Sign Up</title>
  <link rel="stylesheet" href="style.css?v=16">
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

    <form class="login-form" id="signupForm" novalidate>
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
        <input type="password" id="signupPassword" placeholder="Password" required>
        <button type="button" class="input-icon no-border" id="toggleSignupPassword" aria-label="Show password">
          <i class="fa-regular fa-eye-slash"></i>
        </button>
      </div>
      <p class="field-error" id="signupPasswordError">Password must be at least 6 characters</p>

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
</body>
</html>
