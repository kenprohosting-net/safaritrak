(() => {
  const form = document.getElementById('exploreChatForm');
  const input = document.getElementById('exploreChatInput');
  const messages = document.getElementById('exploreChatMessages');
  const status = document.getElementById('exploreChatStatus');
  if (!form || !input || !messages || !status) return;

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const body = input.value.trim();
    if (!body) return;

    const button = form.querySelector('button');
    button.disabled = true;
    input.disabled = true;
    status.textContent = 'Sending message…';
    try {
      const response = await fetch('backend/api/groups/persistent/messages.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ group_id: Number(form.dataset.groupId), body }),
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'Your message could not be sent.');
      }

      const message = document.createElement('div');
      message.className = 'explore-chat-message is-mine';
      const avatar = document.createElement('span');
      avatar.className = 'explore-person-avatar';
      avatar.textContent = 'You';
      const content = document.createElement('span');
      const sender = document.createElement('strong');
      sender.textContent = 'You';
      const text = document.createElement('small');
      text.textContent = body;
      content.append(sender, text);
      const time = document.createElement('time');
      time.textContent = new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' }).format(new Date());
      message.append(avatar, content, time);
      messages.querySelector('.explore-empty-note')?.remove();
      messages.append(message);
      messages.scrollTop = messages.scrollHeight;
      input.value = '';
      status.textContent = 'Message sent.';
    } catch (error) {
      status.textContent = error instanceof Error ? error.message : 'Your message could not be sent.';
    } finally {
      button.disabled = false;
      input.disabled = false;
      input.focus();
    }
  });
})();
