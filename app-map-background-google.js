(() => {
  const mapElement = document.getElementById('appMapBackground');
  if (!mapElement) return;

  let map = null;
  let userMarker = null;
  let searchMarker = null;
  let locationRequestStarted = false;
  let pendingUserLocation = null;
  let pendingSearchLocation = null;
  let features = [];
  let featureMarkers = [];
  let routeLines = [];
  let fallbackMessage = null;
  let mapView = null;
  let usesCloudMapStyle = false;

  function fallbackToMapLibre(message) {
    const fallback = window.SafariTrakMapFallback;
    if (!fallback) {
      showMapError(message);
      return;
    }
    if (!fallbackMessage) {
      fallbackMessage = document.createElement('div');
      fallbackMessage.className = 'app-map-background-status';
      fallbackMessage.setAttribute('role', 'status');
      document.body.append(fallbackMessage);
    }
    mapElement.replaceChildren();
    map = null;
    fallbackMessage.textContent = 'Google Maps is unavailable. Loading the backup map…';
    fallback.load(`app-map-background.js?v=${Date.now()}`)
      .then(() => {
        fallbackMessage.textContent = 'Backup map loaded. Your device location is shown only when you allow it.';
      })
      .catch(error => {
        showMapError(`${message} Backup map error: ${error.message}`);
      });
  }

  const validPoint = point => Number.isFinite(Number(point?.lat))
    && Number.isFinite(Number(point?.lng))
    && Number(point.lat) >= -90 && Number(point.lat) <= 90
    && Number(point.lng) >= -180 && Number(point.lng) <= 180;

  function showMapError(
    message = 'Google Maps could not be loaded. Check the API key, API restrictions, and internet connection.',
    details = message
  ) {
    if (fallbackMessage) return;
    fallbackMessage = document.createElement('div');
    fallbackMessage.className = 'app-map-background-status';
    fallbackMessage.setAttribute('role', 'alert');
    fallbackMessage.textContent = message;
    fallbackMessage.title = details;
    document.body.append(fallbackMessage);
  }

  const billingWarning = "This page can't load Google Maps correctly.";
  const billingMessage = 'Google Maps reports a configuration problem.';
  const billingDetails = 'Check the Cloud project billing account, Maps JavaScript API, browser-key restrictions, and map ID.';
  const billingObserver = new MutationObserver(() => {
    if (!mapElement.textContent.includes(billingWarning)) return;
    fallbackToMapLibre(`${billingMessage} ${billingDetails}`);
    billingObserver.disconnect();
  });
  billingObserver.observe(mapElement, { childList: true, subtree: true, characterData: true });
  if (mapElement.textContent.includes(billingWarning)) {
    fallbackToMapLibre(`${billingMessage} ${billingDetails}`);
    billingObserver.disconnect();
  }

  document.addEventListener('safaritrak-google-maps-error', event => {
    fallbackToMapLibre(event.detail?.message || 'Google Maps authentication failed. Check the API key and billing.');
  });

  function mapStyles(theme = document.documentElement.dataset.theme) {
    const light = theme === 'light';
    return [
      { elementType: 'geometry', stylers: [{ color: light ? '#F8FAFC' : '#0F172A' }] },
      { elementType: 'labels.text.fill', stylers: [{ color: light ? '#0F172A' : '#F8FAFC' }] },
      { elementType: 'labels.text.stroke', stylers: [{ color: light ? '#FFFFFF' : '#000000' }, { weight: 2 }] },
      { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: light ? '#CBD5E1' : '#1E293B' }] },
      { featureType: 'landscape', elementType: 'geometry', stylers: [{ color: light ? '#F8FAFC' : '#0F172A' }] },
      { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: light ? '#D1FAE5' : '#064E3B' }] },
      { featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: light ? '#0F172A' : '#F8FAFC' }] },
      { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: light ? '#C026D3' : '#D946EF' }] },
      { featureType: 'road.arterial', elementType: 'geometry', stylers: [{ color: light ? '#EA580C' : '#F97316' }] },
      { featureType: 'road.local', elementType: 'geometry', stylers: [{ color: light ? '#E2E8F0' : '#334155' }] },
      { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: light ? '#0F172A' : '#F8FAFC' }] },
      { featureType: 'transit', elementType: 'all', stylers: [{ visibility: 'off' }] },
      { featureType: 'water', elementType: 'geometry', stylers: [{ color: light ? '#E0F2FE' : '#050B24' }] },
      { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: light ? '#0F172A' : '#F8FAFC' }] },
    ];
  }

  function saveMapView() {
    if (!map) return;
    const mapType = map.getMapTypeId();
    window.SafariTrakMapView?.save(map, mapType === 'satellite' || mapType === 'hybrid' ? 'satellite' : 'flat');
  }

  function markerIcon(kind) {
    const colors = {
      user: '#13dfff',
      start: '#47f5cf',
      finish: '#ff4db8',
      place: '#55ddff',
      'saved-place': '#55ddff',
      home: '#55ddff',
      search: '#ffcf5c',
      journey: '#d538ff',
    };
    const color = colors[kind] || colors.journey;
    const scale = kind === 'user' ? 9 : 7;
    return {
      path: google.maps.SymbolPath.CIRCLE,
      fillColor: color,
      fillOpacity: 1,
      strokeColor: '#ffffff',
      strokeWeight: 2,
      scale,
    };
  }

  function clearFeatures() {
    featureMarkers.forEach(marker => marker.setMap(null));
    routeLines.forEach(line => line.setMap(null));
    featureMarkers = [];
    routeLines = [];
  }

  function addMarker(point, kind, label, description = '') {
    const marker = new google.maps.Marker({
      map,
      position: { lat: Number(point.lat), lng: Number(point.lng) },
      title: String(label || 'Map location'),
      icon: markerIcon(kind),
      label: label ? { text: String(label), className: `app-google-marker-label app-google-marker-label--${kind}` } : undefined,
      zIndex: kind === 'user' ? 1000 : undefined,
    });
    if (description) {
      const info = new google.maps.InfoWindow({ content: `<div class="app-google-info">${escapeHtml(description)}</div>` });
      marker.addListener('click', () => info.open({ map, anchor: marker }));
    }
    featureMarkers.push(marker);
    return marker;
  }

  function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = String(value);
    return element.innerHTML;
  }

  function renderFeatures() {
    if (!map) return;
    clearFeatures();
    for (const feature of features) {
      if (!validPoint(feature)) continue;
      const kind = ['start', 'finish', 'place', 'saved-place', 'home', 'journey'].includes(feature.kind)
        ? feature.kind
        : 'journey';
      addMarker(feature, kind, feature.label, feature.description);
      if (Array.isArray(feature.route) && feature.route.length > 1) {
        const path = feature.route
          .filter(point => Array.isArray(point) && point.length === 2 && point.every(value => Number.isFinite(Number(value))))
          .map(([lng, lat]) => ({ lat: Number(lat), lng: Number(lng) }));
        if (path.length > 1) {
          const plannedRoute = feature.routeColor === 'planned';
          routeLines.push(new google.maps.Polyline({
            map,
            path,
            geodesic: false,
            strokeColor: plannedRoute ? '#fff0f3' : '#f8eaff',
            strokeOpacity: 0.96,
            strokeWeight: plannedRoute ? 10 : 8,
            zIndex: 19,
          }));
          routeLines.push(new google.maps.Polyline({
            map,
            path,
            geodesic: false,
            strokeColor: plannedRoute ? '#C026D3' : '#D946EF',
            strokeOpacity: 1,
            strokeWeight: plannedRoute ? 6 : 5,
            zIndex: 21,
          }));
        }
      }
    }
  }

  function setFeatures(nextFeatures) {
    features = Array.isArray(nextFeatures) ? nextFeatures : [];
    if (map) renderFeatures();
  }

  function focusFeatures(nextFeatures) {
    setFeatures(nextFeatures);
    if (!map || !features.length) return;
    const bounds = new google.maps.LatLngBounds();
    let pointCount = 0;
    features.forEach(feature => {
      if (validPoint(feature)) {
        bounds.extend({ lat: Number(feature.lat), lng: Number(feature.lng) });
        pointCount += 1;
      }
      if (!Array.isArray(feature.route)) return;
      feature.route.forEach(point => {
        if (!Array.isArray(point) || point.length !== 2 || !point.every(value => Number.isFinite(Number(value)))) return;
        bounds.extend({ lat: Number(point[1]), lng: Number(point[0]) });
        pointCount += 1;
      });
    });
    if (pointCount > 0) map.fitBounds(bounds, 100);
  }

  function setUserLocation(lat, lng, zoom = 16, recenter = false) {
    const point = { lat: Number(lat), lng: Number(lng) };
    if (!validPoint(point)) throw new TypeError('A valid latitude and longitude are required.');
    pendingUserLocation = { ...point, zoom, recenter };
    if (!map) return;
    userMarker?.setMap(null);
    userMarker = window.SafariTrakGoogleLocationMarker.create(map, point, 'Your location');
    if (recenter) {
      map.panTo(point);
      map.setZoom(zoom);
      google.maps.event.addListenerOnce(map, 'idle', keepUserLocationVisible);
    } else {
      keepUserLocationVisible();
    }
  }

  function showSearchLocation(point) {
    if (!validPoint(point)) throw new TypeError('A valid latitude and longitude are required.');
    if (!map) {
      pendingSearchLocation = point;
      return;
    }
    pendingSearchLocation = null;
    searchMarker?.setMap(null);
    searchMarker = new google.maps.Marker({
      map,
      position: { lat: Number(point.lat), lng: Number(point.lng) },
      title: String(point.label || 'Searched location'),
      icon: markerIcon('search'),
      label: { text: String(point.label || 'Searched location'), className: 'app-google-marker-label app-google-marker-label--search' },
      zIndex: 900,
    });
    window.SafariTrakPageMap.searchMarker = searchMarker;
    map.panTo({ lat: Number(point.lat), lng: Number(point.lng) });
    map.setZoom(15);
  }

  function clearSearchLocation() {
    searchMarker?.setMap(null);
    searchMarker = null;
    pendingSearchLocation = null;
    if (window.SafariTrakPageMap) window.SafariTrakPageMap.searchMarker = null;
  }

  function keepUserLocationVisible() {
    if (!userMarker || !map || document.body.classList.contains('map-focus-mode')) return;
    const position = userMarker.getPosition();
    if (!position) return;
    const projection = map.getProjection();
    const bounds = map.getBounds();
    if (!projection || !bounds) return;
    if (!bounds.contains(position)) {
      map.panTo(position);
      return;
    }
    const markerPoint = projection.fromLatLngToPoint(position);
    const worldScale = Math.pow(2, map.getZoom());
    const markerX = markerPoint.x * worldScale;
    const markerY = markerPoint.y * worldScale;
    const mapRect = mapElement.getBoundingClientRect();
    const markerScreen = {
      x: mapRect.left + (mapRect.width / 2) + (markerX - projection.fromLatLngToPoint(map.getCenter()).x * worldScale),
      y: mapRect.top + (mapRect.height / 2) + (markerY - projection.fromLatLngToPoint(map.getCenter()).y * worldScale),
    };
    const panels = [...document.querySelectorAll(
      '.app--map-layout:not(.app--explore) .sidebar, .app--map-layout:not(.app--explore) > main > header, .app--map-layout:not(.app--explore) > main > .content > *'
    )].filter(panel => panel.getClientRects().length && getComputedStyle(panel).visibility !== 'hidden');
    const covered = panels.some(panel => {
      const rect = panel.getBoundingClientRect();
      return markerScreen.x >= rect.left && markerScreen.x <= rect.right
        && markerScreen.y >= rect.top && markerScreen.y <= rect.bottom;
    });
    if (!covered) return;
    const candidates = [
      [0.72, 0.34], [0.72, 0.68], [0.36, 0.34], [0.36, 0.68],
      [0.82, 0.5], [0.28, 0.5], [0.5, 0.25], [0.5, 0.75],
    ].map(([x, y]) => ({ x: mapRect.left + mapRect.width * x, y: mapRect.top + mapRect.height * y }));
    const target = candidates.find(candidate => !panels.some(panel => {
      const rect = panel.getBoundingClientRect();
      return candidate.x >= rect.left && candidate.x <= rect.right
        && candidate.y >= rect.top && candidate.y <= rect.bottom;
    }));
    if (target) map.panBy(markerScreen.x - target.x, markerScreen.y - target.y);
  }

  function requestUserLocation(recenter = true) {
    if (!navigator.geolocation) {
      showMapError('This browser does not support device location. Map content remains available.');
      return;
    }
    if (locationRequestStarted) return;
    locationRequestStarted = true;
    const button = document.getElementById('appMapLocate');
    button?.classList.add('is-loading');
    navigator.geolocation.getCurrentPosition(
      position => {
        button?.classList.remove('is-loading');
        window.SafariTrakMapView?.saveLocation(position);
        setUserLocation(position.coords.latitude, position.coords.longitude, 16, recenter);
        button?.setAttribute('aria-label', 'Your current location is shown on the map');
      },
      error => {
        locationRequestStarted = false;
        button?.classList.remove('is-loading');
        showMapError(error.code === error.PERMISSION_DENIED
          ? 'Location permission was denied. Enable it in your browser to show your position.'
          : 'Your location could not be determined. Try again when GPS is available.');
      },
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
    );
  }

  function initializeMap() {
    mapView = window.SafariTrakMapView?.read() || null;
    const cachedLocation = window.SafariTrakMapView?.readLocation() || null;
    const initialPoint = cachedLocation || mapView || { lat: -1.286389, lng: 36.817223 };
    const mapId = document.querySelector('meta[name="google-map-id"]')?.content.trim();
    usesCloudMapStyle = Boolean(mapId);
    const mapOptions = {
      center: { lat: initialPoint.lat, lng: initialPoint.lng },
      zoom: cachedLocation ? 16 : mapView?.zoom ?? 15,
      mapTypeId: mapView?.projection === 'satellite' ? 'hybrid' : 'roadmap',
      tilt: 0,
      ...(mapId ? { mapId } : { styles: mapStyles() }),
      disableDefaultUI: true,
      clickableIcons: true,
      gestureHandling: 'greedy',
      scrollwheel: false,
      disableDoubleClickZoom: true,
      keyboardShortcuts: false,
      streetViewControl: false,
      mapTypeControl: false,
      fullscreenControl: false,
    };
    map = new google.maps.Map(mapElement, mapOptions);
    mapElement.classList.add('is-ready');
    map.addListener('idle', saveMapView);
    map.addListener('idle', keepUserLocationVisible);
    map.addListener('click', () => {
      if (!document.querySelector('.app--map-layout:not(.app--explore)')) return;
      setMapFocus(true);
    });
    const featureData = document.getElementById('safariTrakMapFeatures');
    if (featureData && !document.querySelector('.app--journeys')) {
      try {
        features = JSON.parse(featureData.textContent || '[]');
        renderFeatures();
      } catch (error) {
        console.error('SafariTrak map feature data is invalid.', error);
        showMapError('Map locations could not be loaded. Other page functions remain available.');
      }
    }
    if (cachedLocation) setUserLocation(cachedLocation.lat, cachedLocation.lng);
    if (pendingUserLocation) {
      setUserLocation(pendingUserLocation.lat, pendingUserLocation.lng, pendingUserLocation.zoom, pendingUserLocation.recenter);
    }
    if (pendingSearchLocation) showSearchLocation(pendingSearchLocation);
    if (!pendingSearchLocation && !pendingUserLocation && !document.querySelector('.app--journeys')) {
      requestUserLocation(true);
    }
    document.dispatchEvent(new Event('safaritrak-map-ready'));
  }

  const focusButton = document.getElementById('appMapFocus');
  const setMapFocus = focused => {
    document.body.classList.toggle('map-focus-mode', focused);
    focusButton?.setAttribute('aria-pressed', String(focused));
    if (focusButton) {
      focusButton.innerHTML = focused
        ? '<i class="fa-solid fa-compress" aria-hidden="true"></i><span>Show panels</span>'
        : '<i class="fa-solid fa-expand" aria-hidden="true"></i><span>Focus map</span>';
      focusButton.setAttribute('aria-label', focused ? 'Show page panels' : 'Focus on the map');
    }
  };
  focusButton?.addEventListener('click', () => setMapFocus(!document.body.classList.contains('map-focus-mode')));
  mapElement.addEventListener('pointerdown', event => {
    if (event.target.closest('.gm-control-active, button')) return;
    setMapFocus(true);
  });
  document.getElementById('appMapLocate')?.addEventListener('click', () => requestUserLocation(true));
  const themeObserver = new MutationObserver(() => {
    if (map && !usesCloudMapStyle) map.setOptions({ styles: mapStyles() });
  });
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
  window.SafariTrakPageMap = {
    setFeatures,
    focusFeatures,
    setUserLocation,
    showSearchLocation,
    clearSearchLocation,
    searchMarker: null,
  };

  window.SafariTrakGoogleMaps?.ready
    .then(() => {
      initializeMap();
    })
    .catch(error => {
      console.error('SafariTrak Google Maps initialization failed.', error);
      fallbackToMapLibre(error.message);
    });
})();
