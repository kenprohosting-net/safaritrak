const notifIconMap = {
  journey_started: 'fa-route',
  journey_completed: 'fa-flag-checkered',
  arrival: 'fa-flag-checkered',
  route_deviation: 'fa-triangle-exclamation',
  new_message: 'fa-regular fa-message',
  location_share: 'fa-location-arrow',
  sos_alert: 'fa-triangle-exclamation',
  contact_request: 'fa-user-plus',
  group_invite: 'fa-user-group',
};

function notifRelativeTime(dateString) {
  const then = new Date(dateString.replace(' ', 'T'));
  const diffMs = Date.now() - then.getTime();
  const diffMin = Math.floor(diffMs / 60000);

  if (diffMin < 1) return 'Just now';
  if (diffMin < 60) return diffMin + ' min ago';
  const diffHr = Math.floor(diffMin / 60);
  if (diffHr < 24) return diffHr + (diffHr === 1 ? ' hour ago' : ' hours ago');
  const diffDay = Math.floor(diffHr / 24);
  if (diffDay === 1) return 'Yesterday';
  if (diffDay < 7) return diffDay + ' days ago';
  return then.toLocaleDateString();
}

function notifLink(n) {
  switch (n.type) {
    case 'location_share':
    case 'journey_started':
      return n.related_journey_id ? 'watch-journey.php?id=' + n.related_journey_id : null;
    case 'journey_completed':
    case 'arrival':
    case 'route_deviation':
      return n.related_journey_id ? 'watch-journey.php?id=' + n.related_journey_id : 'my-journeys.php';
    case 'new_message':
      return n.related_user_id ? 'messages.php?to=' + n.related_user_id : 'messages.php';
    case 'contact_request':
      return 'trusted-contacts.php';
    case 'group_invite':
      return 'groups.php?tab=trips';
    case 'sos_alert':
      return 'notifications.php';
    default:
      return null;
  }
}

async function loadNotifDropdown() {
  const list = document.getElementById('notifDropdownList');
  const dot = document.getElementById('notifDot');
  if (!list) return;

  const setStatus = (state, title, message, retryLabel) => {
    window.SafariTrakUI?.setStatus(list, {
      state,
      title,
      message,
      retryLabel,
      onRetry: loadNotifDropdown,
    });
  };
  setStatus('loading', 'Loading notifications', 'Checking for recent updates.');

  try {
    const response = await fetch('backend/api/notifications/list.php?limit=6');
    const data = await response.json();

    if (!response.ok || !data.success || !Array.isArray(data.notifications)) {
      setStatus('error', 'Notifications unavailable', data.message || 'We could not load recent updates.', 'Try again');
      return;
    }

    if (dot) {
      dot.style.display = data.unread_count > 0 ? 'block' : 'none';
    }

    if (data.notifications.length === 0) {
      setStatus('empty', 'Nothing here yet', 'Journey updates and messages will appear here.');
      return;
    }

    list.innerHTML = data.notifications.map(n => {
      const iconClass = notifIconMap[n.type] || 'fa-bell';
      const iconTag = iconClass.startsWith('fa-regular') ? iconClass : 'fa-solid ' + iconClass;
      const sosClass = n.type === 'sos_alert' ? ' sos' : '';
      const unreadClass = n.is_read ? '' : ' unread';
      const inner = '<i class="' + iconTag + '"></i><div><b>' + escapeHtml(n.title) + '</b><small>' + notifRelativeTime(n.created_at) + '</small></div>';
      const link = notifLink(n);
      const tag = link ? 'a href="' + link + '"' : 'div';
      const closeTag = link ? 'a' : 'div';
      return '<' + tag + ' class="notif-item' + unreadClass + sosClass + '" style="text-decoration:none;color:inherit">' + inner + '</' + closeTag + '>';
    }).join('');
    list.removeAttribute('aria-busy');
  } catch (err) {
    const offline = navigator.onLine === false;
    setStatus(
      offline ? 'offline' : 'error',
      offline ? 'You are offline' : 'Notifications unavailable',
      offline ? 'Reconnect to load recent updates.' : 'We could not load recent updates.',
      'Try again'
    );
  }
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

document.getElementById('notifBell')?.addEventListener('click', async () => {
  const dropdown = document.getElementById('notifDropdown');
  if (dropdown && dropdown.classList.contains('open')) {
    try {
      await fetch('backend/api/notifications/mark-read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ all: true }),
      });
      document.getElementById('notifDot')?.style.setProperty('display', 'none');
    } catch (err) {
      const list = document.getElementById('notifDropdownList');
      window.SafariTrakUI?.setStatus(list, {
        state: navigator.onLine === false ? 'offline' : 'error',
        title: 'Could not update notifications',
        message: 'Your unread updates may still be marked unread.',
        retryLabel: 'Try again',
        onRetry: loadNotifDropdown,
      });
    }
  }
});

loadNotifDropdown();
