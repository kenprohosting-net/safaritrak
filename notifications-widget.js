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
      return 'group-travel.php';
    case 'sos_alert':
      return 'safety.php';
    default:
      return null;
  }
}

async function loadNotifDropdown() {
  const list = document.getElementById('notifDropdownList');
  const dot = document.getElementById('notifDot');
  if (!list) return;

  try {
    const response = await fetch('backend/api/notifications/list.php?limit=6');
    const data = await response.json();

    if (!data.success) {
      list.innerHTML = '<p class="notif-empty">Could not load notifications.</p>';
      return;
    }

    if (dot) {
      dot.style.display = data.unread_count > 0 ? 'block' : 'none';
    }

    if (data.notifications.length === 0) {
      list.innerHTML = '<p class="notif-empty">Nothing here yet.</p>';
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
  } catch (err) {
    list.innerHTML = '<p class="notif-empty">Could not load notifications.</p>';
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
      /* silent */
    }
  }
});

loadNotifDropdown();
