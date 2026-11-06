(() => {
  const form = document.getElementById('dashboardPlanner');
  if (!form) return;

  const fields = {
    startLabel: document.getElementById('startPoint'),
    startLat: document.getElementById('startLat'),
    startLng: document.getElementById('startLng'),
    endLabel: document.getElementById('endPoint'),
    endLat: document.getElementById('endLat'),
    endLng: document.getElementById('endLng'),
  };
  const status = document.getElementById('dashboardPlannerStatus');
  const startButton = document.getElementById('startTripNow');
  const scheduleButton = document.getElementById('scheduleTrip');
  const confirmScheduleButton = document.getElementById('confirmScheduledTrip');
  const departureField = document.getElementById('scheduledDepartureField');
  const departureInput = document.getElementById('scheduledDeparture');
  const result = document.getElementById('dashboardTripResult');
  const resultStatus = document.getElementById('dashboardTripResultStatus');
  const shareButton = document.getElementById('shareDashboardTrip');
  const shareUrl = document.getElementById('dashboardTripShareUrl');
  const copyButton = document.getElementById('copyDashboardTripLink');
  const swapButton = document.getElementById('swapPlannerPoints');
  const retryRouteButton = document.getElementById('retryDashboardRoute');
  const exitFocusButton = document.getElementById('exitDashboardRouteFocus');
  const currentLocationButton = document.getElementById('useCurrentStartLocation');
  const sharedJourneyInput = document.getElementById('sharedJourneyUrl');
  const viewSharedJourneyButton = document.getElementById('viewSharedJourney');
  const sharedJourneyStatus = document.getElementById('sharedJourneyStatus');
  let routeRequest = null;
  let routeRequestId = 0;
  let routePoints = [];
  let routeAvailable = false;
  let selectedAction = null;
  let journeyId = null;
  let positionWatchId = null;
  let lastPositionSentAt = 0;
  let startUsesCurrentLocation = false;
  let publicShareToken = null;
  let publicRoutePoints = [];
  let publicRefreshTimer = null;
  let publicRequestId = 0;
  let publicRefreshInFlight = false;

  const readPoint = (latField, lngField) => {
    const lat = Number(latField.value);
    const lng = Number(lngField.value);
    return latField.value && lngField.value
      && Number.isFinite(lat) && Number.isFinite(lng)
      && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180
      ? { lat, lng }
      : null;
  };

  function setStatus(message, isError = false) {
    status.textContent = message;
    status.classList.toggle('is-error', isError);
    status.classList.toggle('is-loading', !isError && message.startsWith('Finding a road route'));
  }

  function updateActions(enabled) {
    startButton.disabled = !enabled || !startUsesCurrentLocation;
    scheduleButton.disabled = !enabled;
    confirmScheduleButton.disabled = !enabled;
  }

  function clearRoute(message = 'Choose both locations to preview a route.') {
    routeRequestId += 1;
    routeRequest?.abort();
    routeRequest = null;
    routeAvailable = false;
    routePoints = [];
    window.SafariTrakPlannedRouteState = null;
    retryRouteButton.hidden = true;
    window.SafariTrakDashboardMap?.clearPlannedRoute();
    updateActions(false);
    setStatus(message);
  }

  function updateRouteFocus() {
    const hasRoutePoints = Boolean(
      readPoint(fields.startLat, fields.startLng)
      && readPoint(fields.endLat, fields.endLng)
    );
    document.body.classList.toggle('dashboard-route-focus', hasRoutePoints);
    exitFocusButton.hidden = !hasRoutePoints;
  }

  async function refreshRoute() {
    const start = readPoint(fields.startLat, fields.startLng);
    const destination = readPoint(fields.endLat, fields.endLng);
    const requestId = ++routeRequestId;
    routeRequest?.abort();
    routeAvailable = false;
    retryRouteButton.hidden = true;
    updateActions(false);
    updateRouteFocus();

    if (!start || !destination) {
      clearRoute(fields.startLabel.value || fields.endLabel.value
        ? 'Choose a starting point and destination from the suggestions.'
        : 'Choose both locations to preview a route.');
      return;
    }

    routeRequest = new AbortController();
    setStatus('Finding a road route (up to 15 seconds)…');
    window.SafariTrakPlannedRouteState = { start, destination, points: [] };
    window.SafariTrakDashboardMap?.setPlannedRoute(start, destination, []);

    try {
      const route = await window.SafariTrakRouteDirections.getDrivingRoute(start, destination, {
        signal: routeRequest.signal,
      });
      if (requestId !== routeRequestId) return;
      routePoints = route.coordinates.map(([lng, lat]) => ({ lat, lng }));
      window.SafariTrakPlannedRouteState = { start, destination, points: routePoints };
      window.SafariTrakDashboardMap?.setPlannedRoute(start, destination, routePoints);
      routeAvailable = true;
      updateActions(true);
      const currentOriginHint = startUsesCurrentLocation
        ? ''
        : ' Use “Current location” as the origin to start now.';
      setStatus(`Road route · ${(route.distance_meters / 1000).toFixed(1)} km · about ${Math.max(1, Math.round(route.duration_seconds / 60))} min.${currentOriginHint}`);
    } catch (error) {
      if (requestId !== routeRequestId || error.name === 'AbortError') return;
      console.error('Dashboard route preview failed.', error);
      routePoints = [];
      window.SafariTrakPlannedRouteState = { start, destination, points: [] };
      window.SafariTrakDashboardMap?.setPlannedRoute(start, destination, []);
      updateActions(false);
      retryRouteButton.hidden = false;
      setStatus(error.message || 'A road route could not be found. Check both locations and retry.', true);
    }
  }

  async function createJourney(action) {
    const start = readPoint(fields.startLat, fields.startLng);
    const destination = readPoint(fields.endLat, fields.endLng);
    if (!start || !destination) {
      setStatus('Choose both locations from the suggestions before continuing.', true);
      return;
    }
    if (!routeAvailable) {
      setStatus('Wait for a road route before starting or scheduling this trip.', true);
      return;
    }
    if (action === 'start' && !startUsesCurrentLocation) {
      setStatus('Use your current location as the starting point before starting a trip now.', true);
      return;
    }

    const plannedDeparture = action === 'schedule' ? departureInput.value : '';
    if (action === 'schedule' && (!plannedDeparture || new Date(plannedDeparture).getTime() <= Date.now())) {
      setStatus('Choose a future departure time before scheduling.', true);
      departureInput.focus();
      return;
    }

    selectedAction = action;
    const button = action === 'schedule' ? confirmScheduleButton : startButton;
    button.disabled = true;
    const originalLabel = button.innerHTML;
    button.textContent = action === 'schedule' ? 'Scheduling…' : 'Starting…';
    try {
      const response = await fetch('backend/api/journeys/start.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          start_label: fields.startLabel.value.trim(),
          start_lat: start.lat,
          start_lng: start.lng,
          end_label: fields.endLabel.value.trim(),
          end_lat: destination.lat,
          end_lng: destination.lng,
          transport_mode: 'car',
          planned_departure_at: plannedDeparture || null,
          route_deviation_alert: true,
          share_with: [],
        }),
      });
      const payload = await response.json();
      if (!response.ok || !payload.success || !payload.journey_id) {
        throw new Error(payload.message || 'The trip could not be saved.');
      }
      journeyId = Number(payload.journey_id);
      result.hidden = false;
      shareButton.hidden = false;
      shareUrl.hidden = true;
      copyButton.hidden = true;
      resultStatus.textContent = action === 'schedule'
        ? `Trip scheduled for ${new Date(plannedDeparture).toLocaleString()}.`
        : 'Your journey has started. Create a temporary link to share it with anyone.';
      startButton.hidden = true;
      scheduleButton.hidden = true;
      confirmScheduleButton.hidden = true;
      departureField.hidden = true;
      setStatus(action === 'schedule' ? 'Trip saved to My trips.' : 'Journey started and saved to My trips.');
      if (action === 'start') beginJourneyLocationUpdates(journeyId);
    } catch (error) {
      console.error('Dashboard journey creation failed.', error);
      setStatus(error.message || 'The trip could not be saved. Please try again.', true);
      button.disabled = false;
      button.innerHTML = originalLabel;
      return;
    }
    button.disabled = false;
    button.innerHTML = originalLabel;
  }

  function beginJourneyLocationUpdates(activeJourneyId) {
    if (!navigator.geolocation) {
      resultStatus.textContent = 'Journey started. This browser cannot share live location; open the live map to continue.';
      return;
    }
    positionWatchId = navigator.geolocation.watchPosition(async position => {
      const now = Date.now();
      if (now - lastPositionSentAt < 15_000) return;
      lastPositionSentAt = now;
      try {
        const response = await fetch('backend/api/journeys/update-position.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            journey_id: activeJourneyId,
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            speed_kmh: Number.isFinite(position.coords.speed) ? Math.round(position.coords.speed * 3.6) : null,
          }),
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) {
          throw new Error(payload.message || 'Your location update could not be sent.');
        }
        resultStatus.textContent = 'Location updates are active. Create a temporary link when you are ready to share.';
      } catch (error) {
        resultStatus.textContent = error.message || 'Location updates are temporarily unavailable.';
      }
    }, error => {
      resultStatus.textContent = error.code === error.PERMISSION_DENIED
        ? 'Journey started, but location permission is off. Enable location access on the live map to share updates.'
        : 'Journey started. Waiting for a GPS fix before live location can be shared.';
    }, { enableHighAccuracy: true, maximumAge: 10_000, timeout: 15_000 });
  }

  const locationSelected = (event, latField, lngField) => {
    const point = event.detail;
    const lat = Number(point?.lat);
    const lng = Number(point?.lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)
      || lat < -90 || lat > 90 || lng < -180 || lng > 180
    ) {
      setStatus('The selected place has no valid map coordinates. Choose another suggestion.', true);
      return;
    }
    latField.value = String(lat);
    lngField.value = String(lng);
    refreshRoute();
  };
  fields.startLabel.addEventListener('safaritrak-location-selected', event => {
    startUsesCurrentLocation = false;
    locationSelected(event, fields.startLat, fields.startLng);
  });
  fields.endLabel.addEventListener('safaritrak-location-selected', event => {
    locationSelected(event, fields.endLat, fields.endLng);
  });
  [fields.startLabel, fields.endLabel].forEach(input => {
    input.addEventListener('input', () => {
      if (input === fields.startLabel) startUsesCurrentLocation = false;
      result.hidden = true;
      selectedAction = null;
      journeyId = null;
      refreshRoute();
    });
  });

  swapButton.addEventListener('click', () => {
    startUsesCurrentLocation = false;
    [fields.startLabel.value, fields.endLabel.value] = [fields.endLabel.value, fields.startLabel.value];
    [fields.startLat.value, fields.endLat.value] = [fields.endLat.value, fields.startLat.value];
    [fields.startLng.value, fields.endLng.value] = [fields.endLng.value, fields.startLng.value];
    refreshRoute();
  });

  retryRouteButton.addEventListener('click', refreshRoute);
  exitFocusButton.addEventListener('click', () => {
    document.body.classList.remove('dashboard-route-focus');
  });
  startButton.addEventListener('click', () => createJourney('start'));
  scheduleButton.addEventListener('click', () => {
    selectedAction = 'schedule';
    departureField.hidden = false;
    confirmScheduleButton.hidden = false;
    startButton.hidden = true;
    scheduleButton.hidden = true;
    const soon = new Date(Date.now() + 60 * 60 * 1000);
    soon.setMinutes(soon.getMinutes() - soon.getTimezoneOffset());
    departureInput.value = soon.toISOString().slice(0, 16);
    departureInput.focus();
    setStatus('Choose when you plan to leave, then confirm the schedule.');
  });
  confirmScheduleButton.addEventListener('click', () => createJourney('schedule'));

  currentLocationButton.addEventListener('click', () => {
    if (!navigator.geolocation) {
      setStatus('This browser does not support current location. Choose a starting point from the suggestions.', true);
      return;
    }
    currentLocationButton.disabled = true;
    currentLocationButton.textContent = 'Finding your location…';
    navigator.geolocation.getCurrentPosition(position => {
      const { latitude: lat, longitude: lng } = position.coords;
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        setStatus('Your browser returned invalid location coordinates. Try again or choose a place.', true);
        currentLocationButton.disabled = false;
        currentLocationButton.innerHTML = '<i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>Use current location';
        return;
      }
      fields.startLabel.value = 'Current location';
      fields.startLat.value = String(lat);
      fields.startLng.value = String(lng);
      startUsesCurrentLocation = true;
      result.hidden = true;
      selectedAction = null;
      journeyId = null;
      currentLocationButton.disabled = false;
      currentLocationButton.innerHTML = '<i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>Use current location';
      refreshRoute();
    }, error => {
      setStatus(error.code === error.PERMISSION_DENIED
        ? 'Location permission was denied. Allow location access in your browser, then try again.'
        : 'Your current location could not be determined. Check GPS or choose a place from the suggestions.', true);
      currentLocationButton.disabled = false;
      currentLocationButton.innerHTML = '<i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>Use current location';
    }, { enableHighAccuracy: true, timeout: 15_000, maximumAge: 10_000 });
  });

  function parseSharedJourneyToken(value) {
    let url;
    try {
      url = new URL(value.trim(), window.location.href);
    } catch (error) {
      throw new Error('Paste a valid temporary journey link.');
    }
    const expectedPath = new URL('shared-location.php', window.location.href).pathname;
    const token = url.searchParams.get('token') || '';
    if (url.origin !== window.location.origin || url.pathname !== expectedPath || !/^[a-f0-9]{64}$/.test(token)) {
      throw new Error('Paste a SafariTrak shared-location link with a valid token.');
    }
    return token;
  }

  function sharedJourneyMessage(journey) {
    if (journey.status === 'planned') {
      const departure = journey.planned_departure_at
        ? new Date(journey.planned_departure_at.replace(' ', 'T')).toLocaleString()
        : 'departure time not set';
      return `Scheduled trip · ${departure}. No live location is available until it starts.`;
    }
    if (!journey.location) return 'Journey is active; waiting for the first live location update.';
    const recordedAt = journey.location.recorded_at ? Date.parse(journey.location.recorded_at) : NaN;
    return Number.isFinite(recordedAt)
      ? `Live location updated ${new Date(recordedAt).toLocaleTimeString()}. Refreshing every 30 seconds.`
      : 'Live journey location shown. Refreshing every 30 seconds.';
  }

  async function refreshSharedJourney(token, requestId, fitMap = false) {
    if (publicRefreshInFlight || requestId !== publicRequestId) return null;
    publicRefreshInFlight = true;
    try {
      const url = new URL('backend/api/journeys/public-share.php', window.location.href);
      url.searchParams.set('token', token);
      const response = await fetch(url, {
        cache: 'no-store',
        headers: { Accept: 'application/json' },
      });
      const payload = await response.json();
      if (requestId !== publicRequestId) return null;
      if (!response.ok || !payload.success || !payload.journey) {
        if (response.status === 404) {
          window.clearInterval(publicRefreshTimer);
          publicRefreshTimer = null;
        }
        throw new Error(payload.message || 'This shared journey could not be loaded.');
      }
      const journey = payload.journey;
      const pointIsValid = point => point
        && Number.isFinite(Number(point.lat)) && Number.isFinite(Number(point.lng))
        && Number(point.lat) >= -90 && Number(point.lat) <= 90
        && Number(point.lng) >= -180 && Number(point.lng) <= 180;
      if (!pointIsValid({ lat: journey.start_lat, lng: journey.start_lng })
        || !pointIsValid({ lat: journey.end_lat, lng: journey.end_lng })
      ) {
        throw new Error('This shared journey does not contain valid start and finish locations.');
      }
      window.SafariTrakDashboardMap?.showSharedJourney(journey, publicRoutePoints, fitMap);
      sharedJourneyStatus.textContent = sharedJourneyMessage(journey);
      sharedJourneyStatus.classList.remove('is-error');
      return journey;
    } catch (error) {
      if (requestId === publicRequestId) {
        sharedJourneyStatus.textContent = error.message || 'The shared journey could not be refreshed.';
        sharedJourneyStatus.classList.add('is-error');
      }
      return null;
    } finally {
      publicRefreshInFlight = false;
    }
  }

  viewSharedJourneyButton.addEventListener('click', async () => {
    window.clearInterval(publicRefreshTimer);
    publicRefreshTimer = null;
    publicRefreshInFlight = false;
    publicRequestId += 1;
    const requestId = publicRequestId;
    publicRoutePoints = [];
    window.SafariTrakDashboardMap?.clearSharedJourney();
    try {
      publicShareToken = parseSharedJourneyToken(sharedJourneyInput.value);
    } catch (error) {
      publicShareToken = null;
      sharedJourneyStatus.textContent = error.message;
      sharedJourneyStatus.classList.add('is-error');
      return;
    }
    viewSharedJourneyButton.disabled = true;
    sharedJourneyStatus.textContent = 'Loading shared journey and road route…';
    sharedJourneyStatus.classList.remove('is-error');
    let journey = await refreshSharedJourney(publicShareToken, requestId, true);
    if (!journey || requestId !== publicRequestId) {
      viewSharedJourneyButton.disabled = false;
      return;
    }
    try {
      const route = await window.SafariTrakRouteDirections.getDrivingRoute(
        { lat: Number(journey.start_lat), lng: Number(journey.start_lng) },
        { lat: Number(journey.end_lat), lng: Number(journey.end_lng) }
      );
      if (requestId !== publicRequestId) return;
      publicRoutePoints = route.coordinates.map(([lng, lat]) => ({ lat, lng }));
      window.SafariTrakDashboardMap?.showSharedJourney(journey, publicRoutePoints, false);
      sharedJourneyStatus.textContent = sharedJourneyMessage(journey);
    } catch (error) {
      if (requestId === publicRequestId) {
        console.error('Shared journey route preview failed.', error);
        sharedJourneyStatus.textContent = `${sharedJourneyMessage(journey)} Road route unavailable; showing the journey locations.`;
      }
    } finally {
      viewSharedJourneyButton.disabled = false;
    }
    if (requestId === publicRequestId) {
      publicRefreshTimer = window.setInterval(() => {
        refreshSharedJourney(publicShareToken, requestId);
      }, 30_000);
    }
  });

  shareButton.addEventListener('click', async () => {
    shareButton.disabled = true;
    try {
      const response = await fetch('backend/api/journeys/share-link.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create', journey_id: journeyId }),
      });
      const payload = await response.json();
      if (!response.ok || !payload.success || !payload.share_url) {
        throw new Error(payload.message || 'The trip link could not be created.');
      }
      shareUrl.value = payload.share_url;
      shareUrl.hidden = false;
      copyButton.hidden = false;
      resultStatus.textContent = selectedAction === 'schedule'
        ? 'Temporary route link created. It will show the planned route without live location.'
        : 'Temporary live-trip link created. Anyone with the link can view this active trip.';
    } catch (error) {
      console.error('Dashboard trip sharing failed.', error);
      resultStatus.textContent = error.message || 'The trip link could not be created.';
    } finally {
      shareButton.disabled = false;
    }
  });

  copyButton.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(shareUrl.value);
      resultStatus.textContent = 'Trip link copied.';
    } catch (error) {
      resultStatus.textContent = 'Clipboard access is unavailable. Select the link and copy it manually.';
    }
  });
})();
