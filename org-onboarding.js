document.getElementById('createOrgBtn')?.addEventListener('click', async () => {
  const nameInput = document.getElementById('orgName');
  const errorEl = document.getElementById('createOrgError');
  const btn = document.getElementById('createOrgBtn');
  const name = nameInput.value.trim();

  errorEl.style.display = 'none';

  if (!name) {
    errorEl.textContent = 'Enter a name for your organization.';
    errorEl.style.display = 'block';
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Creating...';

  try {
    const response = await fetch('backend/api/org/create.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name }),
    });
    const data = await response.json();

    if (!data.success) {
      errorEl.textContent = data.message || 'That organization could not be created.';
      errorEl.style.display = 'block';
      btn.disabled = false;
      btn.textContent = 'Create organization';
      return;
    }

    location.reload();
  } catch (err) {
    errorEl.textContent = 'Something went wrong. Please try again.';
    errorEl.style.display = 'block';
    btn.disabled = false;
    btn.textContent = 'Create organization';
  }
});
