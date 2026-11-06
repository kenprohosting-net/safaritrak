async function postJson(url, body) {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return response.json();
}

document.getElementById('createGroupBtn')?.addEventListener('click', async () => {
  const title = document.getElementById('groupTitle').value.trim();
  const destination = document.getElementById('groupDestination').value.trim();
  const departure = document.getElementById('groupDeparture').value;
  const errorEl = document.getElementById('createGroupError');
  const btn = document.getElementById('createGroupBtn');

  errorEl.style.display = 'none';

  if (!title || !destination) {
    errorEl.textContent = 'Please enter a trip name and destination.';
    errorEl.style.display = 'block';
    return;
  }

  const inviteIds = Array.from(document.querySelectorAll('.invite-checkbox:checked')).map(el => parseInt(el.value, 10));
  const persistentGroupIds = Array.from(document.querySelectorAll('.invite-group-checkbox:checked')).map(el => parseInt(el.value, 10));

  btn.disabled = true;
  btn.textContent = 'Creating...';

  try {
    const data = await postJson('backend/api/groups/create.php', {
      title,
      destination_label: destination,
      departure_at: departure || null,
      invite_contact_ids: inviteIds,
      persistent_group_ids: persistentGroupIds,
    });

    if (!data.success) {
      errorEl.textContent = data.message || 'That group journey could not be created.';
      errorEl.style.display = 'block';
      return;
    }

    location.reload();
  } catch (err) {
    errorEl.textContent = 'Something went wrong. Please try again.';
    errorEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Create and invite';
  }
});

document.querySelectorAll('.respond-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.getAttribute('data-id');
    const action = btn.getAttribute('data-action');

    if (action === 'leave' && !confirm('Leave this group journey?')) return;

    btn.disabled = true;
    try {
      const data = await postJson('backend/api/groups/respond.php', { member_id: id, action });
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

document.querySelectorAll('.remove-member-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!confirm('Remove this person from the group?')) return;
    btn.disabled = true;

    try {
      const data = await postJson('backend/api/groups/remove-member.php', { member_id: btn.getAttribute('data-member-id') });
      if (!data.success) {
        alert(data.message || 'That member could not be removed.');
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

document.querySelectorAll('.group-action-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const action = btn.getAttribute('data-action');
    const groupId = btn.getAttribute('data-group-id');

    if (action === 'cancel' && !confirm('Cancel this group journey? Everyone will be notified.')) return;

    btn.disabled = true;
    try {
      const data = await postJson('backend/api/groups/' + action + '.php', { group_id: groupId });
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

if (window.ACTIVE_GROUP_ID) {
  const memberIcon = L.divIcon({
    className: 'st-marker',
    html: '<div style="width:16px;height:16px;background:#176b5b;border:3px solid white;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.25)"></div>',
    iconSize: [16, 16],
    iconAnchor: [8, 8]
  });

  const destinationIcon = L.divIcon({
    className: 'st-marker',
    html: '<div style="width:16px;height:16px;background:#d69b2d;border:3px solid white;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.25)"></div>',
    iconSize: [16, 16],
    iconAnchor: [8, 8]
  });

  const groupMap = L.map('groupMap', {
    zoomControl: false,
    scrollWheelZoom: false,
    doubleClickZoom: false,
    touchZoom: false,
    boxZoom: false,
    keyboard: false
  }).setView([-1.286389, 36.817223], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
  }).addTo(groupMap);

  const bounds = [];
  const memberMarkers = {};

  window.ACTIVE_GROUP_MEMBERS.forEach(m => {
    if (m.status !== 'confirmed' || m.lat === null || m.lng === null) return;
    const label = m.user_id === window.MY_USER_ID ? m.name + ' (you)' : m.name;
    const marker = L.marker([m.lat, m.lng], { icon: memberIcon }).addTo(groupMap).bindPopup('<b>' + label + '</b>');
    memberMarkers[m.user_id] = marker;
    bounds.push([m.lat, m.lng]);
  });

  if (window.ACTIVE_GROUP_DEST_LAT !== null && window.ACTIVE_GROUP_DEST_LNG !== null) {
    L.marker([window.ACTIVE_GROUP_DEST_LAT, window.ACTIVE_GROUP_DEST_LNG], { icon: destinationIcon }).addTo(groupMap).bindPopup('<b>Destination</b>');
    bounds.push([window.ACTIVE_GROUP_DEST_LAT, window.ACTIVE_GROUP_DEST_LNG]);
  }

  if (bounds.length > 0) {
    groupMap.fitBounds(bounds, { padding: [40, 40] });
  }

  const amIConfirmedMember = window.ACTIVE_GROUP_MEMBERS.some(m => m.user_id === window.MY_USER_ID && m.status === 'confirmed');

  if (amIConfirmedMember && navigator.geolocation) {
    let lastPush = 0;
    navigator.geolocation.watchPosition((position) => {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;

      if (memberMarkers[window.MY_USER_ID]) {
        memberMarkers[window.MY_USER_ID].setLatLng([lat, lng]);
      } else {
        memberMarkers[window.MY_USER_ID] = L.marker([lat, lng], { icon: memberIcon }).addTo(groupMap).bindPopup('<b>You</b>');
      }

      const now = Date.now();
      if (now - lastPush > 15000) {
        lastPush = now;
        postJson('backend/api/groups/update-position.php', {
          group_id: window.ACTIVE_GROUP_ID,
          lat,
          lng,
        }).catch(() => {});
      }
    }, () => {}, { enableHighAccuracy: true, maximumAge: 10000 });
  }
}
