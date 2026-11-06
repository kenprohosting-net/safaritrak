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
  const email = document.getElementById('addContactEmail').value.trim();
  const relationship = document.getElementById('addContactRelationship').value.trim();
  const errorEl = document.getElementById('addContactError');
  const inviteStatus = document.getElementById('addContactStatus');
  const btn = document.getElementById('sendInviteBtn');
  const actions = document.getElementById('inviteShareActions');

  errorEl.style.display = 'none';
  inviteStatus.textContent = '';
  actions.hidden = true;

  if (!name) {
    errorEl.textContent = 'Please enter their name.';
    errorEl.style.display = 'block';
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Creating link...';

  try {
    const data = await postJson('backend/api/contacts/invite.php', { name, email, relationship });

    if (!data.success) {
      errorEl.textContent = data.message || 'That contact could not be added.';
      errorEl.style.display = 'block';
      return;
    }

    document.getElementById('inviteUrl').value = data.invite_url;
    actions.hidden = false;
    if (email && data.email_sent === false) {
      inviteStatus.textContent = 'The invitation link was created, but email delivery failed. Copy it or open your email app to send it manually.';
    } else if (email && data.email_sent) {
      inviteStatus.textContent = 'Invitation link created and email sent. You can also copy or share the link.';
    } else {
      inviteStatus.textContent = 'Invitation link created. No trusted contact is added until the recipient signs up and accepts.';
    }
  } catch (error) {
    errorEl.textContent = error.message || 'The invitation could not be created. Please try again.';
    errorEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Create invitation link';
  }
});

async function copyText(text, status) {
  try {
    await navigator.clipboard.writeText(text);
    status.textContent = 'Link copied to clipboard.';
  } catch (error) {
    status.textContent = 'Clipboard access is unavailable. Select the link field and copy it manually.';
    status.focus?.();
  }
}

document.getElementById('copyInviteBtn')?.addEventListener('click', () => {
  copyText(document.getElementById('inviteUrl').value, document.getElementById('addContactStatus'));
});

document.getElementById('messageInviteBtn')?.addEventListener('click', async () => {
  const url = document.getElementById('inviteUrl').value;
  const text = `Join me on SafariTrak as a trusted contact: ${url}`;
  if (navigator.share) {
    try {
      await navigator.share({ title: 'SafariTrak invitation', text, url });
    } catch (error) {
      if (error.name !== 'AbortError') {
        document.getElementById('addContactStatus').textContent = 'Your device could not open its share options. Copy the link or use your messaging app.';
      }
    }
    return;
  }
  window.location.href = `sms:?body=${encodeURIComponent(text)}`;
});

document.getElementById('emailInviteBtn')?.addEventListener('click', () => {
  const email = document.getElementById('addContactEmail').value.trim();
  const status = document.getElementById('addContactError');
  if (!email || !document.getElementById('addContactEmail').validity.valid) {
    status.textContent = 'Enter a valid email address first, then open your email app.';
    status.style.display = 'block';
    return;
  }
  const subject = 'SafariTrak trusted-contact invitation';
  const body = `I invited you to connect as a trusted contact on SafariTrak. Sign up and accept here: ${document.getElementById('inviteUrl').value}`;
  window.location.href = `mailto:${encodeURIComponent(email)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
});

const contactSearchInput = document.getElementById('contactSearchInput');
const contactSearchStatus = document.getElementById('contactSearchStatus');
const contactSearchResults = document.getElementById('contactSearchResults');
let contactSearchTimer = null;
let contactSearchController = null;

function renderContactSearchResults(users) {
  contactSearchResults.replaceChildren();
  users.forEach(user => {
    const row = document.createElement('div');
    row.className = 'contact-search-result';
    const identity = document.createElement('div');
    identity.className = 'contact-search-identity';
    const name = document.createElement('strong');
    name.textContent = user.full_name;
    const username = document.createElement('small');
    username.textContent = `@${user.username}`;
    identity.append(name, username);
    const addButton = document.createElement('button');
    addButton.type = 'button';
    addButton.className = 'btn-ghost';
    addButton.textContent = 'Add friend';
    addButton.addEventListener('click', async () => {
      addButton.disabled = true;
      addButton.textContent = 'Sending…';
      try {
        const response = await fetch('backend/api/contacts/add.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ user_id: user.id }),
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) {
          throw new Error(payload.message || 'The contact request could not be sent.');
        }
        addButton.textContent = 'Request sent';
        contactSearchStatus.textContent = payload.message;
      } catch (error) {
        addButton.disabled = false;
        addButton.textContent = 'Add friend';
        contactSearchStatus.textContent = error.message || 'The contact request could not be sent.';
      }
    });
    row.append(identity, addButton);
    contactSearchResults.append(row);
  });
}

contactSearchInput?.addEventListener('input', () => {
  clearTimeout(contactSearchTimer);
  contactSearchController?.abort();
  contactSearchResults.replaceChildren();
  const query = contactSearchInput.value.trim();
  if (query.length < 2) {
    contactSearchStatus.textContent = query ? 'Enter at least two characters.' : '';
    return;
  }
  contactSearchStatus.textContent = 'Searching…';
  contactSearchTimer = window.setTimeout(async () => {
    contactSearchController = new AbortController();
    try {
      const url = new URL('backend/api/contacts/search.php', window.location.href);
      url.searchParams.set('q', query);
      const response = await fetch(url, {
        credentials: 'same-origin',
        signal: contactSearchController.signal,
      });
      const payload = await response.json();
      if (!response.ok || !payload.success || !Array.isArray(payload.users)) {
        throw new Error(payload.message || 'People could not be searched right now.');
      }
      renderContactSearchResults(payload.users);
      contactSearchStatus.textContent = payload.users.length
        ? 'Select Add friend to send a request.'
        : 'No people found. Check the spelling or invite them by email.';
    } catch (error) {
      if (error.name === 'AbortError') return;
      contactSearchStatus.textContent = error.message || 'People could not be searched right now.';
    }
  }, 250);
});

const liveShareStatus = document.getElementById('liveShareStatus');
const liveShareUrl = document.getElementById('liveShareUrl');
document.getElementById('createLiveShareBtn')?.addEventListener('click', async event => {
  const button = event.currentTarget;
  button.disabled = true;
  liveShareStatus.textContent = 'Creating a one-hour link…';
  try {
    const data = await postJson('backend/api/journeys/share-link.php', { action: 'create' });
    if (!data.success) throw new Error(data.message || 'Could not create a live-location link.');
    liveShareUrl.value = data.share_url;
    liveShareUrl.hidden = false;
    document.getElementById('liveShareLinkActions').hidden = false;
    document.getElementById('revokeLiveShareBtn').disabled = false;
    liveShareStatus.textContent = `Link created. It expires at ${new Date(data.expires_at.replace(' ', 'T') + 'Z').toLocaleString()} or when the journey ends.`;
  } catch (error) {
    liveShareStatus.textContent = error.message || 'Could not create a live-location link.';
  } finally {
    button.disabled = false;
  }
});

document.getElementById('revokeLiveShareBtn')?.addEventListener('click', async event => {
  const button = event.currentTarget;
  button.disabled = true;
  try {
    const data = await postJson('backend/api/journeys/share-link.php', { action: 'revoke' });
    if (!data.success) throw new Error(data.message || 'Could not revoke the link.');
    liveShareUrl.value = '';
    liveShareUrl.hidden = true;
    document.getElementById('liveShareLinkActions').hidden = true;
    liveShareStatus.textContent = data.message;
  } catch (error) {
    liveShareStatus.textContent = error.message || 'Could not revoke the link.';
    button.disabled = false;
  }
});

document.getElementById('copyLiveShareBtn')?.addEventListener('click', () => {
  copyText(liveShareUrl.value, liveShareStatus);
});

document.getElementById('shareLiveShareBtn')?.addEventListener('click', async () => {
  const url = liveShareUrl.value;
  const text = `My current SafariTrak journey location (link expires after one hour): ${url}`;
  if (navigator.share) {
    try {
      await navigator.share({ title: 'Live journey location', text, url });
    } catch (error) {
      if (error.name !== 'AbortError') liveShareStatus.textContent = 'Your device could not open its share options. Copy the link to share it manually.';
    }
  } else {
    window.location.href = `sms:?body=${encodeURIComponent(text)}`;
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
