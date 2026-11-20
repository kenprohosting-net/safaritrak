let conversations = [];
let activeUserId = null;
let conversationFilter = 'all';
let threadRefreshTimer = null;
let liveLocationRefreshTimer = null;
let threadRefreshInFlight = false;
let activeSharedLocation = null;
let conversationMapFeatures = [];
let conversationMapFeatureKey = '';
let activeThreadMessages = [];
let activeThreadReady = false;

function isValidLocation(location) {
  return location && location.lat !== null && location.lng !== null
    && Number.isFinite(Number(location.lat))
    && Number.isFinite(Number(location.lng))
    && Number(location.lat) >= -90
    && Number(location.lat) <= 90
    && Number(location.lng) >= -180
    && Number(location.lng) <= 180;
}

function messagePreviewText(message) {
  if (message.message_type === 'photo') return `Photo · ${message.trip_label || 'Trip'}`;
  if (message.message_type === 'location') return 'Shared a location point.';
  return message.body || '';
}

function messageLocationFeature(message) {
  if (message.message_type !== 'location' || !isValidLocation(message.location)) return null;
  const isMine = Boolean(message.is_mine);
  return {
    id: `message-${message.id}`,
    kind: 'shared-location',
    lat: Number(message.location.lat),
    lng: Number(message.location.lng),
    label: isMine ? 'You' : (conversations.find(item => item.user_id === activeUserId)?.full_name || 'Contact'),
    description: `Location shared by ${isMine ? 'you' : (conversations.find(item => item.user_id === activeUserId)?.full_name || 'your contact')}.`,
    created_at: message.created_at || '',
  };
}

const chatPhotoFile = document.getElementById('chatPhotoFile');
const chatPhotoDetails = document.getElementById('chatPhotoDetails');
const chatPhotoPreview = document.getElementById('chatPhotoPreview');
let chatPhotoPreviewUrl = '';

function clearChatPhoto() {
  if (chatPhotoPreviewUrl) URL.revokeObjectURL(chatPhotoPreviewUrl);
  chatPhotoPreviewUrl = '';
  if (chatPhotoFile) chatPhotoFile.value = '';
  const tripLabel = document.getElementById('chatPhotoTripLabel');
  const caption = document.getElementById('chatPhotoCaption');
  if (tripLabel) tripLabel.value = '';
  if (caption) caption.value = '';
  if (chatPhotoPreview) {
    chatPhotoPreview.removeAttribute('src');
    chatPhotoPreview.hidden = true;
  }
  if (chatPhotoDetails) chatPhotoDetails.hidden = true;
}

document.getElementById('attachChatPhoto')?.addEventListener('click', () => chatPhotoFile?.click());
chatPhotoFile?.addEventListener('change', () => {
  const file = chatPhotoFile.files?.[0];
  if (!file) return;
  if (file.size > 6 * 1024 * 1024) {
    window.SafariTrakUI?.setStatus(document.getElementById('chatNotice'), {
      state: 'error',
      title: 'Photo too large',
      message: 'Choose a photo no larger than 6 MB.',
    });
    clearChatPhoto();
    return;
  }
  if (chatPhotoPreviewUrl) URL.revokeObjectURL(chatPhotoPreviewUrl);
  chatPhotoPreviewUrl = URL.createObjectURL(file);
  chatPhotoPreview.src = chatPhotoPreviewUrl;
  chatPhotoPreview.hidden = false;
  chatPhotoDetails.hidden = false;
  document.getElementById('chatPhotoTripLabel')?.focus();
});
document.getElementById('cancelChatPhoto')?.addEventListener('click', clearChatPhoto);
document.getElementById('sendChatPhoto')?.addEventListener('click', async event => {
  const targetUserId = activeUserId;
  const file = chatPhotoFile?.files?.[0];
  const tripLabel = document.getElementById('chatPhotoTripLabel');
  const caption = document.getElementById('chatPhotoCaption');
  const button = event.currentTarget;
  const notice = document.getElementById('chatNotice');
  if (!targetUserId || !file || !tripLabel || !caption) return;
  tripLabel.value = tripLabel.value.trim();
  if (!tripLabel.reportValidity()) return;

  const formData = new FormData();
  formData.append('to', String(targetUserId));
  formData.append('trip_label', tripLabel.value);
  formData.append('caption', caption.value.trim());
  formData.append('photo', file);
  button.disabled = true;
  try {
    const response = await fetch('backend/api/messages/send-photo.php', {
      method: 'POST',
      body: formData,
    });
    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || 'The photo could not be shared.');
    }
    clearChatPhoto();
    if (targetUserId === activeUserId) {
      await refreshActiveThread();
      await loadConversations(false);
    } else {
      await loadConversations(false);
    }
    notice?.replaceChildren();
  } catch (error) {
    window.SafariTrakUI?.setStatus(notice, {
      state: navigator.onLine === false ? 'offline' : 'error',
      title: 'Photo not shared',
      message: error.message || 'The photo could not be shared.',
    });
  } finally {
    button.disabled = false;
  }
});

function updateConversationMapFeatures(messages, focusOnChange = false) {
  const latestByOwner = new Map();
  messages.forEach(message => {
    const feature = messageLocationFeature(message);
    if (feature) latestByOwner.set(message.is_mine ? 'self' : 'contact', feature);
  });
  if (activeSharedLocation && isValidLocation(activeSharedLocation)) {
    const directFeature = latestByOwner.get('contact');
    const directTimestamp = Date.parse(String(directFeature?.created_at || '').replace(' ', 'T')) || 0;
    const liveTimestamp = Date.parse(String(activeSharedLocation.recorded_at || '')) || 0;
    if (!directFeature || liveTimestamp >= directTimestamp) {
      latestByOwner.set('contact', {
        id: `journey-${activeSharedLocation.journey_id}`,
        kind: 'shared-location',
        lat: Number(activeSharedLocation.lat),
        lng: Number(activeSharedLocation.lng),
        label: activeSharedLocation.name || 'Live trip',
        description: 'Live trip location shared with you.',
        created_at: activeSharedLocation.recorded_at || '',
      });
    }
  }

  conversationMapFeatures = [...latestByOwner.values()];
  const nextKey = JSON.stringify(conversationMapFeatures.map(feature => [
    feature.id, feature.lat, feature.lng, feature.label,
  ]));
  const changed = nextKey !== conversationMapFeatureKey;
  conversationMapFeatureKey = nextKey;
  updateLocationCard();

  if (changed && conversationMapFeatures.length && focusOnChange) {
    window.SafariTrakPageMap?.focusFeatures(conversationMapFeatures);
  } else {
    window.SafariTrakPageMap?.setFeatures(conversationMapFeatures);
  }
}

function updateLocationCard() {
  const card = document.getElementById('chatLocationCard');
  const summary = document.getElementById('sharedLocationSummary');
  const empty = document.getElementById('sharedLocationEmpty');
  const contactPin = document.getElementById('contactMapPin');
  const selfPin = document.getElementById('selfMapPin');
  if (!card || !summary || !empty || !contactPin || !selfPin) return;

  card.hidden = !activeUserId || !activeThreadReady;
  const contactFeature = conversationMapFeatures.find(feature => feature.label !== 'You');
  const selfFeature = conversationMapFeatures.find(feature => feature.label === 'You');
  contactPin.hidden = !contactFeature;
  selfPin.hidden = !selfFeature;
  empty.hidden = conversationMapFeatures.length > 0;
  summary.textContent = conversationMapFeatures.length
    ? `${conversationMapFeatures.length} location ${conversationMapFeatures.length === 1 ? 'pin' : 'pins'} shared in this conversation.`
    : 'No locations shared in this conversation yet.';
}

function formatChatListTime(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString.replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return '';
  const today = new Date();
  if (date.toDateString() === today.toDateString()) {
    return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  }
  const yesterday = new Date(today);
  yesterday.setDate(today.getDate() - 1);
  if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
  return date.toLocaleDateString([], { month: 'numeric', day: 'numeric', year: 'numeric' });
}

function formatMessageTime(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString.replace(' ', 'T'));
  return Number.isNaN(date.getTime())
    ? ''
    : date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
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
  const unreadTotal = conversations.reduce((total, conversation) => total + conversation.unread_count, 0);
  const unreadCount = document.getElementById('unreadConversationCount');
  unreadCount.textContent = unreadTotal ? String(unreadTotal) : '';
  document.querySelectorAll('[data-message-filter]').forEach(button => {
    const active = button.dataset.messageFilter === conversationFilter;
    button.classList.toggle('active', active);
    button.setAttribute('aria-pressed', active ? 'true' : 'false');
  });

  if (conversations.length === 0) {
    window.SafariTrakUI?.setStatus(container, {
      state: 'empty',
      title: 'No conversations yet',
      message: 'Add a confirmed trusted person to start messaging.',
      linkLabel: 'Manage trusted people',
      linkHref: 'trusted-contacts.php',
    });
    container.removeAttribute('aria-busy');
    return;
  }

  container.replaceChildren();
  const searchTerm = document.getElementById('chatSearch').value.trim().toLowerCase();
  const filteredConversations = conversations.filter(conversation => {
    if (conversationFilter === 'unread' && conversation.unread_count < 1) return false;
    return !searchTerm
      || `${conversation.full_name} ${conversation.last_message || ''}`.toLowerCase().includes(searchTerm);
  });

  if (filteredConversations.length === 0) {
    const item = document.createElement('li');
    item.className = 'chat-list-empty';
    item.textContent = searchTerm
      ? 'No conversations match your search.'
      : 'No unread conversations.';
    container.append(item);
    container.removeAttribute('aria-busy');
    document.getElementById('chatSearchStatus').textContent = item.textContent;
    return;
  }

  filteredConversations.forEach(conversation => {
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
    const info = document.createElement('span');
    info.className = 'chat-item-info';
    const time = document.createElement('time');
    time.className = 'chat-item-time';
    time.dateTime = conversation.last_message_at || '';
    time.textContent = formatChatListTime(conversation.last_message_at);
    info.append(time);

    if (conversation.unread_count > 0) {
      const unread = document.createElement('em');
      unread.textContent = String(conversation.unread_count);
      unread.setAttribute('aria-label', `${conversation.unread_count} unread messages`);
      info.append(unread);
    }
    button.append(avatar, meta, info);
    item.append(button);
    container.append(item);
  });
  container.removeAttribute('aria-busy');
  document.getElementById('chatSearchStatus').textContent = searchTerm
    ? `${filteredConversations.length} ${filteredConversations.length === 1 ? 'conversation' : 'conversations'} found.`
    : '';
}

async function loadConversations(initialLoad = true) {
  const container = document.getElementById('chatListItems');
  if (initialLoad) {
    window.SafariTrakUI?.setStatus(container, {
      state: 'loading',
      title: 'Loading conversations',
      message: 'Checking your trusted people and organization admins.',
    });
  }
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

    if (initialLoad) {
      const params = new URLSearchParams(window.location.search);
      const preselect = Number(params.get('to'));
      if (Number.isSafeInteger(preselect) && preselect > 0) {
        openThread(preselect);
      } else if (conversations.length > 0) {
        openThread(conversations[0].user_id);
      }
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
  if (activeUserId !== userId) clearChatPhoto();
  activeUserId = userId;
  activeThreadReady = false;
  activeThreadMessages = [];
  activeSharedLocation = null;
  conversationMapFeatureKey = '';
  conversationMapFeatures = [];
  window.SafariTrakPageMap?.setFeatures([]);
  updateLocationCard();
  window.clearInterval(threadRefreshTimer);
  window.clearInterval(liveLocationRefreshTimer);
  renderConversationList();

  const chatHead = document.getElementById('chatHead');
  const chatMessages = document.getElementById('chatMessages');
  const chatForm = document.getElementById('chatForm');
  chatForm.style.display = 'none';

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
    const backButton = document.createElement('button');
    backButton.type = 'button';
    backButton.className = 'chat-back-button';
    backButton.setAttribute('aria-label', 'Back to conversations');
    backButton.innerHTML = '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i>';
    backButton.addEventListener('click', () => document.querySelector('.chat-shell')?.classList.remove('chat-thread-open'));
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
    const identity = document.createElement('span');
    identity.className = 'chat-head-identity';
    const subtitle = document.createElement('small');
    subtitle.textContent = 'Private conversation';
    identity.append(name, subtitle);
    chatHead.append(backButton, person, identity);

    chatForm.style.display = 'flex';
    document.querySelector('.chat-shell')?.classList.add('chat-thread-open');
    activeThreadReady = true;
    updateLocationCard();
    activeThreadMessages = data.messages;
    renderMessages(data.messages);
    updateConversationMapFeatures(data.messages, true);
    window.clearInterval(threadRefreshTimer);
    threadRefreshTimer = window.setInterval(refreshActiveThread, 5000);
    loadActiveSharedLocation(userId, true);
    liveLocationRefreshTimer = window.setInterval(() => loadActiveSharedLocation(userId), 15000);

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

function renderMessages(messages, shouldScroll = true) {
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

    const body = document.createElement('span');
    body.className = 'bubble-body';
    const locationFeature = messageLocationFeature(message);
    if (message.message_type === 'photo') {
      const photoUrl = localAvatarPath(message.attachment_url);
      if (photoUrl) {
        const figure = document.createElement('figure');
        figure.className = 'bubble-photo';
        const image = document.createElement('img');
        image.src = photoUrl;
        image.alt = `Photo from ${message.trip_label || 'a shared trip'}`;
        image.loading = 'lazy';
        const label = document.createElement('figcaption');
        label.textContent = `Trip · ${message.trip_label || 'Trip'}`;
        figure.append(image, label);
        body.append(figure);
      }
      if (message.body) {
        const caption = document.createElement('span');
        caption.className = 'bubble-photo-caption';
        caption.textContent = message.body;
        body.append(caption);
      }
    } else {
      body.textContent = locationFeature ? 'Shared a location point.' : message.body;
    }
    if (locationFeature) {
      const locationLink = document.createElement('button');
      locationLink.type = 'button';
      locationLink.className = 'bubble-location-preview';
      locationLink.innerHTML = '<i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>View shared location</span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>';
      locationLink.addEventListener('click', () => {
        window.SafariTrakPageMap?.focusFeatures(conversationMapFeatures.length
          ? conversationMapFeatures
          : [locationFeature]);
      });
      body.append(locationLink);
    }
    const footer = document.createElement('span');
    footer.className = 'bubble-meta';
    const time = document.createElement('time');
    time.dateTime = message.created_at || '';
    time.textContent = formatMessageTime(message.created_at);
    footer.append(time);
    if (message.is_mine) {
      const readStatus = document.createElement('i');
      readStatus.className = message.read_at ? 'fa-solid fa-check-double' : 'fa-solid fa-check';
      readStatus.setAttribute('aria-label', message.read_at ? 'Read' : 'Sent');
      footer.append(readStatus);
    }
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
    bubble.append(body, footer, remove);
    chatMessages.append(bubble);
  });

  if (shouldScroll) chatMessages.scrollTop = chatMessages.scrollHeight;
}

async function loadActiveSharedLocation(userId, focusOnChange = false) {
  try {
    const response = await fetch('backend/api/journeys/active-shared-locations.php', {
      cache: 'no-store',
    });
    const data = await response.json();
    if (userId !== activeUserId) return;
    if (!response.ok || !data.success || !Array.isArray(data.locations)) {
      throw new Error(data.message || 'Live location is temporarily unavailable.');
    }

    const matchingLocations = data.locations
      .filter(location => Number(location.owner_id) === userId && isValidLocation(location))
      .sort((a, b) => Date.parse(String(b.recorded_at || '')) - Date.parse(String(a.recorded_at || '')));
    activeSharedLocation = matchingLocations[0] || null;
    updateConversationMapFeatures(activeThreadMessages, focusOnChange);
  } catch (error) {
    if (userId !== activeUserId) return;
    if (!conversationMapFeatures.length) {
      const summary = document.getElementById('sharedLocationSummary');
      if (summary) summary.textContent = 'Live location could not be refreshed.';
    }
  }
}

async function refreshActiveThread() {
  if (!activeUserId || threadRefreshInFlight || document.hidden) return;
  threadRefreshInFlight = true;
  const chatMessages = document.getElementById('chatMessages');
  const oldScrollTop = chatMessages.scrollTop;
  const wasAtBottom = chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 80;
  const userId = activeUserId;
  try {
    const response = await fetch('backend/api/messages/thread.php?with=' + encodeURIComponent(userId), {
      cache: 'no-store',
    });
    const data = await response.json();
    if (userId !== activeUserId) return;
    if (!response.ok || !data.success || !Array.isArray(data.messages)) {
      window.SafariTrakUI?.setStatus(document.getElementById('chatNotice'), {
        state: 'error',
        title: 'Messages could not be refreshed',
        message: data.message || 'This conversation is temporarily unavailable.',
      });
      return;
    }
    activeThreadMessages = data.messages;
    renderMessages(data.messages, false);
    updateConversationMapFeatures(data.messages, true);
    const conversation = conversations.find(item => item.user_id === userId);
    const lastMessage = data.messages[data.messages.length - 1];
    if (conversation && lastMessage) {
      conversation.last_message = messagePreviewText(lastMessage);
      conversation.last_message_mine = Boolean(lastMessage.is_mine);
      conversation.last_message_at = lastMessage.created_at;
      conversations.sort((a, b) => (b.last_message_at || '').localeCompare(a.last_message_at || ''));
      renderConversationList();
    }
    chatMessages.scrollTop = wasAtBottom
      ? chatMessages.scrollHeight
      : Math.min(oldScrollTop, chatMessages.scrollHeight);
  } catch (error) {
    window.SafariTrakUI?.setStatus(document.getElementById('chatNotice'), {
      state: navigator.onLine === false ? 'offline' : 'error',
      title: navigator.onLine === false ? 'You are offline' : 'Messages could not be refreshed',
      message: navigator.onLine === false ? 'Reconnect to receive new messages.' : 'Try again in a moment.',
    });
  } finally {
    threadRefreshInFlight = false;
  }
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
    await refreshActiveThread();
  } catch (err) {
    alert('An error occurred while deleting the message.');
  }
}

document.getElementById('chatForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const input = document.getElementById('chatInput');
  const text = input.value.trim();
  if (!text || !activeUserId) return;
  const targetUserId = activeUserId;

  const notice = document.getElementById('chatNotice');
  notice?.replaceChildren();
  input.disabled = true;

  try {
    const response = await fetch('backend/api/messages/send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ to: targetUserId, body: text }),
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

    if (targetUserId === activeUserId) {
      await refreshActiveThread();
    } else {
      await loadConversations(false);
    }

    input.value = '';
    notice?.replaceChildren();

    const conv = conversations.find(c => c.user_id === targetUserId);
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

document.getElementById('shareCurrentLocation')?.addEventListener('click', () => {
  const button = document.getElementById('shareCurrentLocation');
  const notice = document.getElementById('chatNotice');
  if (!activeUserId || !button) return;
  const targetUserId = activeUserId;
  if (!navigator.geolocation) {
    window.SafariTrakUI?.setStatus(notice, {
      state: 'error',
      title: 'Location unavailable',
      message: 'This browser does not support location sharing.',
    });
    return;
  }

  button.disabled = true;
  button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>Getting your location…';
  navigator.geolocation.getCurrentPosition(async position => {
    if (targetUserId !== activeUserId) {
      button.disabled = false;
      button.innerHTML = '<i class="fa-solid fa-location-dot" aria-hidden="true"></i>Share current location';
      return;
    }
    try {
      const response = await fetch('backend/api/messages/send.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          to: targetUserId,
          type: 'location',
          location: {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
          },
        }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || 'That location could not be shared.');
      }

      notice?.replaceChildren();
      if (targetUserId === activeUserId) {
        await refreshActiveThread();
      } else {
        await loadConversations(false);
      }
    } catch (error) {
      window.SafariTrakUI?.setStatus(notice, {
        state: navigator.onLine === false ? 'offline' : 'error',
        title: 'Location not shared',
        message: error.message || 'That location could not be shared.',
      });
    } finally {
      button.disabled = false;
      button.innerHTML = '<i class="fa-solid fa-location-dot" aria-hidden="true"></i>Share current location';
    }
  }, error => {
    const message = error.code === error.PERMISSION_DENIED
      ? 'Allow location access to share your current location.'
      : 'Your current location could not be determined. Try again.';
    window.SafariTrakUI?.setStatus(notice, {
      state: 'error',
      title: 'Location not shared',
      message,
    });
    button.disabled = false;
    button.innerHTML = '<i class="fa-solid fa-location-dot" aria-hidden="true"></i>Share current location';
  }, {
    enableHighAccuracy: true,
    timeout: 12000,
    maximumAge: 30000,
  });
});

document.getElementById('openSharedLocationMap')?.addEventListener('click', () => {
  if (!conversationMapFeatures.length) return;
  window.SafariTrakPageMap?.focusFeatures(conversationMapFeatures);
});

document.getElementById('chatSearch')?.addEventListener('input', () => {
  renderConversationList();
});

document.querySelectorAll('[data-message-filter]').forEach(button => {
  button.addEventListener('click', () => {
    conversationFilter = button.dataset.messageFilter === 'unread' ? 'unread' : 'all';
    renderConversationList();
  });
});

loadConversations();
window.setInterval(() => {
  if (!document.hidden) loadConversations(false);
}, 20000);