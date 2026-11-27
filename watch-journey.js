const initialCenter = (window.WATCH_START_LAT !== null && window.WATCH_START_LNG !== null)
  ? [window.WATCH_START_LAT, window.WATCH_START_LNG]
  : [-1.286389, 36.817223];

const map = L.map('map', {
  zoomControl: false,
  scrollWheelZoom: false,
  doubleClickZoom: true,
  touchZoom: true,
  boxZoom: false,
  keyboard: false,
}).setView(initialCenter, 11);

window.SafariTrakMapStyle.attachLeafletBase(map);

function createJourneyMarker(color) {
  return L.divIcon({
    className: 'st-marker',
    html: `<div style="width:18px;height:18px;background:${color};border:4px solid white;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.28)"></div>`,
    iconSize: [18, 18],
    iconAnchor: [9, 9]
  });
}

const trackingColors = window.SafariTrakMapStyle.getTrackingColors();
const currentIcon = createJourneyMarker(trackingColors.user);
const startIcon = createJourneyMarker(trackingColors.start);
const destinationIcon = createJourneyMarker(trackingColors.destination);
const getJourneyRouteStroke = status => {
  const colors = window.SafariTrakMapStyle.getRouteColors();
  const normalizedStatus = String(status || document.getElementById('journeyStatus')?.textContent || '')
    .trim()
    .toLowerCase();
  return ['ended', 'completed'].includes(normalizedStatus) ? colors.completedStroke : colors.stroke;
};

let currentMarker = null;
let routeLine = null;
let destinationMarker = null;
let startMarker = null;
let plannedRouteLine = null;
let hasCenteredOnDeviceLocation = false;
const routeStatus = document.getElementById('journeyRoadRouteStatus');
const deviceLocationStatus = document.getElementById('deviceLocationStatus');

function reportDeviceLocationUnavailable(error) {
  if (!deviceLocationStatus) return;
  deviceLocationStatus.hidden = false;
  deviceLocationStatus.textContent = error && error.code === error.PERMISSION_DENIED
    ? 'Location permission was denied. The shared journey map remains available.'
    : 'Device location is unavailable. The shared journey map remains available.';
}

if (window.WATCH_START_LAT !== null && window.WATCH_START_LNG !== null) {
  startMarker = L.marker([window.WATCH_START_LAT, window.WATCH_START_LNG], { icon: startIcon })
    .addTo(map)
    .bindPopup('<b>Trip start</b>');
}

if (window.WATCH_END_LAT !== null && window.WATCH_END_LNG !== null) {
  destinationMarker = L.marker([window.WATCH_END_LAT, window.WATCH_END_LNG], { icon: destinationIcon })
    .addTo(map)
    .bindPopup('<b>Destination</b>');
}

if (window.WATCH_START_LAT !== null && window.WATCH_START_LNG !== null
  && window.WATCH_END_LAT !== null && window.WATCH_END_LNG !== null) {
  window.SafariTrakRouteDirections.getDrivingRoute(
    { lat: window.WATCH_START_LAT, lng: window.WATCH_START_LNG },
    { lat: window.WATCH_END_LAT, lng: window.WATCH_END_LNG }
  ).then(route => {
    plannedRouteLine = L.polyline(
      route.coordinates.map(([lng, lat]) => [lat, lng]),
      { color: getJourneyRouteStroke(), weight: 5, opacity: 0.9, dashArray: '9 7' }
    ).addTo(map);
    if (!hasCenteredOnDeviceLocation) {
      if (!window.SafariTrakRouteDirections.focusMobileLeafletRoute(
        map,
        plannedRouteLine.getLatLngs(),
        [window.WATCH_START_LAT, window.WATCH_START_LNG]
      )) {
        map.fitBounds(plannedRouteLine.getBounds(), { padding: [40, 40] });
      }
    }
    if (routeStatus) {
      routeStatus.textContent = `Road route · ${(route.distance_meters / 1000).toFixed(1)} km · about ${Math.max(1, Math.round(route.duration_seconds / 60))} min.`;
    }
  }).catch(error => {
    plannedRouteLine = L.polyline(
      [[window.WATCH_START_LAT, window.WATCH_START_LNG], [window.WATCH_END_LAT, window.WATCH_END_LNG]],
      {
        color: getJourneyRouteStroke(),
        weight: 5,
        opacity: 0.75,
        dashArray: '5 8',
      }
    ).addTo(map);
    if (!hasCenteredOnDeviceLocation) {
      if (!window.SafariTrakRouteDirections.focusMobileLeafletRoute(
        map,
        plannedRouteLine.getLatLngs(),
        [window.WATCH_START_LAT, window.WATCH_START_LNG]
      )) {
        map.fitBounds(plannedRouteLine.getBounds(), { padding: [40, 40] });
      }
    }
    if (routeStatus) {
      routeStatus.textContent = `Road route unavailable. The dashed line is an approximate direction, not a road route. ${error.message || ''}`.trim();
    }
    console.error('Journey road route could not be loaded.', error);
  });
} else if (routeStatus) {
  routeStatus.textContent = 'Saved start or destination coordinates are unavailable for this journey.';
}

function relativeTime(dateString) {
  const then = new Date(dateString.replace(' ', 'T'));
  const diffMin = Math.floor((Date.now() - then.getTime()) / 60000);
  if (diffMin < 1) return 'Just now';
  if (diffMin < 60) return diffMin + ' min ago';
  const diffHr = Math.floor(diffMin / 60);
  if (diffHr < 24) return diffHr + ' hr ago';
  return then.toLocaleDateString();
}

async function refresh() {
  try {
    const response = await fetch('backend/api/journeys/shared-detail.php?id=' + window.WATCH_JOURNEY_ID);
    const data = await response.json();

    if (!data.success) {
      const coveredEl = document.getElementById('coveredKm');
      if (coveredEl) coveredEl.textContent = 'Not available';
      return;
    }

    const coveredEl = document.getElementById('coveredKm');
    if (coveredEl) coveredEl.textContent = data.covered_km + ' km';

    const positions = data.positions || [];
    const routeStroke = getJourneyRouteStroke(data.journey?.status);
    if (plannedRouteLine) plannedRouteLine.setStyle({ color: routeStroke });
    if (routeLine) routeLine.setStyle({ color: routeStroke });

    if (positions.length > 0) {
      const latlngs = positions.map(p => [parseFloat(p.lat), parseFloat(p.lng)]);

      if (routeLine) {
        routeLine.setLatLngs(latlngs);
      } else {
        routeLine = L.polyline(latlngs, { color: routeStroke, weight: 4 }).addTo(map);
      }

      const last = positions[positions.length - 1];
      const lastLatLng = [parseFloat(last.lat), parseFloat(last.lng)];

      if (currentMarker) {
        currentMarker.setLatLng(lastLatLng);
      } else {
        currentMarker = L.marker(lastLatLng, { icon: currentIcon }).addTo(map);
        if (!hasCenteredOnDeviceLocation && !plannedRouteLine && positions.length < 2) map.setView(lastLatLng, 13);
      }

      if (!hasCenteredOnDeviceLocation && !plannedRouteLine && positions.length > 1) {
        if (!window.SafariTrakRouteDirections.focusMobileLeafletRoute(
          map,
          routeLine.getLatLngs(),
          lastLatLng
        )) {
          map.fitBounds(routeLine.getBounds(), { padding: [40, 40] });
        }
      }

      const lastUpdateEl = document.getElementById('lastUpdate');
      if (lastUpdateEl) lastUpdateEl.textContent = relativeTime(last.recorded_at);
    } else {
      const lastUpdateEl = document.getElementById('lastUpdate');
      if (lastUpdateEl) lastUpdateEl.textContent = 'No updates yet';
    }

    if (data.journey && data.journey.status !== 'active') {
      if (pollHandle) clearInterval(pollHandle);
    }
  } catch (err) {
    /* keep the last known state on a failed refresh */
  }
}

let pollHandle = null;
refresh();
if (navigator.geolocation) {
  navigator.geolocation.getCurrentPosition(position => {
    const point = [position.coords.latitude, position.coords.longitude];
    hasCenteredOnDeviceLocation = true;
    L.marker(point, { icon: currentIcon, zIndexOffset: 1000 })
      .addTo(map)
      .bindPopup('<b>Your location on this device</b>');
    map.setView(point, 16);
  }, reportDeviceLocationUnavailable, { enableHighAccuracy: true, timeout: 12_000, maximumAge: 60_000 });
} else {
  reportDeviceLocationUnavailable(null);
}

if (window.WATCH_IS_ACTIVE) {
  pollHandle = setInterval(refresh, 15000);
}