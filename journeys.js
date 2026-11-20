const journeyTabs = document.querySelectorAll('[data-tab-group="journeys"] .tab');
const journeyData = document.getElementById('journeyCarouselData');
const journeyRows = journeyData ? JSON.parse(journeyData.textContent || '[]') : [];
const emptyState = document.getElementById('emptyState');
const mapFeatureData = document.getElementById('safariTrakMapFeatures');
const journeyMapFeatures = mapFeatureData ? JSON.parse(mapFeatureData.textContent || '[]') : [];
const previousJourneyButton = document.getElementById('previousJourney');
const nextJourneyButton = document.getElementById('nextJourney');
const journeyCarouselPosition = document.getElementById('journeyCarouselPosition');
const journeysApp = document.querySelector('.app--journeys');
const journeyPanelsToggle = document.getElementById('journeyPanelsToggle');
const journeyPanelsToggleLabel = document.getElementById('journeyPanelsToggleLabel');
let visibleJourneyRows = [];
let carouselIndex = 0;
let selectedJourneyId = null;
let selectedMapFeatures = [];
let journeyRouteFocusable = false;
let selectedRouteRequest = null;
let selectedRouteSequence = 0;

function setJourneyRouteFocus(focused, refit = true) {
  journeysApp?.classList.toggle('journey-route-focus', focused);
  if (!journeyPanelsToggle) return;
  journeyPanelsToggle.hidden = !journeyRouteFocusable;
  journeyPanelsToggle.setAttribute('aria-pressed', String(focused));
  journeyPanelsToggle.setAttribute(
    'aria-label',
    focused ? 'Show trip panels' : 'Focus on selected trip route'
  );
  if (journeyPanelsToggleLabel) {
    journeyPanelsToggleLabel.textContent = focused ? 'Show trip panels' : 'Focus route';
  }
  if (refit && journeyRouteFocusable && selectedMapFeatures.length) {
    requestAnimationFrame(() => {
      window.SafariTrakPageMap?.setJourneyFeatures(selectedMapFeatures, { focus: true });
    });
  }
}

function setJourneyStatus(label, status) {
  const badge = document.getElementById('tripCarouselStatus');
  badge.textContent = label;
  badge.className = `badge ${['planned', 'active', 'completed', 'cancelled'].includes(status) ? status : ''}`;
}

function setJourneyDate(value) {
  const dateElement = document.getElementById('tripCarouselDate');
  const normalizedValue = value ? value.replace(' ', 'T') : '';
  const date = normalizedValue ? new Date(normalizedValue) : null;
  if (date && !Number.isNaN(date.getTime())) {
    dateElement.dateTime = date.toISOString();
  } else {
    dateElement.removeAttribute('datetime');
  }
  dateElement.textContent = date && !Number.isNaN(date.getTime())
    ? date.toLocaleString()
    : value || 'Not scheduled';
}

function resetJourneySharing() {
  document.getElementById('shareSelectedJourney').disabled = true;
  document.getElementById('selectedJourneyShareUrl').hidden = true;
  document.getElementById('copySelectedJourneyLink').hidden = true;
  document.getElementById('selectedJourneyShareStatus').textContent = '';
  document.getElementById('openCarouselGroup').hidden = true;
  const startButton = document.getElementById('startSelectedJourney');
  startButton.hidden = true;
  delete startButton.dataset.startPlanned;
  document.getElementById('endSelectedJourney').hidden = true;
}

function selectJourney(journey) {
  selectedRouteRequest?.abort();
  selectedRouteRequest = null;
  const routeSequence = ++selectedRouteSequence;
  const journeyId = Number(journey.id);
  selectedJourneyId = journeyId;
  resetJourneySharing();
  document.getElementById('shareSelectedJourney').disabled = false;

  const startButton = document.getElementById('startSelectedJourney');
  if (journey.status === 'planned') {
    startButton.hidden = false;
    startButton.dataset.startPlanned = String(journeyId);
  }
  if (journey.status === 'active') {
    const endButton = document.getElementById('endSelectedJourney');
    endButton.href = `end-journey.php?journey_id=${encodeURIComponent(String(journeyId))}`;
    endButton.hidden = false;
  }

  const features = journeyMapFeatures.filter(feature => Number(feature.journey_id) === journeyId);
  const rawCoordinates = [
    journey.startLat,
    journey.startLng,
    journey.endLat,
    journey.endLng,
  ];
  const [startLat, startLng, endLat, endLng] = rawCoordinates.map(Number);
  const hasCoordinates = rawCoordinates.every(value => value !== null && value !== undefined)
    && [startLat, startLng, endLat, endLng].every(Number.isFinite)
    && Math.abs(startLat) <= 90 && Math.abs(endLat) <= 90
    && Math.abs(startLng) <= 180 && Math.abs(endLng) <= 180;
  const startPoint = { lat: startLat, lng: startLng };
  const endPoint = { lat: endLat, lng: endLng };
  const routeStatus = document.getElementById('tripCarouselRouteStatus');

  if (hasCoordinates && !features.some(feature => feature.kind === 'start')) {
    features.push({ journey_id: journeyId, lat: startLat, lng: startLng, kind: 'start', label: journey.start });
  }
  if (hasCoordinates && !features.some(feature => feature.kind === 'finish')) {
    features.push({ journey_id: journeyId, lat: endLat, lng: endLng, kind: 'finish', label: journey.end });
  }

  const routeFeature = features.find(feature => feature.kind === 'start');
  const hasRecordedRoute = Array.isArray(routeFeature?.route) && routeFeature.route.length > 1;
  selectedMapFeatures = features;
  journeyRouteFocusable = hasCoordinates;
  setJourneyRouteFocus(hasCoordinates, false);
  window.SafariTrakPageMap?.setJourneyFeatures(features, { focus: true });

  document.getElementById('tripCarouselTitle').textContent =
    `${journey.start} → ${journey.end}`;
  document.getElementById('tripCarouselDistance').textContent = journey.distance;
  setJourneyStatus(journey.statusLabel, journey.status);
  setJourneyDate(journey.date);
  document.getElementById('tripCarouselShared').textContent = journey.shared;

  if (!hasCoordinates) {
    routeStatus.textContent = hasRecordedRoute
      ? 'Recorded trip path shown; saved start and destination coordinates are needed for a road-route preview.'
      : 'This trip has no saved coordinates for a road-route preview.';
    routeStatus.classList.toggle('is-error', !hasRecordedRoute);
    return;
  }

  selectedRouteRequest = new AbortController();
  routeStatus.textContent = hasRecordedRoute
    ? 'Loading the road route; the recorded path remains visible in the meantime…'
    : 'Loading this trip’s road route…';
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
    window.SafariTrakPageMap?.setJourneyFeatures(features, { focus: true });
    routeStatus.textContent = `Road route · ${(route.distance_meters / 1000).toFixed(1)} km · about ${Math.max(1, Math.round(route.duration_seconds / 60))} min.`;
    const savedDistance = Number.parseFloat(journey.distance);
    if (!Number.isFinite(savedDistance)) {
      document.getElementById('tripCarouselDistance').textContent =
        `${(route.distance_meters / 1000).toFixed(1)} km (route estimate)`;
    }
  }).catch(error => {
    if (error.name === 'AbortError' || routeSequence !== selectedRouteSequence) return;
    console.error('Selected journey road route failed.', error);
    routeStatus.textContent = hasRecordedRoute
      ? `Road route unavailable. Showing the recorded trip path. ${error.message || ''}`.trim()
      : `A road route could not be loaded, so no straight-line route is shown. ${error.message || ''}`.trim();
    routeStatus.classList.add('is-error');
  });
}

function selectGroupJourney(journey) {
  selectedRouteRequest?.abort();
  selectedRouteRequest = null;
  selectedRouteSequence += 1;
  selectedJourneyId = null;
  selectedMapFeatures = [];
  journeyRouteFocusable = false;
  setJourneyRouteFocus(false, false);
  resetJourneySharing();
  window.SafariTrakPageMap?.setJourneyFeatures([], { focus: false });

  document.getElementById('tripCarouselTitle').textContent =
    `${journey.groupTitle} → ${journey.groupDestination}`;
  document.getElementById('tripCarouselDistance').textContent = journey.distance;
  setJourneyStatus(`Group · ${journey.statusLabel}`, journey.statusLabel.toLowerCase());
  setJourneyDate(journey.date);
  document.getElementById('tripCarouselShared').textContent = journey.shared;
  document.getElementById('tripCarouselRouteStatus').textContent =
    'Group member routes are available in Group trips.';
  document.getElementById('tripCarouselRouteStatus').classList.remove('is-error');
  document.getElementById('openCarouselGroup').hidden = false;
}

function selectCarouselRow(journey) {
  if (journey.type === 'group') {
    selectGroupJourney(journey);
    return;
  }
  selectJourney(journey);
}

function renderCarousel() {
  const rowCount = visibleJourneyRows.length;
  const carouselEmpty = rowCount === 0;
  emptyState.hidden = !carouselEmpty;
  previousJourneyButton.disabled = rowCount < 2;
  nextJourneyButton.disabled = rowCount < 2;
  journeyCarouselPosition.textContent = rowCount
    ? `${carouselIndex + 1} / ${rowCount}`
    : '0 trips';

  if (carouselEmpty) {
    selectedRouteRequest?.abort();
    selectedRouteRequest = null;
    selectedRouteSequence += 1;
    selectedJourneyId = null;
    selectedMapFeatures = [];
    journeyRouteFocusable = false;
    setJourneyRouteFocus(false, false);
    resetJourneySharing();
    window.SafariTrakPageMap?.setJourneyFeatures([], { focus: false });
    document.getElementById('tripCarouselTitle').textContent = 'No trips in this category';
    document.getElementById('tripCarouselDistance').textContent = '—';
    setJourneyStatus('No trips', '');
    setJourneyDate('');
    document.getElementById('tripCarouselShared').textContent = '—';
    const routeStatus = document.getElementById('tripCarouselRouteStatus');
    routeStatus.textContent = 'Choose another filter to see trips.';
    routeStatus.classList.remove('is-error');
    return;
  }

  carouselIndex = (carouselIndex + rowCount) % rowCount;
  selectCarouselRow(visibleJourneyRows[carouselIndex]);
}

function applyJourneyFilter(filter, preferredRow = null) {
  visibleJourneyRows = journeyRows.filter(journey => filter === 'all' || journey.status === filter);
  const preferredIndex = preferredRow ? visibleJourneyRows.indexOf(preferredRow) : -1;
  carouselIndex = preferredIndex >= 0 ? preferredIndex : 0;
  renderCarousel();
}

function moveCarousel(offset) {
  if (visibleJourneyRows.length < 2) return;
  carouselIndex = (carouselIndex + offset + visibleJourneyRows.length) % visibleJourneyRows.length;
  renderCarousel();
}

journeyPanelsToggle?.addEventListener('click', () => {
  if (!journeyRouteFocusable || !journeysApp) return;
  setJourneyRouteFocus(!journeysApp.classList.contains('journey-route-focus'));
});

const requestedJourneyId = Number(new URLSearchParams(window.location.search).get('selected'));
const requestedJourneyRow = (requestedJourneyId
  ? journeyRows.find(journey => Number(journey.id) === requestedJourneyId)
  : null) || journeyRows[0] || null;
applyJourneyFilter('all', requestedJourneyRow);

previousJourneyButton.addEventListener('click', () => moveCarousel(-1));
nextJourneyButton.addEventListener('click', () => moveCarousel(1));

journeyTabs.forEach(tab => {
  tab.addEventListener('click', () => {
    journeyTabs.forEach(item => {
      item.classList.remove('active');
      item.setAttribute('aria-pressed', 'false');
    });
    tab.classList.add('active');
    tab.setAttribute('aria-pressed', 'true');
    applyJourneyFilter(tab.dataset.tab);
  });
});

document.getElementById('shareSelectedJourney')?.addEventListener('click', async event => {
  const button = event.currentTarget;
  if (!selectedJourneyId) return;
  const journeyId = selectedJourneyId;
  button.disabled = true;
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
    if (selectedJourneyId !== journeyId) return;
    const shareUrl = document.getElementById('selectedJourneyShareUrl');
    shareUrl.value = payload.share_url;
    shareUrl.hidden = false;
    document.getElementById('copySelectedJourneyLink').hidden = false;
    document.getElementById('selectedJourneyShareStatus').textContent = payload.trip_status === 'planned'
      ? 'Temporary route link created. It will not show live location before the trip starts.'
      : 'Temporary live-trip link created. Anyone with the link can view this active journey.';
  } catch (error) {
    if (selectedJourneyId !== journeyId) return;
    document.getElementById('selectedJourneyShareStatus').textContent =
      error.message || 'The trip link could not be created.';
  } finally {
    if (selectedJourneyId === journeyId) button.disabled = false;
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
  const startButton = event.target.closest('[data-start-planned]');
  if (!startButton) return;
  event.preventDefault();
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
      startButton.innerHTML = '<i class="fa-solid fa-play" aria-hidden="true"></i>Start now';
      alert(error.message || 'This scheduled trip could not be started.');
    });
});
