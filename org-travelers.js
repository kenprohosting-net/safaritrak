const travelerSearch = document.getElementById('travelerSearch');
const travelerStatusFilter = document.getElementById('travelerStatusFilter');
const travelerRows = document.querySelectorAll('#travelersTable tbody tr');
const emptyState = document.getElementById('travelersEmptyState');

function applyTravelerFilters() {
  const search = travelerSearch.value.trim().toLowerCase();
  const status = travelerStatusFilter.value;
  let visible = 0;

  travelerRows.forEach(row => {
    const matchesSearch = !search || row.textContent.toLowerCase().includes(search);
    const matchesStatus = status === 'all' || row.getAttribute('data-status') === status;
    const show = matchesSearch && matchesStatus;

    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  if (emptyState) emptyState.style.display = visible === 0 ? 'block' : 'none';
}

travelerSearch?.addEventListener('input', applyTravelerFilters);
travelerStatusFilter?.addEventListener('change', applyTravelerFilters);

document.getElementById('addTravelerBtn')?.addEventListener('click', async () => {
  const phoneInput = document.getElementById('addTravelerPhone');
  const errorEl = document.getElementById('addTravelerError');
  const btn = document.getElementById('addTravelerBtn');
  const phone = phoneInput.value.trim();

  errorEl.style.display = 'none';

  if (!phone) {
    errorEl.textContent = 'Enter a phone number.';
    errorEl.style.display = 'block';
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Adding...';

  try {
    const response = await fetch('backend/api/org/add-traveler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone }),
    });
    const data = await response.json();

    if (!data.success) {
      errorEl.textContent = data.message || 'That traveler could not be added.';
      errorEl.style.display = 'block';
      return;
    }

    location.reload();
  } catch (err) {
    errorEl.textContent = 'Something went wrong. Please try again.';
    errorEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Add traveler';
  }
});

document.querySelectorAll('.status-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const action = btn.getAttribute('data-action');
    const rowId = btn.getAttribute('data-row-id');

    const confirmMsg = action === 'deactivate'
      ? 'Deactivate this traveler? They will lose access to your organization.'
      : 'Reactivate this traveler?';

    if (!confirm(confirmMsg)) return;

    btn.disabled = true;

    try {
      const response = await fetch('backend/api/org/traveler-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ traveler_id: rowId, action }),
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
