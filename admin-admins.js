document.getElementById('addAdminBtn')?.addEventListener('click', async () => {
  const input = document.getElementById('addAdminIdentifier');
  const errorEl = document.getElementById('addAdminError');
  const btn = document.getElementById('addAdminBtn');
  const identifier = input.value.trim();

  errorEl.style.display = 'none';

  if (!identifier) {
    errorEl.textContent = 'Enter a username, email or phone number.';
    errorEl.style.display = 'block';
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Adding...';

  try {
    const response = await fetch('backend/api/platform/add-admin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ identifier }),
    });
    const data = await response.json();

    if (!data.success) {
      errorEl.textContent = data.message || 'That admin could not be added.';
      errorEl.style.display = 'block';
      return;
    }

    location.reload();
  } catch (err) {
    errorEl.textContent = 'Something went wrong. Please try again.';
    errorEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Add admin';
  }
});

document.querySelectorAll('.remove-admin-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!confirm('Remove this platform admin? They will lose all platform-wide access.')) return;

    btn.disabled = true;

    try {
      const response = await fetch('backend/api/platform/remove-admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ admin_id: btn.getAttribute('data-admin-id') }),
      });
      const data = await response.json();

      if (!data.success) {
        alert(data.message || 'That admin could not be removed.');
        btn.disabled = false;
        return;
      }

      location.reload();
    } catch (err) {
      alert('Something went wrong. Please try again.');
      btn.disabled = false;
    }
  });
});
