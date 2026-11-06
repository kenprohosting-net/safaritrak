const mapElement = document.getElementById('map');
const mapStatus = document.getElementById('mapStatus');
const locationStatus = document.getElementById('locationStatus');
const configElement = document.getElementById('safariMapConfig');
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const state = {
  map: null,
  maplibre: null,
  journey: null,
  routeCoordinates: [],
  routePreviewKey: null,
  routePreviewController: null,
  markers: new Map(),
  requestController: null,
  pollTimer: null,
  rotationTimer: null,
  rotationResumeTimer: null,
  watchId: null,
  lastPositionSentAt: 0,
  selectedProjection: 'globe',
  styleReady: false,
  hasFittedJourney: false,
  hasUserMovedMap: false,
  activeTripId: null,
  themeObserver: null,
  tornDown: false,
};

function setMapStatus(message, isError = false) {
  if (!mapStatus) return;
  mapStatus.textContent = message;
  mapStatus.classList.toggle('is-error', isError);
}

function setLocationStatus(message, stateName = 'neutral') {
  if (!locationStatus) return;
  locationStatus.textContent = message;
  locationStatus.dataset.state = stateName;
}

function mapStyle(theme) {
  const cartoStyle = theme === 'light' ? 'positron-gl-style' : 'dark-matter-gl-style';
  return `https://basemaps.cartocdn.com/gl/${cartoStyle}/style.json`;
}

function applyNeonStyle() {
  if (!state.map || !state.styleReady) return;
  const light = document.documentElement.dataset.theme === 'light';
  const style = state.map.getStyle();
  for (const layer of style.layers || []) {
    if (!layer.paint) continue;
    const id = layer.id.toLowerCase();
    if (layer.type === 'background' && state.map.getLayer(layer.id)) {
      state.map.setPaintProperty(layer.id, 'background-color', light ? '#F7F5FF' : '#080812');
    } else if (layer.type === 'fill' && id.includes('water')) {
      state.map.setPaintProperty(layer.id, 'fill-color', light ? '#E8E5F4' : '#0C0F18');
    } else if (layer.type === 'fill' && id.includes('landcover')) {
      state.map.setPaintProperty(layer.id, 'fill-color', light ? '#F3F0FA' : '#0D1019');
    } else if (layer.type === 'line' && id.startsWith('road_') && id.includes('case')) {
      state.map.setPaintProperty(layer.id, 'line-color', light ? '#DDD4E9' : '#100D1D');
    } else if (layer.type === 'line' && id.startsWith('road_')) {
      const highway = /mot|trunk|pri/.test(id);
      state.map.setPaintProperty(
        layer.id,
        'line-color',
        light
          ? (highway ? '#A78BC6' : '#C7BED4')
          : (highway ? '#49336A' : '#292535')
      );
    } else if (layer.type === 'line' && id === 'waterway') {
      state.map.setPaintProperty(layer.id, 'line-color', light ? '#C5C1D9' : '#211A36');
    } else if (layer.type === 'symbol' && layer.layout?.['text-field']) {
      state.map.setPaintProperty(layer.id, 'text-color', light ? '#443650' : '#C8C0D8');
      if (layer.paint['text-halo-color'] !== undefined) {
        state.map.setPaintProperty(layer.id, 'text-halo-color', light ? '#F7F5FF' : '#101019');
      }
    }
  }
}

function setProjection(projection) {
  if (!state.map || !['flat', 'globe'].includes(projection)) return;
  state.selectedProjection = projection;
  try {
    state.map.setProjection({ type: projection === 'globe' ? 'globe' : 'mercator' });
  } catch (error) {
    if (projection === 'globe') {
      state.selectedProjection = 'flat';
      try {
        state.map.setProjection({ type: 'mercator' });
      } catch (projectionError) {
        setMapStatus('This browser could not change the map projection.', true);
      }
      setMapStatus('Globe view is unavailable in this browser. Showing the flat map instead.');
      updateProjectionButtons();
      return;
    }
    setMapStatus('This browser could not change the map projection.', true);
  }
  updateProjectionButtons();
  try {
    localStorage.setItem('safaritrak-map-projection', state.selectedProjection);
  } catch (error) {
    // Keep the selected projection for this page if storage is unavailable.
  }
  if (state.selectedProjection !== 'globe') stopRotation();
  else scheduleRotation();
}

function updateProjectionButtons() {
  document.querySelectorAll('[data-map-projection]').forEach(button => {
    const selected = button.dataset.mapProjection === state.selectedProjection;
    button.setAttribute('aria-pressed', selected ? 'true' : 'false');
  });
}

function applyTheme(theme) {
  if (!state.map || state.tornDown) return;
  const center = state.map.getCenter();
  const zoom = state.map.getZoom();
  const pitch = state.map.getPitch();
  const bearing = state.map.getBearing();
  state.styleReady = false;
  state.map.setStyle(mapStyle(theme === 'light' ? 'light' : 'dark'), { diff: false });
  state.map.once('style.load', () => {
    if (state.tornDown) return;
    state.styleReady = true;
    applyNeonStyle();
    state.map.setProjection({ type: state.selectedProjection === 'globe' ? 'globe' : 'mercator' });
    state.map.jumpTo({ center, zoom, pitch, bearing });
    redrawJourney();
  });
}

function createMarkerElement(className, label) {
  const element = document.createElement('button');
  element.type = 'button';
  element.className = className;
  element.setAttribute('aria-label', label);
  element.title = label;
  return element;
}

function removeMarker(id) {
  const marker = state.markers.get(id);
  if (!marker) return;
  marker.remove();
  state.markers.delete(id);
}

function upsertPointMarker(id, point, label, kind) {
  if (!point || !Number.isFinite(point.lat) || !Number.isFinite(point.lng)) {
    removeMarker(id);
    return;
  }
  let marker = state.markers.get(id);
  if (!marker) {
    const element = createMarkerElement(`st-map-point st-map-point--${kind}`, label);
    const core = document.createElement('span');
    core.className = 'st-map-point__core';
    element.append(core);
    marker = new state.maplibre.Marker({ element, anchor: 'center' }).setLngLat([point.lng, point.lat]).addTo(state.map);
    element.addEventListener('click', () => showMarkerPopup(marker, label));
    state.markers.set(id, marker);
  } else {
    marker.setLngLat([point.lng, point.lat]);
    marker.getElement().setAttribute('aria-label', label);
    marker.getElement().title = label;
  }
}

function showMarkerPopup(marker, text) {
  if (!state.maplibre || !state.map) return;
  const content = document.createElement('div');
  content.className = 'st-map-popup';
  const label = document.createElement('strong');
  label.textContent = text;
  content.append(label);
  new state.maplibre.Popup({ offset: 18, closeButton: true, maxWidth: '240px' })
    .setLngLat(marker.getLngLat())
    .setDOMContent(content)
    .addTo(state.map);
}

function updateLiveMarkers(vehicleData) {
  if (!state.map || !Array.isArray(vehicleData)) return;
  const activeIds = new Set();
  for (const vehicle of vehicleData) {
    if (!vehicle || !Number.isFinite(vehicle.lat) || !Number.isFinite(vehicle.lng)) continue;
    const id = `vehicle-${vehicle.id}`;
    activeIds.add(id);
    let marker = state.markers.get(id);
    if (!marker) {
      const element = createMarkerElement('st-live-marker', 'Live traveler location');
      const speed = document.createElement('span');
      speed.className = 'st-live-marker__speed';
      const avatarFrame = document.createElement('span');
      avatarFrame.className = 'st-live-marker__avatar';
      const fallback = document.createElement('span');
      fallback.className = 'st-live-marker__fallback';
      fallback.textContent = vehicle.initials || '?';
      avatarFrame.append(fallback);
      if (vehicle.avatarUrl) {
        const image = document.createElement('img');
        image.alt = '';
        image.src = vehicle.avatarUrl;
        image.addEventListener('error', () => image.remove(), { once: true });
        avatarFrame.append(image);
      }
      element.append(speed, avatarFrame);
      marker = new state.maplibre.Marker({ element, anchor: 'bottom' })
        .setLngLat([vehicle.lng, vehicle.lat])
        .addTo(state.map);
      element.addEventListener('click', () => showMarkerPopup(marker, vehicle.name));
      state.markers.set(id, marker);
    } else {
      marker.setLngLat([vehicle.lng, vehicle.lat]);
    }
    const element = marker.getElement();
    const ageSeconds = vehicle.recordedAt ? (Date.now() - Date.parse(vehicle.recordedAt)) / 1000 : Infinity;
    const freshness = ageSeconds <= 90 ? 'active' : 'delayed';
    element.dataset.freshness = freshness;
    element.setAttribute('aria-label', `${vehicle.name}, ${freshness === 'active' ? 'live' : 'last known'} location${vehicle.speedKmh === null ? '' : `, ${Math.round(vehicle.speedKmh)} kilometres per hour`}`);
    const speedBadge = element.querySelector('.st-live-marker__speed');
    speedBadge.textContent = vehicle.speedKmh === null ? 'LIVE' : `${Math.round(vehicle.speedKmh)} km/h`;
    const fallback = element.querySelector('.st-live-marker__fallback');
    if (fallback && fallback.textContent !== (vehicle.initials || '?')) fallback.textContent = vehicle.initials || '?';
  }
  for (const id of state.markers.keys()) {
    if (id.startsWith('vehicle-') && !activeIds.has(id)) removeMarker(id);
  }
}

function renderAnimatedRoute(coords) {
  if (!state.map || !Array.isArray(coords)) return;
  const coordinates = coords
    .filter(point => point && Number.isFinite(point.lat) && Number.isFinite(point.lng))
    .map(point => [point.lng, point.lat]);
  state.routeCoordinates = coordinates;
  if (!state.styleReady) return;
  const source = state.map.getSource('safaritrak-route');
  const data = {
    type: 'Feature',
    properties: {},
    geometry: { type: 'LineString', coordinates },
  };
  if (source) source.setData(data);
  else state.map.addSource('safaritrak-route', { type: 'geojson', data, lineMetrics: true });

  if (!state.map.getLayer('safaritrak-route-casing')) {
    state.map.addLayer({
      id: 'safaritrak-route-casing',
      type: 'line',
      source: 'safaritrak-route',
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: { 'line-color': '#fff0f3', 'line-width': 11, 'line-opacity': 0.96 },
    });
  }
  if (!state.map.getLayer('safaritrak-route-glow')) {
    state.map.addLayer({
      id: 'safaritrak-route-glow',
      type: 'line',
      source: 'safaritrak-route',
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: { 'line-color': '#D946EF', 'line-width': 13, 'line-blur': 4, 'line-opacity': 0.62 },
    });
  }
  if (!state.map.getLayer('safaritrak-route-core')) {
    state.map.addLayer({
      id: 'safaritrak-route-core',
      type: 'line',
      source: 'safaritrak-route',
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: { 'line-color': '#C026D3', 'line-width': 6, 'line-opacity': 1 },
    });
  }
}

function clearTripState() {
  if (state.pollTimer !== null) clearInterval(state.pollTimer);
  if (state.rotationTimer !== null) clearInterval(state.rotationTimer);
  if (state.rotationResumeTimer !== null) clearTimeout(state.rotationResumeTimer);
  if (state.requestController) state.requestController.abort();
  if (state.routePreviewController) state.routePreviewController.abort();
  state.pollTimer = null;
  state.rotationTimer = null;
  state.rotationResumeTimer = null;
  state.requestController = null;
  state.routePreviewController = null;
  state.routePreviewKey = null;
  state.routeCoordinates = [];
  state.journey = null;
  state.hasFittedJourney = false;
  state.hasUserMovedMap = false;
  state.activeTripId = null;
  for (const marker of state.markers.values()) marker.remove();
  state.markers.clear();
  if (state.watchId !== null && navigator.geolocation) {
    navigator.geolocation.clearWatch(state.watchId);
    state.watchId = null;
  }
  if (state.map?.getLayer('safaritrak-route-core')) state.map.removeLayer('safaritrak-route-core');
  if (state.map?.getLayer('safaritrak-route-glow')) state.map.removeLayer('safaritrak-route-glow');
  if (state.map?.getLayer('safaritrak-route-casing')) state.map.removeLayer('safaritrak-route-casing');
  if (state.map?.getSource('safaritrak-route')) state.map.removeSource('safaritrak-route');
}

function fitJourney(trip) {
  if (state.hasFittedJourney || state.hasUserMovedMap || !state.map) return;
  const points = [...(trip.route || [])];
  if (points.length === 0 && trip.start) points.push(trip.start);
  if (trip.destination) points.push(trip.destination);
  const unique = points.filter((point, index) => points.findIndex(other => point.lat === other.lat && point.lng === other.lng) === index);
  if (unique.length > 1) {
    const bounds = unique.reduce(
      (result, point) => result.extend([point.lng, point.lat]),
      new state.maplibre.LngLatBounds([unique[0].lng, unique[0].lat], [unique[0].lng, unique[0].lat])
    );
    state.map.fitBounds(bounds, {
      padding: 72,
      maxZoom: 14,
      pitch: 45,
      duration: reduceMotion.matches || document.hidden ? 0 : 900,
    });
  } else if (unique.length === 1) {
    state.map.flyTo({
      center: [unique[0].lng, unique[0].lat],
      zoom: 14,
      pitch: 45,
      duration: reduceMotion.matches || document.hidden ? 0 : 800,
    });
  }
  state.hasFittedJourney = true;
}

function updateJourneyDetails(trip) {
  const covered = document.getElementById('coveredKm');
  const total = document.getElementById('totalKm');
  const speed = document.getElementById('currentSpeed');
  const eta = document.getElementById('journeyEta');
  const distance = document.getElementById('journeyRemaining');
  if (covered) covered.textContent = `${Number(trip.covered_km || 0).toFixed(1)} km`;
  if (total) total.textContent = trip.distance_km === null ? 'Unknown' : `${Number(trip.distance_km).toFixed(1)} km`;
  if (speed) speed.textContent = trip.speed_kmh === null ? '—' : `${Math.round(trip.speed_kmh)} km/h`;
  if (eta) eta.textContent = trip.eta_minutes === null ? 'Unavailable' : trip.eta_minutes < 1 ? 'Arriving' : `${trip.eta_minutes} min`;
  if (distance) distance.textContent = trip.remaining_distance_km === null ? 'Unknown' : `${Number(trip.remaining_distance_km).toFixed(1)} km`;

  const lastSeen = trip.last_location_at ? Date.parse(trip.last_location_at) : NaN;
  if (!Number.isFinite(lastSeen)) {
    setLocationStatus('Waiting for the first GPS update. Location sharing is shown here when active.', 'neutral');
  } else if (Date.now() - lastSeen <= 90_000) {
    setLocationStatus(`Live location sharing · updated ${new Date(lastSeen).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`, 'fresh');
  } else {
    setLocationStatus(`Location may be stale · last update ${new Date(lastSeen).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`, 'stale');
  }

  const title = document.getElementById('mapTripTitle');
  if (title) title.textContent = `${trip.start_label} → ${trip.destination_label}`;
}

function updateMapStatusForTrip(trip) {
  const mapLabel = trip.status === 'active'
    ? 'Trip map is up to date.'
    : `This trip is ${trip.status}. Showing its recorded route.`;
  setMapStatus(mapLabel);
}

function updateJourneyRoute(trip) {
  const start = trip.start;
  const destination = trip.destination;
  if (!start || !destination
    || !Number.isFinite(start.lat) || !Number.isFinite(start.lng)
    || !Number.isFinite(destination.lat) || !Number.isFinite(destination.lng)
  ) {
    state.routeCoordinates = [];
    renderAnimatedRoute([]);
    return;
  }

  const key = `${trip.id}:${start.lat},${start.lng}:${destination.lat},${destination.lng}`;
  if (state.routePreviewKey === key) {
    renderAnimatedRoute(state.routeCoordinates.map(([lng, lat]) => ({ lat, lng })));
    return;
  }

  state.routePreviewController?.abort();
  state.routePreviewKey = key;
  const directEstimate = [start, destination];
  state.routeCoordinates = directEstimate.map(point => [point.lng, point.lat]);
  renderAnimatedRoute(directEstimate);

  if (!window.SafariTrakRouteDirections) {
    setMapStatus('Road route service is unavailable. Showing a direct start-to-finish estimate.', true);
    return;
  }

  const controller = new AbortController();
  state.routePreviewController = controller;
  window.SafariTrakRouteDirections.getDrivingRoute(start, destination, { signal: controller.signal })
    .then(route => {
      if (state.routePreviewKey !== key || state.tornDown) return;
      const points = route.coordinates.map(([lng, lat]) => ({ lat, lng }));
      state.routeCoordinates = points.map(point => [point.lng, point.lat]);
      renderAnimatedRoute(points);
      setMapStatus(`Road route · ${(route.distance_meters / 1000).toFixed(1)} km · about ${Math.max(1, Math.round(route.duration_seconds / 60))} min.`);
      if (!state.hasUserMovedMap) {
        const bounds = route.coordinates.reduce(
          (result, point) => result.extend(point),
          new state.maplibre.LngLatBounds(route.coordinates[0], route.coordinates[0])
        );
        state.map.fitBounds(bounds, { padding: 72, maxZoom: 14, pitch: 45, duration: 0 });
        state.hasFittedJourney = true;
      }
    })
    .catch(error => {
      if (error.name === 'AbortError' || state.routePreviewKey !== key) return;
      console.error('Live journey road route failed.', error);
      setMapStatus('Road route service is unavailable. Showing a direct start-to-finish estimate.', true);
    });
}

async function fetchActiveTripData(tripId) {
  if (!state.map || !configElement || !/^\d+$/.test(String(tripId))) {
    throw new Error('A valid trip is required to load map telemetry.');
  }
  if (state.activeTripId !== String(tripId)) {
    clearTripState();
    state.activeTripId = String(tripId);
  } else if (state.requestController) {
    state.requestController.abort();
  }
  const controller = new AbortController();
  state.requestController = controller;
  const url = new URL(configElement.dataset.telemetryUrl, window.location.href);
  url.searchParams.set('id', String(tripId));
  const response = await fetch(url.toString(), { credentials: 'same-origin', signal: controller.signal });
  let payload;
  try {
    payload = await response.json();
  } catch (error) {
    throw new Error('The map service returned an unreadable response.');
  }
  if (!response.ok || !payload.success || !payload.trip) {
    throw new Error(payload.message || `Trip information could not be loaded (HTTP ${response.status}).`);
  }
  if (controller.signal.aborted || state.tornDown) return null;
  return payload.trip;
}

function redrawJourney() {
  if (!state.styleReady || !state.journey) return;
  const trip = state.journey;
  updateJourneyRoute(trip);
  upsertPointMarker('journey-start', trip.start, `Start · ${trip.start_label}`, 'start');
  upsertPointMarker('journey-destination', trip.destination, `Destination · ${trip.destination_label}`, 'destination');
  const current = trip.current;
  if (current) {
    updateLiveMarkers([{
      id: trip.owner_id,
      name: trip.owner_name,
      initials: trip.owner_name.split(/\s+/).map(part => part[0]).slice(0, 2).join('').toUpperCase(),
      avatarUrl: trip.owner_avatar_url,
      lat: current.lat,
      lng: current.lng,
      speedKmh: trip.speed_kmh,
      recordedAt: trip.last_location_at,
    }]);
  } else {
    updateLiveMarkers([]);
  }
  updateJourneyDetails(trip);
  fitJourney(trip);
}

async function refreshJourney() {
  if (!configElement || state.tornDown) return;
  try {
    const trip = await fetchActiveTripData(configElement.dataset.tripId);
    if (!trip) return;
    state.journey = trip;
    redrawJourney();
    updateMapStatusForTrip(trip);
  } catch (error) {
    if (error.name === 'AbortError') return;
    setMapStatus(error.message || 'Trip information could not be loaded. Try again.', true);
  }
}

async function loadTrip(tripId) {
  clearTripState();
  state.activeTripId = String(tripId);
  state.tornDown = false;
  try {
    const trip = await fetchActiveTripData(tripId);
    if (!trip || state.tornDown) return null;
    state.journey = trip;
    redrawJourney();
    updateMapStatusForTrip(trip);
    state.pollTimer = window.setInterval(refreshJourney, 15_000);
    if (configElement.dataset.canUpdateLocation === 'true') startLocationWatch();
    return trip;
  } catch (error) {
    if (error.name !== 'AbortError') setMapStatus(error.message || 'Trip information could not be loaded. Try again.', true);
    return null;
  }
}

async function pushPosition(position) {
  if (!configElement || configElement.dataset.canUpdateLocation !== 'true') return;
  const now = Date.now();
  if (now - state.lastPositionSentAt < 15_000) return;
  state.lastPositionSentAt = now;
  const speedKmh = position.coords.speed === null ? null : position.coords.speed * 3.6;
  try {
    const response = await fetch('backend/api/journeys/update-position.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        journey_id: Number(configElement.dataset.tripId),
        lat: position.coords.latitude,
        lng: position.coords.longitude,
        speed_kmh: speedKmh,
      }),
      credentials: 'same-origin',
    });
    const payload = await response.json();
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Location update was not accepted.');
    await refreshJourney();
  } catch (error) {
    if (error.name !== 'AbortError') setLocationStatus('Live update failed. Waiting to retry on the next GPS reading.', 'stale');
  }
}

function startLocationWatch(followLocation = false) {
  if (!configElement || configElement.dataset.canUpdateLocation !== 'true') {
    setLocationStatus('You are viewing a journey shared with you. Your location is not being sent.', 'neutral');
    return;
  }
  if (!navigator.geolocation) {
    setLocationStatus('This browser does not support GPS location sharing.', 'stale');
    return;
  }
  if (state.watchId !== null) {
    navigator.geolocation.clearWatch(state.watchId);
    state.watchId = null;
  }
  setLocationStatus('Requesting device location. Live updates are visible to journey followers.', 'neutral');
  state.watchId = navigator.geolocation.watchPosition(
    position => {
      pushPosition(position);
      if (followLocation && state.map) {
        state.map.easeTo({
          center: [position.coords.longitude, position.coords.latitude],
          zoom: Math.max(state.map.getZoom(), 16),
          duration: reduceMotion.matches ? 0 : 350,
        });
      }
    },
    error => {
      const message = error.code === error.PERMISSION_DENIED
        ? 'Location permission denied. Allow it in browser settings to resume live trip sharing.'
        : error.code === error.TIMEOUT
          ? 'GPS timed out. SafariTrak will retry when a new location is available.'
          : 'GPS is unavailable. Check device location settings.';
      setLocationStatus(message, 'stale');
    },
    { enableHighAccuracy: true, maximumAge: 10_000, timeout: 20_000 }
  );
}

function stopRotation() {
  if (state.rotationTimer !== null) clearInterval(state.rotationTimer);
  if (state.rotationResumeTimer !== null) clearTimeout(state.rotationResumeTimer);
  state.rotationTimer = null;
  state.rotationResumeTimer = null;
}

function scheduleRotation() {
  stopRotation();
  if (
    reduceMotion.matches
    || state.selectedProjection !== 'globe'
    || !state.map
    || state.tornDown
  ) return;
  state.rotationResumeTimer = window.setTimeout(() => {
    state.rotationTimer = window.setInterval(() => {
      if (!state.map || state.selectedProjection !== 'globe' || state.tornDown) return;
      const center = state.map.getCenter();
      state.map.jumpTo({ center: [center.lng + 0.012, center.lat] });
    }, 120);
  }, 7000);
}

function onMapInteraction() {
  state.hasUserMovedMap = true;
  scheduleRotation();
}

function teardown() {
  if (state.tornDown) return;
  state.tornDown = true;
  state.themeObserver?.disconnect();
  state.themeObserver = null;
  clearTripState();
  if (state.map) state.map.remove();
  state.map = null;
}

window.safariTrakMap = {
  initSafariTrakMap,
  fetchActiveTripData,
  loadTrip,
  renderAnimatedRoute,
  updateLiveMarkers,
  clearTripState,
  teardown,
};

async function initSafariTrakMap() {
  if (!mapElement || !configElement) return;
  try {
    if (state.tornDown) return;
    state.maplibre = window.maplibregl;
    if (!state.maplibre) throw new Error('MapLibre GL JS did not load.');
    const savedView = window.SafariTrakMapView?.read() || null;
    const cachedLocation = window.SafariTrakMapView?.readLocation() || null;
    const initialPoint = savedView || cachedLocation || { lat: -1.286389, lng: 36.817223 };
    state.hasFittedJourney = false;
    if (savedView) state.selectedProjection = savedView.projection;
    try {
      const storedProjection = localStorage.getItem('safaritrak-map-projection');
      if (!savedView && (storedProjection === 'flat' || storedProjection === 'globe')) state.selectedProjection = storedProjection;
    } catch (error) {
      if (!savedView) state.selectedProjection = 'globe';
    }
    state.map = new state.maplibre.Map({
      container: mapElement,
      style: mapStyle(document.documentElement.dataset.theme),
      center: [initialPoint.lng, initialPoint.lat],
      zoom: savedView?.zoom ?? (cachedLocation ? 16 : state.selectedProjection === 'globe' ? 1.8 : 11),
      bearing: savedView?.bearing ?? 0,
      pitch: savedView?.pitch ?? 0,
      projection: { type: state.selectedProjection === 'globe' ? 'globe' : 'mercator' },
      maxPitch: 60,
      attributionControl: true,
      scrollZoom: false,
      boxZoom: false,
      doubleClickZoom: false,
      touchZoomRotate: false,
      touchPitch: false,
      keyboard: false,
      cooperativeGestures: false,
    });
    state.map.on('style.load', () => {
      if (state.tornDown) return;
      state.styleReady = true;
      applyNeonStyle();
      state.map.setProjection({ type: state.selectedProjection === 'globe' ? 'globe' : 'mercator' });
      redrawJourney();
    });
    state.map.on('error', event => {
      if (event?.error?.message) setMapStatus('Map tiles could not be loaded. Check your connection and retry.', true);
    });
    state.map.on('moveend', () => window.SafariTrakMapView?.save(state.map, state.selectedProjection));
    ['dragstart', 'touchstart', 'zoomstart', 'rotatestart'].forEach(eventName => state.map.on(eventName, onMapInteraction));
    state.map.once('style.load', () => {
      state.styleReady = true;
      applyNeonStyle();
      setProjection(state.selectedProjection);
      loadTrip(configElement.dataset.tripId);
      if (configElement.dataset.canUpdateLocation !== 'true') {
        setLocationStatus('You are viewing a journey shared with you. Your location is not being sent.', 'neutral');
      }
    });
    updateProjectionButtons();
    document.querySelectorAll('[data-map-projection]').forEach(button => {
      button.addEventListener('click', () => setProjection(button.dataset.mapProjection));
    });
    document.getElementById('myLocation')?.addEventListener('click', () => startLocationWatch(true));
    state.themeObserver = new MutationObserver(() => applyTheme(document.documentElement.dataset.theme));
    state.themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    window.addEventListener('pagehide', event => {
      if (!event.persisted) teardown();
    }, { once: true });
    reduceMotion.addEventListener?.('change', () => {
      if (reduceMotion.matches) stopRotation();
      else scheduleRotation();
    });
    document.getElementById('confirmEndJourneyBtn')?.addEventListener('click', async event => {
      const button = event.currentTarget;
      button.disabled = true;
      button.textContent = 'Ending…';
      try {
        const response = await fetch('backend/api/journeys/end.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ journey_id: Number(configElement.dataset.tripId) }),
          credentials: 'same-origin',
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'The journey could not be ended.');
        window.location.assign('my-journeys.php');
      } catch (error) {
        button.disabled = false;
        button.textContent = 'End journey';
        setMapStatus(error.message || 'The journey could not be ended. Try again.', true);
      }
    });
    document.querySelectorAll('.stop-sharing-btn').forEach(button => {
      button.addEventListener('click', async () => {
        if (!window.confirm('Stop sharing this journey with this contact?')) return;
        button.disabled = true;
        try {
          const response = await fetch('backend/api/journeys/stop-sharing.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              journey_id: Number(configElement.dataset.tripId),
              trusted_contact_id: Number(button.dataset.contactId),
            }),
            credentials: 'same-origin',
          });
          const payload = await response.json();
          if (!response.ok || !payload.success) throw new Error(payload.message || 'Sharing could not be stopped.');
          button.closest('.watcher-row')?.remove();
        } catch (error) {
          button.disabled = false;
          setMapStatus(error.message || 'Sharing could not be stopped. Try again.', true);
        }
      });
    });
  } catch (error) {
    setMapStatus('The interactive map could not be loaded. Check your internet connection and reload this page.', true);
    setLocationStatus('The live map library did not load.', 'stale');
  }
}

initSafariTrakMap();
