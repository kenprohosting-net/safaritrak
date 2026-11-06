(() => {
  const createDialog = document.getElementById('createGroupDialog');
  const inviteDialog = document.getElementById('inviteGroupDialog');
  const groupId = document.getElementById('selectedGroupConfig')?.dataset.groupId;
  const messages = document.getElementById('groupMessages');
  const status = document.getElementById('groupChatStatus');
  let refreshTimer;

  function showDialog(dialog) {
    if (dialog) dialog.hidden = false;
  }

  function hideDialog(dialog) {
    if (dialog) dialog.hidden = true;
  }

  document.getElementById('createGroupOpen')?.addEventListener('click', () => showDialog(createDialog));
  document.getElementById('groupInviteOpen')?.addEventListener('click', () => showDialog(inviteDialog));
  document.querySelectorAll('[data-close-group-dialog]').forEach(button => {
    button.addEventListener('click', () => hideDialog(button.closest('.groups-dialog')));
  });
  [createDialog, inviteDialog].forEach(dialog => dialog?.addEventListener('click', event => {
    if (event.target === dialog) hideDialog(dialog);
  }));
  document.getElementById('groupOrganization')?.addEventListener('change', event => {
    document.getElementById('organizationGroupMembers').hidden = !event.currentTarget.checked;
  });

  async function postJson(url, body) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.message || `Request failed (HTTP ${response.status}).`);
    }
    return payload;
  }

  document.getElementById('createGroupForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const feedback = document.getElementById('createGroupStatus');
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    feedback.textContent = 'Creating group…';
    try {
      const result = await postJson('backend/api/groups/persistent/create.php', {
        name: document.getElementById('groupName').value.trim(),
        description: document.getElementById('groupDescription').value.trim(),
        organization_id: document.getElementById('groupOrganization')?.checked
          ? document.getElementById('groupOrganization').value
          : null,
        member_ids: Array.from(document.querySelectorAll('.organization-group-member:checked'))
          .map(input => Number.parseInt(input.value, 10)),
      });
      location.href = `groups.php?id=${encodeURIComponent(result.group_id)}`;
    } catch (error) {
      feedback.textContent = error.message;
      button.disabled = false;
    }
  });

  document.getElementById('groupInviteForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const feedback = document.getElementById('groupInviteStatus');
    const button = event.currentTarget.querySelector('[type="submit"]');
    button.disabled = true;
    feedback.textContent = 'Creating link…';
    try {
      const result = await postJson('backend/api/groups/persistent/invite.php', {
        group_id: groupId,
        email: document.getElementById('groupInviteEmail').value.trim(),
      });
      document.getElementById('groupInviteUrl').value = result.invite_url;
      document.getElementById('emailGroupInvite').href =
        `mailto:${encodeURIComponent(document.getElementById('groupInviteEmail').value.trim())}?subject=${encodeURIComponent('Join my SafariTrak group')}&body=${encodeURIComponent(`Join my group on SafariTrak: ${result.invite_url}`)}`;
      document.getElementById('groupInviteResult').hidden = false;
      feedback.textContent = 'Invitation link ready.';
    } catch (error) {
      feedback.textContent = error.message;
    } finally {
      button.disabled = false;
    }
  });

  document.getElementById('copyGroupInvite')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    const input = document.getElementById('groupInviteUrl');
    try {
      await navigator.clipboard.writeText(input.value);
      button.textContent = 'Copied';
    } catch (error) {
      input.select();
      document.execCommand('copy');
      button.textContent = 'Copy link';
      document.getElementById('groupInviteStatus').textContent = 'Select and copy the link from the field.';
    }
  });

  function renderMessages(items) {
    const fragment = document.createDocumentFragment();
    if (!items.length) {
      const empty = document.createElement('li');
      empty.className = 'groups-chat-empty';
      empty.textContent = 'No messages yet. Say hello to your group.';
      fragment.append(empty);
    }
    for (const item of items) {
      const row = document.createElement('li');
      row.className = `groups-chat-message${item.is_mine ? ' is-mine' : ''}`;
      const author = document.createElement('strong');
      author.textContent = item.is_mine ? 'You' : item.sender_name;
      const body = document.createElement('p');
      body.textContent = item.body;
      const time = document.createElement('small');
      time.textContent = new Date(item.created_at.replace(' ', 'T')).toLocaleString([], {
        hour: '2-digit',
        minute: '2-digit',
      });
      row.append(author, body, time);
      fragment.append(row);
    }
    messages.replaceChildren(fragment);
    messages.scrollTop = messages.scrollHeight;
  }

  async function refreshMessages() {
    if (!groupId || !messages) return;
    try {
      const url = new URL('backend/api/groups/persistent/messages.php', location.href);
      url.searchParams.set('group_id', groupId);
      const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
      const payload = await response.json();
      if (!response.ok || !payload.success || !Array.isArray(payload.messages)) {
        throw new Error(payload.message || 'Group messages could not be loaded.');
      }
      renderMessages(payload.messages);
      status.textContent = '';
    } catch (error) {
      status.textContent = error.message;
    }
  }

  document.getElementById('groupMessageForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const input = document.getElementById('groupMessageInput');
    const button = event.currentTarget.querySelector('button');
    if (!input.value.trim()) return;
    button.disabled = true;
    try {
      await postJson('backend/api/groups/persistent/messages.php', {
        group_id: groupId,
        body: input.value.trim(),
      });
      input.value = '';
      await refreshMessages();
    } catch (error) {
      status.textContent = error.message;
    } finally {
      button.disabled = false;
      input.focus();
    }
  });

  if (groupId && messages) {
    refreshMessages();
    refreshTimer = window.setInterval(refreshMessages, 10000);
    window.addEventListener('pagehide', () => window.clearInterval(refreshTimer), { once: true });
  }
})();
