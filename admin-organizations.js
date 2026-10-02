const orgSearch = document.getElementById('orgSearch');
const orgStatusFilter = document.getElementById('orgStatusFilter');
const orgRows = document.querySelectorAll('#orgsTable tbody tr');
const emptyState = document.getElementById('orgsEmptyState');

function applyOrgFilters() {
  const search = orgSearch.value.trim().toLowerCase();
  const status = orgStatusFilter.value;
  let visible = 0;

  orgRows.forEach(row => {
    const matchesSearch = !search || row.textContent.toLowerCase().includes(search);
    const matchesStatus = status === 'all' || row.getAttribute('data-status') === status;
    const show = matchesSearch && matchesStatus;

    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  if (emptyState) emptyState.style.display = visible === 0 ? 'block' : 'none';
}

orgSearch?.addEventListener('input', applyOrgFilters);
orgStatusFilter?.addEventListener('change', applyOrgFilters);

document.querySelectorAll('.org-status-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const action = btn.getAttribute('data-action');
    const orgId = btn.getAttribute('data-org-id');

    const confirmMsg = action === 'suspend'
      ? 'Suspend this organization? Its admin will lose access to manage travelers until reactivated.'
      : 'Reactivate this organization?';

    if (!confirm(confirmMsg)) return;

    btn.disabled = true;

    try {
      const response = await fetch('backend/api/platform/org-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ organization_id: orgId, action }),
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
