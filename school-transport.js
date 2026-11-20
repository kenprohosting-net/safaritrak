(() => {
  const app = document.querySelector('.app--school');
  const workspace = document.getElementById('schoolWorkspace');
  if (!app || !workspace) return;

  const role = app.dataset.schoolRole;
  const isAdmin = role === 'school_admin';
  const currentUserId = Number(app.dataset.currentUserId);
  const stateUrl = 'backend/api/school-transport/state.php';
  const routeCache = new Map();
  let state = null;
  let selectedBusId = null;
  let currentFilter = 'all';
  let mapInitialized = false;
  let routeWarningShown = false;
  let etaUpdatedAt = 0;
  let etaKey = '';
  let positionWatchId = null;
  let lastPositionSentAt = 0;
  let choosingDropoff = false;
  let routeConsentTouched = false;
  let polling = false;

  const byId = id => document.getElementById(id);
  const escapeInitials = name => String(name || '')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map(part => part.charAt(0).toUpperCase())
    .join('') || 'ST';

  function setFeedback(id, message, isError = false) {
    const element = byId(id);
    if (!element) return;
    element.textContent = message;
    element.classList.toggle('is-error', isError);
  }

  async function readResponse(response) {
    let payload;
    try {
      payload = await response.json();
    } catch {
      throw new Error('The school transport service returned an invalid response.');
    }
    if (!response.ok || !payload.success) {
      throw new Error(payload.message || `Request failed (HTTP ${response.status}).`);
    }
    return payload;
  }

  async function getJson(url) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    });
    return readResponse(response);
  }

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    });
    return readResponse(response);
  }

  function textElement(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    element.textContent = text;
    return element;
  }

  function makeButton(label, className, handler, iconClass = '') {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    if (iconClass) {
      const icon = document.createElement('i');
      icon.className = iconClass;
      icon.setAttribute('aria-hidden', 'true');
      button.append(icon);
    }
    button.append(document.createTextNode(label));
    button.addEventListener('click', handler);
    return button;
  }

  function makeLink(label, href, className, iconClass = '') {
    const link = document.createElement('a');
    link.href = href;
    link.className = className;
    if (iconClass) {
      const icon = document.createElement('i');
      icon.className = iconClass;
      icon.setAttribute('aria-hidden', 'true');
      link.append(icon);
    }
    link.append(document.createTextNode(label));
    return link;
  }

  function formatTime(value) {
    if (!value) return '—';
    const date = new Date(`${String(value).replace(' ', 'T')}Z`);
    return Number.isNaN(date.getTime())
      ? '—'
      : new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(date);
  }

  function relativeTime(value) {
    if (!value) return 'No location update yet';
    const date = new Date(`${String(value).replace(' ', 'T')}Z`);
    if (Number.isNaN(date.getTime())) return 'Last update unavailable';
    const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
    if (seconds < 60) return `Last update: ${seconds} second${seconds === 1 ? '' : 's'} ago`;
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `Last update: ${minutes} minute${minutes === 1 ? '' : 's'} ago`;
    return `Last update: ${Math.floor(minutes / 60)} hours ago`;
  }

  function getCurrentPosition() {
    if (!navigator.geolocation) {
      return Promise.reject(new Error('Location sharing is not available in this browser.'));
    }
    return new Promise((resolve, reject) => {
      navigator.geolocation.getCurrentPosition(
        position => resolve(position),
        error => reject(new Error(
          error.code === error.PERMISSION_DENIED
            ? 'Allow location access to share the bus location.'
            : 'Could not read the bus location. Check the device location settings and retry.'
        )),
        { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 }
      );
    });
  }

  function distanceKm(start, end) {
    const radians = value => value * Math.PI / 180;
    const lat1 = radians(Number(start.lat));
    const lat2 = radians(Number(end.lat));
    const latDelta = lat2 - lat1;
    const lngDelta = radians(Number(end.lng) - Number(start.lng));
    const haversine = Math.sin(latDelta / 2) ** 2
      + Math.cos(lat1) * Math.cos(lat2) * Math.sin(lngDelta / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(haversine), Math.sqrt(1 - haversine));
  }

  function estimateRouteProgress(bus) {
    if (bus.trip_status === 'arrived') return { progress: 100, nextStop: null };
    if (bus.trip_status !== 'en_route' || bus.current_lat === null || bus.current_lng === null) {
      return { progress: 0, nextStop: bus.stops?.[0]?.stop_name || null };
    }
    const stops = [
      ...(state?.origin ? [{
        stop_name: state.origin.origin_name,
        lat: Number(state.origin.origin_lat),
        lng: Number(state.origin.origin_lng),
      }] : []),
      ...(bus.stops || []).map(stop => ({
        stop_name: stop.stop_name,
        lat: Number(stop.lat),
        lng: Number(stop.lng),
      })),
    ];
    if (stops.length < 2) return { progress: 0, nextStop: bus.stops?.[0]?.stop_name || null };
    const current = { lat: Number(bus.current_lat), lng: Number(bus.current_lng) };
    let nearestIndex = 1;
    for (let index = 2; index < stops.length; index += 1) {
      if (distanceKm(current, stops[index]) < distanceKm(current, stops[nearestIndex])) {
        nearestIndex = index;
      }
    }
    const total = stops.slice(1).reduce((sum, stop, index) => (
      sum + distanceKm(stops[index], stop)
    ), 0);
    if (total <= 0) return { progress: 0, nextStop: stops[nearestIndex]?.stop_name || null };
    const remaining = distanceKm(current, stops[nearestIndex])
      + stops.slice(nearestIndex + 1).reduce((sum, stop, index) => (
        sum + distanceKm(stops[nearestIndex + index], stop)
      ), 0);
    return {
      progress: Math.max(0, Math.min(99, Math.round((1 - remaining / total) * 100))),
      nextStop: stops[nearestIndex]?.stop_name || null,
    };
  }

  async function createRoadRoute(features) {
    const directionService = window.SafariTrakRouteDirections;
    const output = [];
    for (const feature of features) {
      if (!Array.isArray(feature.routeStops) || feature.routeStops.length < 2) {
        output.push(feature);
        continue;
      }

      const stops = feature.routeStops
        .filter(point => Number.isFinite(Number(point.lat)) && Number.isFinite(Number(point.lng)))
        .slice(0, 20);
      if (stops.length < 2 || !directionService?.getDrivingRoute) {
        output.push(feature);
        continue;
      }

      const cacheKey = stops.map(point => `${Number(point.lat).toFixed(5)},${Number(point.lng).toFixed(5)}`).join('|');
      let routePromise = routeCache.get(cacheKey);
      if (!routePromise) {
        routePromise = (async () => {
          const coordinates = [];
          for (let index = 1; index < stops.length; index += 1) {
            const segment = await directionService.getDrivingRoute(
              { lat: Number(stops[index - 1].lat), lng: Number(stops[index - 1].lng) },
              { lat: Number(stops[index].lat), lng: Number(stops[index].lng) }
            );
            coordinates.push(...segment.coordinates.slice(coordinates.length ? 1 : 0));
          }
          return coordinates;
        })();
        routeCache.set(cacheKey, routePromise);
      }

      try {
        const route = await routePromise;
        output.push({ ...feature, route, routeStops: undefined });
      } catch (error) {
        if (!routeWarningShown) {
          routeWarningShown = true;
          setFeedback(
            isAdmin ? 'schoolLeftFeedback' : 'parentLeftFeedback',
            'Road routing is temporarily unavailable; showing the saved route stops instead.',
            true
          );
        }
        console.warn('School bus road route could not be loaded.', error);
        output.push({
          ...feature,
          route: stops.map(point => [Number(point.lng), Number(point.lat)]),
          routeStops: undefined,
        });
      }
    }
    return output;
  }

  async function updateMap(features, refit = false) {
    const routeFeatures = await createRoadRoute(features || []);
    if (!window.SafariTrakPageMap) return;
    if (refit || !mapInitialized) {
      window.SafariTrakPageMap.focusFeatures(routeFeatures);
      mapInitialized = true;
    } else {
      window.SafariTrakPageMap.setFeatures(routeFeatures);
    }
  }

  async function refreshState({ refit = false } = {}) {
    if (polling) return;
    polling = true;
    try {
      const query = isAdmin && selectedBusId ? `?bus_id=${encodeURIComponent(selectedBusId)}` : '';
      const payload = await getJson(`${stateUrl}${query}`);
      state = payload;
      if (isAdmin) {
        selectedBusId = payload.selected_bus?.id ?? null;
        renderAdmin();
      } else {
        renderParent();
      }
      await updateMap(payload.map_features || [], refit);
      if (isAdmin) updateAdminEta();
      else updateParentEta();
    } catch (error) {
      setFeedback(isAdmin ? 'schoolLeftFeedback' : 'parentLeftFeedback', error.message, true);
    } finally {
      polling = false;
    }
  }

  function renderAdmin() {
    renderBusList();
    renderRoster();
    updateBusSelector();
    const selected = state?.selected_bus;
    const button = byId('broadcastHomebound');
    const status = selected?.trip_status === 'en_route';
    if (button) {
      button.replaceChildren();
      const icon = document.createElement('i');
      icon.className = `fa-solid ${status && selected.broadcast_active ? 'fa-pause' : 'fa-tower-broadcast'}`;
      icon.setAttribute('aria-hidden', 'true');
      button.append(icon, document.createTextNode(
        status && selected.broadcast_active
          ? 'Pause Live Location Broadcast'
          : status
            ? 'Broadcast Homebound Trip to Parents'
            : 'Share Live Trip'
      ));
      button.disabled = !selected;
    }
  }

  function renderBusList() {
    const list = byId('schoolBusList');
    if (!list) return;
    list.replaceChildren();
    const buses = state?.buses || [];
    const filtered = buses.filter(bus => {
      if (currentFilter === 'all') return true;
      if (currentFilter === 'en_route') return bus.trip_status === 'en_route';
      return bus.trip_status === 'arrived';
    });
    if (!filtered.length) {
      list.append(textElement(
        'div',
        'school-empty',
        buses.length
          ? 'No buses match this filter.'
          : 'No buses registered yet. Add your school origin and the first bus route to begin.'
      ));
      return;
    }

    filtered.forEach(bus => {
      const card = document.createElement('article');
      card.className = `school-bus-card${bus.id === selectedBusId ? ' is-selected' : ''}`;
      const head = document.createElement('div');
      head.className = 'school-bus-card-head';
      const select = document.createElement('button');
      select.type = 'button';
      select.className = 'school-bus-select';
      select.append(
        textElement('strong', '', `Bus ${bus.bus_number} · ${bus.route_name}`),
        textElement('small', '', `${bus.student_count} ${bus.student_count === 1 ? 'student' : 'students'}`)
      );
      select.addEventListener('click', () => {
        if (selectedBusId === bus.id) return;
        selectedBusId = bus.id;
        etaKey = '';
        refreshState({ refit: true });
        setSchoolPanel('right');
      });
      head.append(select, textElement(
        'span',
        bus.trip_status === 'en_route' ? 'school-live-pill' : 'school-live-pill',
        bus.trip_status === 'en_route'
          ? bus.broadcast_active ? 'En route · Live' : 'En route'
          : bus.trip_status === 'arrived' ? 'Arrived' : 'Ready'
      ));
      const progressState = estimateRouteProgress(bus);
      const nextStop = progressState.nextStop || 'Route stops not listed';
      const routeMeta = document.createElement('div');
      routeMeta.className = 'school-bus-card-meta';
      routeMeta.append(
        textElement('i', 'fa-solid fa-location-dot', ''),
        document.createTextNode(`Next stop: ${nextStop}`)
      );
      const progress = document.createElement('div');
      progress.className = 'school-progress';
      progress.setAttribute('role', 'progressbar');
      progress.setAttribute('aria-label', `Estimated route progress for Bus ${bus.bus_number}`);
      progress.setAttribute('aria-valuemin', '0');
      progress.setAttribute('aria-valuemax', '100');
      progress.setAttribute('aria-valuenow', String(progressState.progress));
      const progressBar = document.createElement('span');
      progressBar.style.setProperty('--progress', `${progressState.progress}%`);
      progress.append(progressBar);
      const progressLabel = textElement(
        'small',
        'school-progress-label',
        bus.trip_status === 'en_route'
          ? `Estimated route progress · ${progressState.progress}%`
          : bus.trip_status === 'arrived'
            ? 'Trip complete · 100%'
            : 'Trip not started'
      );
      const actions = document.createElement('div');
      actions.className = 'school-bus-card-actions';
      const toggleLabel = document.createElement('label');
      toggleLabel.className = 'school-broadcast-toggle';
      const toggle = document.createElement('input');
      toggle.type = 'checkbox';
      toggle.checked = Boolean(bus.broadcast_active);
      toggle.disabled = bus.trip_status !== 'en_route';
      toggle.setAttribute('aria-label', `Live broadcast for Bus ${bus.bus_number}`);
      toggle.addEventListener('change', () => changeBroadcast(bus, toggle.checked));
      toggleLabel.append(toggle, document.createTextNode('Live broadcast'));
      actions.append(toggleLabel);
      if (bus.trip_status === 'en_route') {
        actions.append(makeButton(
          'Mark arrived',
          'school-card-action',
          () => runTripAction('arrived', bus),
          'fa-solid fa-flag-checkered'
        ));
      } else {
        actions.append(makeButton(
          bus.trip_status === 'arrived' ? 'Start new trip' : 'Share Live Trip',
          'school-card-action school-card-action--primary',
          () => startTrip(bus),
          'fa-solid fa-location-arrow'
        ));
      }
      card.append(head, routeMeta, progressLabel, progress, actions);
      list.append(card);
    });
  }

  function renderRoster() {
    const roster = byId('schoolParentRoster');
    if (!roster) return;
    roster.replaceChildren();
    if (!state?.selected_bus) {
      roster.append(textElement('div', 'school-empty', 'Add a bus to start assigning verified parent accounts.'));
      return;
    }
    if (!state.roster?.length) {
      roster.append(textElement('div', 'school-empty', 'No verified parents are assigned to this bus yet.'));
      return;
    }
    state.roster.forEach(parent => {
      const card = document.createElement('article');
      card.className = 'school-parent-card';
      const head = document.createElement('div');
      head.className = 'school-parent-card-head';
      const avatar = textElement('span', 'school-avatar school-avatar--small', escapeInitials(parent.parent_name));
      const identity = document.createElement('div');
      identity.className = 'school-parent-identity';
      identity.append(
        textElement('strong', '', parent.parent_name),
        textElement('small', '', `Parent of ${parent.student_name}${parent.student_grade ? ` · ${parent.student_grade}` : ''}`)
      );
      head.append(avatar, identity);
      const status = textElement(
        'span',
        parent.trip_student_status === 'dropped_off' ? 'school-live-pill' : 'school-verified',
        parent.trip_student_status === 'dropped_off' ? 'Dropped off' : 'Live link active'
      );
      const actions = document.createElement('div');
      actions.className = 'school-parent-card-actions';
      actions.append(makeLink('Message', `messages.php?to=${encodeURIComponent(parent.parent_user_id)}`, '', 'fa-regular fa-message'));
      if (parent.parent_phone) {
        actions.append(makeLink('Call', `tel:${encodeURIComponent(parent.parent_phone)}`, '', 'fa-solid fa-phone'));
      }
      if (state.selected_bus.trip_status === 'en_route') {
        const nextStatus = parent.trip_student_status === 'approaching' ? 'dropped_off' : 'approaching';
        actions.append(makeButton(
          nextStatus === 'approaching' ? 'Approaching' : 'Mark dropped off',
          'school-card-action',
          () => updateStudentStatus(parent, nextStatus),
          nextStatus === 'approaching' ? 'fa-solid fa-route' : 'fa-solid fa-check'
        ));
      }
      card.append(head, status, actions);
      roster.append(card);
    });
  }

  function updateBusSelector() {
    const select = byId('verifyBusSelect');
    if (!select) return;
    const selected = select.value;
    select.replaceChildren(new Option('Choose a bus', ''));
    (state?.buses || []).forEach(bus => {
      select.add(new Option(`Bus ${bus.bus_number} · ${bus.route_name}`, String(bus.id)));
    });
    if ([...select.options].some(option => option.value === selected)) select.value = selected;
    else if (selectedBusId) select.value = String(selectedBusId);
  }

  function etaTarget(bus) {
    const firstStop = bus?.stops?.[0];
    if (firstStop) return { lat: Number(firstStop.lat), lng: Number(firstStop.lng), name: firstStop.stop_name };
    const origin = state?.origin;
    return origin ? { lat: Number(origin.origin_lat), lng: Number(origin.origin_lng), name: origin.origin_name } : null;
  }

  async function calculateEta(start, destination) {
    if (!window.SafariTrakRouteDirections?.getDrivingRoute) return null;
    return window.SafariTrakRouteDirections.getDrivingRoute(start, destination);
  }

  function updateAdminEta() {
    const bus = state?.selected_bus;
    const title = byId('schoolEtaTitle');
    const detail = byId('schoolEtaDetail');
    if (!title || !detail) return;
    if (!bus) {
      title.textContent = 'Select a bus';
      detail.textContent = 'Add a bus to view parent and trip details.';
      return;
    }
    const target = etaTarget(bus);
    detail.textContent = `Next scheduled stop: ${target?.name || 'not set'}`;
    if (bus.trip_status !== 'en_route'
      || bus.current_lat === null || bus.current_lng === null || !target
    ) {
      title.textContent = bus.trip_status === 'arrived' ? 'Trip arrived' : 'Waiting for a live location';
      return;
    }
    const key = `${bus.id}:${Number(bus.current_lat).toFixed(3)},${Number(bus.current_lng).toFixed(3)}:${target.lat},${target.lng}`;
    if (key === etaKey && Date.now() - etaUpdatedAt < 60000) return;
    etaKey = key;
    etaUpdatedAt = Date.now();
    title.textContent = 'Calculating road ETA…';
    calculateEta(
      { lat: bus.current_lat, lng: bus.current_lng },
      { lat: target.lat, lng: target.lng }
    ).then(route => {
      if (etaKey !== key) return;
      const minutes = Math.max(1, Math.ceil(route.duration_seconds / 60));
      title.textContent = `ETA: ${minutes} min to ${target.name}`;
      detail.textContent = `${(route.distance_meters / 1000).toFixed(1)} km by road · Assigned parents only`;
    }).catch(error => {
      if (etaKey !== key) return;
      title.textContent = 'ETA unavailable';
      detail.textContent = 'Road routing is temporarily unavailable.';
      console.warn('School bus ETA could not be calculated.', error);
    });
  }

  function nearestStopName(bus, lat, lng) {
    const points = [
      ...(bus?.stops || []),
      ...(state?.origin ? [{
        stop_name: state.origin.origin_name,
        lat: state.origin.origin_lat,
        lng: state.origin.origin_lng,
      }] : []),
    ];
    if (!points.length) return 'Shared bus location';
    const distance = point => {
      const latDiff = Number(point.lat) - lat;
      const lngDiff = (Number(point.lng) - lng) * Math.cos(lat * Math.PI / 180);
      return (latDiff * latDiff) + (lngDiff * lngDiff);
    };
    return points.reduce((nearest, point) => distance(point) < distance(nearest) ? point : nearest).stop_name;
  }

  function renderParent() {
    const student = state?.student || {};
    const bus = state?.bus;
    const trip = state?.trip;
    const name = student.name || 'Student';
    const initials = escapeInitials(name);
    byId('studentName').textContent = `${name}${student.grade ? ` · Grade ${student.grade}` : ''}`;
    byId('studentGrade').textContent = `Verified parent · ${state.school?.name || 'School'}`;
    byId('studentAvatar').textContent = initials;
    byId('rosterStudentAvatar').textContent = initials;
    byId('rosterStudentName').textContent = name;
    const routeConsent = byId('routeServiceConsent');
    if (routeConsent && !routeConsentTouched) {
      routeConsent.checked = Boolean(student.route_service_consent);
    }

    const busCard = byId('assignedBusCard');
    busCard.replaceChildren();
    if (!bus) {
      busCard.append(textElement(
        'div',
        'school-empty',
        'Your school has verified your account but has not assigned a bus yet. The school team will update this view when one is assigned.'
      ));
    } else {
      const vehicle = document.createElement('div');
      vehicle.className = 'school-vehicle-thumb';
      const busIcon = document.createElement('i');
      busIcon.className = 'fa-solid fa-bus';
      busIcon.setAttribute('aria-hidden', 'true');
      vehicle.append(busIcon);
      const title = textElement('h2', '', `Bus ${bus.bus_number} · ${bus.route_name}`);
      const driver = textElement('p', '', `Driver: ${bus.driver_name || 'School transport team'}`);
      const next = textElement('p', '', `Next stop: ${bus.stops?.[0]?.stop_name || 'Route stops not listed'}`);
      const eta = textElement('p', '', 'ETA: Waiting for live location');
      eta.id = 'parentBusEta';
      const count = textElement('small', '', `${bus.student_count || 0} students assigned`);
      busCard.append(vehicle, title, driver, next, eta, count);
    }

    const status = state.student_status?.status || '';
    const statusPill = byId('studentStatusPill');
    statusPill.textContent = status === 'dropped_off'
      ? 'Dropped off'
      : status === 'approaching'
        ? 'Approaching'
        : status === 'on_board'
          ? 'On board'
          : 'Waiting';
    statusPill.classList.toggle('school-verified', status === 'on_board' || status === 'dropped_off');
    const rosterStatus = byId('parentRosterStatus');
    const statusText = rosterStatus.querySelector('small');
    const statusBadge = rosterStatus.querySelector('.school-live-pill');
    statusText.textContent = state.student_status?.updated_at
      ? `${status === 'dropped_off' ? 'Dropped off' : status === 'approaching' ? 'Approaching stop' : 'Boarded'} · ${formatTime(state.student_status.updated_at)}`
      : 'Waiting for boarding status';
    statusBadge.textContent = status === 'dropped_off'
      ? 'Dropped off'
      : status === 'approaching'
        ? 'Approaching'
        : status === 'on_board'
          ? 'On board'
          : 'Not boarded';

    const timeline = byId('schoolTimeline');
    timeline.replaceChildren();
    const timelineItems = [];
    if (trip) {
      timelineItems.push({
        title: 'Boarded at School',
        time: formatTime(trip.started_at),
        complete: Boolean(status),
      });
      if (status === 'approaching' || status === 'dropped_off') {
        timelineItems.push({
          title: `Approaching ${bus?.stops?.[0]?.stop_name || 'drop-off'}`,
          time: formatTime(state.student_status?.updated_at),
          complete: true,
        });
      }
      if (status === 'dropped_off') {
        timelineItems.push({
          title: 'Dropped Off',
          time: formatTime(state.student_status?.updated_at),
          complete: true,
        });
      }
    } else {
      timelineItems.push({
        title: bus ? 'Waiting for the school to start the trip' : 'Waiting for bus assignment',
        time: 'No live trip yet',
        complete: false,
      });
    }
    timelineItems.forEach(item => {
      const row = document.createElement('li');
      if (item.complete) row.classList.add('is-complete');
      const icon = document.createElement('i');
      icon.className = 'fa-solid fa-check';
      icon.setAttribute('aria-hidden', 'true');
      const detail = document.createElement('div');
      detail.append(textElement('strong', '', item.title), textElement('small', '', item.time));
      row.append(icon, detail);
      timeline.append(row);
    });

    const note = byId('parentDriverNote');
    note.hidden = !trip?.driver_note;
    note.textContent = trip?.driver_note ? `Driver updated: “${trip.driver_note}”` : '';
    const broadcast = byId('parentBroadcastStatus');
    const broadcastActive = Boolean(trip?.broadcast_active && trip?.current_lat !== null);
    broadcast.classList.toggle('is-live', broadcastActive);
    broadcast.querySelector('strong').textContent = broadcastActive
      ? 'Broadcast Link Active ✓'
      : trip?.status === 'arrived'
        ? 'Trip arrived · broadcast ended'
        : 'Waiting for school broadcast';
    byId('parentLastUpdate').textContent = broadcastActive ? relativeTime(trip.last_update) : 'Not sharing';
    byId('parentCurrentRoad').textContent = broadcastActive && bus
      ? `Near ${nearestStopName(bus, trip.current_lat, trip.current_lng)}`
      : 'Waiting for bus location';

    const actions = byId('parentContactActions');
    actions.replaceChildren();
    if (!bus) {
      actions.append(textElement('div', 'school-empty', 'Contact options appear after the school assigns a bus.'));
    } else {
      if (bus.driver_user_id) {
        actions.append(makeLink('Message Driver', `messages.php?to=${encodeURIComponent(bus.driver_user_id)}`, '', 'fa-regular fa-message'));
      } else if (bus.driver_phone) {
        actions.append(makeLink('Call Driver', `tel:${encodeURIComponent(bus.driver_phone)}`, '', 'fa-solid fa-phone'));
      } else {
        actions.append(textElement('div', 'school-empty', 'The school has not added driver contact details.'));
      }
      actions.append(makeLink('View Safety Profile', 'trusted-contacts.php', '', 'fa-solid fa-shield-halved'));
      actions.append(makeLink(
        'School Contact',
        state.school_contact_user_id
          ? `messages.php?to=${encodeURIComponent(state.school_contact_user_id)}`
          : 'school-trips.php',
        'school-contact-primary',
        'fa-solid fa-school'
      ));
    }
  }

  async function updateParentEta() {
    const bus = state?.bus;
    const trip = state?.trip;
    const destination = state?.student;
    const distanceLabel = byId('parentRemainingDistance');
    if (!distanceLabel) return;
    if (!bus || !trip?.broadcast_active || trip.current_lat === null || trip.current_lng === null) {
      distanceLabel.textContent = destination?.home_dropoff_lat === null
        ? 'Choose a home drop-off point'
        : 'Waiting for active location';
      return;
    }
    if (destination.home_dropoff_lat === null || destination.home_dropoff_lng === null) {
      distanceLabel.textContent = 'Choose a home drop-off point';
      return;
    }
    if (!destination.route_service_consent) {
      distanceLabel.textContent = 'Enable route-service consent for a road ETA';
      return;
    }
    const key = `parent:${bus.id}:${Number(trip.current_lat).toFixed(3)},${Number(trip.current_lng).toFixed(3)}:${destination.home_dropoff_lat},${destination.home_dropoff_lng}`;
    if (key === etaKey && Date.now() - etaUpdatedAt < 60000) return;
    etaKey = key;
    etaUpdatedAt = Date.now();
    distanceLabel.textContent = 'Calculating road distance…';
    try {
      const route = await calculateEta(
        { lat: trip.current_lat, lng: trip.current_lng },
        { lat: destination.home_dropoff_lat, lng: destination.home_dropoff_lng }
      );
      if (etaKey !== key) return;
      const distance = `${(route.distance_meters / 1000).toFixed(1)} km`;
      const eta = `ETA ${Math.max(1, Math.ceil(route.duration_seconds / 60))} min`;
      distanceLabel.textContent = `${distance} · ${eta}`;
      const busEta = byId('parentBusEta');
      if (busEta) busEta.textContent = eta;
    } catch (error) {
      if (etaKey !== key) return;
      distanceLabel.textContent = 'Road distance unavailable';
      console.warn('Parent home drop-off ETA could not be calculated.', error);
    }
  }

  function changeBroadcast(bus, enabled) {
    const action = enabled ? 'broadcast' : 'pause';
    runTripAction(action, bus);
  }

  async function runTripAction(action, bus) {
    try {
      setFeedback('schoolRightFeedback', 'Updating live trip…');
      await postJson('backend/api/school-transport/trips.php', {
        action,
        bus_id: bus.id,
      });
      setFeedback('schoolRightFeedback', action === 'arrived' ? 'Trip marked as arrived.' : 'Live trip updated.');
      if (action === 'arrived' || action === 'pause') stopPositionTracking();
      if (action === 'broadcast') startPositionTracking();
      await refreshState();
    } catch (error) {
      setFeedback('schoolRightFeedback', error.message, true);
      await refreshState();
    }
  }

  async function startTrip(bus) {
    try {
      if (bus.driver_user_id && Number(bus.driver_user_id) !== currentUserId) {
        throw new Error('Only the driver assigned to this bus can start GPS tracking. Ask them to sign in on the bus device.');
      }
      if (!window.confirm('This device’s GPS will be shared with verified parents assigned to this bus. Start only from the bus device.')) {
        return;
      }
      setFeedback('schoolLeftFeedback', 'Requesting location permission…');
      const position = await getCurrentPosition();
      await postJson('backend/api/school-transport/trips.php', {
        action: 'start',
        bus_id: bus.id,
        lat: position.coords.latitude,
        lng: position.coords.longitude,
      });
      setFeedback('schoolLeftFeedback', 'Trip started and shared with verified parents.');
      startPositionTracking();
      await refreshState({ refit: true });
    } catch (error) {
      setFeedback('schoolLeftFeedback', error.message, true);
    }
  }

  function startPositionTracking() {
    if (!navigator.geolocation || positionWatchId !== null) return;
    if (state?.selected_bus?.driver_user_id
      && Number(state.selected_bus.driver_user_id) !== currentUserId
    ) {
      setFeedback('schoolRightFeedback', 'Live broadcast is active. The assigned driver must share GPS from the bus device.');
      return;
    }
    positionWatchId = navigator.geolocation.watchPosition(
      position => {
        if (!state?.selected_bus?.trip_id || !state.selected_bus.broadcast_active) return;
        if (Date.now() - lastPositionSentAt < 15000) return;
        lastPositionSentAt = Date.now();
        postJson('backend/api/school-transport/positions.php', {
          trip_id: state.selected_bus.trip_id,
          lat: position.coords.latitude,
          lng: position.coords.longitude,
          speed_kmh: Number.isFinite(position.coords.speed) ? Math.max(0, position.coords.speed * 3.6) : null,
          accuracy_meters: Number.isFinite(position.coords.accuracy) ? position.coords.accuracy : null,
        }).then(() => refreshState()).catch(error => {
          setFeedback('schoolRightFeedback', error.message, true);
        });
      },
      error => {
        stopPositionTracking();
        setFeedback(
          'schoolRightFeedback',
          error.code === error.PERMISSION_DENIED
            ? 'Location permission was denied. Enable it to share the bus live location.'
            : 'Live location is temporarily unavailable on this device.',
          true
        );
      },
      { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 }
    );
  }

  function stopPositionTracking() {
    if (positionWatchId !== null) navigator.geolocation?.clearWatch(positionWatchId);
    positionWatchId = null;
  }

  async function updateStudentStatus(parent, status) {
    const trip = state?.selected_bus?.trip_id;
    if (!trip) return;
    try {
      await postJson('backend/api/school-transport/student-status.php', {
        verification_id: parent.verification_id,
        trip_id: trip,
        status,
      });
      setFeedback('schoolRightFeedback', `${parent.student_name}’s status was updated.`);
      await refreshState();
    } catch (error) {
      setFeedback('schoolRightFeedback', error.message, true);
    }
  }

  function openDialog(id) {
    const dialog = byId(id);
    if (dialog && !dialog.open) dialog.showModal();
  }

  function setSchoolPanel(panel) {
    app.classList.toggle('school-show-right', panel === 'right');
    document.querySelectorAll('[data-school-panel]').forEach(button => {
      const active = button.dataset.schoolPanel === panel;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', String(active));
    });
  }

  function setupAdminUi() {
    byId('openBusModal')?.addEventListener('click', () => openDialog('addBusDialog'));
    byId('openVerifyParentModal')?.addEventListener('click', () => {
      if (!state?.buses?.length) {
        setFeedback('schoolRightFeedback', 'Register a bus before assigning a parent.', true);
        return;
      }
      openDialog('verifyParentDialog');
    });
    document.querySelectorAll('[data-close-school-dialog]').forEach(button => {
      button.addEventListener('click', () => button.closest('dialog')?.close());
    });
    document.querySelectorAll('[data-bus-filter]').forEach(button => {
      button.addEventListener('click', () => {
        currentFilter = button.dataset.busFilter;
        document.querySelectorAll('[data-bus-filter]').forEach(filter => {
          filter.classList.toggle('is-active', filter === button);
        });
        renderBusList();
      });
    });
    byId('broadcastHomebound')?.addEventListener('click', async () => {
      const bus = state?.selected_bus;
      if (!bus) return;
      if (bus.trip_status === 'en_route') {
        if (bus.broadcast_active) await runTripAction('pause', bus);
        else await runTripAction('broadcast', bus);
      } else {
        await startTrip(bus);
      }
    });
    byId('driverNoteForm')?.addEventListener('submit', async event => {
      event.preventDefault();
      const bus = state?.selected_bus;
      const note = byId('driverNote').value.trim();
      if (!bus?.trip_id || !note) {
        setFeedback('schoolRightFeedback', 'Start a bus trip and enter an update first.', true);
        return;
      }
      try {
        await postJson('backend/api/school-transport/trips.php', {
          action: 'note',
          bus_id: bus.id,
          note,
        });
        byId('driverNote').value = '';
        setFeedback('schoolRightFeedback', 'Driver update shared with assigned parents.');
        await refreshState();
      } catch (error) {
        setFeedback('schoolRightFeedback', error.message, true);
      }
    });
    byId('addStopRow')?.addEventListener('click', () => {
      const rows = byId('schoolStopRows');
      if (rows.children.length >= 20) {
        setFeedback('addBusFeedback', 'A route can have up to 20 stops.', true);
        return;
      }
      const row = document.createElement('div');
      row.className = 'school-stop-row';
      row.innerHTML = '<input aria-label="Stop name" placeholder="Stop name" maxlength="120" required><select aria-label="Stop type"><option value="dropoff">Drop-off</option><option value="pickup">Pickup</option><option value="waypoint">Waypoint</option></select><input aria-label="Stop latitude" type="number" step="any" min="-90" max="90" placeholder="Latitude" required><input aria-label="Stop longitude" type="number" step="any" min="-180" max="180" placeholder="Longitude" required><button type="button" class="school-remove-stop" aria-label="Remove stop"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>';
      rows.append(row);
      updateRemoveStopButtons();
    });
    byId('schoolStopRows')?.addEventListener('click', event => {
      const button = event.target.closest('.school-remove-stop');
      if (!button || button.disabled) return;
      button.closest('.school-stop-row')?.remove();
      updateRemoveStopButtons();
    });
    byId('addBusForm')?.addEventListener('submit', createBus);
    byId('verifyParentForm')?.addEventListener('submit', verifyParent);
  }

  function updateRemoveStopButtons() {
    const rows = [...(byId('schoolStopRows')?.querySelectorAll('.school-stop-row') || [])];
    rows.forEach(row => {
      const button = row.querySelector('.school-remove-stop');
      button.disabled = rows.length <= 1;
    });
  }

  async function createBus(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const values = Object.fromEntries(new FormData(form).entries());
    const rows = [...byId('schoolStopRows').querySelectorAll('.school-stop-row')];
    const stops = rows.map(row => {
      const fields = row.querySelectorAll('input, select');
      return {
        name: fields[0].value.trim(),
        type: fields[1].value,
        lat: fields[2].value,
        lng: fields[3].value,
      };
    }).filter(stop => stop.name || stop.lat || stop.lng);
    try {
      setFeedback('addBusFeedback', 'Saving bus and route…');
      const response = await postJson('backend/api/school-transport/buses.php', {
        ...values,
        stops,
      });
      selectedBusId = response.bus_id;
      form.reset();
      byId('schoolStopRows').replaceChildren(byId('schoolStopRows').firstElementChild);
      updateRemoveStopButtons();
      byId('addBusDialog').close();
      setFeedback('schoolLeftFeedback', 'Bus and ordered route saved.');
      await refreshState({ refit: true });
    } catch (error) {
      setFeedback('addBusFeedback', error.message, true);
    }
  }

  async function verifyParent(event) {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(event.currentTarget).entries());
    try {
      setFeedback('verifyParentFeedback', 'Checking accepted guardian access…');
      await postJson('backend/api/school-transport/verify-parent.php', values);
      event.currentTarget.reset();
      byId('verifyParentDialog').close();
      setFeedback('schoolRightFeedback', 'Parent account verified and assigned.');
      await refreshState({ refit: true });
    } catch (error) {
      setFeedback('verifyParentFeedback', error.message, true);
    }
  }

  function setupParentUi() {
    async function saveDropoff(label, lat, lng) {
      await postJson('backend/api/school-transport/parent-dropoff.php', {
        label,
        lat,
        lng,
        route_service_consent: Boolean(byId('routeServiceConsent')?.checked),
      });
      routeConsentTouched = false;
      setFeedback('parentLeftFeedback', 'Home drop-off location saved.');
      await refreshState({ refit: true });
    }

    byId('routeServiceConsent')?.addEventListener('change', async event => {
      routeConsentTouched = true;
      const student = state?.student;
      if (student?.home_dropoff_lat === null || student?.home_dropoff_lng === null) {
        setFeedback('parentLeftFeedback', 'Choose your home drop-off point to save this route-service choice.');
        return;
      }

      const checkbox = event.currentTarget;
      checkbox.disabled = true;
      try {
        await saveDropoff(
          student.home_dropoff_label || 'Home drop-off',
          student.home_dropoff_lat,
          student.home_dropoff_lng
        );
      } catch (error) {
        setFeedback('parentLeftFeedback', error.message, true);
      } finally {
        checkbox.disabled = false;
      }
    });
    byId('chooseDropoff')?.addEventListener('click', () => {
      choosingDropoff = true;
      setFeedback('parentLeftFeedback', 'Tap the map to select your child’s home drop-off point.');
    });
    document.addEventListener('safaritrak:school-map-click', async event => {
      if (!choosingDropoff) return;
      choosingDropoff = false;
      const { lat, lng } = event.detail || {};
      if (!Number.isFinite(Number(lat)) || !Number.isFinite(Number(lng))) return;
      const label = byId('dropoffLabel').value.trim() || 'Home drop-off';
      const confirmed = window.confirm(
        `Save this point as “${label}”? School transport administrators will be able to see it for this student’s bus route.`
      );
      if (!confirmed) return;
      try {
        await saveDropoff(label, lat, lng);
      } catch (error) {
        setFeedback('parentLeftFeedback', error.message, true);
      }
    });
  }

  document.querySelectorAll('[data-school-panel]').forEach(button => {
    button.addEventListener('click', () => setSchoolPanel(button.dataset.schoolPanel));
  });
  if (isAdmin) setupAdminUi();
  else setupParentUi();
  refreshState({ refit: true });
  window.setInterval(() => refreshState(), 15000);
  window.setInterval(() => {
    if (!isAdmin && state?.trip?.broadcast_active) {
      byId('parentLastUpdate').textContent = relativeTime(state.trip.last_update);
    }
  }, 10000);
  window.addEventListener('beforeunload', stopPositionTracking);
})();
