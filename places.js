const categoryLabels = {
  home: 'Home',
  work: 'Work',
  hospital: 'Hospital',
  police: 'Police',
  fuel: 'Fuel station',
  hotel: 'Hotel',
  restaurant: 'Restaurant',
  car_wash: 'Car wash',
  pharmacy: 'Pharmacy',
  school: 'School',
  other: 'Other place'
};

let userLocation = null;
let activeCategory = 'all';
let currentPlaces = [];
let selectedPlace = null;
let customPlaceSelection = null;

const googlePlaceTypes = {
  hospital: ['hospital'],
  police: ['police'],
  fuel: ['gas_station'],
  hotel: ['lodging'],
  restaurant: ['restaurant'],
  car_wash: ['car_wash'],
  pharmacy: ['pharmacy'],
  school: ['school'],
};

function distanceKm(a, b) {
  const radians = value => value * Math.PI / 180;
  const dLat = radians(b.lat - a.lat);
  const dLng = radians(b.lng - a.lng);
  const value = Math.sin(dLat / 2) ** 2
    + Math.cos(radians(a.lat)) * Math.cos(radians(b.lat)) * Math.sin(dLng / 2) ** 2;
  return 6371 * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value));
}

async function loadSavedPlaces() {
  const list = document.getElementById('savedPlacesList');
  try {
    const response = await fetch('backend/api/places/saved.php', { credentials: 'same-origin', cache: 'no-store' });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Saved locations could not be loaded.');
    if (!data.locations.length) {
      list.replaceChildren(emptyNotice('No saved locations yet. Add a place to use it in trip planning.'));
      return;
    }
    list.replaceChildren(...data.locations.map(location => {
      const row = document.createElement('article');
      row.className = 'places-saved-card';
      const heading = document.createElement('div');
      heading.className = 'places-saved-card-heading';
      const icon = document.createElement('span');
      icon.className = 'places-saved-pin';
      icon.innerHTML = '<i class="fa-solid fa-location-dot" aria-hidden="true"></i>';
      const info = document.createElement('div');
      info.className = 'places-saved-info';
      const name = document.createElement('b');
      name.textContent = location.name;
      const category = document.createElement('small');
      category.className = 'places-saved-category';
      category.textContent = categoryLabels[location.category] || 'Other place';
      info.append(name, category);
      if (location.address) {
        const address = document.createElement('small');
        address.className = 'places-saved-address';
        address.textContent = location.address;
        info.append(address);
      }
      heading.append(icon, info);
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'places-remove-button';
      remove.setAttribute('aria-label', `Remove ${location.name} from saved locations`);
      remove.textContent = 'Remove';
      remove.addEventListener('click', () => deleteSavedPlace(location.id, row));
      heading.append(remove);
      const actions = document.createElement('div');
      actions.className = 'places-saved-actions';
      const view = document.createElement('button');
      view.type = 'button';
      view.className = 'places-secondary-button';
      view.innerHTML = '<i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>View on map';
      view.addEventListener('click', () => focusSavedPlace(location));
      const trip = document.createElement('a');
      trip.className = 'places-primary-button';
      trip.href = tripPlannerUrl(location);
      trip.innerHTML = '<i class="fa-solid fa-route" aria-hidden="true"></i>Start trip';
      actions.append(view, trip);
      row.append(heading);
      if (location.note) {
        const note = document.createElement('p');
        note.className = 'places-saved-note';
        note.textContent = location.note;
        row.append(note);
      }
      row.append(actions);
      return row;
    }));
  } catch (error) {
    console.error('Saved places could not be loaded.', error);
    list.replaceChildren(emptyNotice('Saved locations could not be loaded right now. Please try again.'));
  }
}

function emptyNotice(message) {
  const notice = document.createElement('p');
  notice.className = 'places-empty';
  notice.textContent = message;
  return notice;
}

function tripPlannerUrl(location) {
  const params = new URLSearchParams({
    destination: location.name,
    destination_lat: String(location.lat),
    destination_lng: String(location.lng),
  });
  if (location.id) params.set('saved_location_id', String(location.id));
  return `index.php?${params.toString()}`;
}

function focusSavedPlace(location) {
  const feature = {
    lat: Number(location.lat),
    lng: Number(location.lng),
    kind: location.category === 'home' || location.category === 'work' ? location.category : 'saved-place',
    label: location.name,
    description: location.address || categoryLabels[location.category] || 'Saved place',
  };
  window.SafariTrakPageMap?.focusFeatures([feature]);
}

async function deleteSavedPlace(id, row) {
  const feedback = document.getElementById('placeSaveStatus');
  try {
    const response = await fetch('backend/api/places/saved.php', {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id }),
    });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Saved location could not be removed.');
    row.remove();
    if (!document.querySelector('#savedPlacesList .places-saved-card')) {
      document.getElementById('savedPlacesList').replaceChildren(emptyNotice('No saved locations yet. Add a place to use it in trip planning.'));
    }
    feedback.textContent = '';
  } catch (error) {
    feedback.textContent = error.message || 'Saved location could not be removed.';
  }
}

const addPlaceModal = document.getElementById('addPlaceModal');
const customPlaceName = document.getElementById('customPlaceName');
const customPlaceSaveButton = document.getElementById('saveCustomPlaceButton');

function updateCustomPlaceSaveButton() {
  customPlaceSaveButton.disabled = !customPlaceSelection || !customPlaceName.value.trim();
}

function clearCustomPlaceSelection() {
  addPlaceModal.classList.remove('is-map-picking');
  document.getElementById('pickCustomPlaceOnMap').setAttribute('aria-pressed', 'false');
  document.getElementById('pickCustomPlaceOnMap').innerHTML = '<i class="fa-solid fa-map-pin" aria-hidden="true"></i><span>Pick on map</span>';
  customPlaceSelection = null;
  document.getElementById('customPlaceSelectionStatus').textContent = 'Choose a search result or pick a point on the map.';
  window.SafariTrakPageMap?.clearSearchLocation();
  window.SafariTrakPageMap?.setFeatures(placeMapFeatures(currentPlaces));
  updateCustomPlaceSaveButton();
}

function closeCustomPlaceFlow() {
  clearCustomPlaceSelection();
}

document.getElementById('addPlaceButton')?.addEventListener('click', () => {
  clearCustomPlaceSelection();
  document.getElementById('customPlaceTypeStep').hidden = false;
  document.getElementById('customPlaceLocationStep').hidden = true;
  document.getElementById('customPlaceType').value = '';
  document.getElementById('customPlaceTypeStatus').textContent = '';
  document.getElementById('customPlaceFormStatus').textContent = '';
  document.getElementById('customPlaceSearch').value = '';
  customPlaceName.value = '';
  delete customPlaceName.dataset.userEdited;
  document.getElementById('continueCustomPlace').hidden = false;
  document.getElementById('backCustomPlaceStep').hidden = true;
  customPlaceSaveButton.hidden = true;
});

document.getElementById('continueCustomPlace')?.addEventListener('click', () => {
  const category = document.getElementById('customPlaceType').value;
  if (!category) {
    document.getElementById('customPlaceTypeStatus').textContent = 'Choose a place type to continue.';
    document.getElementById('customPlaceType').focus();
    return;
  }
  document.getElementById('customPlaceTypeStatus').textContent = '';
  document.getElementById('customPlaceTypeStep').hidden = true;
  document.getElementById('customPlaceLocationStep').hidden = false;
  document.getElementById('continueCustomPlace').hidden = true;
  document.getElementById('backCustomPlaceStep').hidden = false;
  customPlaceSaveButton.hidden = false;
  document.getElementById('customPlaceSearch').focus();
});

document.getElementById('backCustomPlaceStep')?.addEventListener('click', () => {
  clearCustomPlaceSelection();
  document.getElementById('customPlaceLocationStep').hidden = true;
  document.getElementById('customPlaceTypeStep').hidden = false;
  document.getElementById('continueCustomPlace').hidden = false;
  document.getElementById('backCustomPlaceStep').hidden = true;
  customPlaceSaveButton.hidden = true;
});

customPlaceName.addEventListener('input', () => {
  customPlaceName.dataset.userEdited = 'true';
  updateCustomPlaceSaveButton();
});

document.getElementById('pickCustomPlaceOnMap')?.addEventListener('click', event => {
  const picking = !addPlaceModal.classList.contains('is-map-picking');
  addPlaceModal.classList.toggle('is-map-picking', picking);
  event.currentTarget.setAttribute('aria-pressed', String(picking));
  event.currentTarget.innerHTML = picking
    ? '<i class="fa-solid fa-xmark" aria-hidden="true"></i><span>Stop picking</span>'
    : '<i class="fa-solid fa-map-pin" aria-hidden="true"></i><span>Pick on map</span>';
  document.getElementById('customPlaceSelectionStatus').textContent = picking
    ? 'Click the map outside this dialog to choose a location.'
    : customPlaceSelection
      ? 'Location selected. You can save it or choose another.'
      : 'Choose a search result or pick a point on the map.';
});

document.getElementById('addPlaceModal').querySelectorAll('[data-close-modal]').forEach(button => {
  button.addEventListener('click', closeCustomPlaceFlow);
});
document.getElementById('addPlaceModal').addEventListener('click', event => {
  if (event.target === addPlaceModal) closeCustomPlaceFlow();
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && (customPlaceSelection || addPlaceModal.classList.contains('is-map-picking'))) {
    closeCustomPlaceFlow();
  }
});

document.getElementById('addPlaceModal').addEventListener('safaritrak-location-selected', event => {
  if (event.target.id !== 'customPlaceSearch') return;
  const { lat, lng, label } = event.detail;
  const selectedLabel = String(label || '').trim();
  customPlaceSelection = {
    lat: Number(lat),
    lng: Number(lng),
    address: selectedLabel.slice(0, 500),
    label: selectedLabel,
  };
  addPlaceModal.classList.remove('is-map-picking');
  const pickButton = document.getElementById('pickCustomPlaceOnMap');
  pickButton.setAttribute('aria-pressed', 'false');
  pickButton.innerHTML = '<i class="fa-solid fa-map-pin" aria-hidden="true"></i><span>Pick on map</span>';
  document.getElementById('customPlaceSelectionStatus').textContent = `Selected: ${customPlaceSelection.label}`;
  if (customPlaceName.dataset.userEdited !== 'true') customPlaceName.value = selectedLabel.slice(0, 160);
  document.getElementById('customPlaceFormStatus').textContent = '';
  window.SafariTrakPageMap?.showSearchLocation({
    lat: customPlaceSelection.lat,
    lng: customPlaceSelection.lng,
    label: customPlaceSelection.label,
  });
  updateCustomPlaceSaveButton();
});

document.addEventListener('safaritrak:places-map-click', event => {
  if (!addPlaceModal.classList.contains('open') || !addPlaceModal.classList.contains('is-map-picking')) return;
  const lat = Number(event.detail?.lat);
  const lng = Number(event.detail?.lng);
  if (!Number.isFinite(lat) || lat < -90 || lat > 90 || !Number.isFinite(lng) || lng < -180 || lng > 180) return;
  const category = document.getElementById('customPlaceType').value;
  const coordinates = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
  const label = `${categoryLabels[category] || 'Place'} · ${coordinates}`;
  customPlaceSelection = { lat, lng, address: '', label };
  if (customPlaceName.dataset.userEdited !== 'true') customPlaceName.value = label;
  addPlaceModal.classList.remove('is-map-picking');
  const pickButton = document.getElementById('pickCustomPlaceOnMap');
  pickButton.setAttribute('aria-pressed', 'false');
  pickButton.innerHTML = '<i class="fa-solid fa-map-pin" aria-hidden="true"></i><span>Pick on map</span>';
  document.getElementById('customPlaceSelectionStatus').textContent = `Map location selected: ${coordinates}`;
  document.getElementById('customPlaceFormStatus').textContent = '';
  window.SafariTrakPageMap?.clearSearchLocation();
  window.SafariTrakPageMap?.focusFeatures([
    ...placeMapFeatures(currentPlaces),
    { lat, lng, kind: 'saved-place', label: customPlaceName.value, description: categoryLabels[category] || 'Place' },
  ]);
  updateCustomPlaceSaveButton();
});

customPlaceSaveButton.addEventListener('click', async () => {
  const category = document.getElementById('customPlaceType').value;
  const name = customPlaceName.value.trim();
  const feedback = document.getElementById('customPlaceFormStatus');
  if (!customPlaceSelection || !name || !category) {
    feedback.textContent = 'Choose a place type, location, and name before saving.';
    return;
  }
  customPlaceSaveButton.disabled = true;
  feedback.textContent = 'Saving place…';
  try {
    const response = await fetch('backend/api/places/saved.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name,
        category,
        address: customPlaceSelection.address,
        note: '',
        lat: customPlaceSelection.lat,
        lng: customPlaceSelection.lng,
      }),
    });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Place could not be saved.');
    await loadSavedPlaces();
    document.getElementById('placeSaveStatus').textContent = 'Place saved for trip planning.';
    addPlaceModal.classList.remove('open');
    if (!document.querySelector('.modal-overlay.open')) document.body.classList.remove('modal-overlay-open');
    closeCustomPlaceFlow();
  } catch (error) {
    feedback.textContent = error.message || 'Place could not be saved.';
  } finally {
    updateCustomPlaceSaveButton();
  }
});

document.getElementById('savePlaceButton')?.addEventListener('click', async event => {
  const button = event.currentTarget;
  const feedback = document.getElementById('placeSaveModalStatus');
  if (!selectedPlace) return;
  button.disabled = true;
  feedback.textContent = 'Saving location…';
  try {
    const response = await fetch('backend/api/places/saved.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: selectedPlace.name,
        category: document.getElementById('placeSaveCategory').value,
        address: selectedPlace.address,
        note: document.getElementById('placeSaveNote').value.trim(),
        lat: Number(selectedPlace.lat),
        lng: Number(selectedPlace.lng),
      }),
    });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Location could not be saved.');
    feedback.textContent = 'Saved. You can now choose this location in Explore trip planning.';
    await loadSavedPlaces();
    document.getElementById('placeSaveStatus').textContent = 'Location saved.';
  } catch (error) {
    feedback.textContent = error.message || 'Location could not be saved.';
  } finally {
    button.disabled = false;
  }
});

document.addEventListener('safaritrak-location-selected', event => {
  if (event.target.id === 'customPlaceSearch') return;
  if (event.target.id !== 'placeSearch') return;
  userLocation = { lat: Number(event.detail.lat), lng: Number(event.detail.lng) };
  performSearch(event.detail.label);
});

function setRetryStatus() {
  const status = document.getElementById('placesStatus');
  if (!status) return;
  status.replaceChildren();
  status.hidden = false;
  status.classList.add('is-error');
  const message = document.createElement('span');
  message.textContent = 'No nearby stops loaded right now — try searching a town above or retry.';
  const retry = document.createElement('button');
  retry.type = 'button';
  retry.className = 'places-retry-button';
  retry.textContent = 'Retry';
  retry.addEventListener('click', () => performSearch(document.getElementById('placeSearch').value.trim()));
  status.append(message, retry);
}

function setMobilePlacesTab(showSaved) {
  document.body.classList.toggle('places-show-saved', showSaved);
  document.getElementById('showNearbyPlaces')?.setAttribute('aria-selected', String(!showSaved));
  document.getElementById('showSavedPlacesTab')?.setAttribute('aria-selected', String(showSaved));
  document.getElementById('showSavedPlaces')?.setAttribute('aria-expanded', String(showSaved));
  if (showSaved) loadSavedPlaces();
}

document.getElementById('showSavedPlaces')?.addEventListener('click', () => setMobilePlacesTab(true));
document.getElementById('showSavedPlacesTab')?.addEventListener('click', () => setMobilePlacesTab(true));
document.getElementById('showNearbyPlaces')?.addEventListener('click', () => setMobilePlacesTab(false));
document.getElementById('hideSavedPlaces')?.addEventListener('click', () => setMobilePlacesTab(false));

function setSearchStatus(text) {
  const status = document.getElementById('placesStatus');
  if (!status) return;
  status.replaceChildren();
  status.hidden = !text;
  status.classList.remove('is-error');
  if (text) status.textContent = text;
}

function setLoadingStatus(text) {
  const status = document.getElementById('placesStatus');
  if (!status) return;
  status.replaceChildren();
  status.hidden = false;
  status.classList.remove('is-error');
  status.textContent = text;
}

function setPlacesCount(text) {
  document.getElementById('resultsCount').textContent = text;
}

function createPlaceCard(place, index) {
  const card = document.createElement('article');
  card.className = 'places-result-card';
  const top = document.createElement('div');
  top.className = 'places-result-heading';
  const title = document.createElement('h3');
  const details = document.createElement('button');
  details.type = 'button';
  details.className = 'places-result-details';
  details.textContent = place.name;
  details.addEventListener('click', () => openPlaceModal(place));
  title.append(details);
  const distance = document.createElement('span');
  distance.className = 'places-result-distance';
  distance.textContent = `${place.distance_km} km`;
  top.append(title, distance);
  const category = document.createElement('p');
  category.className = 'places-result-category';
  category.textContent = categoryLabels[place.category] || 'Place';
  const add = document.createElement('a');
  add.className = 'places-add-button';
  add.href = tripPlannerUrl(place);
  add.innerHTML = '<i class="fa-solid fa-plus" aria-hidden="true"></i>Add to trip';
  card.append(top, category, add);
  return card;
}

function createNearbyEmpty(message) {
  const empty = document.createElement('p');
  empty.className = 'places-results-empty';
  empty.textContent = message;
  return empty;
}

function updateCategoryButtons() {
  document.querySelectorAll('#placeTabs .places-category').forEach(button => {
    const active = button.dataset.category === activeCategory;
    button.classList.toggle('is-active', active);
    button.setAttribute('aria-pressed', String(active));
  });
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
  selectedPlace = place;
  document.getElementById('placeDetailTitle').textContent = place.name;
  document.getElementById('placeDetailSubtitle').textContent =
    (categoryLabels[place.category] || 'Place') + ' · ' + place.distance_km + ' km away';
  document.getElementById('placeDetailAddress').textContent = `Address: ${place.address || 'Address unavailable'}`;
  document.getElementById('placeDetailHours').textContent = `Hours: ${place.hours || 'Hours unavailable'}`;

  const directions = document.getElementById('placeDetailDirections');
  directions.href = 'https://www.google.com/maps/dir/?api=1&destination=' + place.lat + ',' + place.lng;
  document.getElementById('placeSaveModalStatus').textContent = '';
  document.getElementById('placeSaveStatus').textContent = '';
  const category = document.getElementById('placeSaveCategory');
  const saveCategory = activeCategory === 'all' ? place.category : activeCategory;
  category.value = [...category.options].some(option => option.value === saveCategory)
    ? saveCategory
    : 'other';
  document.getElementById('placeSaveNote').value = '';

  document.getElementById('placeDetailModal').classList.add('open');
}

function renderPlaces(places) {
  currentPlaces = places;
  const list = document.getElementById('placesList');

  if (!places.length) {
    list.replaceChildren(createNearbyEmpty('No nearby stops found. Try another town or category.'));
    setPlacesCount('No stops found');
    setSearchStatus('');
    window.SafariTrakPageMap?.setFeatures([]);
    return;
  }

  setSearchStatus('');
  setPlacesCount(`${places.length} nearby ${places.length === 1 ? 'stop' : 'stops'}`);
  list.replaceChildren(...places.map(place => createPlaceCard(place)));
  window.SafariTrakPageMap?.focusFeatures(placeMapFeatures(places));
}

async function performSearch(searchText) {
  if (!userLocation && !searchText) {
    setSearchStatus('Allow location access to find nearby stops, or search for a town above.');
    return;
  }

  setLoadingStatus('Searching nearby…');
  setPlacesCount('Searching…');

  try {
    const maps = await window.SafariTrakGoogleMaps?.ready;
    const placesLibrary = await maps?.importLibrary('places');
    if (!placesLibrary?.Place) {
      throw new Error('Google Places is unavailable. Check that Places API (New) is enabled for this API key.');
    }
    const fields = ['displayName', 'location', 'formattedAddress', 'primaryType', 'regularOpeningHours'];
    const result = searchText
      ? await placesLibrary.Place.searchByText({
        textQuery: searchText,
        fields,
        ...(userLocation ? { locationBias: { center: userLocation, radius: 50_000 } } : {}),
        maxResultCount: 20,
      })
      : await placesLibrary.Place.searchNearby({
        locationRestriction: { center: userLocation, radius: 20_000 },
        maxResultCount: 20,
        fields,
        ...(activeCategory !== 'all' ? { includedTypes: googlePlaceTypes[activeCategory] } : {}),
      });
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
        distance_km: userLocation ? distanceKm(userLocation, point).toFixed(1) : '—',
      };
    }).filter(place => activeCategory === 'all' || place.category === activeCategory || searchText !== '');
    renderPlaces(places);
  } catch (error) {
    console.error('Google Places nearby search failed.', error);
    setRetryStatus();
    setPlacesCount('Nearby stops');
    document.getElementById('placesList').replaceChildren();
    currentPlaces = [];
    window.SafariTrakPageMap?.setFeatures([]);
  }
}

function initLocation() {
  if (!navigator.geolocation) {
    setPlacesCount('Location unavailable');
    setSearchStatus('This browser does not support location. Search for a town above to find nearby places.');
    return;
  }

  navigator.geolocation.getCurrentPosition(
    position => {
      userLocation = { lat: position.coords.latitude, lng: position.coords.longitude };
      window.SafariTrakPageMap?.setUserLocation(userLocation.lat, userLocation.lng, 12);
      performSearch('');
    },
    error => {
      setPlacesCount('Location unavailable');
      setSearchStatus(error.code === error.PERMISSION_DENIED
        ? 'Location permission is off. Search for a town above or allow location access to find nearby places.'
        : 'Your location is unavailable. Search for a town above or retry when GPS is ready.');
    },
    { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
  );
}

document.querySelectorAll('#placeTabs .places-category').forEach(tab => {
  tab.addEventListener('click', () => {
    activeCategory = tab.getAttribute('data-category');
    updateCategoryButtons();
    performSearch(document.getElementById('placeSearch').value.trim());
  });
});

document.getElementById('placesSearchForm')?.addEventListener('submit', event => {
  event.preventDefault();
  performSearch(document.getElementById('placeSearch').value.trim());
});

document.addEventListener('safaritrak-map-ready', () => {
  if (currentPlaces.length) {
    window.SafariTrakPageMap?.focusFeatures(placeMapFeatures(currentPlaces));
  }
  if (userLocation) {
    window.SafariTrakPageMap?.setUserLocation(userLocation.lat, userLocation.lng, 12);
  }
});

initLocation();
loadSavedPlaces();
