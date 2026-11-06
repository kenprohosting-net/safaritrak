document.getElementById('useMyLocationBtn')?.addEventListener('click', () => {
  const btn = document.getElementById('useMyLocationBtn');
  const startPoint = document.getElementById('startPoint');

  if (!navigator.geolocation) {
    alert('Location is not supported by this browser.');
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Getting your location...';

  navigator.geolocation.getCurrentPosition(
    async (position) => {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;

      document.getElementById('startLat').value = lat;
      document.getElementById('startLng').value = lng;
      if (startPoint && !startPoint.value.trim()) startPoint.value = `Current location (${lat.toFixed(5)}, ${lng.toFixed(5)})`;
      startPoint?.dispatchEvent(new CustomEvent('safaritrak-location-selected', {
        bubbles: true,
        detail: { lat, lng, label: startPoint.value },
      }));
      refreshRoutePreview();

      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-check"></i> Location added';
    },
    () => {
      alert('Please allow SafariTrak to access your location.');
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Use my current location';
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
});

let routeRequest;
let routePreviewAvailable = false;
const submitJourneyButton = document.getElementById('submitJourney');
const departureTimeInput = document.getElementById('departureTime');

function updateJourneyActionLabel() {
  if (submitJourneyButton) {
    submitJourneyButton.innerHTML = departureTimeInput?.value
      ? '<i class="fa-solid fa-calendar-check"></i>Schedule trip'
      : '<i class="fa-solid fa-route"></i>Start journey';
  }
}

departureTimeInput?.addEventListener('change', updateJourneyActionLabel);

async function refreshRoutePreview() {
  const status = document.getElementById('routePreviewStatus');
  const rawCoordinates = [
    document.getElementById('startLat').value,
    document.getElementById('startLng').value,
    document.getElementById('endLat').value,
    document.getElementById('endLng').value,
  ];
  const [startLat, startLng, endLat, endLng] = rawCoordinates.map(Number);
  const mode = document.getElementById('transportMode').value;
  routeRequest?.abort();

  if (rawCoordinates.some(value => value.trim() === '') || ![startLat, startLng, endLat, endLng].every(Number.isFinite)) {
    routePreviewAvailable = false;
    window.SafariTrakPageMap?.clearSearchLocation();
    window.SafariTrakPageMap?.setFeatures([]);
    if (status) status.textContent = 'Choose both locations from the suggestions (or use your current location) to preview a route.';
    return;
  }
  if (mode !== 'car') {
    routePreviewAvailable = true;
    window.SafariTrakPageMap?.clearSearchLocation();
    window.SafariTrakPageMap?.setFeatures([
      { lat: startLat, lng: startLng, kind: 'start', label: `Start · ${document.getElementById('startPoint').value}` },
      { lat: endLat, lng: endLng, kind: 'finish', label: `Destination · ${document.getElementById('endPoint').value}` },
    ]);
    if (status) status.textContent = 'Driving route preview is available for car trips only. You can still start this journey.';
    return;
  }

  routeRequest = new AbortController();
  routePreviewAvailable = false;
  if (status) status.textContent = 'Loading driving route preview…';
  window.SafariTrakPageMap?.clearSearchLocation();
  window.SafariTrakPageMap?.setFeatures([
    { lat: startLat, lng: startLng, kind: 'start', label: `Start · ${document.getElementById('startPoint').value}` },
    {
      lat: endLat,
      lng: endLng,
      kind: 'finish',
      label: `Destination · ${document.getElementById('endPoint').value}`,
      route: [[startLng, startLat], [endLng, endLat]],
      routeColor: 'planned',
      routeApproximate: true,
    },
  ]);
  try {
    const route = await window.SafariTrakRouteDirections.getDrivingRoute(
      { lat: startLat, lng: startLng },
      { lat: endLat, lng: endLng },
      { signal: routeRequest.signal }
    );
    const points = route.coordinates;
    routePreviewAvailable = true;
    window.SafariTrakPageMap?.clearSearchLocation();
    window.SafariTrakPageMap?.setFeatures([
      { lat: startLat, lng: startLng, kind: 'start', label: `Start · ${document.getElementById('startPoint').value}` },
      {
        lat: endLat,
        lng: endLng,
        kind: 'finish',
        label: `Destination · ${document.getElementById('endPoint').value}`,
        route: points,
        routeColor: 'planned',
        routeApproximate: false,
      },
    ]);
    if (status) {
      status.textContent = `Driving route · ${(route.distance_meters / 1000).toFixed(1)} km · about ${Math.max(1, Math.round(route.duration_seconds / 60))} min. Not traffic-aware.`;
    }
  } catch (error) {
    if (error.name === 'AbortError') return;
    console.error('Driving route preview failed.', error);
    routePreviewAvailable = false;
    if (status) status.textContent = `${error.message || 'The driving route service is unavailable.'} Retry after checking both locations.`;
    window.SafariTrakPageMap?.clearSearchLocation();
    window.SafariTrakPageMap?.setFeatures([
      { lat: startLat, lng: startLng, kind: 'start', label: `Start · ${document.getElementById('startPoint').value}` },
      {
        lat: endLat,
        lng: endLng,
        kind: 'finish',
        label: `Destination · ${document.getElementById('endPoint').value}`,
        route: [[startLng, startLat], [endLng, endLat]],
        routeColor: 'planned',
        routeApproximate: true,
      },
    ]);
  }
}

document.addEventListener('safaritrak-location-selected', event => {
  if (event.target.id === 'startPoint' || event.target.id === 'endPoint') refreshRoutePreview();
});
['startPoint', 'endPoint'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', refreshRoutePreview);
});
document.getElementById('transportMode')?.addEventListener('change', refreshRoutePreview);
refreshRoutePreview();

submitJourneyButton?.addEventListener('click', async () => {
  const startPoint = document.getElementById('startPoint');
  const endPoint = document.getElementById('endPoint');
  const btn = submitJourneyButton;

  if (!startPoint.value.trim() || !endPoint.value.trim()) {
    alert('Please fill in both your starting point and destination.');
    return;
  }

  const startLatVal = document.getElementById('startLat').value;
  const startLngVal = document.getElementById('startLng').value;
  const endLatVal = document.getElementById('endLat').value;
  const endLngVal = document.getElementById('endLng').value;
  const validPoint = (lat, lng) => lat.trim() !== '' && lng.trim() !== ''
    && Number.isFinite(Number(lat)) && Number.isFinite(Number(lng))
    && Number(lat) >= -90 && Number(lat) <= 90
    && Number(lng) >= -180 && Number(lng) <= 180;

  if (!validPoint(startLatVal, startLngVal) || !validPoint(endLatVal, endLngVal)) {
    const missingPoint = validPoint(startLatVal, startLngVal) ? endPoint : startPoint;
    document.getElementById('routePreviewStatus').textContent = 'Choose both locations from the suggestions. Use your current location for the starting point.';
    missingPoint.focus();
    return;
  }
  if (document.getElementById('transportMode').value === 'car' && !routePreviewAvailable) {
    document.getElementById('routePreviewStatus').textContent = 'Wait for a valid driving route before starting this journey.';
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
    transport_mode: document.getElementById('transportMode').value,
    planned_departure_at: document.getElementById('departureTime').value || null,
    note: document.getElementById('journeyNote').value.trim(),
    route_deviation_alert: document.getElementById('deviationAlert').checked,
    share_with: shareWith,
  };

  btn.disabled = true;
  btn.textContent = 'Starting...';

  try {
    const response = await fetch('backend/api/journeys/start.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await response.json();

    if (!data.success) {
      alert(data.message || 'That journey could not be started.');
      btn.disabled = false;
      updateJourneyActionLabel();
      return;
    }

    window.location.href = data.redirect || 'my-journeys.php';
  } catch (err) {
    alert('Something went wrong starting that journey. Please try again.');
    btn.disabled = false;
    updateJourneyActionLabel();
  }
});

updateJourneyActionLabel();