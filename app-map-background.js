(() => {
  const mapElement = document.getElementById('appMapBackground');
  if (!mapElement) return;

  const isJourneyPage = mapElement.dataset.mapPage === 'journeys';
  const isGroupsPage = mapElement.dataset.mapPage === 'groups';
  const isMessagesPage = mapElement.dataset.mapPage === 'messages';
  const isCarHirePage = mapElement.dataset.mapPage === 'car-hire';
  const isPlacesPage = mapElement.dataset.mapPage === 'places';
  const isProfilePage = mapElement.dataset.mapPage === 'profile';
  const isSchoolPage = mapElement.dataset.mapPage === 'school';
  const journeyFeaturesStorageKey = `safaritrak-selected-journey-${mapElement.dataset.mapUserId}`;
  let map;
  let fallbackMessage;
  let userMarker;
  let pendingUserLocation;
  let featureMarkers = [];
  let features = [];
  let locationRequestStarted = false;
  let startupLocationRequested = false;
  let pendingSearchLocation;
  let searchMarker;
  let pendingFeatureFit = false;
  let pendingFeatureRender = false;
  let mapLoadStarted = false;
  let journeyFeatures = [];
  let sharedLocationFeatures = [];

  try {
    const savedJourneyFeatures = sessionStorage.getItem(journeyFeaturesStorageKey);
    if (savedJourneyFeatures) {
      const parsedFeatures = JSON.parse(savedJourneyFeatures);
      if (Array.isArray(parsedFeatures)) journeyFeatures = parsedFeatures;
    }
  } catch (error) {
    console.warn('SafariTrak could not restore the selected journey route.', error);
  }
  pendingFeatureFit = journeyFeatures.length > 0;

  function activateMap() {
    document.body.classList.add('map-background-visible');
    if (mapLoadStarted) return;
    mapLoadStarted = true;
    loadMapLibre();
  }

  function showMapError(message = 'Map imagery is unavailable. SafariTrak remains usable; check your internet connection.') {
    if (fallbackMessage) return;
    fallbackMessage = document.createElement('div');
    fallbackMessage.className = 'app-map-background-status';
    fallbackMessage.setAttribute('role', 'status');
    fallbackMessage.textContent = message;
    document.body.append(fallbackMessage);
  }

  function mapStyle() {
    return window.SafariTrakMapStyle.styleUrl;
  }

  function applyMapStyle() {
    window.SafariTrakMapStyle.apply(map);
  }

  function validPoint(point) {
    const lat = Number(point?.lat);
    const lng = Number(point?.lng);
    return Number.isFinite(lat) && Number.isFinite(lng)
      && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
  }

  function removeFeatures() {
    featureMarkers.forEach(marker => marker.remove());
    featureMarkers = [];
    if (!map) return;
    for (const layerId of (map.getStyle()?.layers || []).map(layer => layer.id)) {
      if (layerId.startsWith('safaritrak-page-route-')) map.removeLayer(layerId);
    }
    const sources = map.getStyle()?.sources || {};
    for (const sourceId of Object.keys(sources)) {
      if (sourceId.startsWith('safaritrak-page-route-')) map.removeSource(sourceId);
    }
  }

  function renderFeatures() {
    if (!map?.isStyleLoaded()) {
      pendingFeatureRender = true;
      return;
    }
    pendingFeatureRender = false;
    removeFeatures();
    const renderedFeatures = [...features, ...journeyFeatures, ...sharedLocationFeatures];
    let sharedMemberIndex = 0;
    for (const feature of renderedFeatures) {
      if (!validPoint(feature)) continue;
      const kind = ['start', 'finish', 'place', 'saved-place', 'home', 'journey', 'shared-location', 'rental', 'school', 'bus', 'student', 'dropoff'].includes(feature.kind)
        ? feature.kind
        : 'journey';
      const markerElement = document.createElement('button');
      markerElement.type = 'button';
      markerElement.className = `app-map-marker app-map-marker--${kind}`;
      markerElement.setAttribute('aria-label', String(feature.label || 'Map location'));
      if (feature.routeStroke) {
        markerElement.style.setProperty('--marker-color', feature.routeStroke);
      } else if (feature.routeColor) {
        const routeColors = window.SafariTrakMapStyle.getRouteColors();
        markerElement.style.setProperty(
          '--marker-color',
          feature.routeColor === 'completed' ? routeColors.completedStroke : routeColors.stroke
        );
      } else if (kind === 'shared-location' || kind === 'journey') {
        const groupColors = window.SafariTrakMapStyle.getTrackingColors().groupMembers;
        markerElement.style.setProperty(
          '--marker-color',
          groupColors[sharedMemberIndex++ % groupColors.length]
        );
      }
      const iconClasses = {
        school: 'fa-solid fa-graduation-cap',
        bus: 'fa-solid fa-bus',
        student: 'fa-solid fa-child-reaching',
        dropoff: 'fa-solid fa-house',
      };
      if (iconClasses[kind]) {
        const icon = document.createElement('i');
        icon.className = iconClasses[kind];
        icon.setAttribute('aria-hidden', 'true');
        markerElement.append(icon);
      } else {
        const dot = document.createElement('span');
        dot.className = 'app-map-marker-dot';
        markerElement.append(dot);
      }
      if (feature.label) {
        const label = document.createElement('span');
        label.className = 'app-map-marker-label';
        label.textContent = String(feature.label);
        markerElement.append(label);
      }
      const marker = new maplibregl.Marker({ element: markerElement, anchor: 'bottom' })
        .setLngLat([Number(feature.lng), Number(feature.lat)])
        .addTo(map);
      if (feature.description) {
        markerElement.addEventListener('click', () => {
          if (kind === 'rental') {
            window.dispatchEvent(new CustomEvent('safaritrak:rental-feature-click', {
              detail: { vehicleId: Number(feature.vehicleId) },
            }));
            return;
          }
          new maplibregl.Popup({ closeButton: true, offset: 18 })
            .setLngLat([Number(feature.lng), Number(feature.lat)])
            .setText(String(feature.description))
            .addTo(map);
        });
      }
      featureMarkers.push(marker);
    }

    renderedFeatures.forEach((feature, index) => {
      if (!Array.isArray(feature.route) || feature.route.length < 2) return;
      const validCoordinates = feature.route.filter(point => Array.isArray(point) && point.length === 2
        && Number.isFinite(Number(point[0])) && Number.isFinite(Number(point[1])));
      if (validCoordinates.length > 1) {
        const suffix = String(index);
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
        map.addSource(`safaritrak-page-route-${suffix}`, {
          type: 'geojson',
          data: {
            type: 'Feature',
            properties: {},
            geometry: { type: 'LineString', coordinates: validCoordinates },
          },
        });
        if (!isPlannedRoute) {
          map.addLayer({
            id: `safaritrak-page-route-casing-${suffix}`,
            type: 'line',
            source: `safaritrak-page-route-${suffix}`,
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
              'line-color': feature.routeCasing || routeColors.casing,
                'line-width': 8,
                'line-opacity': 0.9,
            },
          });
        }
        map.addLayer({
          id: `safaritrak-page-route-glow-${suffix}`,
          type: 'line',
          source: `safaritrak-page-route-${suffix}`,
          layout: { 'line-cap': 'round', 'line-join': 'round' },
          paint: {
            'line-color': routeGlow,
            'line-width': 8,
            'line-opacity': isPlannedRoute ? 0.28 : 0.22,
            'line-blur': 2,
            ...(feature.routeApproximate ? { 'line-dasharray': [2, 2] } : {}),
          },
        });
        map.addLayer({
          id: `safaritrak-page-route-line-${suffix}`,
          type: 'line',
          source: `safaritrak-page-route-${suffix}`,
          layout: { 'line-cap': 'round', 'line-join': 'round' },
          paint: {
            'line-color': routeStroke,
            'line-width': isPlannedRoute ? 4 : 5,
            'line-opacity': 1,
            ...(feature.routeApproximate ? { 'line-dasharray': [2, 2] } : {}),
          },
        });
      }
    });

  }

  function setFeatures(nextFeatures) {
    features = Array.isArray(nextFeatures) ? nextFeatures : [];
    if (features.length || journeyFeatures.length) activateMap();
    renderFeatures();
  }

  function journeyFitPadding() {
    const canvasRect = map.getCanvas().getBoundingClientRect();
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

  function fitFeatureBounds(targetFeatures = [...features, ...journeyFeatures, ...sharedLocationFeatures]) {
    if (!map?.isStyleLoaded() || !targetFeatures.length) return;
    if (isJourneyPage) {
      map.setProjection({ type: 'mercator' });
    }
    const routeFeature = targetFeatures.find(feature => Array.isArray(feature.route) && feature.route.length > 1);
    if (routeFeature) {
      const routeCoordinates = routeFeature.route.filter(point => Array.isArray(point) && point.length === 2)
        .map(point => point.map(Number));
      const routeStart = validPoint(routeFeature)
        ? [Number(routeFeature.lng), Number(routeFeature.lat)]
        : routeCoordinates[0];
      if (window.SafariTrakRouteDirections?.focusMobileRoute(map, routeCoordinates, routeStart)) return;
    }
    const coordinates = [];
    targetFeatures.forEach(feature => {
      if (validPoint(feature)) coordinates.push([Number(feature.lng), Number(feature.lat)]);
      if (Array.isArray(feature.route)) {
        feature.route.forEach(point => {
          if (Array.isArray(point) && point.length === 2
            && Number.isFinite(Number(point[0])) && Number.isFinite(Number(point[1]))) {
            coordinates.push([Number(point[0]), Number(point[1])]);
          }
        });
      }
    });
    if (!coordinates.length) return;

    const longitudes = coordinates.map(([lng]) => lng);
    const latitudes = coordinates.map(([, lat]) => lat);
    const workspacePadding = window.matchMedia('(max-width: 1100px)').matches
      ? { top: 100, right: 24, bottom: Math.min(360, Math.round(map.getCanvas().clientHeight * 0.5)), left: 24 }
      : { left: 360, right: 380, top: 100, bottom: 60 };
    map.fitBounds([
      [Math.min(...longitudes), Math.min(...latitudes)],
      [Math.max(...longitudes), Math.max(...latitudes)],
    ], {
      padding: isSchoolPage
        ? window.matchMedia('(max-width: 800px)').matches
          ? { top: 100, right: 24, bottom: 250, left: 24 }
          : { left: 380, right: 380, top: 100, bottom: 60 }
        : isGroupsPage || isMessagesPage
        ? workspacePadding
        : isJourneyPage
        ? journeyFitPadding()
        : isCarHirePage
          ? window.matchMedia('(max-width: 800px)').matches
            ? { top: 100, right: 24, bottom: 230, left: 24 }
            : { left: 460, right: 360, top: 100, bottom: 60 }
        : isPlacesPage
          ? window.matchMedia('(max-width: 800px)').matches
            ? { top: 100, right: 24, bottom: 220, left: 24 }
            : window.matchMedia('(max-width: 1100px)').matches
              ? { left: 320, right: 300, top: 100, bottom: 60 }
              : { left: 420, right: 360, top: 100, bottom: 60 }
        : window.matchMedia('(max-width: 800px)').matches
          ? { top: 88, right: 24, bottom: 190, left: 24 }
          : { top: 88, right: 380, bottom: 76, left: 96 },
      maxZoom: 15,
      duration: 500,
    });
  }

  function focusFeatures(nextFeatures) {
    features = Array.isArray(nextFeatures) ? nextFeatures : [];
    if (features.length) activateMap();
    pendingFeatureFit = true;
    renderFeatures();
    fitFeatureBounds(features);
    if (map?.isStyleLoaded()) pendingFeatureFit = false;
  }

  function setSharedLocationFeatures(nextFeatures, { fit = false } = {}) {
    sharedLocationFeatures = Array.isArray(nextFeatures) ? nextFeatures : [];
    if (sharedLocationFeatures.length) activateMap();
    if (fit) pendingFeatureFit = true;
    renderFeatures();
    if (fit) fitFeatureBounds();
    if (fit && map?.isStyleLoaded()) pendingFeatureFit = false;
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
    if (journeyFeatures.length) activateMap();
    if (focus) pendingFeatureFit = true;
    renderFeatures();
    if (focus) fitFeatureBounds(journeyFeatures);
    if (focus && map?.isStyleLoaded()) pendingFeatureFit = false;
  }

  function showSearchLocation(point) {
    if (!validPoint(point)) throw new TypeError('A valid latitude and longitude are required.');
    activateMap();
    if (!map || !map.loaded()) {
      pendingSearchLocation = point;
      return;
    }
    pendingSearchLocation = null;
    const markerElement = document.createElement('div');
    markerElement.className = 'app-map-marker app-map-marker--search';
    markerElement.setAttribute('role', 'img');
    markerElement.setAttribute('aria-label', String(point.label || 'Searched location'));
    markerElement.innerHTML = '<span class="app-map-marker-dot"></span>';
    const label = document.createElement('span');
    label.className = 'app-map-marker-label';
    label.textContent = String(point.label || 'Searched location');
    markerElement.append(label);
    const marker = new maplibregl.Marker({ element: markerElement, anchor: 'bottom' })
      .setLngLat([Number(point.lng), Number(point.lat)])
      .addTo(map);
    searchMarker?.remove();
    searchMarker = marker;
    window.SafariTrakPageMap.searchMarker = marker;
    const offset = isGroupsPage || isMessagesPage
      ? window.matchMedia('(max-width: 1100px)').matches
        ? [0, (100 - Math.min(360, Math.round(map.getCanvas().clientHeight * 0.5))) / 2]
        : [-10, 20]
      : undefined;
    map.flyTo({
      center: [Number(point.lng), Number(point.lat)],
      zoom: 15,
      ...(offset ? { offset } : {}),
      essential: true,
      duration: 650,
    });
  }

  function clearSearchLocation() {
    searchMarker?.remove();
    searchMarker = null;
    pendingSearchLocation = null;
    window.SafariTrakPageMap.searchMarker = null;
  }

  function setUserLocation(lat, lng, zoom = 16, recenter = false) {
    const point = { lat: Number(lat), lng: Number(lng) };
    if (!validPoint(point)) throw new TypeError('A valid latitude and longitude are required.');
    activateMap();
    pendingUserLocation = { ...point, zoom, recenter };
    if (!map?.loaded()) return;
    if (userMarker) userMarker.remove();
    const markerElement = document.createElement('div');
    markerElement.className = 'app-map-user-marker';
    markerElement.setAttribute('role', 'img');
    markerElement.setAttribute('aria-label', 'Your current location');
    markerElement.innerHTML = '<span class="app-map-user-label">Your location</span>';
    userMarker = new maplibregl.Marker({ element: markerElement, anchor: 'center' })
      .setLngLat([point.lng, point.lat])
      .addTo(map);
    if (recenter) {
      map.flyTo({ center: [point.lng, point.lat], zoom, essential: true, duration: 850 });
      map.once('moveend', keepUserLocationVisible);
    } else {
      keepUserLocationVisible();
    }
  }

  window.SafariTrakPageMap = {
    setFeatures,
    focusFeatures,
    setSharedLocationFeatures,
    setJourneyFeatures,
    setUserLocation,
    showSearchLocation,
    clearSearchLocation,
    searchMarker: null,
  };

  function keepUserLocationVisible() {
    if (!userMarker || !map?.loaded() || (isJourneyPage && journeyFeatures.length)) return;
    const markerRect = userMarker.getElement().getBoundingClientRect();
    const markerPoint = { x: markerRect.left + markerRect.width / 2, y: markerRect.top + markerRect.height / 2 };
    const canvasRect = map.getCanvas().getBoundingClientRect();
    const panels = [...document.querySelectorAll(isGroupsPage
      ? '.app--groups .groups-list-panel, .app--groups .groups-chat-panel, .app--groups .group-hub-tabs'
      : isMessagesPage
      ? '.app--messages .messages-conversations, .app--messages .messages-thread'
      : '.app--map-layout:not(.app--explore) .sidebar, .app--map-layout:not(.app--explore) > main > header, .app--map-layout:not(.app--explore) > main > .content > *'
    )].filter(panel => panel.getClientRects().length && getComputedStyle(panel).visibility !== 'hidden');
    const blocked = panels.some(panel => {
      const rect = panel.getBoundingClientRect();
      return markerPoint.x >= rect.left && markerPoint.x <= rect.right
        && markerPoint.y >= rect.top && markerPoint.y <= rect.bottom;
    });
    if (!blocked) return;

    const candidates = [
      [0.72, 0.34], [0.72, 0.68], [0.36, 0.34], [0.36, 0.68],
      [0.82, 0.5], [0.28, 0.5], [0.5, 0.25], [0.5, 0.75],
    ].map(([x, y]) => ({ x: canvasRect.left + canvasRect.width * x, y: canvasRect.top + canvasRect.height * y }));
    const target = candidates.find(candidate => !panels.some(panel => {
      const rect = panel.getBoundingClientRect();
      return candidate.x >= rect.left && candidate.x <= rect.right
        && candidate.y >= rect.top && candidate.y <= rect.bottom;
    }));
    if (!target) return;

    const dx = target.x - markerPoint.x;
    const dy = target.y - markerPoint.y;
    const center = map.unproject([
      map.getCanvas().clientWidth / 2 - dx,
      map.getCanvas().clientHeight / 2 - dy,
    ]);
    map.easeTo({ center, duration: 500 });
  }

  function requestUserLocation(recenter = true) {
    if (!navigator.geolocation) {
      showMapError('This browser does not support device location. Map content remains available.');
      return;
    }
    if (locationRequestStarted) return;
    locationRequestStarted = true;
    const locateButton = document.getElementById('appMapLocate');
    locateButton?.classList.add('is-loading');
    navigator.geolocation.getCurrentPosition(
      position => {
        locateButton?.classList.remove('is-loading');
        window.SafariTrakMapView?.saveLocation(position);
        setUserLocation(position.coords.latitude, position.coords.longitude, 16, recenter);
        locateButton?.setAttribute('aria-label', 'Your current location is shown on the map');
      },
      error => {
        locationRequestStarted = false;
        locateButton?.classList.remove('is-loading');
        const message = error.code === error.PERMISSION_DENIED
          ? 'Location permission was denied. Enable it in your browser to show your position.'
          : 'Your location could not be determined. Try again when GPS is available.';
        showMapError(message);
      },
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
    );
  }

  function initializeMap() {
    try {
      const isMobile = window.matchMedia('(max-width: 800px)').matches;
      map = new maplibregl.Map({
        container: mapElement,
        style: mapStyle(),
        center: [36.817223, -1.286389],
        zoom: 15,
        bearing: 0,
        pitch: 0,
        projection: { type: 'mercator' },
        attributionControl: false,
        interactive: true,
        dragRotate: false,
        pitchWithRotate: false,
        scrollZoom: false,
        boxZoom: false,
        doubleClickZoom: isMobile,
        touchZoomRotate: isMobile,
        touchPitch: false,
        keyboard: false,
        cooperativeGestures: false,
        fadeDuration: 0,
        renderWorldCopies: false,
      });
      map.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');
      const attributionContainer = mapElement.querySelector('.maplibregl-ctrl-bottom-right');
      if (attributionContainer) {
        attributionContainer.classList.add('app-map-attribution');
        document.body.append(attributionContainer);
      }
      map.on('style.load', () => {
        applyMapStyle();
        map.setProjection({ type: 'mercator' });
        map.resize();
        if (pendingFeatureRender || features.length || journeyFeatures.length) renderFeatures();
        if (pendingFeatureFit) {
          fitFeatureBounds();
          pendingFeatureFit = false;
        }
      });
      map.on('load', () => {
        mapElement.classList.add('is-ready');
        renderFeatures();
        if (pendingFeatureFit) {
          fitFeatureBounds();
          pendingFeatureFit = false;
        }
        if (pendingUserLocation) {
          setUserLocation(
            pendingUserLocation.lat,
            pendingUserLocation.lng,
            pendingUserLocation.zoom,
            pendingUserLocation.recenter
          );
        }
        if (pendingSearchLocation) showSearchLocation(pendingSearchLocation);
        map.on('moveend', () => window.SafariTrakMapView?.save(
          map,
          'flat'
        ));
        document.dispatchEvent(new Event('safaritrak-map-ready'));
        if (!startupLocationRequested) {
          startupLocationRequested = true;
          requestUserLocation(true);
        }
      });
      if (isSchoolPage) {
        map.on('click', event => {
          if (event.originalEvent?.target?.closest?.('.maplibregl-ctrl, .app-map-marker')) return;
          document.dispatchEvent(new CustomEvent('safaritrak:school-map-click', {
            detail: {
              lat: event.lngLat.lat,
              lng: event.lngLat.lng,
            },
          }));
        });
      }
      if (isPlacesPage) {
        map.on('click', event => {
          if (event.originalEvent?.target?.closest?.('.maplibregl-ctrl, .app-map-marker')) return;
          document.dispatchEvent(new CustomEvent('safaritrak:places-map-click', {
            detail: {
              lat: event.lngLat.lat,
              lng: event.lngLat.lng,
            },
          }));
        });
      }
      map.on('idle', () => {
        if (pendingFeatureRender) renderFeatures();
        if (pendingFeatureFit) {
          fitFeatureBounds();
          pendingFeatureFit = false;
        }
      });
      map.on('error', () => {
        if (!map.isStyleLoaded()) showMapError();
      });

      const featureData = document.getElementById('safariTrakMapFeatures');
      if (featureData && !isJourneyPage) {
        try {
          setFeatures(JSON.parse(featureData.textContent || '[]'));
        } catch (error) {
          showMapError('Map locations could not be loaded. Other page functions remain available.');
          console.error('SafariTrak map feature data is invalid.', error);
        }
      }
    } catch (error) {
      showMapError();
      throw error;
    }
  }

  function loadMapLibre() {
    if (!document.querySelector('link[href*="maplibre-gl.css"]')) {
      const stylesheet = document.createElement('link');
      stylesheet.rel = 'stylesheet';
      stylesheet.href = 'https://unpkg.com/maplibre-gl@5.6.2/dist/maplibre-gl.css';
      document.head.append(stylesheet);
    }
    if (window.maplibregl) {
      initializeMap();
      return;
    }
    const script = document.createElement('script');
    script.src = 'https://unpkg.com/maplibre-gl@5.6.2/dist/maplibre-gl.js';
    script.onload = initializeMap;
    script.onerror = () => showMapError();
    document.head.append(script);
  }

  document.getElementById('appMapLocate')?.addEventListener('click', requestUserLocation);
  const focusButton = document.getElementById('appMapFocus');
  const setMapFocus = focused => {
    document.body.classList.toggle('map-focus-mode', focused);
    const panelsToggle = document.getElementById('appMapPanelsToggle');
    if (panelsToggle) {
      panelsToggle.hidden = !focused && !document.body.classList.contains('page-panel-focus');
    }
    focusButton?.setAttribute('aria-pressed', String(focused));
    if (focusButton) {
      focusButton.innerHTML = focused
        ? '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Back</span>'
        : '<i class="fa-solid fa-expand" aria-hidden="true"></i><span>Focus map</span>';
      focusButton.setAttribute('aria-label', focused ? 'Back' : 'Focus on the map');
    }
  };
  focusButton?.addEventListener('click', () => {
    setMapFocus(!document.body.classList.contains('map-focus-mode'));
  });
  document.getElementById('appMapPanelsToggle')?.addEventListener('click', () => {
    document.body.classList.remove('map-focus-mode');
    document.getElementById('appMapPanelsToggle').hidden = true;
  });
  document.addEventListener('safaritrak-panels-restored', () => {
    setMapFocus(false);
  });
  if (!isJourneyPage && !isGroupsPage && !isMessagesPage && !isProfilePage && !isSchoolPage) {
    mapElement.addEventListener('pointerdown', event => {
      if (event.target.closest('.maplibregl-ctrl, .app-map-marker')) return;
      if (isPlacesPage && document.getElementById('addPlaceModal')?.classList.contains('is-map-picking')) return;
      setMapFocus(true);
    });
  }
  const themeObserver = new MutationObserver(() => {
    if (!map) return;
    map.setStyle(mapStyle(document.documentElement.dataset.theme), { diff: false });
  });
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

  const initialFeatureData = document.getElementById('safariTrakMapFeatures');
  if (initialFeatureData && !isJourneyPage) {
    try {
      features = JSON.parse(initialFeatureData.textContent || '[]');
      if (features.length || journeyFeatures.length) activateMap();
    } catch (error) {
      console.error('SafariTrak map feature data is invalid.', error);
      showMapError('Map locations could not be loaded. Other page functions remain available.');
    }
  }
  activateMap();
})();
