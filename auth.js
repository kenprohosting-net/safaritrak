function togglePassword(buttonId, inputId) {
  const button = document.getElementById(buttonId);
  const input = document.getElementById(inputId);
  if (!button || !input) return;
  button.addEventListener('click', () => {
    const icon = button.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    } else {
      input.type = 'password';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    }
  });
}

function showError(id, show, message) {
  const el = document.getElementById(id);
  if (!el) return;
  if (message) el.textContent = message;
  el.classList.toggle('show', show);
}

function clearErrors(ids) {
  ids.forEach(id => showError(id, false));
}

function setSubmitting(button, isSubmitting, defaultLabel) {
  if (!button) return;
  button.disabled = isSubmitting;
  button.textContent = isSubmitting ? 'Please wait...' : defaultLabel;
}

async function postJSON(url, payload) {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  const data = await response.json().catch(() => ({}));
  return { ok: response.ok, status: response.status, data };
}

// Shared by signup/org-signup success: hides the form, reuses the .subtitle
// element (same trick forgotForm already uses), and shows the dev link
// when SAFARITRAK_DEV_MODE is on.
function showNeedsVerification(form, data) {
  form.style.display = 'none';
  const subtitle = document.querySelector('.subtitle');
  if (subtitle) {
    subtitle.textContent = data.message || 'Check your email to verify your account.';
  }
  if (data.dev_verification_link) {
    const devNote = document.createElement('p');
    devNote.className = 'signup-text';
    devNote.innerHTML = 'Dev mode: <a href="' + data.dev_verification_link + '">open your verification link</a>';
    form.insertAdjacentElement('afterend', devNote);
  }
}

togglePassword('toggleLoginPassword', 'loginPassword');
togglePassword('toggleSignupPassword', 'signupPassword');
togglePassword('toggleNewPassword', 'newPassword');
togglePassword('toggleConfirmPassword', 'confirmPassword');

const loginForm = document.getElementById('loginForm');
if (loginForm) {
  const submitBtn = loginForm.querySelector('.login-btn');

  function showResendVerification(email) {
    if (document.getElementById('resendVerificationBtn')) return;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'resendVerificationBtn';
    btn.className = 'btn-ghost';
    btn.style.marginTop = '10px';
    btn.style.width = '100%';
    btn.textContent = 'Resend verification email';
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      btn.textContent = 'Sending...';
      const { data } = await postJSON('backend/api/resend-verification.php', { email });
      btn.textContent = data.message || 'Check your inbox for a new link.';
      if (data.dev_verification_link) {
        const devNote = document.createElement('p');
        devNote.className = 'signup-text';
        devNote.innerHTML = 'Dev mode: <a href="' + data.dev_verification_link + '">open your verification link</a>';
        btn.insertAdjacentElement('afterend', devNote);
      }
    });
    loginForm.insertAdjacentElement('afterend', btn);
  }

  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('loginUsername');
    const password = document.getElementById('loginPassword');
    const remember = document.getElementById('remember');
    let valid = true;

    if (!username || !username.value.trim()) {
      showError('loginUsernameError', true);
      valid = false;
    } else {
      showError('loginUsernameError', false);
    }

    if (!password || !password.value.trim()) {
      showError('loginPasswordError', true);
      valid = false;
    } else {
      showError('loginPasswordError', false);
    }

    if (!valid) return;

    setSubmitting(submitBtn, true, 'Login');

    let ok = false;
    let data = {};
    try {
      ({ ok, data } = await postJSON('backend/api/login.php', {
        username: username.value.trim(),
        password: password.value,
        remember: remember ? remember.checked : false
      }));
    } catch (err) {
      setSubmitting(submitBtn, false, 'Login');
      showError('loginPasswordError', true, 'Could not reach the server. Please check your connection and try again.');
      return;
    }

    setSubmitting(submitBtn, false, 'Login');

    if (ok && data.success) {
      window.location.href = data.redirect || 'index.php';
      return;
    }

    showError('loginPasswordError', true, data.message || 'That username or password is not right.');

    if (data.unverified && data.email) {
      showResendVerification(data.email);
    }
  });
}

const signupForm = document.getElementById('signupForm');
if (signupForm) {
  const submitBtn = signupForm.querySelector('.login-btn');
  const fieldIds = ['fullNameError', 'signupUsernameError', 'emailError', 'signupPhoneError', 'signupPasswordError', 'termsError'];

  signupForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fullName = document.getElementById('fullName');
    const username = document.getElementById('signupUsername');
    const email = document.getElementById('email');
    const phone = document.getElementById('signupPhone');
    const password = document.getElementById('signupPassword');
    const terms = document.getElementById('terms');
    let valid = true;

    clearErrors(fieldIds);

    if (!fullName || !fullName.value.trim()) {
      showError('fullNameError', true);
      valid = false;
    }

    if (!username || !username.value.trim()) {
      showError('signupUsernameError', true);
      valid = false;
    }

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const emailVal = email ? email.value.trim() : '';
    if (!emailPattern.test(emailVal)) {
      showError('emailError', true);
      valid = false;
    }

    const phoneDigits = phone ? phone.value.replace(/\D/g, '') : '';
    if (phoneDigits.length < 9) {
      showError('signupPhoneError', true);
      valid = false;
    }

    if (!password || password.value.trim().length < 6) {
      showError('signupPasswordError', true);
      valid = false;
    }

    if (!terms || !terms.checked) {
      showError('termsError', true);
      valid = false;
    }

    if (!valid) return;

    setSubmitting(submitBtn, true, 'Create Account');
    const { ok, data } = await postJSON('backend/api/register.php', {
      full_name: fullName.value.trim(),
      username: username.value.trim(),
      email: emailVal,
      phone: phone ? phone.value.trim() : '',
      password: password.value,
      terms: terms.checked
    });
    setSubmitting(submitBtn, false, 'Create Account');

    if (ok && data.success) {
      if (data.need_verification) {
        showNeedsVerification(signupForm, data);
        return;
      }
      window.location.href = data.redirect || 'index.php';
      return;
    }

    if (data.errors) {
      const map = {
        full_name: 'fullNameError',
        username: 'signupUsernameError',
        email: 'emailError',
        password: 'signupPasswordError',
        terms: 'termsError'
      };
      Object.keys(data.errors).forEach(key => {
        if (map[key]) showError(map[key], true, data.errors[key]);
      });
    } else {
      showError('emailError', true, data.message || 'Something went wrong. Please try again.');
    }
  });
}

const forgotForm = document.getElementById('forgotForm');
if (forgotForm) {
  const submitBtn = forgotForm.querySelector('.login-btn');

  forgotForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const contact = document.getElementById('resetContact');

    if (!contact || !contact.value.trim()) {
      showError('resetContactError', true);
      return;
    }
    showError('resetContactError', false);

    setSubmitting(submitBtn, true, 'Send reset link');
    const subtitle = document.querySelector('.subtitle');
    try {
      const { ok, data } = await postJSON('backend/api/forgot-password.php', {
        contact: contact.value.trim()
      });

      if (!ok || !data.success) {
        if (subtitle) subtitle.textContent = data.message || 'Could not send the reset link. Please try again.';
        return;
      }

      if (subtitle) {
        subtitle.textContent = data.message || 'If that account exists, a reset link has been sent.';
      }
      forgotForm.style.display = 'none';

      if (data.dev_reset_link) {
        const devNote = document.createElement('p');
        devNote.className = 'signup-text';
        devNote.innerHTML = 'Dev mode: <a href="' + data.dev_reset_link + '">open your reset link</a>';
        forgotForm.insertAdjacentElement('afterend', devNote);
      }
    } catch (error) {
      if (subtitle) subtitle.textContent = 'Could not reach the server. Check your connection and try again.';
    } finally {
      setSubmitting(submitBtn, false, 'Send reset link');
    }
  });
}

const resetForm = document.getElementById('resetForm');
if (resetForm) {
  const submitBtn = resetForm.querySelector('.login-btn');

  resetForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    let valid = true;

    if (!newPassword || newPassword.value.trim().length < 6) {
      showError('newPasswordError', true);
      valid = false;
    } else {
      showError('newPasswordError', false);
    }

    if (!confirmPassword || confirmPassword.value.trim() !== newPassword.value.trim() || !confirmPassword.value.trim()) {
      showError('confirmPasswordError', true);
      valid = false;
    } else {
      showError('confirmPasswordError', false);
    }

    if (!valid) return;

    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');

    if (!token) {
      showError('confirmPasswordError', true, 'This reset link is missing its token. Request a new one.');
      return;
    }

    setSubmitting(submitBtn, true, 'Update password');
    const { ok, data } = await postJSON('backend/api/reset-password.php', {
      token,
      password: newPassword.value
    });
    setSubmitting(submitBtn, false, 'Update password');

    if (ok && data.success) {
      window.location.href = data.redirect || 'login.html';
      return;
    }

    showError('confirmPasswordError', true, data.message || 'This reset link is invalid or has expired.');
  });
}

const params = new URLSearchParams(window.location.search);
if (params.get('suspended') === '1') {
  const subtitle = document.querySelector('.subtitle');
  if (subtitle) {
    subtitle.textContent = 'This account has been suspended. Contact SafariTrak support for help.';
    subtitle.style.color = '#ffb3b3';
  }
}
