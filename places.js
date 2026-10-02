const categoryColors = {
  hospital: '#c94b4b',
  police: '#2f6fed',
  fuel: '#d69b2d',
  hotel: '#8b5cf6',
  restaurant: '#10b981',
  other: '#6b7280'
};

const categoryIcons = {
  hospital: 'fa-kit-medical',
  police: 'fa-building-shield',
  fuel: 'fa-gas-pump',
  hotel: 'fa-bed',
  restaurant: 'fa-utensils',
  other: 'fa-location-dot'
};

const categoryLabels = {
  hospital: 'Hospital',
  police: 'Police',
  fuel: 'Fuel station',
  hotel: 'Hotel',
  restaurant: 'Restaurant',
  other: 'Place'
};

const NAIROBI_FALLBACK = { lat: -1.2833, lng: 36.8167 };

const placesMap = L.map('placesMap').setView([NAIROBI_FALLBACK.lat, NAIROBI_FALLBACK.lng], 8);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19,
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(placesMap);

let userLocation = null;
let resultMarkers = [];
let userMarker = null;
let activeCategory = 'all';
let activeController = null;

function setStatus(text) {
  const status = document.getElementById('placesStatus');
  if (status) {
    status.textContent = text;
    status.style.display = text ? 'block' : 'none';
  }
}

function clearMarkers() {
  resultMarkers.forEach(m => placesMap.removeLayer(m));
  resultMarkers = [];
}

function markerIcon(category) {
  const color = categoryColors[category] || categoryColors.other;
  return L.divIcon({
    className: 'st-marker',
    html: '<div style="width:16px;height:16px;background:' + color + ';border:3px solid white;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.25)"></div>',
    iconSize: [16, 16],
    iconAnchor: [8, 8]
  });
}

function openPlaceModal(place) {
  document.getElementById('placeDetailTitle').textContent = place.name;
  document.getElementById('placeDetailSubtitle').textContent =
    (categoryLabels[place.category] || 'Place') + ' \u00b7 ' + place.distance_km + ' km away';
  document.getElementById('placeDetailAddress').innerHTML = '<b>Address:</b> ' + escapeHtml(place.address);
  document.getElementById('placeDetailHours').innerHTML = '<b>Hours:</b> ' + escapeHtml(place.hours);

  const directions = document.getElementById('placeDetailDirections');
  directions.href = 'https://www.google.com/maps/dir/?api=1&destination=' + place.lat + ',' + place.lng;

  document.getElementById('placeDetailModal').classList.add('open');
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text || '';
  return div.innerHTML;
}

function renderPlaces(places) {
  clearMarkers();

  const list = document.getElementById('placesList');
  const resultsCount = document.getElementById('resultsCount');

  if (!places.length) {
    list.innerHTML = '';
    resultsCount.textContent = '0 places found';
    setStatus('No places found. Try a different search or category.');
    return;
  }

  setStatus('');
  resultsCount.textContent = places.length + (places.length === 1 ? ' place found' : ' places found');

  list.innerHTML = places.map((place, i) => {
    const icon = categoryIcons[place.category] || categoryIcons.other;
    const label = categoryLabels[place.category] || 'Place';
    return '<div class="journey-row" data-index="' + i + '">' +
      '<div class="jicon"><i class="fa-solid ' + icon + '"></i></div>' +
      '<div class="jinfo"><b>' + escapeHtml(place.name) + '</b><small>' + label + ' \u00b7 ' + place.distance_km + ' km away</small></div>' +
      '<div class="jmeta"><strong>' + escapeHtml(place.hours) + '</strong></div>' +
      '</div>';
  }).join('');

  list.querySelectorAll('.journey-row').forEach(row => {
    row.addEventListener('click', () => openPlaceModal(places[Number(row.dataset.index)]));
  });

  places.forEach(place => {
    const marker = L.marker([place.lat, place.lng], { icon: markerIcon(place.category) })
      .addTo(placesMap)
      .bindPopup('<b>' + escapeHtml(place.name) + '</b>');
    marker.on('click', () => openPlaceModal(place));
    resultMarkers.push(marker);
  });
}

async function performSearch(searchText) {
  if (!userLocation) return;

  if (activeController) activeController.abort();
  activeController = new AbortController();

  setStatus('Searching...');
  document.getElementById('resultsCount').textContent = 'Searching...';

  const params = new URLSearchParams({
    category: activeCategory,
    q: searchText || '',
    lat: userLocation.lat,
    lng: userLocation.lng,
    radius: searchText ? 50000 : 20000
  });

  try {
    const response = await fetch('backend/api/places/search.php?' + params.toString(), {
      signal: activeController.signal
    });
    const data = await response.json();

    if (!data.success) {
      setStatus(data.message || 'Could not load places. Please try again.');
      document.getElementById('resultsCount').textContent = 'Places nearby';
      document.getElementById('placesList').innerHTML = '';
      clearMarkers();
      return;
    }

    renderPlaces(data.places);
  } catch (err) {
    if (err.name === 'AbortError') return;
    setStatus('Could not load places. Please try again.');
    document.getElementById('resultsCount').textContent = 'Places nearby';
  }
}

function initLocation() {
  const finish = (lat, lng) => {
    userLocation = { lat, lng };
    placesMap.setView([lat, lng], 12);

    if (userMarker) placesMap.removeLayer(userMarker);
    userMarker = L.marker([lat, lng], {
      icon: L.divIcon({
        className: 'st-marker',
        html: '<div style="width:16px;height:16px;background:#2f6fed;border:3px solid white;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.3)"></div>',
        iconSize: [16, 16],
        iconAnchor: [8, 8]
      })
    }).addTo(placesMap).bindPopup('You are here');

    performSearch('');
  };

  let settled = false;
  const settleOnce = (lat, lng) => {
    if (settled) return;
    settled = true;
    finish(lat, lng);
  };

  if (!navigator.geolocation) {
    settleOnce(NAIROBI_FALLBACK.lat, NAIROBI_FALLBACK.lng);
    return;
  }

  // Hard fallback: some localhost/XAMPP setups never fire either
  // geolocation callback. Force a result after 8s so the page never
  // hangs on "Getting your location...".
  const hardTimeout = setTimeout(() => {
    settleOnce(NAIROBI_FALLBACK.lat, NAIROBI_FALLBACK.lng);
  }, 8000);

  navigator.geolocation.getCurrentPosition(
    pos => { clearTimeout(hardTimeout); settleOnce(pos.coords.latitude, pos.coords.longitude); },
    () => { clearTimeout(hardTimeout); settleOnce(NAIROBI_FALLBACK.lat, NAIROBI_FALLBACK.lng); },
    { timeout: 6000, maximumAge: 60000 }
  );
}

document.querySelectorAll('#placeTabs .tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('#placeTabs .tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    activeCategory = tab.getAttribute('data-category');
    performSearch(document.getElementById('placeSearch').value.trim());
  });
});

document.getElementById('placeSearchBtn')?.addEventListener('click', () => {
  performSearch(document.getElementById('placeSearch').value.trim());
});

document.getElementById('placeSearch')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    performSearch(e.target.value.trim());
  }
});

initLocation();