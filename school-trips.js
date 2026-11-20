document.querySelectorAll('.school-access-action').forEach(button => {
  button.addEventListener('click', async () => {
    const status = document.getElementById('schoolAccessStatus');
    const action = button.dataset.action;
    if (action === 'revoke' && !window.confirm('Stop viewing this student’s trips?')) return;
    button.disabled = true;
    status.textContent = action === 'accept'
      ? 'Accepting invitation…'
      : (action === 'revoke' ? 'Removing trip access…' : 'Declining invitation…');
    try {
      const response = await fetch('backend/api/org/respond-child-viewer.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ access_id: button.dataset.accessId, action }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'This invitation could not be updated.');
      window.location.reload();
    } catch (error) {
      status.textContent = error.message || 'This invitation could not be updated.';
      button.disabled = false;
    }
  });
});
