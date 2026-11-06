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

let userLocation = null;
let activeCategory = 'all';
let currentPlaces = [];

const googlePlaceTypes = {
  hospital: ['hospital'],
  police: ['police'],
  fuel: ['gas_station'],
  hotel: ['lodging'],
  restaurant: ['restaurant'],
};

function distanceKm(a, b) {
  const radians = value => value * Math.PI / 180;
  const dLat = radians(b.lat - a.lat);
  const dLng = radians(b.lng - a.lng);
  const value = Math.sin(dLat / 2) ** 2
    + Math.cos(radians(a.lat)) * Math.cos(radians(b.lat)) * Math.sin(dLng / 2) ** 2;
  return 6371 * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value));
}

document.addEventListener('safaritrak-location-selected', event => {
  if (event.target.id !== 'placeSearch') return;
  userLocation = { lat: Number(event.detail.lat), lng: Number(event.detail.lng) };
  performSearch(event.detail.label);
});

function setStatus(text) {
  const status = document.getElementById('placesStatus');
  if (status) {
    status.textContent = text;
    status.style.display = text ? 'block' : 'none';
  }
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text || '';
  return div.innerHTML;
}

function placeMapFeatures(places) {
  return places.map(place => ({
    lat: Number(place.lat),
    lng: Number(place.lng),
    kind: 'place',
    label: place.name,
    description: (categoryLabels[place.category] || 'Place') + ' · ' + place.distance_km + ' km away',
  }));
}

function openPlaceModal(place) {
  document.getElementById('placeDetailTitle').textContent = place.name;
  document.getElementById('placeDetailSubtitle').textContent =
    (categoryLabels[place.category] || 'Place') + ' · ' + place.distance_km + ' km away';
  document.getElementById('placeDetailAddress').innerHTML = '<b>Address:</b> ' + escapeHtml(place.address);
  document.getElementById('placeDetailHours').innerHTML = '<b>Hours:</b> ' + escapeHtml(place.hours);

  const directions = document.getElementById('placeDetailDirections');
  directions.href = 'https://www.google.com/maps/dir/?api=1&destination=' + place.lat + ',' + place.lng;

  document.getElementById('placeDetailModal').classList.add('open');
}

function renderPlaces(places) {
  currentPlaces = places;
  const list = document.getElementById('placesList');
  const resultsCount = document.getElementById('resultsCount');

  if (!places.length) {
    list.innerHTML = '';
    resultsCount.textContent = '0 places found';
    setStatus('No places found. Try a different search or category.');
    window.SafariTrakPageMap?.setFeatures([]);
    return;
  }

  setStatus('');
  resultsCount.textContent = places.length + (places.length === 1 ? ' place found' : ' places found');
  list.innerHTML = places.map((place, index) => {
    const icon = categoryIcons[place.category] || categoryIcons.other;
    const label = categoryLabels[place.category] || 'Place';
    return '<button type="button" class="journey-row" data-index="' + index + '">' +
      '<span class="jicon"><i class="fa-solid ' + icon + '"></i></span>' +
      '<span class="jinfo"><b>' + escapeHtml(place.name) + '</b><small>' + label + ' · ' + place.distance_km + ' km away</small></span>' +
      '<span class="jmeta"><strong>' + escapeHtml(place.hours) + '</strong></span>' +
      '</button>';
  }).join('');

  list.querySelectorAll('.journey-row').forEach(row => {
    row.addEventListener('click', () => openPlaceModal(places[Number(row.dataset.index)]));
  });
  window.SafariTrakPageMap?.setFeatures(placeMapFeatures(places));
}

async function performSearch(searchText) {
  if (!userLocation) {
    setStatus('Allow location access to search around your current position.');
    return;
  }

  setStatus('Searching...');
  document.getElementById('resultsCount').textContent = 'Searching...';

  try {
    const maps = await window.SafariTrakGoogleMaps?.ready;
    const placesLibrary = await maps?.importLibrary('places');
    if (!placesLibrary?.Place) {
      throw new Error('Google Places is unavailable. Check that Places API (New) is enabled for this API key.');
    }
    const request = {
      locationRestriction: {
        center: userLocation,
        radius: searchText ? 50_000 : 20_000,
      },
      maxResultCount: 20,
      fields: ['displayName', 'location', 'formattedAddress', 'primaryType', 'regularOpeningHours'],
    };
    if (activeCategory !== 'all') request.includedTypes = googlePlaceTypes[activeCategory];
    const result = await placesLibrary.Place.searchNearby(request);
    const places = (result.places || []).filter(place => place.location).map(place => {
      const point = { lat: place.location.lat(), lng: place.location.lng() };
      const primaryType = place.primaryType || '';
      const category = Object.entries(googlePlaceTypes).find(([, types]) => types.includes(primaryType))?.[0] || 'other';
      return {
        name: place.displayName || 'Nearby place',
        category,
        address: place.formattedAddress || 'Address unavailable',
        hours: place.regularOpeningHours?.openNow === true ? 'Open now'
          : place.regularOpeningHours?.openNow === false ? 'Closed now' : 'Hours unavailable',
        lat: point.lat,
        lng: point.lng,
        distance_km: distanceKm(userLocation, point).toFixed(1),
      };
    });
    renderPlaces(places);
  } catch (error) {
    console.error('Google Places nearby search failed.', error);
    setStatus('Nearby places are unavailable. Check the Places API, billing, and key restrictions, then try again.');
    document.getElementById('resultsCount').textContent = 'Places nearby';
    document.getElementById('placesList').innerHTML = '';
    currentPlaces = [];
    window.SafariTrakPageMap?.setFeatures([]);
  }
}

function initLocation() {
  if (!navigator.geolocation) {
    document.getElementById('resultsCount').textContent = 'Location unavailable';
    setStatus('This browser does not support location. Enable location in a supported browser to search nearby places.');
    return;
  }

  navigator.geolocation.getCurrentPosition(
    position => {
      userLocation = { lat: position.coords.latitude, lng: position.coords.longitude };
      window.SafariTrakPageMap?.setUserLocation(userLocation.lat, userLocation.lng, 12);
      performSearch('');
    },
    error => {
      document.getElementById('resultsCount').textContent = 'Location unavailable';
      setStatus(error.code === error.PERMISSION_DENIED
        ? 'Location permission was denied. Enable it to search near your current position.'
        : 'Your location is unavailable. Try again when GPS is ready.');
    },
    { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
  );
}

document.querySelectorAll('#placeTabs .tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('#placeTabs .tab').forEach(item => item.classList.remove('active'));
    tab.classList.add('active');
    activeCategory = tab.getAttribute('data-category');
    performSearch(document.getElementById('placeSearch').value.trim());
  });
});

document.getElementById('placeSearchBtn')?.addEventListener('click', () => {
  performSearch(document.getElementById('placeSearch').value.trim());
});

document.getElementById('placeSearch')?.addEventListener('keydown', event => {
  if (event.key === 'Enter') performSearch(event.target.value.trim());
});

document.addEventListener('safaritrak-map-ready', () => {
  if (currentPlaces.length) {
    window.SafariTrakPageMap?.setFeatures(placeMapFeatures(currentPlaces));
  }
  if (userLocation) {
    window.SafariTrakPageMap?.setUserLocation(userLocation.lat, userLocation.lng, 12);
  }
});

initLocation();
