const dashboardMapElement = document.getElementById('dashboardMapCanvas') || document.getElementById('dashboardMap');
const dashboardMapStatus = document.getElementById('dashboardMapStatus');
const dashboardMapConfig = document.getElementById('safariDashboardMapConfig');
const dashboardReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const dashboardMapState = {
  map: null,
  route: [],
  plannedRoute: null,
  publicJourney: null,
  markers: new Map(),
  projection: 'flat',
  userCentered: false,
  locationPending: false,
  locationError: null,
  deviceLocation: null,
  sharedLocations: [],
  refreshTimer: null,
  themeObserver: null,
};

function setDashboardMapStatus(message, isError = false) {
  if (!dashboardMapStatus) return;
  dashboardMapStatus.textContent = message;
  dashboardMapStatus.classList.toggle('is-error', isError);
}

function dashboardStyleUrl() {
  return window.SafariTrakMapStyle.styleUrl;
}

function applyDashboardNeonStyle() {
  window.SafariTrakMapStyle.apply(dashboardMapState.map);
}

function markerElement(className, label, icon) {
  const element = document.createElement('button');
  element.type = 'button';
  element.className = className;
  element.setAttribute('aria-label', label);
  element.title = label;
  const glyph = document.createElement('i');
  glyph.className = `fa-solid ${icon}`;
  glyph.setAttribute('aria-hidden', 'true');
  element.append(glyph);
  return element;
}

function setUserMarker(point, sourceLabel = 'Your location') {
  if (!point || !Number.isFinite(point.lat) || !Number.isFinite(point.lng)) return;
  const id = 'current';
  let marker = dashboardMapState.markers.get(id);
  if (!marker) {
    const element = markerElement('dashboard-map-marker dashboard-map-marker--location', sourceLabel, 'fa-location-crosshairs');
    const label = document.createElement('span');
    label.className = 'dashboard-map-marker__label';
    label.textContent = 'Your location';
    element.append(label);
    marker = new maplibregl.Marker({ element, anchor: 'center' })
      .setLngLat([point.lng, point.lat])
      .addTo(dashboardMapState.map);
    dashboardMapState.markers.set(id, marker);
    element.addEventListener('click', () => {
      setDashboardMapStatus('Your current device location. It is not shared from this map.');
    });
  } else {
    marker.setLngLat([point.lng, point.lat]);
    marker.getElement().setAttribute('aria-label', sourceLabel);
    marker.getElement().title = sourceLabel;
  }
}

function setJourneyMarker(id, point, label, className, icon) {
  if (!point || !Number.isFinite(point.lat) || !Number.isFinite(point.lng)) return;
  let marker = dashboardMapState.markers.get(id);
  if (!marker) {
    marker = new maplibregl.Marker({ element: markerElement(className, label, icon), anchor: 'center' })
      .setLngLat([point.lng, point.lat])
      .addTo(dashboardMapState.map);
    dashboardMapState.markers.set(id, marker);
  } else {
    marker.setLngLat([point.lng, point.lat]);
  }
}

function showDashboardSearchLocation(point) {
  if (!point || !Number.isFinite(Number(point.lat)) || !Number.isFinite(Number(point.lng))) {
    throw new TypeError('A valid searched location is required.');
  }
  if (!dashboardMapState.map || !dashboardMapState.map.loaded()) {
    dashboardMapState.pendingSearchLocation = point;
    return;
  }
  dashboardMapState.pendingSearchLocation = null;
  const existing = dashboardMapState.markers.get('search-result');
  existing?.remove();
  dashboardMapState.markers.delete('search-result');
  const location = { lat: Number(point.lat), lng: Number(point.lng) };
  setJourneyMarker(
    'search-result',
    location,
    String(point.label || 'Searched location'),
    'dashboard-map-marker dashboard-map-marker--destination',
    'fa-location-dot'
  );
  dashboardMapState.map.flyTo({
    center: [location.lng, location.lat],
    zoom: 15,
    duration: dashboardReducedMotion.matches ? 0 : 650,
  });
}

function drawDashboardRoute(route) {
  const map = dashboardMapState.map;
  if (!map?.isStyleLoaded()) return;
  const routeColors = window.SafariTrakMapStyle.getRouteColors();
  const coordinates = route
    .filter(point => point && Number.isFinite(point.lat) && Number.isFinite(point.lng))
    .map(point => [point.lng, point.lat]);
  dashboardMapState.route = coordinates;
  const data = {
    type: 'Feature',
    properties: {},
    geometry: { type: 'LineString', coordinates },
  };
  const source = map.getSource('safaritrak-dashboard-route');
  if (source) source.setData(data);
  else map.addSource('safaritrak-dashboard-route', { type: 'geojson', data, lineMetrics: true });

  if (!map.getLayer('safaritrak-dashboard-route-glow')) {
    map.addLayer({
      id: 'safaritrak-dashboard-route-glow',
      type: 'line',
      source: 'safaritrak-dashboard-route',
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: { 'line-color': routeColors.glow, 'line-width': 9, 'line-blur': 5, 'line-opacity': 0.8 },
    });
    map.addLayer({
      id: 'safaritrak-dashboard-route-core',
      type: 'line',
      source: 'safaritrak-dashboard-route',
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: {
        'line-width': 2.5,
        'line-gradient': ['interpolate', ['linear'], ['line-progress'], 0, routeColors.stroke, 1, routeColors.stroke],
      },
    });
  }
}

function drawDashboardPlannedRoute(plannedRoute) {
  const map = dashboardMapState.map;
  if (!map?.isStyleLoaded() || !plannedRoute) return;
  const routeColors = window.SafariTrakMapStyle.getRouteColors();
  const coordinates = plannedRoute.points
    .filter(point => point && Number.isFinite(Number(point.lat)) && Number.isFinite(Number(point.lng)))
    .map(point => [Number(point.lng), Number(point.lat)]);
  const sourceId = 'safaritrak-planned-route';
  const data = coordinates.length >= 2
    ? {
      type: 'Feature',
      properties: {},
      geometry: { type: 'LineString', coordinates },
    }
    : { type: 'FeatureCollection', features: [] };
  const source = map.getSource(sourceId);
  if (source) source.setData(data);
  else map.addSource(sourceId, { type: 'geojson', data });

  if (!map.getLayer('safaritrak-planned-route-casing')) {
    map.addLayer({
      id: 'safaritrak-planned-route-casing',
      type: 'line',
      source: sourceId,
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: { 'line-color': '#450A0A', 'line-width': 9, 'line-opacity': 0.96 },
    });
    map.addLayer({
      id: 'safaritrak-planned-route-glow',
      type: 'line',
      source: sourceId,
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: { 'line-color': routeColors.glow, 'line-width': 7, 'line-blur': 3, 'line-opacity': 0.72 },
    });
    map.addLayer({
      id: 'safaritrak-planned-route-core',
      type: 'line',
      source: sourceId,
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: { 'line-color': routeColors.stroke, 'line-width': 5, 'line-opacity': 1 },
    });
  }

  const routeBounds = [
    [Number(plannedRoute.start.lng), Number(plannedRoute.start.lat)],
    [Number(plannedRoute.destination.lng), Number(plannedRoute.destination.lat)],
    ...coordinates,
  ];
  const longitudes = routeBounds.map(([lng]) => lng);
  const latitudes = routeBounds.map(([, lat]) => lat);
  map.fitBounds([
    [Math.min(...longitudes), Math.min(...latitudes)],
    [Math.max(...longitudes), Math.max(...latitudes)],
  ], {
    padding: 110,
    maxZoom: 16,
    duration: dashboardReducedMotion.matches ? 0 : 650,
  });
}

function setPlannedDashboardRoute(start, destination, points) {
  if (!start || !destination
    || !Number.isFinite(Number(start.lat)) || !Number.isFinite(Number(start.lng))
    || !Number.isFinite(Number(destination.lat)) || !Number.isFinite(Number(destination.lng))
  ) {
    throw new TypeError('Valid starting and destination coordinates are required.');
  }
  dashboardMapState.plannedRoute = {
    start: { lat: Number(start.lat), lng: Number(start.lng) },
    destination: { lat: Number(destination.lat), lng: Number(destination.lng) },
    points: Array.isArray(points) ? points : [],
  };
  if (!dashboardMapState.map?.isStyleLoaded()) return;

  setJourneyMarker('planned-start', dashboardMapState.plannedRoute.start, 'Trip start', 'dashboard-map-marker dashboard-map-marker--start', 'fa-flag');
  setJourneyMarker('planned-destination', dashboardMapState.plannedRoute.destination, 'Trip destination', 'dashboard-map-marker dashboard-map-marker--destination', 'fa-location-dot');
  drawDashboardPlannedRoute(dashboardMapState.plannedRoute);
}

function clearPlannedDashboardRoute() {
  dashboardMapState.plannedRoute = null;
  for (const id of ['planned-start', 'planned-destination']) {
    dashboardMapState.markers.get(id)?.remove();
    dashboardMapState.markers.delete(id);
  }
  if (!dashboardMapState.map?.isStyleLoaded()) return;
  const source = dashboardMapState.map.getSource('safaritrak-planned-route');
  if (source) source.setData({ type: 'FeatureCollection', features: [] });
}

function drawPublicDashboardRoute(points) {
  const map = dashboardMapState.map;
  if (!map?.isStyleLoaded()) return;
  const coordinates = points
    .filter(point => point && Number.isFinite(Number(point.lat)) && Number.isFinite(Number(point.lng)))
    .map(point => [Number(point.lng), Number(point.lat)]);
  const sourceId = 'safaritrak-public-journey-route';
  const data = coordinates.length > 1
    ? { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates } }
    : { type: 'FeatureCollection', features: [] };
  const source = map.getSource(sourceId);
  if (source) source.setData(data);
  else map.addSource(sourceId, { type: 'geojson', data });
  if (!map.getLayer('safaritrak-public-journey-route-casing')) {
    map.addLayer({
      id: 'safaritrak-public-journey-route-casing',
      type: 'line',
      source: sourceId,
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: { 'line-color': '#450A0A', 'line-width': 9, 'line-opacity': 0.96 },
    });
    map.addLayer({
      id: 'safaritrak-public-journey-route',
      type: 'line',
      source: sourceId,
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: { 'line-color': routeColors.stroke, 'line-width': 5, 'line-opacity': 1 },
    });
  }
}

function showSharedDashboardJourney(journey, points = [], fit = false) {
  const map = dashboardMapState.map;
  const routeColors = window.SafariTrakMapStyle.getRouteColors();
  const shouldFit = fit || (!map?.isStyleLoaded() && Boolean(dashboardMapState.publicJourney?.fit));
  dashboardMapState.publicJourney = { journey, points, fit: shouldFit };
  window.SafariTrakSharedJourneyState = dashboardMapState.publicJourney;
  if (!map?.isStyleLoaded()) return;
  const start = { lat: Number(journey.start_lat), lng: Number(journey.start_lng) };
  const finish = { lat: Number(journey.end_lat), lng: Number(journey.end_lng) };
  if (![start.lat, start.lng, finish.lat, finish.lng].every(Number.isFinite)) return;
  setJourneyMarker(
    'public-shared-start',
    start,
    `Start · ${journey.start_label}`,
    'dashboard-map-marker dashboard-map-marker--start',
    'fa-flag'
  );
  setJourneyMarker(
    'public-shared-finish',
    finish,
    `Finish · ${journey.end_label}`,
    'dashboard-map-marker dashboard-map-marker--destination',
    'fa-location-dot'
  );
  if (journey.location
    && Number.isFinite(Number(journey.location.lat))
    && Number.isFinite(Number(journey.location.lng))
  ) {
    setJourneyMarker(
      'public-shared-current',
      { lat: Number(journey.location.lat), lng: Number(journey.location.lng) },
      'Current shared location',
      'dashboard-map-marker dashboard-map-marker--location',
      'fa-location-crosshairs'
    );
  } else {
    dashboardMapState.markers.get('public-shared-current')?.remove();
    dashboardMapState.markers.delete('public-shared-current');
  }
  drawPublicDashboardRoute(points);
  if (shouldFit) {
    const coordinates = [
      [start.lng, start.lat],
      [finish.lng, finish.lat],
      ...(journey.location ? [[Number(journey.location.lng), Number(journey.location.lat)]] : []),
      ...points.filter(point => Number.isFinite(Number(point.lat)) && Number.isFinite(Number(point.lng)))
        .map(point => [Number(point.lng), Number(point.lat)]),
    ];
    const bounds = coordinates.reduce(
      (result, coordinate) => result.extend(coordinate),
      new maplibregl.LngLatBounds(coordinates[0], coordinates[0])
    );
    map.fitBounds(bounds, {
      padding: 110,
      maxZoom: 16,
      duration: dashboardReducedMotion.matches ? 0 : 650,
    });
  }
  dashboardMapState.publicJourney.fit = false;
}

function clearSharedDashboardJourney() {
  dashboardMapState.publicJourney = null;
  window.SafariTrakSharedJourneyState = null;
  for (const id of ['public-shared-start', 'public-shared-current', 'public-shared-finish']) {
    dashboardMapState.markers.get(id)?.remove();
    dashboardMapState.markers.delete(id);
  }
  if (!dashboardMapState.map?.isStyleLoaded()) return;
  const source = dashboardMapState.map.getSource('safaritrak-public-journey-route');
  if (source) source.setData({ type: 'FeatureCollection', features: [] });
}

function updateDashboardJourney(trip) {
  drawDashboardRoute(trip.route || []);
  setJourneyMarker('start', trip.start, `Start · ${trip.start_label}`, 'dashboard-map-marker dashboard-map-marker--start', 'fa-flag');
  setJourneyMarker('destination', trip.destination, `Destination · ${trip.destination_label}`, 'dashboard-map-marker dashboard-map-marker--destination', 'fa-location-dot');
  if (trip.current && !dashboardMapState.deviceLocation) {
    setUserMarker(trip.current, 'Your last reported journey location');
  }

  const speed = document.getElementById('dashboardSpeed');
  const remaining = document.getElementById('dashboardRemaining');
  const eta = document.getElementById('dashboardEta');
  if (speed) speed.textContent = trip.speed_kmh === null ? '—' : `${Math.round(trip.speed_kmh)} km/h`;
  if (remaining) remaining.textContent = trip.remaining_distance_km === null ? '—' : `${Number(trip.remaining_distance_km).toFixed(1)} km`;
  if (eta) eta.textContent = trip.eta_minutes === null ? '—' : trip.eta_minutes < 1 ? 'Arriving' : `${trip.eta_minutes} min`;
}

async function refreshDashboardJourney() {
  const tripId = dashboardMapConfig?.dataset.tripId;
  if (!tripId) return;
  const url = new URL(dashboardMapConfig.dataset.telemetryUrl, window.location.href);
  url.searchParams.set('id', tripId);
  const response = await fetch(url, { credentials: 'same-origin' });
  let payload;
  try {
    payload = await response.json();
  } catch (error) {
    throw new Error('Journey details could not be read.');
  }
  if (!response.ok || !payload.success || !payload.trip) {
    throw new Error(payload.message || `Journey details could not be loaded (HTTP ${response.status}).`);
  }
  updateDashboardJourney(payload.trip);
  if (!dashboardMapState.deviceLocation && payload.trip.current && !dashboardMapState.userCentered) {
    centerOnUser(payload.trip.current, 'Your last reported journey location', false);
  }
  setDashboardMapStatus('Live trip route · location sharing follows your journey permissions.');
}

function setSharedLocations(locations) {
  const activeIds = new Set();
  dashboardMapState.sharedLocations = locations;
  for (const location of locations) {
    if (!Number.isFinite(location.lat) || !Number.isFinite(location.lng)) continue;
    const id = `shared-${location.journey_id}`;
    activeIds.add(id);
    const recordedAt = location.recorded_at ? Date.parse(location.recorded_at) : NaN;
    const stale = !Number.isFinite(recordedAt) || Date.now() - recordedAt > 90_000;
    const stateLabel = stale ? 'Last known' : 'Live';
    const accessibleLabel = `${location.name}, ${stateLabel} location shared with you`;
    let marker = dashboardMapState.markers.get(id);
    if (!marker) {
      const element = document.createElement('button');
      element.type = 'button';
      element.className = 'dashboard-shared-marker';
      element.setAttribute('aria-label', accessibleLabel);
      element.title = accessibleLabel;
      const avatar = document.createElement('span');
      avatar.className = 'dashboard-shared-marker__avatar';
      const initials = String(location.name || '?').trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase();
      if (location.avatar_url) {
        const image = document.createElement('img');
        image.src = location.avatar_url;
        image.alt = '';
        image.addEventListener('error', () => image.remove(), { once: true });
        avatar.append(image);
      } else {
        avatar.textContent = initials || '?';
      }
      const name = document.createElement('span');
      name.className = 'dashboard-shared-marker__name';
      name.textContent = location.name;
      const freshness = document.createElement('small');
      freshness.className = 'dashboard-shared-marker__freshness';
      element.append(avatar, name, freshness);
      marker = new maplibregl.Marker({ element, anchor: 'bottom' })
        .setLngLat([location.lng, location.lat])
        .addTo(dashboardMapState.map);
      element.addEventListener('click', () => {
        setDashboardMapStatus(`${location.name}, ${stateLabel.toLowerCase()} location shared with you.`);
      });
      dashboardMapState.markers.set(id, marker);
    } else {
      marker.setLngLat([location.lng, location.lat]);
    }
    const element = marker.getElement();
    element.dataset.freshness = stale ? 'stale' : 'live';
    element.setAttribute('aria-label', accessibleLabel);
    element.title = accessibleLabel;
    const freshness = element.querySelector('.dashboard-shared-marker__freshness');
    if (freshness) {
      freshness.textContent = Number.isFinite(recordedAt)
        ? `${stateLabel} · ${new Date(recordedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
        : 'Location unavailable';
    }
  }
  for (const [id, marker] of dashboardMapState.markers) {
    if (id.startsWith('shared-') && !activeIds.has(id)) {
      marker.remove();
      dashboardMapState.markers.delete(id);
    }
  }
}

async function refreshSharedLocations() {
  if (!dashboardMapConfig?.dataset.sharedLocationsUrl) return;
  const response = await fetch(
    new URL(dashboardMapConfig.dataset.sharedLocationsUrl, window.location.href),
    { credentials: 'same-origin' }
  );
  let payload;
  try {
    payload = await response.json();
  } catch (error) {
    throw new Error('Shared locations could not be read.');
  }
  if (!response.ok || !payload.success || !Array.isArray(payload.locations)) {
    throw new Error(payload.message || `Shared locations could not be loaded (HTTP ${response.status}).`);
  }
  setSharedLocations(payload.locations);
  if (payload.locations.length > 0 && !dashboardMapConfig.dataset.tripId) {
    setDashboardMapStatus(`${payload.locations.length} location${payload.locations.length === 1 ? '' : 's'} shared with you.`);
  }
  return payload.locations;
}

function centerOnUser(point, sourceLabel = 'Your location', recenter = true) {
  if (!point || !Number.isFinite(point.lat) || !Number.isFinite(point.lng)) return;
  dashboardMapState.userCentered = true;
  setUserMarker(point, sourceLabel);
  if (recenter) {
    dashboardMapState.map.flyTo({
      center: [point.lng, point.lat],
      zoom: 16,
      duration: dashboardReducedMotion.matches || document.hidden ? 0 : 900,
    });
  }
}

function setDashboardProjection(projection) {
  if (!dashboardMapState.map || !['flat', 'globe'].includes(projection)) return;
  try {
    dashboardMapState.map.setProjection({ type: projection === 'globe' ? 'globe' : 'mercator' });
    dashboardMapState.projection = projection;
    window.SafariTrakMapView?.save(dashboardMapState.map, dashboardMapState.projection);
    document.querySelectorAll('[data-dashboard-projection]').forEach(button => {
      const selected = button.dataset.dashboardProjection === projection;
      button.setAttribute('aria-selected', selected ? 'true' : 'false');
      button.tabIndex = selected ? 0 : -1;
    });
  } catch (error) {
    setDashboardMapStatus('This browser could not change the map projection.', true);
  }
}

function locateOnDashboardMap(recenter = true) {
  if (!navigator.geolocation) {
    dashboardMapState.locationError = 'This browser does not support device location.';
    setDashboardMapStatus(dashboardMapState.locationError, true);
    return;
  }
  dashboardMapState.locationError = null;
  setDashboardMapStatus('Requesting your device location. It is not shared from this map.');
  dashboardMapState.locationPending = true;
  navigator.geolocation.getCurrentPosition(position => {
    dashboardMapState.locationPending = false;
    dashboardMapState.deviceLocation = {
      lat: position.coords.latitude,
      lng: position.coords.longitude,
      accuracy: position.coords.accuracy,
    };
    window.SafariTrakMapView?.saveLocation(position);
    centerOnUser(dashboardMapState.deviceLocation, 'Your location', recenter);
    setDashboardMapStatus('Your device location is shown here only; it is not shared with contacts.');
  }, error => {
    dashboardMapState.locationPending = false;
    const message = error.code === error.PERMISSION_DENIED
      ? 'Location permission was denied. Change browser permissions to show your location.'
      : error.code === error.TIMEOUT
        ? 'Device location timed out. Check your signal and try again.'
        : 'Device location is unavailable. Check your browser settings.';
    dashboardMapState.locationError = message;
    if (!dashboardMapState.userCentered) setDashboardMapStatus(message, true);
  }, { enableHighAccuracy: true, timeout: 12_000, maximumAge: 10_000 });
}

function reportDashboardRefresh(results, hasTrip) {
  const sharedResult = results[0];
  const tripResult = hasTrip ? results[1] : null;
  const sharedError = sharedResult?.status === 'rejected' ? sharedResult.reason?.message : null;
  const tripError = tripResult?.status === 'rejected' ? tripResult.reason?.message : null;
  if (sharedError && tripError) {
    setDashboardMapStatus(`${sharedError} ${tripError}`, true);
  } else if (sharedError) {
    setDashboardMapStatus(`Trip details are available, but shared locations could not be loaded: ${sharedError}`, true);
  } else if (tripError) {
    const sharedStatus = dashboardMapState.sharedLocations.length > 0
      ? 'Shared locations are available'
      : 'No locations are currently shared with you';
    setDashboardMapStatus(`${sharedStatus}, but trip details could not be refreshed: ${tripError}`, true);
  } else if (!hasTrip && dashboardMapState.sharedLocations.length === 0) {
    if (dashboardMapState.deviceLocation) {
      setDashboardMapStatus('Your device location is shown here only; it is not shared with contacts.');
    } else if (dashboardMapState.locationPending) {
      return;
    } else if (dashboardMapState.locationError) {
      setDashboardMapStatus(`${dashboardMapState.locationError} No other locations are currently shared with you.`, true);
    } else {
      setDashboardMapStatus('No other locations are currently shared with you.');
    }
  }
}

function reloadDashboardStyle() {
  const map = dashboardMapState.map;
  if (!map) return;
  map.setStyle(dashboardStyleUrl(document.documentElement.dataset.theme), { diff: false });
  map.once('style.load', () => {
    applyDashboardNeonStyle();
    setDashboardProjection(dashboardMapState.projection);
    if (dashboardMapState.route.length > 0) {
      const route = dashboardMapState.route.map(([lng, lat]) => ({ lng, lat }));
      drawDashboardRoute(route);
    }
  });
}

function initDashboardMap() {
  if (!dashboardMapElement) return;
  if (!window.maplibregl) {
    setDashboardMapStatus('The interactive map could not be loaded. Check your internet connection and reload.', true);
    return;
  }

  try {
    try {
      const mapUserId = dashboardMapElement.closest('[data-map-user-id]')?.dataset.mapUserId;
      const savedJourneyFeatures = JSON.parse(
        mapUserId ? sessionStorage.getItem(`safaritrak-selected-journey-${mapUserId}`) || '[]' : '[]'
      );
      if (Array.isArray(savedJourneyFeatures)) {
        const start = savedJourneyFeatures.find(feature => feature.kind === 'start');
        const destination = savedJourneyFeatures.find(feature => feature.kind === 'finish');
        const routeFeature = savedJourneyFeatures.find(feature => Array.isArray(feature.route) && feature.route.length > 1);
        const points = routeFeature?.route
          .filter(point => Array.isArray(point) && point.length === 2
            && Number.isFinite(Number(point[0])) && Number.isFinite(Number(point[1])))
          .map(([lng, lat]) => ({ lat: Number(lat), lng: Number(lng) })) || [];
        if (start && destination && points.length > 1
          && Number.isFinite(Number(start.lat)) && Number.isFinite(Number(start.lng))
          && Number.isFinite(Number(destination.lat)) && Number.isFinite(Number(destination.lng))) {
          dashboardMapState.plannedRoute = {
            start: { lat: Number(start.lat), lng: Number(start.lng) },
            destination: { lat: Number(destination.lat), lng: Number(destination.lng) },
            points,
          };
        }
      }
    } catch (error) {
      console.warn('SafariTrak could not restore the selected journey route on Explore.', error);
    }
    dashboardMapState.map = new maplibregl.Map({
      container: dashboardMapElement,
      style: dashboardStyleUrl(),
      center: [36.817223, -1.286389],
      zoom: 15,
      bearing: 0,
      pitch: 0,
      projection: { type: 'mercator' },
      attributionControl: false,
      scrollZoom: false,
      boxZoom: false,
      doubleClickZoom: false,
      touchZoomRotate: false,
      touchPitch: false,
      keyboard: false,
      cooperativeGestures: false,
    });
    dashboardMapState.map.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-left');
    dashboardMapState.map.on('style.load', () => {
      applyDashboardNeonStyle();
      setDashboardProjection(dashboardMapState.projection);
      if (dashboardMapState.route.length > 1) {
        drawDashboardRoute(dashboardMapState.route.map(([lng, lat]) => ({ lng, lat })));
      }
      if (dashboardMapState.plannedRoute) {
        setPlannedDashboardRoute(
          dashboardMapState.plannedRoute.start,
          dashboardMapState.plannedRoute.destination,
          dashboardMapState.plannedRoute.points
        );
      }
      if (dashboardMapState.publicJourney) {
        showSharedDashboardJourney(
          dashboardMapState.publicJourney.journey,
          dashboardMapState.publicJourney.points,
          dashboardMapState.publicJourney.fit
        );
      }
    });
    dashboardMapState.map.on('error', event => {
      if (event?.error?.message) setDashboardMapStatus('Map tiles are unavailable. Check your internet connection.', true);
    });
    dashboardMapState.map.once('style.load', async () => {
      setDashboardProjection(dashboardMapState.projection);
      document.dispatchEvent(new Event('safaritrak-map-ready'));
      if (dashboardMapState.pendingSearchLocation) {
        showDashboardSearchLocation(dashboardMapState.pendingSearchLocation);
      }
      const plannedRoute = window.SafariTrakPlannedRouteState;
      if (plannedRoute) {
        setPlannedDashboardRoute(plannedRoute.start, plannedRoute.destination, plannedRoute.points);
      }
      if (window.SafariTrakSharedJourneyState) {
        showSharedDashboardJourney(
          window.SafariTrakSharedJourneyState.journey,
          window.SafariTrakSharedJourneyState.points,
          window.SafariTrakSharedJourneyState.fit
        );
      }
      const requests = [refreshSharedLocations()];
      if (dashboardMapConfig?.dataset.tripId) requests.push(refreshDashboardJourney());
      const results = await Promise.allSettled(requests);
      reportDashboardRefresh(results, Boolean(dashboardMapConfig?.dataset.tripId));
      if (!dashboardMapState.refreshTimer) {
        dashboardMapState.refreshTimer = window.setInterval(() => {
          Promise.allSettled([
            refreshSharedLocations(),
            dashboardMapConfig?.dataset.tripId ? refreshDashboardJourney() : Promise.resolve(),
          ]).then(refreshResults => reportDashboardRefresh(refreshResults, Boolean(dashboardMapConfig?.dataset.tripId)));
        }, 15_000);
      }
    });
    dashboardMapState.map.on('moveend', () => {
      window.SafariTrakMapView?.save(dashboardMapState.map, dashboardMapState.projection);
    });

    document.querySelectorAll('[data-dashboard-projection]').forEach(button => {
      button.addEventListener('click', () => setDashboardProjection(button.dataset.dashboardProjection));
      button.addEventListener('keydown', event => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const mode = event.key === 'Home'
          ? 'flat'
          : event.key === 'End'
            ? 'globe'
            : button.dataset.dashboardProjection === 'flat' ? 'globe' : 'flat';
        setDashboardProjection(mode);
        document.querySelector(`[data-dashboard-projection="${mode}"]`)?.focus();
      });
    });
    document.getElementById('dashboardLocate')?.addEventListener('click', locateOnDashboardMap);
    dashboardMapState.themeObserver = new MutationObserver(reloadDashboardStyle);
    dashboardMapState.themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
  } catch (error) {
    setDashboardMapStatus('The interactive map could not be initialized. Reload the page to try again.', true);
  }
}

window.SafariTrakDashboardMap = {
  showSearchLocation: showDashboardSearchLocation,
  setPlannedRoute: setPlannedDashboardRoute,
  clearPlannedRoute: clearPlannedDashboardRoute,
  showSharedJourney: showSharedDashboardJourney,
  clearSharedJourney: clearSharedDashboardJourney,
};

initDashboardMap();
