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
  const destination = document.getElementById('endPoint').value.trim();
  const startPoint = document.getElementById('startPoint').value.trim();
  const startLat = document.getElementById('startLat').value;
  const startLng = document.getElementById('startLng').value;
  const destinationLat = document.getElementById('endLat').value;
  const destinationLng = document.getElementById('endLng').value;
  const departure = document.getElementById('groupDeparture').value;
  const errorEl = document.getElementById('createGroupError');
  const btn = document.getElementById('createGroupBtn');

  errorEl.style.display = 'none';

  if (!title || !destination || !startPoint) {
    errorEl.textContent = 'Please enter a trip name, starting point, and destination.';
    errorEl.style.display = 'block';
    return;
  }
  const coordinates = [startLat, startLng, destinationLat, destinationLng].map(Number);
  if ([startLat, startLng, destinationLat, destinationLng].some(value => value.trim() === '')
    || !coordinates.every(Number.isFinite)
    || Math.abs(coordinates[0]) > 90 || Math.abs(coordinates[2]) > 90
    || Math.abs(coordinates[1]) > 180 || Math.abs(coordinates[3]) > 180) {
    errorEl.textContent = 'Choose both the starting point and destination from the location suggestions.';
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
      start_label: startPoint,
      start_lat: Number(startLat),
      start_lng: Number(startLng),
      destination_label: destination,
      destination_lat: Number(destinationLat),
      destination_lng: Number(destinationLng),
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
  const trackingColors = window.SafariTrakMapStyle.getTrackingColors();
  const groupMemberColors = trackingColors.groupMembers;
  const memberRouteColor = member => groupMemberColors[
    Math.abs(Number(member.member_id)) % groupMemberColors.length
  ];
  const memberColor = member => member.user_id === window.MY_USER_ID
    ? trackingColors.user
    : memberRouteColor(member);
  const createGroupMarker = (color, size = 16) => L.divIcon({
    className: 'st-marker',
    html: `<div style="width:${size}px;height:${size}px;background:${color};border:3px solid white;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.28)"></div>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
  });
  const destinationIcon = createGroupMarker(trackingColors.destination);

  const initialCenter = window.ACTIVE_GROUP_START_LAT !== null && window.ACTIVE_GROUP_START_LNG !== null
    ? [window.ACTIVE_GROUP_START_LAT, window.ACTIVE_GROUP_START_LNG]
    : [-1.286389, 36.817223];
  const groupMap = L.map('groupMap', {
    zoomControl: false,
    scrollWheelZoom: false,
    doubleClickZoom: true,
    touchZoom: true,
    boxZoom: false,
    keyboard: false
  }).setView(initialCenter, window.matchMedia('(max-width: 800px)').matches ? 12 : 6);
  window.SafariTrakMapStyle.attachLeafletBase(groupMap);

  const bounds = [];
  const memberMarkers = {};
  const memberRoutes = {};
  const plannedRoutePane = groupMap.createPane('group-planned-route');
  plannedRoutePane.style.zIndex = '350';
  let plannedRouteLine = null;
  const mapStatus = document.getElementById('groupMapStatus');
  const deviceLocationStatus = document.getElementById('groupDeviceLocationStatus');
  let hasCenteredOnCurrentLocation = false;
  const centerOnCurrentLocation = point => {
    if (hasCenteredOnCurrentLocation) return;
    groupMap.setView(point, 16);
    hasCenteredOnCurrentLocation = true;
  };
  const reportLocationUnavailable = error => {
    if (!deviceLocationStatus) return;
    deviceLocationStatus.hidden = false;
    deviceLocationStatus.textContent = error && error.code === error.PERMISSION_DENIED
      ? 'Location permission was denied. The group trip map remains available.'
      : 'Device location is unavailable. The group trip map remains available.';
  };
  const startPoint = window.ACTIVE_GROUP_START_LAT !== null && window.ACTIVE_GROUP_START_LNG !== null
    ? [window.ACTIVE_GROUP_START_LAT, window.ACTIVE_GROUP_START_LNG]
    : null;
  const destinationPoint = window.ACTIVE_GROUP_DEST_LAT !== null && window.ACTIVE_GROUP_DEST_LNG !== null
    ? [window.ACTIVE_GROUP_DEST_LAT, window.ACTIVE_GROUP_DEST_LNG]
    : null;
  const plannedRouteColors = window.SafariTrakMapStyle.getRouteColors();
  const completedRoute = ['ended', 'completed'].includes(String(window.ACTIVE_GROUP_STATUS || '').toLowerCase());
  const plannedRouteColor = completedRoute
    ? plannedRouteColors.completedStroke
    : plannedRouteColors.stroke;
  const inferredStart = startPoint || window.ACTIVE_GROUP_MEMBERS
    .map(member => Array.isArray(member.route) ? member.route[0] : null)
    .find(point => Array.isArray(point) && point.length === 2);
  if (inferredStart) {
    const icon = createGroupMarker(trackingColors.start);
    L.marker(inferredStart, { icon }).addTo(groupMap).bindPopup('<b>Trip start</b>');
    bounds.push(inferredStart);
  }

  window.ACTIVE_GROUP_MEMBERS.forEach(m => {
    if (m.status !== 'confirmed') return;
    const color = memberColor(m);
    const route = Array.isArray(m.route) ? m.route : [];
    if (route.length > 1) {
      memberRoutes[m.member_id] = L.polyline(route, { color: memberRouteColor(m), weight: 5, opacity: 0.82 }).addTo(groupMap);
      route.forEach(point => bounds.push(point));
    }
    if (m.lat === null || m.lng === null) return;
    const label = m.user_id === window.MY_USER_ID ? m.name + ' (you)' : m.name;
    const icon = L.divIcon({
      className: 'st-marker',
      html: '<div style="width:16px;height:16px;background:' + color + ';border:3px solid white;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.25)"></div>',
      iconSize: [16, 16],
      iconAnchor: [8, 8]
    });
    const popup = document.createElement('b');
    popup.textContent = label;
    const marker = L.marker([m.lat, m.lng], { icon }).addTo(groupMap).bindPopup(popup);
    memberMarkers[m.user_id] = marker;
    bounds.push([m.lat, m.lng]);
  });

  if (destinationPoint) {
    L.marker(destinationPoint, { icon: destinationIcon }).addTo(groupMap).bindPopup('<b>Destination</b>');
    bounds.push(destinationPoint);
  }

  const hasPlannedEndpoints = Boolean(inferredStart && destinationPoint);
  const isMobile = window.matchMedia('(max-width: 800px)').matches;
  if (bounds.length > 0 && !(isMobile && hasPlannedEndpoints)) {
    groupMap.fitBounds(bounds, { padding: [40, 40] });
  }

  if (inferredStart && destinationPoint) {
    mapStatus.textContent = 'Loading the planned road route…';
    window.SafariTrakRouteDirections.getDrivingRoute(
      { lat: inferredStart[0], lng: inferredStart[1] },
      { lat: destinationPoint[0], lng: destinationPoint[1] }
    ).then(route => {
      plannedRouteLine = L.polyline(
        route.coordinates.map(([lng, lat]) => [lat, lng]),
        {
          pane: 'group-planned-route',
          color: plannedRouteColor,
          weight: 5,
          opacity: 0.9,
          dashArray: '9 7',
        }
      ).addTo(groupMap);
      bounds.push(...plannedRouteLine.getLatLngs());
      if (!hasCenteredOnCurrentLocation) {
        if (!window.SafariTrakRouteDirections.focusMobileLeafletRoute(
          groupMap,
          plannedRouteLine.getLatLngs(),
          inferredStart
        )) {
          groupMap.fitBounds(bounds, { padding: [40, 40] });
        }
      }
      mapStatus.textContent = `Planned road route · ${(route.distance_meters / 1000).toFixed(1)} km · about ${Math.max(1, Math.round(route.duration_seconds / 60))} min. Recorded member tracks appear above it.`;
    }).catch(error => {
      plannedRouteLine = L.polyline(
        [inferredStart, destinationPoint],
        {
          pane: 'group-planned-route',
          color: plannedRouteColor,
          weight: 5,
          opacity: 0.75,
          dashArray: '5 8',
        }
      ).addTo(groupMap);
      if (!hasCenteredOnCurrentLocation) {
        if (!window.SafariTrakRouteDirections.focusMobileLeafletRoute(
          groupMap,
          plannedRouteLine.getLatLngs(),
          inferredStart
        )) {
          groupMap.fitBounds(plannedRouteLine.getBounds(), { padding: [40, 40] });
        }
      }
      mapStatus.textContent = `Road route unavailable; the dashed line is an approximate direction, not a road route. ${error.message || ''}`.trim();
    });
  } else if (window.ACTIVE_GROUP_STATUS === 'upcoming') {
    mapStatus.textContent = 'This scheduled trip needs a saved starting point and destination to show its road route.';
  } else {
    mapStatus.textContent = 'Recorded member routes are shown where location updates were saved.';
  }

  const amIConfirmedMember = window.ACTIVE_GROUP_STATUS === 'active'
    && window.ACTIVE_GROUP_MEMBERS.some(m => m.user_id === window.MY_USER_ID && m.status === 'confirmed');

  if (amIConfirmedMember && navigator.geolocation) {
    let lastPush = 0;
    navigator.geolocation.watchPosition((position) => {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      centerOnCurrentLocation([lat, lng]);

      if (memberMarkers[window.MY_USER_ID]) {
        memberMarkers[window.MY_USER_ID].setLatLng([lat, lng]);
      } else {
        const icon = L.divIcon({
          className: 'st-marker',
          html: `<div style="width:16px;height:16px;background:${trackingColors.user};border:3px solid white;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.28)"></div>`,
          iconSize: [16, 16],
          iconAnchor: [8, 8],
        });
        memberMarkers[window.MY_USER_ID] = L.marker([lat, lng], { icon }).addTo(groupMap).bindPopup('<b>You</b>');
      }

      const now = Date.now();
      if (now - lastPush > 15000) {
        lastPush = now;
        postJson('backend/api/groups/update-position.php', {
          group_id: window.ACTIVE_GROUP_ID,
          lat,
          lng,
          speed_kmh: position.coords.speed === null ? null : position.coords.speed * 3.6,
          heading: position.coords.heading,
          accuracy: position.coords.accuracy,
        }).catch(() => {});
      }
      const currentMember = window.ACTIVE_GROUP_MEMBERS.find(m => m.user_id === window.MY_USER_ID);
      if (currentMember) {
        currentMember.route = currentMember.route || [];
        const previous = currentMember.route[currentMember.route.length - 1];
        if (!previous || L.latLng(previous).distanceTo([lat, lng]) >= 15) {
          currentMember.route.push([lat, lng]);
          if (!memberRoutes[currentMember.member_id]) {
            memberRoutes[currentMember.member_id] = L.polyline([], { color: memberRouteColor(currentMember), weight: 5, opacity: 0.82 }).addTo(groupMap);
          }
          memberRoutes[currentMember.member_id].addLatLng([lat, lng]);
        }
      }
    }, reportLocationUnavailable, { enableHighAccuracy: true, maximumAge: 10000 });
  } else if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(position => {
      const point = [position.coords.latitude, position.coords.longitude];
      L.marker(point, { icon: createGroupMarker(trackingColors.user, 18), zIndexOffset: 1000 })
        .addTo(groupMap)
        .bindPopup('<b>Your location on this device</b>');
      centerOnCurrentLocation(point);
    }, reportLocationUnavailable, { enableHighAccuracy: true, maximumAge: 10000, timeout: 12000 });
  } else {
    reportLocationUnavailable(null);
  }

  const refreshMemberRoutes = async () => {
    try {
      const response = await fetch(`backend/api/journeys/position.php?group_journey_id=${encodeURIComponent(window.ACTIVE_GROUP_ID)}`, {
        credentials: 'same-origin',
        cache: 'no-store',
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'Group member routes could not be refreshed.');
      }
      for (const member of payload.data.members) {
        const initialMember = window.ACTIVE_GROUP_MEMBERS.find(item => item.member_id === member.group_member_id);
        const points = Array.isArray(member.route) ? member.route : [];
        if (memberRoutes[member.group_member_id]) {
          memberRoutes[member.group_member_id].setLatLngs(points);
        } else if (points.length > 1) {
          memberRoutes[member.group_member_id] = L.polyline(points, {
            color: initialMember ? memberRouteColor(initialMember) : groupMemberColors[
              Math.abs(Number(member.group_member_id)) % groupMemberColors.length
            ],
            weight: 5,
            opacity: 0.82,
          }).addTo(groupMap);
        }
        if (member.lat !== null && member.lng !== null && memberMarkers[member.user_id]) {
          memberMarkers[member.user_id].setLatLng([member.lat, member.lng]);
        }
      }
      if (window.ACTIVE_GROUP_STATUS === 'active') {
        mapStatus.textContent = 'Member routes updated just now. Only confirmed trip members can view them.';
      }
    } catch (error) {
      mapStatus.textContent = error.message || 'Group member routes could not be refreshed.';
    }
  };
  refreshMemberRoutes();
  if (window.ACTIVE_GROUP_STATUS === 'active') window.setInterval(refreshMemberRoutes, 30000);
}
