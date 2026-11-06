(() => {
  const mapElement = document.getElementById('dashboardMapCanvas');
  const statusElement = document.getElementById('dashboardMapStatus');
  const config = document.getElementById('safariDashboardMapConfig');
  if (!mapElement) return;

  let billingWarningMessage = null;
  let usesCloudMapStyle = false;
  const state = {
    map: null,
    markers: new Map(),
    sharedMarkerListeners: new Map(),
    routeLine: null,
    routeGlowLine: null,
    plannedRouteLine: null,
    plannedRouteGlowLine: null,
    publicJourneyLines: [],
    plannedStartMarker: null,
    plannedDestinationMarker: null,
    pendingPlannedRoute: null,
    publicJourney: null,
    pendingSearchLocation: null,
    deviceLocation: null,
    sharedLocations: [],
    locationPending: false,
    locationError: null,
    userCentered: false,
    refreshTimer: null,
    savedView: window.SafariTrakMapView?.read() || null,
  };
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const validPoint = point => Number.isFinite(Number(point?.lat)) && Number.isFinite(Number(point?.lng))
    && Number(point.lat) >= -90 && Number(point.lat) <= 90
    && Number(point.lng) >= -180 && Number(point.lng) <= 180;

  function setStatus(message, isError = false) {
    if (!statusElement) return;
    if (billingWarningMessage && !isError) {
      message = billingWarningMessage;
      isError = true;
    }

    const themeObserver = new MutationObserver(() => {
      if (state.map && !usesCloudMapStyle && state.map.getMapTypeId() !== 'hybrid') {
        state.map.setOptions({ styles: mapStyles() });
      }
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    statusElement.textContent = message;
    statusElement.classList.toggle('is-error', isError);
    statusElement.title = isError ? message : '';
    statusElement.closest('.explore-map-heading')?.classList.toggle('has-error', isError);
  }

  const billingWarning = "This page can't load Google Maps correctly.";
  const reportBillingWarning = () => {
    billingWarningMessage = 'Google Maps reports a configuration problem.';
    const detail = `${billingWarningMessage} Check the Cloud project billing account, Maps JavaScript API, browser-key restrictions, and map ID.`;
    if (statusElement) statusElement.title = detail;
    fallbackToMapLibre(detail);
    billingObserver.disconnect();
  };
  const billingObserver = new MutationObserver(() => {
    if (mapElement.textContent.includes(billingWarning)) reportBillingWarning();
  });
  billingObserver.observe(mapElement, { childList: true, subtree: true, characterData: true });
  if (mapElement.textContent.includes(billingWarning)) reportBillingWarning();

  function fallbackToMapLibre(message) {
    if (!window.SafariTrakMapFallback) {
      setStatus(message, true);
      return;
    }
    mapElement.replaceChildren();
    state.map = null;
    setStatus('Google Maps is unavailable. Loading the backup flat map…');
    window.SafariTrakMapFallback.load(`dashboard-map.js?v=${Date.now()}`)
      .then(() => setStatus('Backup flat map loaded. Your device location is not shared from this map.'))
      .catch(error => {
        console.error('SafariTrak backup globe map failed.', error);
        setStatus(`${message} Backup map error: ${error.message}`, true);
      });
  }

  document.addEventListener('safaritrak-google-maps-error', event => {
    fallbackToMapLibre(event.detail?.message || 'Google Maps authentication failed. Check the API key and billing.');
  });

  function mapStyles(theme = document.documentElement.dataset.theme) {
    const light = theme === 'light';
    return [
      { elementType: 'geometry', stylers: [{ color: light ? '#F8FAFC' : '#0F172A' }] },
      { elementType: 'labels.text.fill', stylers: [{ color: light ? '#0F172A' : '#F8FAFC' }] },
      { elementType: 'labels.text.stroke', stylers: [{ color: light ? '#FFFFFF' : '#000000' }, { weight: 2 }] },
      { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: light ? '#E2E8F0' : '#1E293B' }] },
      { featureType: 'landscape', elementType: 'geometry', stylers: [{ color: light ? '#F8FAFC' : '#0F172A' }] },
      { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: light ? '#D1FAE5' : '#064E3B' }] },
      { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: light ? '#C026D3' : '#D946EF' }] },
      { featureType: 'road.arterial', elementType: 'geometry', stylers: [{ color: light ? '#EA580C' : '#F97316' }] },
      { featureType: 'road.local', elementType: 'geometry', stylers: [{ color: light ? '#E2E8F0' : '#334155' }] },
      { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: light ? '#0F172A' : '#F8FAFC' }] },
      { featureType: 'transit', elementType: 'all', stylers: [{ visibility: 'off' }] },
      { featureType: 'water', elementType: 'geometry', stylers: [{ color: light ? '#E0F2FE' : '#050B24' }] },
      { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: light ? '#0F172A' : '#F8FAFC' }] },
    ];
  }

  function mapIcon(color, scale = 7) {
    return {
      path: google.maps.SymbolPath.CIRCLE,
      fillColor: color,
      fillOpacity: 1,
      strokeColor: '#ffffff',
      strokeWeight: 2,
      scale,
    };
  }

  function markerLabel(text, kind = 'place') {
    return text ? { text: String(text), className: `dashboard-google-marker-label dashboard-google-marker-label--${kind}` } : undefined;
  }

  function setMarker(id, point, title, color, kind = 'place', scale = 7) {
    if (!validPoint(point)) return null;
    let marker = state.markers.get(id);
    if (!marker) {
      marker = new google.maps.Marker({
        map: state.map,
        position: { lat: Number(point.lat), lng: Number(point.lng) },
        title,
        icon: mapIcon(color, scale),
        label: markerLabel(title, kind),
        zIndex: kind === 'user' ? 1000 : undefined,
      });
      state.markers.set(id, marker);
    } else {
      marker.setMap(state.map);
      marker.setPosition({ lat: Number(point.lat), lng: Number(point.lng) });
      marker.setTitle(title);
      marker.setLabel(markerLabel(title, kind));
    }
    return marker;
  }

  function setUserMarker(point, source = 'Your location') {
    if (!validPoint(point)) return;
    let marker = state.markers.get('current');
    if (!marker) {
      marker = window.SafariTrakGoogleLocationMarker.create(state.map, point, source);
      state.markers.set('current', marker);
    } else {
      marker.setPosition({ lat: Number(point.lat), lng: Number(point.lng) });
      marker.setTitle(source);
    }
  }

  function keepUserLocationVisible() {
    const marker = state.markers.get('current');
    if (!marker || !state.map) return;
    const projection = state.map.getProjection();
    const bounds = state.map.getBounds();
    if (!projection || !bounds) return;
    if (!bounds.contains(marker.getPosition())) {
      state.map.panTo(marker.getPosition());
      return;
    }
    const mapRect = mapElement.getBoundingClientRect();
    const markerPoint = projection.fromLatLngToPoint(marker.getPosition());
    const centerPoint = projection.fromLatLngToPoint(state.map.getCenter());
    const worldScale = 2 ** state.map.getZoom();
    const screenPoint = {
      x: mapRect.left + mapRect.width / 2 + (markerPoint.x - centerPoint.x) * worldScale,
      y: mapRect.top + mapRect.height / 2 + (markerPoint.y - centerPoint.y) * worldScale,
    };
    const panels = [...document.querySelectorAll(
      '.app--explore .sidebar, .app--explore .app-header, .app--explore .app-search-row, .app--explore .explore-panel, .app--explore .explore-shortcuts, .app--explore .mobile-bottom-nav'
    )].filter(panel => panel.getClientRects().length && getComputedStyle(panel).visibility !== 'hidden');
    if (!panels.some(panel => {
      const rect = panel.getBoundingClientRect();
      return screenPoint.x >= rect.left && screenPoint.x <= rect.right
        && screenPoint.y >= rect.top && screenPoint.y <= rect.bottom;
    })) return;
    const targets = [[0.52, 0.48], [0.72, 0.5], [0.34, 0.5], [0.5, 0.32], [0.5, 0.68]]
      .map(([x, y]) => ({ x: mapRect.left + mapRect.width * x, y: mapRect.top + mapRect.height * y }));
    const target = targets.find(candidate => !panels.some(panel => {
      const rect = panel.getBoundingClientRect();
      return candidate.x >= rect.left && candidate.x <= rect.right
        && candidate.y >= rect.top && candidate.y <= rect.bottom;
    }));
    if (target) state.map.panBy(screenPoint.x - target.x, screenPoint.y - target.y);
  }

  function centerOnUser(point, source = 'Your location', recenter = true, zoom = 16) {
    if (!validPoint(point)) return;
    state.userCentered = true;
    state.deviceLocation = point;
    if (!state.map) return;
    setUserMarker(point, source);
    if (recenter) {
      state.map.panTo({ lat: Number(point.lat), lng: Number(point.lng) });
      state.map.setZoom(zoom);
    }
  }

  function showDashboardSearchLocation(point) {
    if (!validPoint(point)) throw new TypeError('A valid searched location is required.');
    if (!state.map) {
      state.pendingSearchLocation = point;
      return;
    }
    state.pendingSearchLocation = null;
    setMarker('search-result', point, point.label || 'Searched location', '#ffcf5c', 'search', 8);
    state.map.panTo({ lat: Number(point.lat), lng: Number(point.lng) });
    state.map.setZoom(15);
  }

  function clearMarker(id) {
    const marker = state.markers.get(id);
    if (marker) marker.setMap(null);
    state.markers.delete(id);
  }

  function setRoute(points) {
    state.routeLine?.setMap(null);
    state.routeGlowLine?.setMap(null);
    const path = points.filter(validPoint).map(point => ({ lat: Number(point.lat), lng: Number(point.lng) }));
    state.routeGlowLine = path.length > 1 ? new google.maps.Polyline({
      map: state.map,
      path,
      strokeColor: '#D946EF',
      strokeOpacity: 0.42,
      strokeWeight: 12,
      zIndex: 9,
    }) : null;
    state.routeLine = path.length > 1 ? new google.maps.Polyline({
      map: state.map,
      path,
      strokeColor: '#C026D3',
      strokeOpacity: 0.96,
      strokeWeight: 5,
      zIndex: 10,
    }) : null;
  }

  function setPlannedRoute(start, destination, points) {
    if (!validPoint(start) || !validPoint(destination)) {
      throw new TypeError('Valid starting and destination coordinates are required.');
    }
    const path = Array.isArray(points)
      ? points.filter(validPoint).map(point => ({ lat: Number(point.lat), lng: Number(point.lng) }))
      : [];
    state.pendingPlannedRoute = { start, destination, path };
    if (!state.map) return;

    state.plannedRouteLine?.setMap(null);
    state.plannedRouteGlowLine?.setMap(null);
    state.plannedStartMarker?.setMap(null);
    state.plannedDestinationMarker?.setMap(null);
    state.plannedStartMarker = setMarker('planned-start', start, 'Trip start', '#47f5cf', 'start', 8);
    state.plannedDestinationMarker = setMarker('planned-destination', destination, 'Trip destination', '#ff4db8', 'finish', 8);
    state.plannedRouteGlowLine = path.length > 1 ? new google.maps.Polyline({
      map: state.map,
      path,
      strokeColor: '#D946EF',
      strokeOpacity: 0.42,
      strokeWeight: 13,
      zIndex: 29,
    }) : null;
    state.plannedRouteLine = path.length > 1 ? new google.maps.Polyline({
      map: state.map,
      path,
      strokeColor: '#C026D3',
      strokeOpacity: 0.96,
      strokeWeight: 5,
      zIndex: 30,
    }) : null;

    const bounds = new google.maps.LatLngBounds();
    bounds.extend({ lat: Number(start.lat), lng: Number(start.lng) });
    bounds.extend({ lat: Number(destination.lat), lng: Number(destination.lng) });
    path.forEach(point => bounds.extend(point));
    state.map.fitBounds(bounds, 90);
  }

  function clearPlannedRoute() {
    state.pendingPlannedRoute = null;
    state.plannedRouteLine?.setMap(null);
    state.plannedRouteGlowLine?.setMap(null);
    clearMarker('planned-start');
    clearMarker('planned-destination');
    state.plannedRouteLine = null;
    state.plannedStartMarker = null;
    state.plannedDestinationMarker = null;
  }

  function showSharedJourney(journey, points = [], fit = false) {
    const shouldFit = fit || (!state.map && Boolean(state.publicJourney?.fit));
    state.publicJourney = { journey, points, fit: shouldFit };
    window.SafariTrakSharedJourneyState = state.publicJourney;
    if (!state.map) return;
    const start = { lat: Number(journey.start_lat), lng: Number(journey.start_lng) };
    const finish = { lat: Number(journey.end_lat), lng: Number(journey.end_lng) };
    if (!validPoint(start) || !validPoint(finish)) return;
    setMarker('public-shared-start', start, `Start · ${journey.start_label}`, '#47f5cf', 'start', 8);
    setMarker('public-shared-finish', finish, `Finish · ${journey.end_label}`, '#ff4db8', 'finish', 8);
    const current = validPoint(journey.location) ? journey.location : null;
    if (current) {
      setMarker('public-shared-current', current, 'Current shared location', '#ffcf5c', 'user', 8);
    } else {
      clearMarker('public-shared-current');
    }
    const path = Array.isArray(points) ? points.filter(validPoint).map(point => ({
      lat: Number(point.lat),
      lng: Number(point.lng),
    })) : [];
    state.publicJourneyLines.forEach(line => line.setMap(null));
    state.publicJourneyLines = path.length > 1 ? [
      new google.maps.Polyline({
        map: state.map,
        path,
        strokeColor: '#D946EF',
        strokeOpacity: 0.42,
        strokeWeight: 13,
        zIndex: 34,
      }),
      new google.maps.Polyline({
        map: state.map,
        path,
        strokeColor: '#C026D3',
        strokeOpacity: 0.96,
        strokeWeight: 5,
        zIndex: 35,
      }),
    ] : [];
    if (shouldFit) {
      const bounds = new google.maps.LatLngBounds();
      [start, finish, ...(current ? [current] : []), ...path].forEach(point => bounds.extend(point));
      state.map.fitBounds(bounds, 90);
    }
    state.publicJourney.fit = false;
  }

  function clearSharedJourney() {
    state.publicJourney = null;
    window.SafariTrakSharedJourneyState = null;
    state.publicJourneyLines.forEach(line => line.setMap(null));
    state.publicJourneyLines = [];
    ['public-shared-start', 'public-shared-current', 'public-shared-finish'].forEach(clearMarker);
  }

  function updateTrip(trip) {
    setRoute(trip.route || []);
    setMarker('trip-start', trip.start, `Start · ${trip.start_label}`, '#47f5cf', 'start', 8);
    setMarker('trip-destination', trip.destination, `Destination · ${trip.destination_label}`, '#ff4db8', 'finish', 8);
    if (trip.current && !state.deviceLocation) setUserMarker(trip.current, 'Your last reported journey location');
    const speed = document.getElementById('dashboardSpeed');
    const remaining = document.getElementById('dashboardRemaining');
    const eta = document.getElementById('dashboardEta');
    if (speed) speed.textContent = trip.speed_kmh === null ? '—' : `${Math.round(trip.speed_kmh)} km/h`;
    if (remaining) remaining.textContent = trip.remaining_distance_km === null ? '—' : `${Number(trip.remaining_distance_km).toFixed(1)} km`;
    if (eta) eta.textContent = trip.eta_minutes === null ? '—' : trip.eta_minutes < 1 ? 'Arriving' : `${trip.eta_minutes} min`;
  }

  async function refreshTrip() {
    if (!config?.dataset.tripId) return;
    const url = new URL(config.dataset.telemetryUrl, location.href);
    url.searchParams.set('id', config.dataset.tripId);
    const response = await fetch(url, { credentials: 'same-origin' });
    const payload = await response.json();
    if (!response.ok || !payload.success || !payload.trip) {
      throw new Error(payload.message || `Journey details could not be loaded (HTTP ${response.status}).`);
    }
    updateTrip(payload.trip);
    if (!state.deviceLocation && payload.trip.current && !state.userCentered) {
      centerOnUser(payload.trip.current, 'Your last reported journey location', false);
    }
  }

  function updateSharedLocations(locations) {
    state.sharedLocations = locations;
    const activeIds = new Set();
    locations.forEach(location => {
      if (!validPoint(location)) return;
      const id = `shared-${location.journey_id}`;
      activeIds.add(id);
      const timestamp = Date.parse(location.recorded_at || '');
      const live = Number.isFinite(timestamp) && Date.now() - timestamp <= 90_000;
      const freshness = live ? 'Live' : 'Last known';
      const title = `${location.name}, ${freshness.toLowerCase()} location shared with you`;
      const marker = setMarker(id, location, title, '#b258ff', 'shared', 9);
      if (marker && !state.sharedMarkerListeners.has(id)) {
        state.sharedMarkerListeners.set(id, marker.addListener('click', () => setStatus(marker.getTitle())));
      }
    });
    for (const id of state.markers.keys()) {
      if (id.startsWith('shared-') && !activeIds.has(id)) {
        state.sharedMarkerListeners.get(id)?.remove();
        state.sharedMarkerListeners.delete(id);
        clearMarker(id);
      }
    }
  }

  async function refreshSharedLocations() {
    if (!config?.dataset.sharedLocationsUrl) return [];
    const response = await fetch(new URL(config.dataset.sharedLocationsUrl, location.href), { credentials: 'same-origin' });
    const payload = await response.json();
    if (!response.ok || !payload.success || !Array.isArray(payload.locations)) {
      throw new Error(payload.message || `Shared locations could not be loaded (HTTP ${response.status}).`);
    }
    updateSharedLocations(payload.locations);
    return payload.locations;
  }

  async function refreshData() {
    const requests = await Promise.allSettled([
      refreshSharedLocations(),
      config?.dataset.tripId ? refreshTrip() : Promise.resolve(),
    ]);
    const errors = requests.filter(result => result.status === 'rejected').map(result => result.reason.message);
    if (errors.length) {
      setStatus(`Map data could not be refreshed: ${errors.join(' ')}`, true);
    } else if (config?.dataset.tripId) {
      setStatus('Live trip route · location sharing follows your journey permissions.');
    } else if (state.sharedLocations.length) {
      setStatus(`${state.sharedLocations.length} location${state.sharedLocations.length === 1 ? '' : 's'} shared with you.`);
    } else if (state.deviceLocation) {
      setStatus('Your device location is shown here only; it is not shared with contacts.');
    } else if (!state.locationPending) {
      setStatus(state.locationError || 'No other locations are currently shared with you.', Boolean(state.locationError));
    }
  }

  function locateOnMap(recenter = true, zoom = 16) {
    if (!navigator.geolocation) {
      state.locationError = 'This browser does not support device location.';
      setStatus(state.locationError, true);
      return;
    }
    state.locationPending = true;
    navigator.geolocation.getCurrentPosition(
      position => {
        state.locationPending = false;
        state.deviceLocation = { lat: position.coords.latitude, lng: position.coords.longitude };
        window.SafariTrakMapView?.saveLocation(position);
        centerOnUser(state.deviceLocation, 'Your location', recenter, zoom);
        setStatus('Your device location is shown here only; it is not shared with contacts.');
      },
      error => {
        state.locationPending = false;
        state.locationError = error.code === error.PERMISSION_DENIED
          ? 'Location permission was denied. Enable it in your browser to show your position.'
          : 'Your location could not be determined. Try again when GPS is available.';
        setStatus(state.locationError, true);
      },
      { enableHighAccuracy: true, timeout: 12_000, maximumAge: 60_000 }
    );
  }

  function setMapMode(mode) {
    if (!state.map || !['flat', 'satellite'].includes(mode)) return;
    const mapTypeId = mode === 'satellite' ? 'hybrid' : 'roadmap';
    state.map.setMapTypeId(mapTypeId);
    if (mapTypeId === 'roadmap' && !document.querySelector('meta[name="google-map-id"]')?.content.trim()) {
      state.map.setOptions({ styles: mapStyles() });
    }
    state.savedView = { ...(state.savedView || {}), projection: mode };
    window.SafariTrakMapView?.save(state.map, mode);
    document.querySelectorAll('[data-dashboard-projection]').forEach(button => {
      const selected = button.dataset.dashboardProjection === mode;
      button.setAttribute('aria-selected', String(selected));
      button.tabIndex = selected ? 0 : -1;
    });
  }

  function initializeMap() {
    if (!window.google?.maps) throw new Error('Google Maps JavaScript API did not load.');
    const saved = window.SafariTrakMapView?.read() || null;
    const cachedLocation = window.SafariTrakMapView?.readLocation() || null;
    const initialPoint = cachedLocation || saved || { lat: -1.286389, lng: 36.817223 };
    const mapId = document.querySelector('meta[name="google-map-id"]')?.content.trim();
    usesCloudMapStyle = Boolean(mapId);
    state.map = new google.maps.Map(mapElement, {
      center: { lat: initialPoint.lat, lng: initialPoint.lng },
      zoom: cachedLocation ? 16 : saved?.zoom ?? 4.8,
      mapTypeId: saved?.projection === 'satellite' ? 'hybrid' : 'roadmap',
      tilt: 0,
      ...(mapId ? { mapId } : { styles: mapStyles() }),
      disableDefaultUI: true,
      clickableIcons: true,
      gestureHandling: 'greedy',
      scrollwheel: false,
      disableDoubleClickZoom: true,
      keyboardShortcuts: false,
    });
    mapElement.classList.add('is-ready');
    google.maps.event.addListener(state.map, 'idle', () => {
      const mapType = state.map.getMapTypeId();
      window.SafariTrakMapView?.save(state.map, mapType === 'satellite' || mapType === 'hybrid' ? 'satellite' : 'flat');
      keepUserLocationVisible();
    });
    if (cachedLocation) {
      state.deviceLocation = cachedLocation;
      setUserMarker(cachedLocation);
      state.userCentered = true;
    }
    if (!state.pendingSearchLocation) locateOnMap(true, 16);
    if (state.pendingSearchLocation) showDashboardSearchLocation(state.pendingSearchLocation);
    if (state.pendingPlannedRoute) {
      const { start, destination, path } = state.pendingPlannedRoute;
      setPlannedRoute(start, destination, path);
    }
    if (state.publicJourney) {
      showSharedJourney(state.publicJourney.journey, state.publicJourney.points, state.publicJourney.fit);
    }
    document.querySelectorAll('[data-dashboard-projection]').forEach(button => {
      button.addEventListener('click', () => setMapMode(button.dataset.dashboardProjection));
      button.addEventListener('keydown', event => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const mode = event.key === 'Home' ? 'flat' : event.key === 'End' ? 'satellite'
          : button.dataset.dashboardProjection === 'flat' ? 'satellite' : 'flat';
        setMapMode(mode);
        document.querySelector(`[data-dashboard-projection="${mode}"]`)?.focus();
      });
    });
    document.getElementById('dashboardLocate')?.addEventListener('click', () => locateOnMap(true));
    document.addEventListener('safaritrak-location-selected', event => {
      if (event.target.id === 'destination') showDashboardSearchLocation(event.detail);
    });
    refreshData();
    state.refreshTimer = window.setInterval(refreshData, 15_000);
    setMapMode(saved?.projection === 'satellite' ? 'satellite' : 'flat');
  }

  window.SafariTrakDashboardMap = {
    showSearchLocation: showDashboardSearchLocation,
    setPlannedRoute,
    clearPlannedRoute,
    showSharedJourney,
    clearSharedJourney,
  };
  window.SafariTrakGoogleMaps?.ready
    .then(initializeMap)
    .catch(error => {
      console.error('SafariTrak Explore Google map failed.', error);
      fallbackToMapLibre(error.message || 'The interactive Google map could not be loaded.');
    });
})();
