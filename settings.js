// Assumes dashboard.js already wires up the generic tab switching
// (data-tab-group / data-tab-panel-group) and modal open/close
// (data-open-modal / data-close-modal) used elsewhere in the app.

function stClearFieldErrors(ids) {
  ids.forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.textContent = '';
  });
}

function stShowFieldErrors(errors) {
  Object.entries(errors || {}).forEach(([field, message]) => {
    // field names come back as snake_case from the API, ids are camel-ish
    // e.g. full_name -> settingFullNameError
    const idBase = 'setting' + field
      .split('_')
      .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
      .join('');
    const el = document.getElementById(idBase + 'Error');
    if (el) el.textContent = message;
  });
}

function stShowMessage(el, text, isError) {
  if (!el) return;
  el.textContent = text;
  el.style.display = 'block';
  el.classList.toggle('form-message-error', !!isError);
  el.classList.toggle('form-message-success', !isError);
}

async function stPostJson(url, payload) {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  let data;
  try {
    data = await response.json();
  } catch (err) {
    data = { success: false, message: 'Unexpected server response.' };
  }
  return data;
}

/* ---------------- Profile form ---------------- */

const saveProfileBtn = document.getElementById('saveProfileBtn');
const profileMessage = document.getElementById('profileMessage');

const profileFieldIds = [
  'settingFullNameError',
  'settingUsernameError',
  'settingPhoneError',
  'settingEmailError',
  'settingHomeAddressError',
];

saveProfileBtn?.addEventListener('click', async () => {
  stClearFieldErrors(profileFieldIds);
  if (profileMessage) profileMessage.style.display = 'none';

  const payload = {
    full_name: document.getElementById('settingFullName').value.trim(),
    username: document.getElementById('settingUsername').value.trim(),
    phone: document.getElementById('settingPhone').value.trim(),
    email: document.getElementById('settingEmail').value.trim(),
    home_address: document.getElementById('settingHomeAddress').value.trim(),
  };

  saveProfileBtn.disabled = true;
  const originalText = saveProfileBtn.textContent;
  saveProfileBtn.textContent = 'Saving...';

  try {
    const data = await stPostJson('backend/api/update-profile.php', payload);

    if (!data.success) {
      stShowFieldErrors(data.errors);
      stShowMessage(profileMessage, data.message || 'Could not save your changes.', true);
      return;
    }

    document.getElementById('settingPhone').value = data.user.phone_display || '';
    document.querySelectorAll('.account b').forEach((el) => {
      el.textContent = data.user.full_name;
    });
    stShowMessage(profileMessage, data.message || 'Saved.', false);
  } catch (err) {
    stShowMessage(profileMessage, 'Something went wrong saving your profile. Please try again.', true);
  } finally {
    saveProfileBtn.disabled = false;
    saveProfileBtn.textContent = originalText;
  }
});

/* ---------------- Password form ---------------- */

const updatePasswordBtn = document.getElementById('updatePasswordBtn');
const passwordMessage = document.getElementById('passwordMessage');

const passwordFieldIds = [
  'settingCurrentPasswordError',
  'settingNewPasswordError',
  'settingConfirmPasswordError',
];

updatePasswordBtn?.addEventListener('click', async () => {
  stClearFieldErrors(passwordFieldIds);
  if (passwordMessage) passwordMessage.style.display = 'none';

  const currentPasswordEl = document.getElementById('settingCurrentPassword');
  const newPasswordEl = document.getElementById('settingNewPassword');
  const confirmPasswordEl = document.getElementById('settingConfirmPassword');

  const payload = {
    current_password: currentPasswordEl.value,
    new_password: newPasswordEl.value,
    confirm_password: confirmPasswordEl.value,
  };

  updatePasswordBtn.disabled = true;
  const originalText = updatePasswordBtn.textContent;
  updatePasswordBtn.textContent = 'Updating...';

  try {
    const data = await stPostJson('backend/api/update-password.php', payload);

    if (!data.success) {
      stShowFieldErrors(data.errors);
      stShowMessage(passwordMessage, data.message || 'Could not update your password.', true);
      return;
    }

    currentPasswordEl.value = '';
    newPasswordEl.value = '';
    confirmPasswordEl.value = '';
    stShowMessage(passwordMessage, data.message || 'Password updated.', false);
  } catch (err) {
    stShowMessage(passwordMessage, 'Something went wrong updating your password. Please try again.', true);
  } finally {
    updatePasswordBtn.disabled = false;
    updatePasswordBtn.textContent = originalText;
  }
});

/* ---------------- Notification / privacy toggles ---------------- */

document.querySelectorAll('input[type="checkbox"][data-setting]').forEach((checkbox) => {
  checkbox.addEventListener('change', async () => {
    const field = checkbox.dataset.setting;
    const value = checkbox.checked;
    checkbox.disabled = true;

    try {
      const data = await stPostJson('backend/api/safety/update-settings.php', { field, value });
      if (!data.success) {
        checkbox.checked = !value; // revert on failure
        alert(data.message || 'Could not save that setting.');
      }
    } catch (err) {
      checkbox.checked = !value;
      alert('Something went wrong saving that setting. Please try again.');
    } finally {
      checkbox.disabled = false;
    }
  });
});

/* ---------------- Avatar upload / remove ---------------- */

const avatarFileInput = document.getElementById('avatarFileInput');
const changePhotoBtn = document.getElementById('changePhotoBtn');
const removePhotoBtn = document.getElementById('removePhotoBtn');

function updateAvatarEverywhere(avatarPath, initials) {
  const html = avatarPath
    ? '<img src="' + avatarPath + '?v=' + Date.now() + '" class="avatar-img" alt="">'
    : initials;

  const preview = document.getElementById('profileAvatarPreview');
  const sidebar = document.getElementById('sidebarAvatar');
  const header = document.getElementById('headerAvatar');
  if (preview) preview.innerHTML = html;
  if (sidebar) sidebar.innerHTML = html;
  if (header) header.innerHTML = html;
}

changePhotoBtn?.addEventListener('click', () => {
  avatarFileInput.click();
});

avatarFileInput?.addEventListener('change', async () => {
  const file = avatarFileInput.files[0];
  if (!file) return;

  if (file.size > 4 * 1024 * 1024) {
    alert('Please choose an image smaller than 4 MB.');
    avatarFileInput.value = '';
    return;
  }

  const formData = new FormData();
  formData.append('avatar', file);

  changePhotoBtn.textContent = 'Uploading...';
  changePhotoBtn.disabled = true;

  try {
    const response = await fetch('backend/api/upload-avatar.php', {
      method: 'POST',
      body: formData,
    });
    const data = await response.json();

    if (!data.success) {
      alert(data.message || 'That photo could not be uploaded.');
      return;
    }

    updateAvatarEverywhere(data.avatar_path, '');
    if (!removePhotoBtn) {
      location.reload();
    }
  } catch (err) {
    alert('Something went wrong uploading that photo. Please try again.');
  } finally {
    changePhotoBtn.textContent = 'Change photo';
    changePhotoBtn.disabled = false;
    avatarFileInput.value = '';
  }
});

removePhotoBtn?.addEventListener('click', async () => {
  if (!confirm('Remove your profile photo?')) return;

  const formData = new FormData();
  formData.append('remove', '1');

  try {
    const response = await fetch('backend/api/upload-avatar.php', {
      method: 'POST',
      body: formData,
    });
    const data = await response.json();

    if (!data.success) {
      alert(data.message || 'That photo could not be removed.');
      return;
    }

    location.reload();
  } catch (err) {
    alert('Something went wrong removing that photo. Please try again.');
  }
});

/* ---------------- Delete account ---------------- */

const confirmDeleteAccountBtn = document.getElementById('confirmDeleteAccountBtn');
const deleteAccountPasswordEl = document.getElementById('deleteAccountPassword');
const deleteAccountPasswordError = document.getElementById('deleteAccountPasswordError');

confirmDeleteAccountBtn?.addEventListener('click', async () => {
  if (deleteAccountPasswordError) deleteAccountPasswordError.textContent = '';

  const password = deleteAccountPasswordEl?.value || '';
  if (!password) {
    if (deleteAccountPasswordError) deleteAccountPasswordError.textContent = 'Enter your password to confirm.';
    return;
  }

  confirmDeleteAccountBtn.disabled = true;
  const originalText = confirmDeleteAccountBtn.textContent;
  confirmDeleteAccountBtn.textContent = 'Deleting...';

  try {
    const data = await stPostJson('delete.php', { password });

    if (!data.success) {
      if (deleteAccountPasswordError) {
        deleteAccountPasswordError.textContent = data.message || 'Could not delete your account.';
      }
      return;
    }

    window.location.href = data.redirect || 'login.php';
  } catch (err) {
    if (deleteAccountPasswordError) {
      deleteAccountPasswordError.textContent = 'Something went wrong. Please try again.';
    }
  } finally {
    confirmDeleteAccountBtn.disabled = false;
    confirmDeleteAccountBtn.textContent = originalText;
  }
});
