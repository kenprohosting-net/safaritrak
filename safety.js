document.getElementById('confirmSosBtn')?.addEventListener('click', async () => {
  const btn = document.getElementById('confirmSosBtn');
  btn.disabled = true;
  btn.textContent = 'Sending...';

  const send = (lat, lng) => {
    fetch('backend/api/safety/trigger-sos.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ lat, lng }),
    })
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          alert(data.message || 'That SOS alert could not be sent.');
          btn.disabled = false;
          btn.textContent = 'Send SOS';
          return;
        }
        location.reload();
      })
      .catch(() => {
        alert('Something went wrong sending that alert. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Send SOS';
      });
  };

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (position) => send(position.coords.latitude, position.coords.longitude),
      () => send(null, null),
      { enableHighAccuracy: true, timeout: 5000 }
    );
  } else {
    send(null, null);
  }
});

document.getElementById('resolveSosBtn')?.addEventListener('click', async () => {
  if (!confirm('Let your trusted contacts know you are safe now?')) return;

  const btn = document.getElementById('resolveSosBtn');
  btn.disabled = true;

  try {
    const response = await fetch('backend/api/safety/resolve-sos.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ alert_id: window.MY_ACTIVE_ALERT_ID }),
    });
    const data = await response.json();

    if (!data.success) {
      alert(data.message || 'That could not be resolved.');
      btn.disabled = false;
      return;
    }

    location.reload();
  } catch (err) {
    alert('Something went wrong. Please try again.');
    btn.disabled = false;
  }
});

document.querySelectorAll('.safety-toggle').forEach(toggle => {
  toggle.addEventListener('change', async () => {
    const field = toggle.getAttribute('data-field');
    const value = toggle.checked;

    try {
      const response = await fetch('backend/api/safety/update-settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ field, value }),
      });
      const data = await response.json();
      if (!data.success) {
        alert(data.message || 'That could not be saved.');
        toggle.checked = !value;
      }
    } catch (err) {
      toggle.checked = !value;
      alert('Something went wrong. Please try again.');
    }
  });
});