const journeyTabs = document.querySelectorAll('[data-tab-group="journeys"] .tab');
const journeyRows = document.querySelectorAll('#journeyList .journey-row');
const emptyState = document.getElementById('emptyState');
const mapFeatureData = document.getElementById('safariTrakMapFeatures');
const journeyMapFeatures = mapFeatureData ? JSON.parse(mapFeatureData.textContent || '[]') : [];
const journeyPickerPanel = document.getElementById('journeyPickerPanel');
const journeyPickerToggle = document.getElementById('journeyPickerToggle');
const journeyPage = document.querySelector('.app--journeys');
let selectedJourneyId = null;
let selectedRouteRequest = null;
let selectedRouteSequence = 0;

function selectJourney(row) {
  if (!row?.dataset.journeyId) return;
  selectedRouteRequest?.abort();
  selectedRouteRequest = null;
  const routeSequence = ++selectedRouteSequence;
  const journeyId = Number(row.dataset.journeyId);
  selectedJourneyId = journeyId;
  document.getElementById('shareSelectedJourney').disabled = false;
  document.getElementById('selectedJourneyShareUrl').hidden = true;
  document.getElementById('copySelectedJourneyLink').hidden = true;
  document.getElementById('selectedJourneyShareStatus').textContent = '';
  const features = journeyMapFeatures.filter(feature => Number(feature.journey_id) === journeyId);
  const rawCoordinates = [
    row.dataset.startLat,
    row.dataset.startLng,
    row.dataset.endLat,
    row.dataset.endLng,
  ];
  const [startLat, startLng, endLat, endLng] = rawCoordinates.map(Number);
  const hasCoordinates = rawCoordinates.every(value => value !== '')
    && [startLat, startLng, endLat, endLng].every(Number.isFinite)
    && Math.abs(startLat) <= 90 && Math.abs(endLat) <= 90
    && Math.abs(startLng) <= 180 && Math.abs(endLng) <= 180;
  const startPoint = { lat: startLat, lng: startLng };
  const endPoint = { lat: endLat, lng: endLng };
  const routeStatus = document.getElementById('journeyMapDetailRouteStatus');
  if (hasCoordinates && !features.some(feature => feature.kind === 'start')) {
    features.push({ journey_id: journeyId, lat: startLat, lng: startLng, kind: 'start', label: row.dataset.journeyStart });
  }
  if (hasCoordinates && !features.some(feature => feature.kind === 'finish')) {
    features.push({ journey_id: journeyId, lat: endLat, lng: endLng, kind: 'finish', label: row.dataset.journeyEnd });
  }
  const routeFeature = features.find(feature => feature.kind === 'start');
  const hasRecordedRoute = Array.isArray(routeFeature?.route) && routeFeature.route.length > 1;
  if (hasCoordinates && !hasRecordedRoute && routeFeature) {
    routeFeature.route = [[startLng, startLat], [endLng, endLat]];
    routeFeature.routeColor = 'planned';
    routeFeature.routeApproximate = true;
  }
  journeyPickerPanel.hidden = true;
  journeyPage?.classList.remove('journey-picker-open');
  journeyPickerToggle?.setAttribute('aria-expanded', 'false');
  window.SafariTrakPageMap?.focusFeatures(features);

  document.getElementById('journeyMapDetailTitle').textContent =
    `${row.dataset.journeyStart} → ${row.dataset.journeyEnd}`;
  document.getElementById('journeyMapDetailStatus').textContent = row.dataset.journeyStatus;
  document.getElementById('journeyMapDetailDistance').textContent = row.dataset.journeyDistance;
  document.getElementById('journeyMapDetailDate').textContent =
    new Date(row.dataset.journeyDate.replace(' ', 'T')).toLocaleString();
  document.getElementById('journeyMapDetailShared').textContent = row.dataset.journeyShared;
  document.getElementById('journeyMapDetailNote').textContent = row.dataset.journeyNote;
  journeyRows.forEach(item => item.classList.toggle('is-map-selected', item === row));

  if (hasRecordedRoute) {
    routeFeature.routeColor = 'planned';
    routeFeature.routeApproximate = false;
    routeStatus.textContent = 'Recorded trip path shown on the map.';
    routeStatus.classList.remove('is-error');
    window.SafariTrakPageMap?.focusFeatures(features);
    return;
  }
  if (!hasCoordinates) {
    routeStatus.textContent = 'This trip has no saved coordinates for a road-route preview.';
    routeStatus.classList.add('is-error');
    return;
  }

  selectedRouteRequest = new AbortController();
  routeStatus.textContent = 'Loading this trip’s road route…';
  routeStatus.classList.remove('is-error');
  window.SafariTrakRouteDirections.getDrivingRoute(startPoint, endPoint, {
    signal: selectedRouteRequest.signal,
  }).then(route => {
    if (routeSequence !== selectedRouteSequence) return;
    if (!routeFeature) {
      throw new Error('The selected trip route endpoints are unavailable.');
    }
    routeFeature.route = route.coordinates;
    routeFeature.routeColor = 'planned';
    routeFeature.routeApproximate = false;
    window.SafariTrakPageMap?.focusFeatures(features);
    routeStatus.textContent = `Road route · ${(route.distance_meters / 1000).toFixed(1)} km · about ${Math.max(1, Math.round(route.duration_seconds / 60))} min.`;
    const savedDistance = Number.parseFloat(row.dataset.journeyDistance);
    if (!Number.isFinite(savedDistance)) {
      document.getElementById('journeyMapDetailDistance').textContent =
        `${(route.distance_meters / 1000).toFixed(1)} km (route estimate)`;
    }
  }).catch(error => {
    if (error.name === 'AbortError' || routeSequence !== selectedRouteSequence) return;
    console.error('Selected journey road route failed.', error);
    routeStatus.textContent = `Road route unavailable. Showing a direct start-to-finish estimate. ${error.message || ''}`.trim();
    routeStatus.classList.add('is-error');
  });
}

const requestedJourneyId = Number(new URLSearchParams(window.location.search).get('selected'));
const requestedJourneyRow = requestedJourneyId
  ? document.querySelector(`#journeyList .journey-row[data-journey-id="${requestedJourneyId}"]`)
  : null;
if (requestedJourneyRow) selectJourney(requestedJourneyRow);

journeyPickerToggle?.addEventListener('click', () => {
  const isOpen = journeyPickerPanel.hidden;
  journeyPickerPanel.hidden = !isOpen;
  journeyPage?.classList.toggle('journey-picker-open', isOpen);
  journeyPickerToggle.setAttribute('aria-expanded', String(isOpen));
  if (isOpen) journeyPickerPanel.querySelector('.journey-row:not([hidden])')?.focus();
});

document.getElementById('shareSelectedJourney')?.addEventListener('click', async event => {
  const button = event.currentTarget;
  if (!selectedJourneyId) return;
  button.disabled = true;
  try {
    const response = await fetch('backend/api/journeys/share-link.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'create', journey_id: selectedJourneyId }),
    });
    const payload = await response.json();
    if (!response.ok || !payload.success || !payload.share_url) {
      throw new Error(payload.message || 'The trip link could not be created.');
    }
    const shareUrl = document.getElementById('selectedJourneyShareUrl');
    shareUrl.value = payload.share_url;
    shareUrl.hidden = false;
    document.getElementById('copySelectedJourneyLink').hidden = false;
    document.getElementById('selectedJourneyShareStatus').textContent = payload.trip_status === 'planned'
      ? 'Temporary route link created. It will not show live location before the trip starts.'
      : 'Temporary live-trip link created. Anyone with the link can view this active journey.';
  } catch (error) {
    document.getElementById('selectedJourneyShareStatus').textContent =
      error.message || 'The trip link could not be created.';
  } finally {
    button.disabled = false;
  }
});

document.getElementById('copySelectedJourneyLink')?.addEventListener('click', async () => {
  const status = document.getElementById('selectedJourneyShareStatus');
  try {
    await navigator.clipboard.writeText(document.getElementById('selectedJourneyShareUrl').value);
    status.textContent = 'Trip link copied.';
  } catch (error) {
    status.textContent = 'Clipboard access is unavailable. Select the link and copy it manually.';
  }
});

document.addEventListener('click', event => {
  const row = event.target.closest('#journeyList .journey-row[data-journey-id]');
  if (row) selectJourney(row);

  const startButton = event.target.closest('[data-start-planned]');
  if (!startButton) return;
  event.preventDefault();
  event.stopPropagation();
  startButton.disabled = true;
  startButton.textContent = 'Starting…';
  fetch('backend/api/journeys/activate.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ journey_id: Number(startButton.dataset.startPlanned) }),
  })
    .then(async response => {
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'This scheduled trip could not be started.');
      }
      window.location.href = payload.redirect || 'my-journeys.php';
    })
    .catch(error => {
      startButton.disabled = false;
      startButton.textContent = 'Start now';
      alert(error.message || 'This scheduled trip could not be started.');
    });
});

document.addEventListener('keydown', event => {
    if (!['Enter', ' '].includes(event.key)) return;
    const row = event.target.closest('#journeyList .journey-row[role="button"][data-journey-id]');
    if (!row) return;
    event.preventDefault();
    selectJourney(row);
});

journeyTabs.forEach(tab => {
  tab.addEventListener('click', () => {
    journeyTabs.forEach(t => {
      t.classList.remove('active');
      t.setAttribute('aria-pressed', 'false');
    });
    tab.classList.add('active');
    tab.setAttribute('aria-pressed', 'true');
    
    const filter = tab.getAttribute('data-tab');
    let visibleCount = 0;

    journeyRows.forEach(row => {
      const show = filter === 'all' || row.getAttribute('data-status') === filter;
      row.style.display = show ? '' : 'none';
      if (show) visibleCount++;
    });

    if (emptyState) {
      emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
    }
  });
});

document.addEventListener('click', (e) => {
  const row = e.target.closest('[data-open-modal]');
  if (!row) return;

  const modalId = row.getAttribute('data-open-modal');
  const targetModal = document.getElementById(modalId);
  if (targetModal) {
    targetModal.classList.add('active');
  }
});

document.addEventListener('click', (e) => {
  if (e.target.closest('[data-close-modal]') || e.target.classList.contains('modal-overlay')) {
    const activeModal = e.target.closest('.modal-overlay');
    if (activeModal) {
      activeModal.classList.remove('active');
    }
  }
});