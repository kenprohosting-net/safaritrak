togglePassword('toggleOrgPassword', 'orgPassword');

const orgSignupForm = document.getElementById('orgSignupForm');
if (orgSignupForm) {
  const submitBtn = orgSignupForm.querySelector('.login-btn');
  const fieldIds = ['orgNameError', 'fullNameError', 'orgUsernameError', 'orgEmailError', 'orgPhoneError', 'orgPasswordError', 'orgTermsError'];

  orgSignupForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const orgName = document.getElementById('orgName');
    const fullName = document.getElementById('fullName');
    const username = document.getElementById('orgUsername');
    const email = document.getElementById('orgEmail');
    const phone = document.getElementById('orgPhone');
    const password = document.getElementById('orgPassword');
    const terms = document.getElementById('orgTerms');
    let valid = true;

    clearErrors(fieldIds);

    if (!orgName.value.trim()) {
      showError('orgNameError', true);
      valid = false;
    }

    if (!fullName.value.trim()) {
      showError('fullNameError', true);
      valid = false;
    }

    if (!username.value.trim()) {
      showError('orgUsernameError', true);
      valid = false;
    }

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email.value.trim())) {
      showError('orgEmailError', true);
      valid = false;
    }

    const phoneDigits = phone.value.replace(/\D/g, '');
    if (phoneDigits.length < 9) {
      showError('orgPhoneError', true);
      valid = false;
    }

    if (password.value.trim().length < 6) {
      showError('orgPasswordError', true);
      valid = false;
    }

    if (!terms.checked) {
      showError('orgTermsError', true);
      valid = false;
    }

    if (!valid) return;

    setSubmitting(submitBtn, true, 'Create organization account');
    const { ok, data } = await postJSON('backend/api/org/signup.php', {
      org_name: orgName.value.trim(),
      full_name: fullName.value.trim(),
      username: username.value.trim(),
      email: email.value.trim(),
      phone: phone.value.trim(),
      password: password.value,
      terms: terms.checked
    });
    setSubmitting(submitBtn, false, 'Create organization account');

    if (ok && data.success) {
      if (data.need_verification) {
        showNeedsVerification(orgSignupForm, data);
        return;
      }
      window.location.href = data.redirect || 'org-dashboard.php';
      return;
    }

    if (data.errors) {
      const map = {
        org_name: 'orgNameError',
        full_name: 'fullNameError',
        username: 'orgUsernameError',
        email: 'orgEmailError',
        phone: 'orgPhoneError',
        password: 'orgPasswordError',
        terms: 'orgTermsError'
      };
      Object.keys(data.errors).forEach(key => {
        if (map[key]) showError(map[key], true, data.errors[key]);
      });
    } else {
      showError('orgEmailError', true, data.message || 'Something went wrong. Please try again.');
    }
  });
}
