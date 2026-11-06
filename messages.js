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
  return (name || '').split(/\s+/).slice(0, 2).map(p => p.charAt(0).toUpperCase()).join('') || '?';
}

function localAvatarPath(path) {
  if (typeof path !== 'string' || path === '') return '';
  try {
    const url = new URL(path, window.location.href);
    return url.origin === window.location.origin ? url.href : '';
  } catch (error) {
    return '';
  }
}

function renderConversationList() {
  const container = document.getElementById('chatListItems');

  if (conversations.length === 0) {
    window.SafariTrakUI?.setStatus(container, {
      state: 'empty',
      title: 'No conversations yet',
      message: 'Add a confirmed trusted person to start messaging.',
      linkLabel: 'Manage trusted people',
      linkHref: 'trusted-contacts.php',
    });
    return;
  }

  container.replaceChildren();
  conversations.forEach(conversation => {
    const item = document.createElement('li');
    const button = document.createElement('button');
    const isActive = conversation.user_id === activeUserId;
    button.className = 'chat-item' + (isActive ? ' active' : '');
    button.type = 'button';
    button.dataset.userId = String(conversation.user_id);
    button.dataset.name = conversation.full_name;
    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    button.addEventListener('click', () => openThread(conversation.user_id));

    const avatar = document.createElement('span');
    avatar.className = 'person';
    avatar.setAttribute('aria-hidden', 'true');
    const avatarPath = localAvatarPath(conversation.avatar_path);
    if (avatarPath) {
      const image = document.createElement('img');
      image.src = avatarPath;
      image.className = 'avatar-img';
      image.alt = '';
      avatar.append(image);
    } else {
      avatar.textContent = initialsOf(conversation.full_name);
    }

    const meta = document.createElement('span');
    meta.className = 'meta';
    const name = document.createElement('b');
    name.textContent = conversation.full_name;
    const preview = document.createElement('small');
    preview.textContent = conversation.last_message
      ? (conversation.last_message_mine ? 'You: ' : '') + conversation.last_message
      : 'Say hello';
    meta.append(name, preview);
    button.append(avatar, meta);

    if (conversation.unread_count > 0) {
      const unread = document.createElement('em');
      unread.textContent = String(conversation.unread_count);
      unread.setAttribute('aria-label', `${conversation.unread_count} unread messages`);
      button.append(unread);
    }
    item.append(button);
    container.append(item);
  });
  container.removeAttribute('aria-busy');
}

async function loadConversations() {
  const container = document.getElementById('chatListItems');
  window.SafariTrakUI?.setStatus(container, {
    state: 'loading',
    title: 'Loading conversations',
    message: 'Checking your trusted people.',
  });
  try {
    const response = await fetch('backend/api/messages/conversations.php');
    const data = await response.json();
    if (!response.ok || !data.success || !Array.isArray(data.conversations)) {
      window.SafariTrakUI?.setStatus(container, {
        state: 'error',
        title: 'Conversations unavailable',
        message: data.message || 'We could not load your conversations.',
        retryLabel: 'Try again',
        onRetry: loadConversations,
      });
      return;
    }

    conversations = data.conversations
      .filter(conversation => Number.isSafeInteger(Number(conversation.user_id)) && Number(conversation.user_id) > 0)
      .map(conversation => ({ ...conversation, user_id: Number(conversation.user_id) }));
    renderConversationList();

    const params = new URLSearchParams(window.location.search);
    const preselect = params.get('to');

    if (preselect) {
      openThread(parseInt(preselect, 10));
    } else if (conversations.length > 0) {
      openThread(conversations[0].user_id);
    }
  } catch (err) {
    const offline = navigator.onLine === false;
    window.SafariTrakUI?.setStatus(container, {
      state: offline ? 'offline' : 'error',
      title: offline ? 'You are offline' : 'Conversations unavailable',
      message: offline ? 'Reconnect to load your conversations.' : 'We could not load your conversations.',
      retryLabel: 'Try again',
      onRetry: loadConversations,
    });
  }
}

async function openThread(userId) {
  activeUserId = userId;
  renderConversationList();

  const chatHead = document.getElementById('chatHead');
  const chatMessages = document.getElementById('chatMessages');
  const chatForm = document.getElementById('chatForm');

  window.SafariTrakUI?.setStatus(chatMessages, {
    state: 'loading',
    title: 'Loading messages',
    message: 'Opening this conversation.',
  });

  try {
    const response = await fetch('backend/api/messages/thread.php?with=' + userId);
    const data = await response.json();

    if (userId !== activeUserId) return;
    if (!response.ok || !data.success || !Array.isArray(data.messages) || !data.other) {
      window.SafariTrakUI?.setStatus(chatMessages, {
        state: navigator.onLine === false ? 'offline' : 'error',
        title: 'Conversation unavailable',
        message: data.message || 'We could not load this conversation.',
        retryLabel: 'Try again',
        onRetry: () => openThread(userId),
      });
      chatForm.style.display = 'none';
      return;
    }

    chatHead.replaceChildren();
    const person = document.createElement('span');
    person.className = 'person';
    const avatarPath = localAvatarPath(data.other.avatar_path);
    if (avatarPath) {
      const image = document.createElement('img');
      image.src = avatarPath;
      image.className = 'avatar-img';
      image.alt = '';
      person.append(image);
    } else {
      person.textContent = initialsOf(data.other.full_name);
    }
    const name = document.createElement('b');
    name.textContent = data.other.full_name;
    chatHead.append(person, name);

    chatForm.style.display = 'flex';
    renderMessages(data.messages);

    const conv = conversations.find(c => c.user_id === userId);
    if (conv) conv.unread_count = 0;
    renderConversationList();
  } catch (err) {
    if (userId !== activeUserId) return;
    const offline = navigator.onLine === false;
    window.SafariTrakUI?.setStatus(chatMessages, {
      state: offline ? 'offline' : 'error',
      title: offline ? 'You are offline' : 'Conversation unavailable',
      message: offline ? 'Reconnect to load this conversation.' : 'We could not load this conversation.',
      retryLabel: 'Try again',
      onRetry: () => openThread(userId),
    });
    chatForm.style.display = 'none';
  }
}

function renderMessages(messages) {
  const chatMessages = document.getElementById('chatMessages');

  if (messages.length === 0) {
    window.SafariTrakUI?.setStatus(chatMessages, {
      state: 'empty',
      title: 'No messages yet',
      message: 'Send a message to start this conversation.',
    });
    return;
  }

  chatMessages.replaceChildren();
  messages.forEach(message => {
    const bubble = document.createElement('div');
    bubble.className = `bubble ${message.is_mine ? 'me' : 'them'}`;
    bubble.dataset.msgId = String(message.id);
    bubble.style.display = 'flex';
    bubble.style.alignItems = 'center';
    bubble.style.justifyContent = 'space-between';
    bubble.style.gap = '8px';

    const body = document.createElement('span');
    body.textContent = message.body;
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'delete-single-msg-btn';
    remove.setAttribute('aria-label', 'Delete message');
    remove.title = 'Delete message';
    remove.style.cssText = 'background:none;border:none;color:#ef4444;cursor:pointer;font-size:12px;opacity:.7';
    const icon = document.createElement('i');
    icon.className = 'fa-solid fa-trash';
    icon.setAttribute('aria-hidden', 'true');
    remove.append(icon);
    remove.addEventListener('click', () => deleteSingleMessage(message.id));
    bubble.append(body, remove);
    chatMessages.append(bubble);
  });

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

  const notice = document.getElementById('chatNotice');
  notice?.replaceChildren();
  input.disabled = true;

  try {
    const response = await fetch('backend/api/messages/send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ to: activeUserId, body: text }),
    });
    const data = await response.json();

    if (!data.success) {
      window.SafariTrakUI?.setStatus(notice, {
        state: 'error',
        title: 'Message not sent',
        message: data.message || 'That message could not be sent.',
      });
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
    notice?.replaceChildren();

    const conv = conversations.find(c => c.user_id === activeUserId);
    if (conv) {
      conv.last_message = text;
      conv.last_message_mine = true;
      conv.last_message_at = data.created_at;
      conversations.sort((a, b) => (b.last_message_at || '').localeCompare(a.last_message_at || ''));
      renderConversationList();
    }
  } catch (err) {
    const offline = navigator.onLine === false;
    window.SafariTrakUI?.setStatus(notice, {
      state: offline ? 'offline' : 'error',
      title: offline ? 'You are offline' : 'Message not sent',
      message: offline ? 'Reconnect and try sending again.' : 'Something went wrong sending this message.',
    });
  } finally {
    input.disabled = false;
    input.focus();
  }
});

document.getElementById('chatSearch')?.addEventListener('input', (e) => {
  const term = e.target.value.trim().toLowerCase();
  const items = [...document.querySelectorAll('#chatListItems .chat-item')];
  let visibleCount = 0;
  items.forEach(item => {
    const name = (item.getAttribute('data-name') || '').toLowerCase();
    const isVisible = name.includes(term);
    item.closest('li').hidden = !isVisible;
    if (isVisible) visibleCount++;
  });
  const status = document.getElementById('chatSearchStatus');
  if (status) status.textContent = term && visibleCount === 0
    ? 'No conversations match your search.'
    : term ? `${visibleCount} ${visibleCount === 1 ? 'conversation' : 'conversations'} found.` : '';
});

loadConversations();