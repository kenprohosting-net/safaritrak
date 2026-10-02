let conversations = [];
let activeUserId = null;

function relativeChatTime(dateString) {
  if (!dateString) return '';
  const then = new Date(dateString.replace(' ', 'T'));
  const diffMin = Math.floor((Date.now() - then.getTime()) / 60000);
  if (diffMin < 1) return 'Just now';
  if (diffMin < 60) return diffMin + 'm ago';
  const diffHr = Math.floor(diffMin / 60);
  if (diffHr < 24) return diffHr + 'h ago';
  const diffDay = Math.floor(diffHr / 24);
  if (diffDay === 1) return 'Yesterday';
  return then.toLocaleDateString();
}

function initialsOf(name) {
  return name.split(/\s+/).slice(0, 2).map(p => p.charAt(0).toUpperCase()).join('') || '?';
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function renderConversationList() {
  const container = document.getElementById('chatListItems');

  if (conversations.length === 0) {
    container.innerHTML = '<p class="notif-empty">No confirmed trusted contacts yet. Add one to start messaging.</p>';
    return;
  }

  container.innerHTML = conversations.map(c => {
    const activeClass = c.user_id === activeUserId ? ' active' : '';
    const preview = c.last_message
      ? (c.last_message_mine ? 'You: ' : '') + escapeHtml(c.last_message)
      : 'Say hello';
    const avatar = c.avatar_path
      ? '<img src="' + c.avatar_path + '" class="avatar-img" alt="">'
      : initialsOf(c.full_name);
    const badge = c.unread_count > 0 ? '<em style="background:var(--gold);color:#fff;border-radius:20px;padding:2px 7px;font-size:9px;margin-left:auto">' + c.unread_count + '</em>' : '';

    return '<div class="chat-item' + activeClass + '" data-user-id="' + c.user_id + '" data-name="' + escapeHtml(c.full_name) + '">' +
      '<span class="person">' + avatar + '</span>' +
      '<div class="meta"><b>' + escapeHtml(c.full_name) + '</b><small>' + preview + '</small></div>' +
      badge +
      '</div>';
  }).join('');

  container.querySelectorAll('.chat-item').forEach(item => {
    item.addEventListener('click', () => openThread(parseInt(item.getAttribute('data-user-id'), 10)));
  });
}

async function loadConversations() {
  try {
    const response = await fetch('backend/api/messages/conversations.php');
    const data = await response.json();
    if (!data.success) return;

    conversations = data.conversations;
    renderConversationList();

    const params = new URLSearchParams(window.location.search);
    const preselect = params.get('to');

    if (preselect) {
      openThread(parseInt(preselect, 10));
    } else if (conversations.length > 0) {
      openThread(conversations[0].user_id);
    }
  } catch (err) {
    document.getElementById('chatListItems').innerHTML = '<p class="notif-empty">Could not load conversations.</p>';
  }
}

async function openThread(userId) {
  activeUserId = userId;
  renderConversationList();

  const chatHead = document.getElementById('chatHead');
  const chatMessages = document.getElementById('chatMessages');
  const chatForm = document.getElementById('chatForm');

  chatMessages.innerHTML = '<p class="notif-empty">Loading...</p>';

  try {
    const response = await fetch('backend/api/messages/thread.php?with=' + userId);
    const data = await response.json();

    if (!data.success) {
      chatMessages.innerHTML = '<p class="notif-empty">' + escapeHtml(data.message || 'Could not load this conversation.') + '</p>';
      chatForm.style.display = 'none';
      return;
    }

    const avatar = data.other.avatar_path
      ? '<img src="' + data.other.avatar_path + '" class="avatar-img" alt="">'
      : initialsOf(data.other.full_name);

    chatHead.innerHTML = `
      <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span class="person">${avatar}</span>
          <b>${escapeHtml(data.other.full_name)}</b>
        </div>
      </div>
    `;

    chatForm.style.display = 'flex';
    renderMessages(data.messages);

    const conv = conversations.find(c => c.user_id === userId);
    if (conv) conv.unread_count = 0;
    renderConversationList();
  } catch (err) {
    chatMessages.innerHTML = '<p class="notif-empty">Something went wrong loading this conversation.</p>';
  }
}

function renderMessages(messages) {
  const chatMessages = document.getElementById('chatMessages');

  if (messages.length === 0) {
    chatMessages.innerHTML = '<p class="notif-empty">No messages yet. Say hello.</p>';
    return;
  }

  chatMessages.innerHTML = messages.map(m => {
    const isMine = m.is_mine ? 'me' : 'them';
    return `
      <div class="bubble ${isMine}" data-msg-id="${m.id}" style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
        <span>${escapeHtml(m.body)}</span>
        <button type="button" class="delete-single-msg-btn" onclick="deleteSingleMessage(${m.id})" title="Delete message" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:12px; opacity:0.7;">
          <i class="fa-solid fa-trash"></i>
        </button>
      </div>
    `;
  }).join('');

  chatMessages.scrollTop = chatMessages.scrollHeight;
}

async function deleteSingleMessage(messageId) {
  if (!confirm('Delete this message?')) return;

  try {
    const response = await fetch('delete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message_id: messageId }),
    });

    const data = await response.json();

    if (!data.success) {
      alert(data.message || 'Could not delete message.');
      return;
    }

    const bubbleEl = document.querySelector(`.bubble[data-msg-id="${messageId}"]`);
    if (bubbleEl) bubbleEl.remove();
  } catch (err) {
    alert('An error occurred while deleting the message.');
  }
}

document.getElementById('chatForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const input = document.getElementById('chatInput');
  const text = input.value.trim();
  if (!text || !activeUserId) return;

  input.disabled = true;

  try {
    const response = await fetch('backend/api/messages/send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ to: activeUserId, body: text }),
    });
    const data = await response.json();

    if (!data.success) {
      alert(data.message || 'That message could not be sent.');
      return;
    }

    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages.querySelector('.notif-empty')) chatMessages.innerHTML = '';
    
    // Refresh the thread after sending to maintain correct message IDs in DOM
    const threadRes = await fetch('backend/api/messages/thread.php?with=' + activeUserId);
    const threadData = await threadRes.json();
    if (threadData.success) {
      renderMessages(threadData.messages);
    }

    input.value = '';

    const conv = conversations.find(c => c.user_id === activeUserId);
    if (conv) {
      conv.last_message = text;
      conv.last_message_mine = true;
      conv.last_message_at = data.created_at;
      conversations.sort((a, b) => (b.last_message_at || '').localeCompare(a.last_message_at || ''));
      renderConversationList();
    }
  } catch (err) {
    alert('Something went wrong sending that message. Please try again.');
  } finally {
    input.disabled = false;
    input.focus();
  }
});

document.getElementById('chatSearch')?.addEventListener('input', (e) => {
  const term = e.target.value.trim().toLowerCase();
  document.querySelectorAll('#chatListItems .chat-item').forEach(item => {
    const name = (item.getAttribute('data-name') || '').toLowerCase();
    item.style.display = name.includes(term) ? 'flex' : 'none';
  });
});

loadConversations();