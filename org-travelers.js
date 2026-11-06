const travelerSearch = document.getElementById('travelerSearch');
const travelerStatusFilter = document.getElementById('travelerStatusFilter');
const travelerRows = document.querySelectorAll('#travelersTable tbody tr');
const emptyState = document.getElementById('travelersEmptyState');

function applyTravelerFilters() {
  const search = travelerSearch.value.trim().toLowerCase();
  const status = travelerStatusFilter.value;
  const isSchoolFilter = ['student', 'guardian', 'staff'].includes(status);
  let visible = 0;

  travelerRows.forEach(row => {
    const matchesSearch = !search || row.textContent.toLowerCase().includes(search);
    const matchesStatus = status === 'all'
      || (isSchoolFilter ? row.getAttribute('data-member-type') === status : row.getAttribute('data-status') === status);
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

document.getElementById('createSchoolInvite')?.addEventListener('click', async event => {
  const button = event.currentTarget;
  const message = document.getElementById('schoolInviteError');
  const email = document.getElementById('schoolInviteEmail').value.trim();
  message.textContent = '';
  button.disabled = true;
  try {
    const response = await fetch('backend/api/org/invite.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email,
        member_type: document.getElementById('schoolInviteType').value,
      }),
    });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Could not create the school invitation.');
    document.getElementById('schoolInviteUrl').value = data.invite_url;
    document.getElementById('schoolInviteLinkWrap').hidden = false;
    document.getElementById('emailSchoolInvite').href =
      `mailto:${encodeURIComponent(email)}?subject=${encodeURIComponent('SafariTrak school invitation')}&body=${encodeURIComponent(`Request to join our school community on SafariTrak: ${data.invite_url}`)}`;
    message.textContent = 'Invitation link created.';
  } catch (error) {
    message.textContent = error.message || 'Could not create the school invitation.';
  } finally {
    button.disabled = false;
  }
});

document.getElementById('copySchoolInvite')?.addEventListener('click', async event => {
  const button = event.currentTarget;
  const input = document.getElementById('schoolInviteUrl');
  try {
    await navigator.clipboard.writeText(input.value);
    button.textContent = 'Copied';
  } catch (error) {
    input.select();
    document.execCommand('copy');
    document.getElementById('schoolInviteError').textContent = 'Select and copy the link from the field.';
  }
});

document.querySelectorAll('.enrollment-review-btn').forEach(button => {
  button.addEventListener('click', async () => {
    const action = button.dataset.action;
    const requestId = button.dataset.requestId;
    button.disabled = true;
    try {
      const response = await fetch('backend/api/org/review-membership.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ request_id: requestId, action }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Could not review this request.');
      location.reload();
    } catch (error) {
      alert(error.message || 'Could not review this request.');
      button.disabled = false;
    }
  });
});
