const initialCenter = (JOURNEY_START_LAT !== null && JOURNEY_START_LNG !== null)
  ? [JOURNEY_START_LAT, JOURNEY_START_LNG]
  : [-1.286389, 36.817223];

const map = L.map('map', {
  zoomControl: false,
  scrollWheelZoom: false,
  doubleClickZoom: true,
  touchZoom: true,
  boxZoom: false,
  keyboard: false,
}).setView(initialCenter, 12);
window.SafariTrakMapStyle.attachLeafletBase(map);

const trackingColors = window.SafariTrakMapStyle.getTrackingColors();
const currentIcon = L.divIcon({
  className: 'st-marker',
  html: `<div style="width:18px;height:18px;background:${trackingColors.user};border:4px solid white;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.28)"></div>`,
  iconSize: [18, 18],
  iconAnchor: [9, 9]
});

const destinationIcon = L.divIcon({
  className: 'st-marker',
  html: `<div style="width:18px;height:18px;background:${trackingColors.destination};border:4px solid white;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.28)"></div>`,
  iconSize: [18, 18],
  iconAnchor: [9, 9]
});

const marker = L.marker(initialCenter, { icon: currentIcon }).addTo(map).bindPopup('<b>You are here</b>');

if (JOURNEY_END_LAT !== null && JOURNEY_END_LNG !== null) {
  L.marker([JOURNEY_END_LAT, JOURNEY_END_LNG], { icon: destinationIcon }).addTo(map).bindPopup('<b>Destination</b>');
  if (JOURNEY_START_LAT !== null && JOURNEY_START_LNG !== null) {
    const route = [[JOURNEY_START_LAT, JOURNEY_START_LNG], [JOURNEY_END_LAT, JOURNEY_END_LNG]];
    if (!window.SafariTrakRouteDirections?.focusMobileLeafletRoute(
      map,
      route,
      [JOURNEY_START_LAT, JOURNEY_START_LNG]
    )) {
      map.fitBounds(route, { padding: [40, 40] });
    }
  }
}

let lastPositionTime = 0;
let hasCenteredOnCurrentLocation = false;

async function pushPosition(lat, lng, speedKmh) {
  try {
    const response = await fetch('backend/api/journeys/update-position.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ journey_id: ACTIVE_JOURNEY_ID, lat, lng, speed_kmh: speedKmh }),
    });
    const data = await response.json();
    if (data.success) {
      const coveredEl = document.getElementById('coveredKm');
      if (coveredEl && data.covered_km !== undefined) {
        coveredEl.textContent = data.covered_km + ' km';
      }
    }
  } catch (err) {
    /* Silent retry on next interval */
  }
}

function handlePosition(position) {
  const lat = position.coords.latitude;
  const lng = position.coords.longitude;
  const speedMs = position.coords.speed;
  const speedKmh = (speedMs !== null && speedMs !== undefined) ? Math.round(speedMs * 3.6) : null;

  marker.setLatLng([lat, lng]);
  if (!hasCenteredOnCurrentLocation) {
    map.setView([lat, lng], 16);
    hasCenteredOnCurrentLocation = true;
  }

  const speedEl = document.getElementById('currentSpeed');
  if (speedEl) {
    speedEl.textContent = speedKmh !== null ? speedKmh + ' km/h' : '-';
  }

  const now = Date.now();
  if (now - lastPositionTime > 15000) {
    lastPositionTime = now;
    pushPosition(lat, lng, speedKmh);
  }
}

function locate() {
  if (!navigator.geolocation) return alert('Location is not supported by this browser.');
  navigator.geolocation.getCurrentPosition(p => {
    handlePosition(p);
    map.setView([p.coords.latitude, p.coords.longitude], 15);
    hasCenteredOnCurrentLocation = true;
    marker.openPopup();
  }, () => alert('Please allow SafariTrak to access your location.'), { enableHighAccuracy: true, timeout: 10000 });
}

function reportLocationUnavailable(error) {
  const status = document.getElementById('trackingStatus');
  if (!status) return;
  status.textContent = error && error.code === error.PERMISSION_DENIED
    ? 'Location permission was denied. The trip map remains available.'
    : 'Device location is unavailable. The trip map remains available.';
}

document.getElementById('myLocation')?.addEventListener('click', locate);

if (navigator.geolocation) {
  navigator.geolocation.watchPosition(handlePosition, reportLocationUnavailable, { enableHighAccuracy: true, maximumAge: 10000 });
} else {
  reportLocationUnavailable(null);
}

document.getElementById('confirmEndJourneyBtn')?.addEventListener('click', async () => {
  const btn = document.getElementById('confirmEndJourneyBtn');
  btn.disabled = true;
  btn.textContent = 'Ending...';

  try {
    const response = await fetch('backend/api/journeys/end.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ journey_id: ACTIVE_JOURNEY_ID }),
    });
    const data = await response.json();

    if (!data.success) {
      alert(data.message || 'That journey could not be ended.');
      btn.disabled = false;
      btn.textContent = 'End journey';
      return;
    }

    window.location.href = 'my-journeys.php';
  } catch (err) {
    alert('Something went wrong ending that journey. Please try again.');
    btn.disabled = false;
    btn.textContent = 'End journey';
  }
});

document.querySelectorAll('.stop-sharing-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!confirm('Stop sharing this journey with them?')) return;
    btn.disabled = true;

    try {
      const response = await fetch('backend/api/journeys/stop-sharing.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          journey_id: ACTIVE_JOURNEY_ID,
          trusted_contact_id: btn.getAttribute('data-contact-id')
        }),
      });
      const data = await response.json();

      if (!data.success) {
        alert(data.message || 'That could not be completed.');
        btn.disabled = false;
        return;
      }

      const row = btn.closest('div');
      const container = row.parentElement;
      row.remove();

      if (container && container.querySelectorAll('div').length === 0) {
        container.innerHTML = '<p class="hint" style="padding:16px 21px;color:var(--muted);font-size:11px">You did not share this journey with anyone.</p>';
      }
    } catch (err) {
      alert('Something went wrong. Please try again.');
      btn.disabled = false;
    }
  });
});