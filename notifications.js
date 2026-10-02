const notifTabs = document.querySelectorAll('#notifTabs .tab');
const notifRows = document.querySelectorAll('.notif-row');
const notifGroups = document.querySelectorAll('.notif-page-list');
const notifDateLabels = document.querySelectorAll('.notif-date-label');
const emptyState = document.getElementById('notifEmptyState');

function applyFilter(filter) {
  let visibleTotal = 0;

  notifGroups.forEach(group => {
    let visibleInGroup = 0;
    group.querySelectorAll('.notif-row').forEach(row => {
      let show = true;
      if (filter === 'unread') show = row.classList.contains('unread');
      if (filter === 'safety') show = row.getAttribute('data-type') === 'safety';
      if (filter === 'messages') show = row.getAttribute('data-type') === 'messages';

      row.style.display = show ? 'flex' : 'none';
      if (show) visibleInGroup++;
    });
    visibleTotal += visibleInGroup;

    const label = group.previousElementSibling;
    if (label && label.classList.contains('notif-date-label')) {
      label.style.display = visibleInGroup === 0 ? 'none' : 'block';
    }
  });

  if (emptyState) emptyState.style.display = visibleTotal === 0 ? 'block' : 'none';
}

notifTabs.forEach(tab => {
  tab.addEventListener('click', () => {
    notifTabs.forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    applyFilter(tab.getAttribute('data-filter'));
  });
});

document.getElementById('markAllRead')?.addEventListener('click', async () => {
  const btn = document.getElementById('markAllRead');
  btn.disabled = true;

  try {
    const response = await fetch('backend/api/notifications/mark-read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ all: true }),
    });
    const data = await response.json();

    if (!data.success) {
      alert('That could not be completed. Please try again.');
      return;
    }

    notifRows.forEach(row => {
      row.classList.remove('unread');
      row.querySelector('.unread-dot')?.remove();
    });
    document.getElementById('notifDot')?.style.setProperty('display', 'none');

    const activeTab = document.querySelector('#notifTabs .tab.active');
    if (activeTab) applyFilter(activeTab.getAttribute('data-filter'));
  } catch (err) {
    alert('Something went wrong. Please try again.');
  } finally {
    btn.disabled = false;
  }
});
