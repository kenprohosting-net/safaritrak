const NAIROBI_FALLBACK = [-1.286389, 36.817223];

const journeyMap = L.map('map').setView(NAIROBI_FALLBACK, 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19,
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(journeyMap);

const startIcon = L.divIcon({
  className: 'st-marker',
  html: '<div style="width:18px;height:18px;background:#10b981;border:4px solid white;border-radius:50%;box-shadow:0 2px 10px rgba(0,0,0,.25)"></div>',
  iconSize: [18, 18],
  iconAnchor: [9, 9]
});

const endIcon = L.divIcon({
  className: 'st-marker',
  html: '<div style="width:18px;height:18px;background:#d69b2d;border:4px solid white;border-radius:50%;box-shadow:0 2px 10px rgba(0,0,0,.25)"></div>',
  iconSize: [18, 18],
  iconAnchor: [9, 9]
});

let startMarker = null;
let endMarker = null;
let routeLine = null;
let computedTotalDistanceKm = 0; // Store real OSRM road distance

function setJourneyMarker(kind, lat, lng, popupText) {
  const position = [lat, lng];
  const icon = kind === 'start' ? startIcon : endIcon;

  if (kind === 'start') {
    if (startMarker) {
      startMarker.setLatLng(position);
    } else {
      startMarker = L.marker(position, { icon }).addTo(journeyMap);
    }
    startMarker.bindPopup(popupText);
  } else {
    if (endMarker) {
      endMarker.setLatLng(position);
    } else {
      endMarker = L.marker(position, { icon }).addTo(journeyMap);
    }
    endMarker.bindPopup(popupText);
  }

  drawJourneyRoute();
}

async function drawJourneyRoute() {
  if (startMarker && endMarker) {
    const sPos = startMarker.getLatLng();
    const ePos = endMarker.getLatLng();

    try {
      // Query OSRM road routing instead of drawing straight line
      const url = `https://router.project-osrm.org/route/v1/driving/${sPos.lng},${sPos.lat};${ePos.lng},${ePos.lat}?overview=full&geometries=geojson`;
      const response = await fetch(url);
      const data = await response.json();

      if (data.routes && data.routes.length > 0) {
        const route = data.routes[0];
        computedTotalDistanceKm = parseFloat((route.distance / 1000).toFixed(1));

        const routeCoords = route.geometry.coordinates.map(c => [c[1], c[0]]);

        if (routeLine) {
          routeLine.setLatLngs(routeCoords);
        } else {
          routeLine = L.polyline(routeCoords, { color: '#176b5b', weight: 4, opacity: 0.85 }).addTo(journeyMap);
        }

        journeyMap.fitBounds(L.latLngBounds(routeCoords), { padding: [50, 50] });
      } else {
        // Fallback to straight-line if OSRM gives no route
        drawStraightLineFallback(sPos, ePos);
      }
    } catch (err) {
      console.error('OSRM route fetch error:', err);
      drawStraightLineFallback(sPos, ePos);
    }
  } else if (startMarker) {
    journeyMap.setView(startMarker.getLatLng(), 14);
  } else if (endMarker) {
    journeyMap.setView(endMarker.getLatLng(), 14);
  }
}

function drawStraightLineFallback(sPos, ePos) {
  const points = [sPos, ePos];
  if (routeLine) {
    routeLine.setLatLngs(points);
  } else {
    routeLine = L.polyline(points, { color: '#176b5b', weight: 4, dashArray: '8, 10', opacity: 0.85 }).addTo(journeyMap);
  }
  journeyMap.fitBounds(L.latLngBounds(points), { padding: [50, 50] });
}

async function geocodeSearch(query) {
  const url = 'https://nominatim.openstreetmap.org/search?format=json&q=' +
    encodeURIComponent(query) + '&limit=5&addressdetails=1&countrycodes=ke';

  const response = await fetch(url, {
    headers: { 'Accept': 'application/json', 'Accept-Language': 'en' }
  });

  if (!response.ok) throw new Error('Location search failed.');
  return response.json();
}

function showGeocodeResults(results, container, onSelect) {
  container.innerHTML = '';

  if (!results.length) {
    container.style.display = 'none';
    return;
  }

  container.style.display = 'block';

  results.forEach(result => {
    const item = document.createElement('div');
    item.style.cssText = 'padding:10px 12px;font-size:12px;cursor:pointer;border-top:1px solid var(--line)';
    item.textContent = result.display_name;
    item.addEventListener('mouseenter', () => { item.style.background = '#f7fbf9'; });
    item.addEventListener('mouseleave', () => { item.style.background = ''; });
    item.addEventListener('click', () => {
      onSelect(result);
      container.style.display = 'none';
    });
    container.appendChild(item);
  });
}

function wireLocationSearch({ inputId, findBtnId, resultsId, statusId, latId, lngId, foundLabel, markerKind }) {
  const input = document.getElementById(inputId);
  const findBtn = document.getElementById(findBtnId);
  const results = document.getElementById(resultsId);
  const status = document.getElementById(statusId);
  const latField = document.getElementById(latId);
  const lngField = document.getElementById(lngId);

  if (!input || !findBtn) return;

  async function runSearch() {
    const query = input.value.trim();
    if (!query) {
      status.textContent = 'Please enter a location first.';
      status.style.color = '#c94b4b';
      input.focus();
      return;
    }

    findBtn.disabled = true;
    const originalHtml = findBtn.innerHTML;
    findBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Searching';
    status.textContent = 'Searching...';
    status.style.color = 'var(--muted)';
    latField.value = '';
    lngField.value = '';

    try {
      const found = await geocodeSearch(query);

      if (!found.length) {
        status.textContent = 'Nothing found. Try a more specific search.';
        status.style.color = '#c94b4b';
        return;
      }

      showGeocodeResults(found, results, (place) => {
        input.value = place.display_name;
        latField.value = place.lat;
        lngField.value = place.lon;
        status.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + foundLabel;
        status.style.color = 'var(--p)';
        setJourneyMarker(markerKind, parseFloat(place.lat), parseFloat(place.lon), input.value);
      });

      status.textContent = 'Select a result below.';
      status.style.color = 'var(--muted)';
    } catch (err) {
      status.textContent = 'Search failed. Check your connection and try again.';
      status.style.color = '#c94b4b';
    } finally {
      findBtn.disabled = false;
      findBtn.innerHTML = originalHtml;
    }
  }

  findBtn.addEventListener('click', runSearch);
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      e.preventDefault();
      runSearch();
    }
  });
  input.addEventListener('input', () => {
    latField.value = '';
    lngField.value = '';
    results.style.display = 'none';
    status.textContent = 'Location changed. Search again to confirm.';
    status.style.color = 'var(--muted)';

    if (markerKind === 'start' && startMarker) {
      journeyMap.removeLayer(startMarker);
      startMarker = null;
    }
    if (markerKind === 'end' && endMarker) {
      journeyMap.removeLayer(endMarker);
      endMarker = null;
    }
    if (routeLine) {
      journeyMap.removeLayer(routeLine);
      routeLine = null;
    }
  });
}

wireLocationSearch({
  inputId: 'startPoint', findBtnId: 'findStartBtn', resultsId: 'startResults',
  statusId: 'startStatus', latId: 'startLat', lngId: 'startLng',
  foundLabel: 'Starting point set.', markerKind: 'start'
});

wireLocationSearch({
  inputId: 'endPoint', findBtnId: 'findEndBtn', resultsId: 'endResults',
  statusId: 'endStatus', latId: 'endLat', lngId: 'endLng',
  foundLabel: 'Destination set.', markerKind: 'end'
});

document.getElementById('useMyLocationBtn')?.addEventListener('click', () => {
  const btn = document.getElementById('useMyLocationBtn');
  const startPoint = document.getElementById('startPoint');
  const status = document.getElementById('startStatus');

  if (!navigator.geolocation) {
    alert('Location is not supported by this browser.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Getting your location...';

  navigator.geolocation.getCurrentPosition(
    async (position) => {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;

      document.getElementById('startLat').value = lat;
      document.getElementById('startLng').value = lng;

      try {
        const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
        const data = await res.json();
        startPoint.value = data && data.display_name ? data.display_name : (lat.toFixed(6) + ', ' + lng.toFixed(6));
      } catch (e) {
        startPoint.value = lat.toFixed(6) + ', ' + lng.toFixed(6);
      }

      status.innerHTML = '<i class="fa-solid fa-circle-check"></i> Current location set.';
      status.style.color = 'var(--p)';
      setJourneyMarker('start', lat, lng, startPoint.value);

      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Use my current location';
    },
    () => {
      alert('Please allow SafariTrak to access your location.');
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Use my current location';
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
});

document.getElementById('submitJourney')?.addEventListener('click', async () => {
  const startPoint = document.getElementById('startPoint');
  const endPoint = document.getElementById('endPoint');
  const btn = document.getElementById('submitJourney');
  const errorBox = document.getElementById('journeyFormError');

  errorBox.style.display = 'none';

  if (!startPoint.value.trim() || !endPoint.value.trim()) {
    errorBox.textContent = 'Please fill in both your starting point and destination.';
    errorBox.style.display = 'block';
    return;
  }

  const startLatVal = document.getElementById('startLat').value;
  const startLngVal = document.getElementById('startLng').value;
  const endLatVal = document.getElementById('endLat').value;
  const endLngVal = document.getElementById('endLng').value;

  if (!startLatVal || !startLngVal) {
    errorBox.textContent = 'Please search for your starting point and select a result, or use your current location.';
    errorBox.style.display = 'block';
    return;
  }

  if (!endLatVal || !endLngVal) {
    errorBox.textContent = 'Please search for your destination and select a result.';
    errorBox.style.display = 'block';
    return;
  }

  const shareWith = Array.from(document.querySelectorAll('.share-checkbox:checked'))
    .map(el => parseInt(el.value, 10));

  const payload = {
    start_label: startPoint.value.trim(),
    start_lat: parseFloat(startLatVal),
    start_lng: parseFloat(startLngVal),
    end_label: endPoint.value.trim(),
    end_lat: parseFloat(endLatVal),
    end_lng: parseFloat(endLngVal),
    total_distance_km: computedTotalDistanceKm, // Send accurate road distance to database
    transport_mode: document.getElementById('transportMode').value,
    planned_departure_at: document.getElementById('departureTime').value || null,
    note: document.getElementById('journeyNote').value.trim(),
    route_deviation_alert: document.getElementById('deviationAlert').checked,
    share_with: shareWith,
  };

  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Starting...';

  try {
    const response = await fetch('backend/api/journeys/start.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await response.json();

    if (!data.success) {
      errorBox.textContent = data.message || 'That journey could not be started.';
      errorBox.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-route"></i> Start journey';
      return;
    }

    window.location.href = data.redirect || 'live-tracking.php';
  } catch (err) {
    errorBox.textContent = 'Something went wrong starting that journey. Please try again.';
    errorBox.style.display = 'block';
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-route"></i> Start journey';
  }
});