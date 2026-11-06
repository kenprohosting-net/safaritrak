const initialCenter = (window.WATCH_START_LAT !== null && window.WATCH_START_LNG !== null)
  ? [window.WATCH_START_LAT, window.WATCH_START_LNG]
  : [-1.286389, 36.817223];

const map = L.map('map', {
  zoomControl: false,
  scrollWheelZoom: false,
  doubleClickZoom: false,
  touchZoom: false,
  boxZoom: false,
  keyboard: false,
}).setView(initialCenter, 11);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19,
  attribution: '© OpenStreetMap contributors'
}).addTo(map);

const currentIcon = L.divIcon({
  className: 'st-marker',
  html: '<div style="width:18px;height:18px;background:#176b5b;border:4px solid white;border-radius:50%;box-shadow:0 2px 10px rgba(0,0,0,.25)"></div>',
  iconSize: [18, 18],
  iconAnchor: [9, 9]
});

const destinationIcon = L.divIcon({
  className: 'st-marker',
  html: '<div style="width:18px;height:18px;background:#d69b2d;border:4px solid white;border-radius:50%;box-shadow:0 2px 10px rgba(0,0,0,.25)"></div>',
  iconSize: [18, 18],
  iconAnchor: [9, 9]
});

let currentMarker = null;
let routeLine = null;

if (window.WATCH_END_LAT !== null && window.WATCH_END_LNG !== null) {
  L.marker([window.WATCH_END_LAT, window.WATCH_END_LNG], { icon: destinationIcon })
    .addTo(map)
    .bindPopup('<b>Destination</b>');
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

    if (positions.length > 0) {
      const latlngs = positions.map(p => [parseFloat(p.lat), parseFloat(p.lng)]);

      if (routeLine) {
        routeLine.setLatLngs(latlngs);
      } else {
        routeLine = L.polyline(latlngs, { color: '#10b981', weight: 3 }).addTo(map);
      }

      const last = positions[positions.length - 1];
      const lastLatLng = [parseFloat(last.lat), parseFloat(last.lng)];

      if (currentMarker) {
        currentMarker.setLatLng(lastLatLng);
      } else {
        currentMarker = L.marker(lastLatLng, { icon: currentIcon }).addTo(map);
        map.setView(lastLatLng, 13);
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

if (window.WATCH_IS_ACTIVE) {
  pollHandle = setInterval(refresh, 15000);
}