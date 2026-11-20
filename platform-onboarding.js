document.getElementById('claimOwnershipBtn')?.addEventListener('click', async () => {
  const btn = document.getElementById('claimOwnershipBtn');
  const errorEl = document.getElementById('claimError');
  const tokenInput = document.getElementById('claimBootstrapToken');
  const bootstrapToken = tokenInput.value.trim();

  errorEl.style.display = 'none';
  if (!bootstrapToken) {
    errorEl.textContent = 'Enter the one-time platform setup key.';
    errorEl.style.display = 'block';
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Claiming...';

  try {
    const response = await fetch('backend/api/platform/claim.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ bootstrap_token: bootstrapToken }),
    });
    const data = await response.json();

    if (!data.success) {
      errorEl.textContent = data.message || 'That could not be completed.';
      errorEl.style.display = 'block';
      btn.disabled = false;
      btn.textContent = 'Claim ownership';
      return;
    }

    location.reload();
  } catch (err) {
    errorEl.textContent = 'Something went wrong. Please try again.';
    errorEl.style.display = 'block';
    btn.disabled = false;
    btn.textContent = 'Claim ownership';
  }
});
