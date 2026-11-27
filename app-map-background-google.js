(() => {
  const mapElement = document.getElementById('appMapBackground');
  if (!mapElement) return;

  const isJourneyPage = mapElement.dataset.mapPage === 'journeys';
  const isGroupsPage = mapElement.dataset.mapPage === 'groups';
  const isMessagesPage = mapElement.dataset.mapPage === 'messages';
  const isCarHirePage = mapElement.dataset.mapPage === 'car-hire';
  let map = null;
  let userMarker = null;
  let searchMarker = null;
  let locationRequestStarted = false;
  let pendingUserLocation = null;
  let pendingSearchLocation = null;
  let features = [];
  let journeyFeatures = [];
  let featureMarkers = [];
  let routeLines = [];
  let fallbackMessage = null;
  let usesCloudMapStyle = false;
  const journeyFeaturesStorageKey = `safaritrak-selected-journey-${mapElement.dataset.mapUserId}`;
  try {
    const savedJourneyFeatures = sessionStorage.getItem(journeyFeaturesStorageKey);
    if (savedJourneyFeatures) {
      const parsedFeatures = JSON.parse(savedJourneyFeatures);
      if (Array.isArray(parsedFeatures)) journeyFeatures = parsedFeatures;
    }
  } catch (error) {
    console.warn('SafariTrak could not restore the selected journey route.', error);
  }

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
    const color = (name, fallback) =>
      getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
    return [
      { elementType: 'geometry', stylers: [{ color: color('--map-background', light ? '#F4F1EB' : '#050817') }] },
      { elementType: 'labels.text.fill', stylers: [{ color: color('--map-label', light ? '#45505A' : '#E8E8FF') }] },
      { elementType: 'labels.text.stroke', stylers: [{ color: color('--map-label-halo', light ? '#F4F1EB' : '#050817') }, { weight: 2 }] },
      { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: color('--map-boundary', light ? '#817B99' : '#7654C9') }] },
      { featureType: 'landscape', elementType: 'geometry', stylers: [{ color: color('--map-land', light ? '#E8E4DA' : '#050817') }] },
      { featureType: 'landscape.man_made', elementType: 'geometry', stylers: [{ color: color('--map-urban', light ? '#D6D1C8' : '#10152D') }] },
      { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: color('--map-park', light ? '#AFC9B2' : '#087A50') }] },
      { featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: color('--map-label', light ? '#45505A' : '#E8E8FF') }] },
      { featureType: 'road', elementType: 'geometry', stylers: [{ color: color('--map-road-secondary', light ? '#7D35B5' : '#B14DFF') }] },
      { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: color('--map-road-highway', light ? '#A76233' : '#C97835') }] },
      { featureType: 'road.arterial', elementType: 'geometry', stylers: [{ color: color('--map-road-major', light ? '#D97520' : '#FF8A24') }] },
      { featureType: 'road.local', elementType: 'geometry', stylers: [{ color: color('--map-road-residential', light ? '#6D917D' : '#82AD98') }] },
      { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: color('--map-label', light ? '#45505A' : '#E8E8FF') }] },
      { featureType: 'transit', elementType: 'all', stylers: [{ visibility: 'off' }] },
      { featureType: 'water', elementType: 'geometry', stylers: [{ color: color('--map-ocean', light ? '#AFCADB' : '#082D50') }] },
      { featureType: 'water', elementType: 'geometry.stroke', stylers: [{ color: color('--map-waterway', light ? '#6EA7B7' : '#00D9FF') }, { weight: 1 }] },
      { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: color('--map-label-water', light ? '#376C7A' : '#E8E8FF') }] },
    ];
  }

  function saveMapView() {
    if (!map) return;
    const mapType = map.getMapTypeId();
    window.SafariTrakMapView?.save(map, mapType === 'satellite' || mapType === 'hybrid' ? 'satellite' : 'flat');
  }

  function markerIcon(kind, feature) {
    const trackingColors = window.SafariTrakMapStyle.getTrackingColors();
    const routeColors = window.SafariTrakMapStyle.getRouteColors();
    const colors = {
      user: trackingColors.user,
      start: trackingColors.start,
      finish: trackingColors.destination,
      place: trackingColors.destination,
      'saved-place': trackingColors.destination,
      home: trackingColors.start,
      search: cssColor('--accent-yellow', '#FFD600'),
      journey: trackingColors.groupMembers[0],
      'shared-location': trackingColors.groupMembers[0],
      rental: trackingColors.destination,
    };
    const color = feature?.routeStroke
      || (feature?.routeColor === 'completed'
        ? routeColors.completedStroke
        : feature?.routeColor === 'planned' ? routeColors.stroke : colors[kind] || colors.journey);
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

  function cssColor(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
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
      icon: markerIcon(kind, point),
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

  function getPolylineColor(color, opacity = 1) {
    const components = color.match(/^rgba?\(([^)]+)\)$/i)?.[1]?.split(',').map(value => value.trim());
    if (!components || components.length < 3) return { color, opacity };
    const parsedOpacity = components.length > 3 ? Number(components[3]) : opacity;
    return {
      color: `rgb(${components.slice(0, 3).join(', ')})`,
      opacity: Number.isFinite(parsedOpacity) ? parsedOpacity : opacity,
    };
  }

  function renderFeatures() {
    if (!map) return;
    clearFeatures();
    for (const feature of [...features, ...journeyFeatures]) {
      if (!validPoint(feature)) continue;
      const kind = ['start', 'finish', 'place', 'saved-place', 'home', 'journey', 'shared-location', 'rental'].includes(feature.kind)
        ? feature.kind
        : 'journey';
      addMarker(feature, kind, feature.label, feature.description);
      if (kind === 'rental') {
        const marker = featureMarkers[featureMarkers.length - 1];
        marker.addListener('click', () => {
          window.dispatchEvent(new CustomEvent('safaritrak:rental-feature-click', {
            detail: { vehicleId: Number(feature.vehicleId) },
          }));
        });
      }
      if (Array.isArray(feature.route) && feature.route.length > 1) {
        const path = feature.route
          .filter(point => Array.isArray(point) && point.length === 2 && point.every(value => Number.isFinite(Number(value))))
          .map(([lng, lat]) => ({ lat: Number(lat), lng: Number(lng) }));
        if (path.length > 1) {
          const isPlannedRoute = feature.routeColor === 'planned';
          const isCompletedRoute = feature.routeColor === 'completed';
          const routeColors = window.SafariTrakMapStyle.getRouteColors();
          const routeStroke = isCompletedRoute
            ? routeColors.completedStroke
            : isPlannedRoute
              ? routeColors.stroke
              : feature.routeStroke || routeColors.stroke;
          const routeGlow = isCompletedRoute
            ? routeColors.completedGlow
            : isPlannedRoute
              ? routeColors.glow
              : feature.routeGlow || routeColors.glow;
          const glowColor = getPolylineColor(routeGlow, 0.22);
          const strokeColor = getPolylineColor(routeStroke);
          const approximateDash = feature.routeApproximate
            ? [{
              icon: {
                path: 'M 0,-1 0,1',
                strokeColor: strokeColor.color || routeColors.stroke,
                strokeOpacity: 0.9,
                scale: 3,
              },
              offset: '0',
              repeat: '14px',
            }]
            : undefined;
          routeLines.push(new google.maps.Polyline({
            map,
            path,
            geodesic: false,
            strokeColor: glowColor.color,
            strokeOpacity: feature.routeApproximate ? 0.12 : Math.min(glowColor.opacity, 0.28),
            strokeWeight: 8,
            zIndex: 21,
          }));
          routeLines.push(new google.maps.Polyline({
            map,
            path,
            geodesic: false,
            strokeColor: strokeColor.color || routeColors.stroke,
            strokeOpacity: feature.routeApproximate ? 0 : strokeColor.opacity,
            strokeWeight: isPlannedRoute ? 4 : 5,
            zIndex: 22,
            ...(approximateDash ? { icons: approximateDash } : {}),
          }));
        }
      }
    }
  }

  function setFeatures(nextFeatures) {
    features = Array.isArray(nextFeatures) ? nextFeatures : [];
    if (map) renderFeatures();
  }

  function journeyFitPadding() {
    const canvasRect = map.getDiv().getBoundingClientRect();
    const isMobile = window.matchMedia('(max-width: 800px)').matches;
    const journeyPanels = [...document.querySelectorAll(
      '.app--journeys .journey-overview-panel, .app--journeys .trip-carousel-panel'
    )].filter(panel => {
      const style = getComputedStyle(panel);
      return panel.getClientRects().length && style.display !== 'none' && style.visibility !== 'hidden';
    });

    if (isMobile) {
      let top = 88;
      let bottom = 80;
      const verticalCenter = canvasRect.top + canvasRect.height / 2;
      journeyPanels.forEach(panel => {
        const rect = panel.getBoundingClientRect();
        const spansCanvas = rect.left <= canvasRect.left + 20 && rect.right >= canvasRect.right - 20;
        if (!spansCanvas) return;
        if (rect.top <= verticalCenter) {
          top = Math.max(top, Math.ceil(rect.bottom - canvasRect.top + 16));
        } else {
          bottom = Math.max(bottom, Math.ceil(canvasRect.bottom - rect.top + 16));
        }
      });

      const maxTotalPadding = Math.max(0, canvasRect.height - 120);
      if (top + bottom > maxTotalPadding) {
        const scale = maxTotalPadding / (top + bottom);
        top = Math.floor(top * scale);
        bottom = Math.max(0, maxTotalPadding - top);
      }
      return { top, right: 18, bottom, left: 18 };
    }

    return {
      left: 480,
      right: 60,
      top: 100,
      bottom: 60,
    };
  }

  function fitFeatures(targetFeatures) {
    if (!map || !targetFeatures.length) return;
    const routeFeature = targetFeatures.find(feature => Array.isArray(feature.route) && feature.route.length > 1);
    if (routeFeature && window.SafariTrakRouteDirections?.focusMobileGoogleRoute(
      map,
      routeFeature.route,
      targetFeatures.find(feature => feature.kind === 'start') || routeFeature.route[0]
    )) return;

    const bounds = new google.maps.LatLngBounds();
    let pointCount = 0;
    targetFeatures.forEach(feature => {
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
    if (pointCount > 0) {
      const padding = isGroupsPage || isMessagesPage
        ? window.matchMedia('(max-width: 1100px)').matches
          ? { top: 100, right: 24, bottom: Math.min(360, Math.round(mapElement.clientHeight * 0.5)), left: 24 }
          : { top: 100, right: 380, bottom: 60, left: 360 }
        : isJourneyPage ? journeyFitPadding()
        : isCarHirePage ? window.matchMedia('(max-width: 800px)').matches
          ? { top: 100, right: 24, bottom: 230, left: 24 }
          : { left: 460, right: 360, top: 100, bottom: 60 }
        : 100;
      map.fitBounds(bounds, padding);
    }
  }

  function focusFeatures(nextFeatures) {
    setFeatures(nextFeatures);
    fitFeatures(features);
  }

  function setJourneyFeatures(nextFeatures, { focus = false } = {}) {
    journeyFeatures = Array.isArray(nextFeatures) ? nextFeatures : [];
    try {
      if (journeyFeatures.length) {
        sessionStorage.setItem(journeyFeaturesStorageKey, JSON.stringify(journeyFeatures));
      } else {
        sessionStorage.removeItem(journeyFeaturesStorageKey);
      }
    } catch (error) {
      console.warn('SafariTrak could not save the selected journey route for other pages.', error);
    }
    if (map) renderFeatures();
    if (focus) fitFeatures(journeyFeatures);
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
    if (isGroupsPage) {
      const verticalPadding = Math.min(360, Math.round(mapElement.clientHeight * 0.5));
      const offsetX = window.matchMedia('(max-width: 1100px)').matches ? 0 : -10;
      const offsetY = window.matchMedia('(max-width: 1100px)').matches ? (100 - verticalPadding) / 2 : 20;
      google.maps.event.addListenerOnce(map, 'idle', () => map.panBy(-offsetX, -offsetY));
    }
  }

  function clearSearchLocation() {
    searchMarker?.setMap(null);
    searchMarker = null;
    pendingSearchLocation = null;
    if (window.SafariTrakPageMap) window.SafariTrakPageMap.searchMarker = null;
  }

  function keepUserLocationVisible() {
    if (!userMarker || !map || document.body.classList.contains('map-focus-mode')
      || (isJourneyPage && journeyFeatures.length)) return;
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
    const panels = [...document.querySelectorAll(isGroupsPage
      ? '.app--groups .groups-list-panel, .app--groups .groups-chat-panel, .app--groups .group-hub-tabs'
      : isMessagesPage
      ? '.app--messages .messages-conversations, .app--messages .messages-thread'
      : '.app--map-layout:not(.app--explore) .sidebar, .app--map-layout:not(.app--explore) > main > header, .app--map-layout:not(.app--explore) > main > .content > *'
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
    const mapId = document.querySelector('meta[name="google-map-id"]')?.content.trim();
    usesCloudMapStyle = Boolean(mapId);
    const mapOptions = {
      center: { lat: -1.286389, lng: 36.817223 },
      zoom: 15,
      mapTypeId: 'roadmap',
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
      if (isGroupsPage || isMessagesPage) return;
      if (!document.querySelector('.app--map-layout:not(.app--explore)')) return;
      setMapFocus(true);
    });
    const featureData = document.getElementById('safariTrakMapFeatures');
    if (featureData && !isJourneyPage) {
      try {
        features = JSON.parse(featureData.textContent || '[]');
        renderFeatures();
      } catch (error) {
        console.error('SafariTrak map feature data is invalid.', error);
        showMapError('Map locations could not be loaded. Other page functions remain available.');
      }
    }
    if (pendingUserLocation) {
      setUserLocation(pendingUserLocation.lat, pendingUserLocation.lng, pendingUserLocation.zoom, pendingUserLocation.recenter);
    }
    if (pendingSearchLocation) showSearchLocation(pendingSearchLocation);
    if (journeyFeatures.length) fitFeatures(journeyFeatures);
    document.dispatchEvent(new Event('safaritrak-map-ready'));
    requestUserLocation(true);
  }

  const focusButton = document.getElementById('appMapFocus');
  const setMapFocus = focused => {
    document.body.classList.toggle('map-focus-mode', focused);
    focusButton?.setAttribute('aria-pressed', String(focused));
    if (focusButton) {
      focusButton.innerHTML = focused
        ? '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Back</span>'
        : '<i class="fa-solid fa-expand" aria-hidden="true"></i><span>Focus map</span>';
      focusButton.setAttribute('aria-label', focused ? 'Back' : 'Focus on the map');
    }
  };
  focusButton?.addEventListener('click', () => setMapFocus(!document.body.classList.contains('map-focus-mode')));
  if (!isJourneyPage && !isGroupsPage && !isMessagesPage) {
    mapElement.addEventListener('pointerdown', event => {
      if (event.target.closest('.gm-control-active, button')) return;
      setMapFocus(true);
    });
  }
  document.getElementById('appMapLocate')?.addEventListener('click', () => requestUserLocation(true));
  const themeObserver = new MutationObserver(() => {
    if (!map) return;
    if (!usesCloudMapStyle) map.setOptions({ styles: mapStyles() });
    renderFeatures();
  });
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
  window.SafariTrakPageMap = {
    setFeatures,
    focusFeatures,
    setJourneyFeatures,
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
