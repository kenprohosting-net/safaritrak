document.getElementById('claimOwnershipBtn')?.addEventListener('click', async () => {
  const btn = document.getElementById('claimOwnershipBtn');
  const errorEl = document.getElementById('claimError');

  errorEl.style.display = 'none';
  btn.disabled = true;
  btn.textContent = 'Claiming...';

  try {
    const response = await fetch('backend/api/platform/claim.php', { method: 'POST' });
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
