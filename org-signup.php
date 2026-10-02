<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SafariTrak | Register Your Organization</title>
  <link rel="stylesheet" href="style.css?v=16">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

  <div class="login-card">
    <div class="brand-mark">
      <i class="fa-solid fa-building"></i>
      <span>SafariTrak for Organizations</span>
    </div>
    <h1 class="title">Register your organization</h1>
    <p class="subtitle">Manage your travelers, track their journeys, and see safety reports in one place</p>

    <form class="login-form" id="orgSignupForm" novalidate>
      <div class="input-group">
        <input type="text" id="orgName" placeholder="Organization name" required>
        <i class="fa-solid fa-building input-icon"></i>
      </div>
      <p class="field-error" id="orgNameError">Enter your organization name</p>

      <div class="input-group">
        <input type="text" id="fullName" placeholder="Your full name" required>
        <i class="fa-regular fa-id-card input-icon"></i>
      </div>
      <p class="field-error" id="fullNameError">Enter your full name</p>

      <div class="input-group">
        <input type="text" id="orgUsername" placeholder="Username" required>
        <i class="fa-regular fa-user input-icon"></i>
      </div>
      <p class="field-error" id="orgUsernameError">Enter a username</p>

      <div class="input-group">
        <input type="email" id="orgEmail" placeholder="Work email address" required>
        <i class="fa-regular fa-envelope input-icon"></i>
      </div>
      <p class="field-error" id="orgEmailError">Enter a valid email address</p>

      <div class="input-group">
        <input type="tel" id="orgPhone" placeholder="Phone Number, e.g. 0712 345 678" required>
        <i class="fa-solid fa-phone input-icon"></i>
      </div>
      <p class="field-error" id="orgPhoneError">Enter a valid phone number</p>

      <div class="input-group">
        <input type="password" id="orgPassword" placeholder="Password" required>
        <button type="button" class="input-icon" id="toggleOrgPassword" aria-label="Show password">
          <i class="fa-regular fa-eye-slash"></i>
        </button>
      </div>
      <p class="field-error" id="orgPasswordError">Password must be at least 6 characters</p>

      <div class="remember-me">
        <input type="checkbox" id="orgTerms" required>
        <label for="orgTerms">I agree to the <a href="terms.html" target="_blank">Terms &amp; Conditions</a> and <a href="privacy.html" target="_blank">Privacy Policy</a></label>
      </div>
      <p class="field-error" id="orgTermsError">You need to accept the terms to continue</p>

      <button type="submit" class="login-btn">Create organization account</button>
    </form>

    <p class="signup-text">
      Just want a personal account? <a href="signup.php">Sign up as a traveler</a>
    </p>
    <p class="signup-text">
      Already have an account? <a href="login.php">Login</a>
    </p>
  </div>

  <script src="auth.js"></script>
  <script src="org-signup.js"></script>
</body>
</html>
