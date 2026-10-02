async function postJson(url, body) {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return response.json();
}

document.getElementById('sendInviteBtn')?.addEventListener('click', async () => {
  const name = document.getElementById('addContactName').value.trim();
  const phone = document.getElementById('addContactPhone').value.trim();
  const relationship = document.getElementById('addContactRelationship').value.trim();
  const errorEl = document.getElementById('addContactError');
  const btn = document.getElementById('sendInviteBtn');

  errorEl.style.display = 'none';

  if (!name || !phone) {
    errorEl.textContent = 'Please enter a name and phone number.';
    errorEl.style.display = 'block';
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Sending...';

  try {
    const data = await postJson('backend/api/contacts/add.php', { name, phone, relationship });

    if (!data.success) {
      errorEl.textContent = data.message || 'That contact could not be added.';
      errorEl.style.display = 'block';
      return;
    }

    location.reload();
  } catch (err) {
    errorEl.textContent = 'Something went wrong. Please try again.';
    errorEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Send invite';
  }
});

document.querySelectorAll('.respond-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.getAttribute('data-id');
    const action = btn.getAttribute('data-action');
    btn.disabled = true;

    try {
      const data = await postJson('backend/api/contacts/respond.php', { contact_id: id, action });
      if (!data.success) {
        alert(data.message || 'That could not be completed.');
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

document.querySelectorAll('.perm-toggle').forEach(toggle => {
  toggle.addEventListener('change', async () => {
    const card = toggle.closest('.contact-card');
    const contactId = card.getAttribute('data-contact-id');
    const field = toggle.getAttribute('data-field');
    const value = toggle.checked;

    try {
      const data = await postJson('backend/api/contacts/update-permission.php', {
        contact_id: contactId,
        field,
        value,
      });
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

let contactIdToRemove = null;

document.querySelectorAll('.remove-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const card = btn.closest('.contact-card');
    contactIdToRemove = card.getAttribute('data-contact-id');
    document.getElementById('removeContactName').textContent = 'Remove ' + card.getAttribute('data-contact-name') + '?';
    document.getElementById('removeContactModal').classList.add('open');
  });
});

document.getElementById('confirmRemoveBtn')?.addEventListener('click', async () => {
  if (!contactIdToRemove) return;
  const btn = document.getElementById('confirmRemoveBtn');
  btn.disabled = true;

  try {
    const data = await postJson('backend/api/contacts/remove.php', { contact_id: contactIdToRemove });
    if (!data.success) {
      alert(data.message || 'That contact could not be removed.');
      btn.disabled = false;
      return;
    }
    location.reload();
  } catch (err) {
    alert('Something went wrong. Please try again.');
    btn.disabled = false;
  }
});
