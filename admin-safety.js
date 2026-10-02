document.querySelectorAll('.resolve-sos-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!confirm('Mark this SOS alert as resolved? Only do this once you have confirmed the traveler is safe.')) return;

    btn.disabled = true;
    btn.textContent = 'Resolving...';

    try {
      const response = await fetch('backend/api/platform/resolve-sos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ alert_id: btn.getAttribute('data-alert-id') }),
      });
      const data = await response.json();

      if (!data.success) {
        alert(data.message || 'That could not be resolved.');
        btn.disabled = false;
        btn.textContent = 'Mark resolved';
        return;
      }

      location.reload();
    } catch (err) {
      alert('Something went wrong. Please try again.');
      btn.disabled = false;
      btn.textContent = 'Mark resolved';
    }
  });
});
