const userSearch = document.getElementById('userSearch');
const userStatusFilter = document.getElementById('userStatusFilter');
const userRows = document.querySelectorAll('#usersTable tbody tr');
const emptyState = document.getElementById('usersEmptyState');

function applyUserFilters() {
  const search = userSearch.value.trim().toLowerCase();
  const status = userStatusFilter.value;
  let visible = 0;

  userRows.forEach(row => {
    const matchesSearch = !search || row.textContent.toLowerCase().includes(search);
    const matchesStatus = status === 'all' || row.getAttribute('data-status') === status;
    const show = matchesSearch && matchesStatus;

    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  if (emptyState) emptyState.style.display = visible === 0 ? 'block' : 'none';
}

userSearch?.addEventListener('input', applyUserFilters);
userStatusFilter?.addEventListener('change', applyUserFilters);

document.querySelectorAll('.status-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const action = btn.getAttribute('data-action');
    const userId = btn.getAttribute('data-user-id');

    const confirmMsg = action === 'suspend'
      ? 'Suspend this account? They will be logged out immediately and unable to log back in.'
      : 'Reactivate this account?';

    if (!confirm(confirmMsg)) return;

    btn.disabled = true;

    try {
      const response = await fetch('backend/api/platform/user-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, action }),
      });
      const data = await response.json();

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
