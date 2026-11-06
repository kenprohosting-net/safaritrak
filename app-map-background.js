(() => {
  const mapElement = document.getElementById('appMapBackground');
  if (!mapElement) return;

  let map;
  let fallbackMessage;
  let userMarker;
  let pendingUserLocation;
  let featureMarkers = [];
  let features = [];
  let mapView;
  let locationRequestStarted = false;
  let pendingSearchLocation;
  let searchMarker;
  let pendingFeatureFit = false;
  let pendingFeatureRender = false;
  let mapLoadStarted = false;

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

  function mapStyle(theme) {
    const style = theme === 'light' ? 'positron-gl-style' : 'dark-matter-gl-style';
    return `https://basemaps.cartocdn.com/gl/${style}/style.json`;
  }

  function applyMapStyle() {
    if (!map?.getStyle()?.layers) return;
    const light = document.documentElement.dataset.theme === 'light';
    const roadLabelMinZooms = {
      roadname_major: 8,
      roadname_pri: 9,
      roadname_sec: 10,
      roadname_minor: 11,
    };

    for (const layer of map.getStyle().layers) {
      if (Object.hasOwn(roadLabelMinZooms, layer.id)) {
        map.setLayerZoomRange(layer.id, roadLabelMinZooms[layer.id], 24);
      }
      if (!layer.paint) continue;
      const id = layer.id.toLowerCase();
      if (layer.type === 'background') {
        map.setPaintProperty(layer.id, 'background-color', light ? '#F1F5F9' : '#090D16');
      } else if (layer.type === 'fill' && id.includes('water')) {
        map.setPaintProperty(layer.id, 'fill-color', light ? '#E0F2FE' : '#050B24');
      } else if (layer.type === 'fill' && /park|green|wood|forest/.test(id)) {
        map.setPaintProperty(layer.id, 'fill-color', light ? '#D1FAE5' : '#064E3B');
      } else if (layer.type === 'fill' && (id.includes('landcover') || id.includes('landuse'))) {
        map.setPaintProperty(layer.id, 'fill-color', light ? '#F8FAFC' : '#0F172A');
      } else if (layer.type === 'line' && id.startsWith('road_') && id.includes('case')) {
        map.setPaintProperty(layer.id, 'line-color', light ? '#CBD5E1' : '#1E293B');
        map.setPaintProperty(layer.id, 'line-opacity', 0.96);
      } else if (layer.type === 'line' && id.startsWith('road_')) {
        const color = /motorway/.test(id)
          ? (light ? '#C026D3' : '#D946EF')
          : /trunk|primary/.test(id)
            ? (light ? '#C026D3' : '#D946EF')
            : /secondary|tertiary/.test(id)
              ? (light ? '#EA580C' : '#F97316')
              : (light ? '#E2E8F0' : '#334155');
        map.setPaintProperty(layer.id, 'line-color', color);
        map.setPaintProperty(layer.id, 'line-opacity', /motorway|trunk|primary|secondary/.test(id) ? 0.98 : 0.86);
        if (map.getPaintProperty(layer.id, 'line-blur') !== undefined) {
          map.setPaintProperty(layer.id, 'line-blur', 0.35);
        }
      } else if (layer.type === 'line' && id.includes('waterway')) {
        map.setPaintProperty(layer.id, 'line-color', light ? '#0891B2' : '#22D3EE');
        map.setPaintProperty(layer.id, 'line-opacity', 0.92);
      } else if (layer.type === 'symbol' && layer.layout?.['text-field']) {
        const roadLabel = id.includes('roadname');
        const cityLabel = /place|city|town|suburb|village/.test(id);
        const labelColor = roadLabel
          ? (light ? '#0F172A' : '#F8FAFC')
          : (light ? '#0F172A' : '#F8FAFC');
        map.setPaintProperty(layer.id, 'text-color', labelColor);
        map.setPaintProperty(layer.id, 'text-halo-color', light ? '#FFFFFF' : '#000000');
        map.setPaintProperty(layer.id, 'text-halo-width', roadLabel || cityLabel ? 2 : 1.8);
        map.setPaintProperty(layer.id, 'text-opacity', 1);
        if (map.getPaintProperty(layer.id, 'text-halo-blur') !== undefined) {
          map.setPaintProperty(layer.id, 'text-halo-blur', 0.45);
        }
      }
    }
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
    for (const feature of features) {
      if (!validPoint(feature)) continue;
      const kind = ['start', 'finish', 'place', 'saved-place', 'home', 'journey'].includes(feature.kind)
        ? feature.kind
        : 'journey';
      const markerElement = document.createElement('button');
      markerElement.type = 'button';
      markerElement.className = `app-map-marker app-map-marker--${kind}`;
      markerElement.setAttribute('aria-label', String(feature.label || 'Map location'));
      markerElement.innerHTML = '<span class="app-map-marker-dot"></span>';
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
          new maplibregl.Popup({ closeButton: true, offset: 18 })
            .setLngLat([Number(feature.lng), Number(feature.lat)])
            .setText(String(feature.description))
            .addTo(map);
        });
      }
      featureMarkers.push(marker);
    }

    features.forEach((feature, index) => {
      if (!Array.isArray(feature.route) || feature.route.length < 2) return;
      const validCoordinates = feature.route.filter(point => Array.isArray(point) && point.length === 2
        && Number.isFinite(Number(point[0])) && Number.isFinite(Number(point[1])));
      if (validCoordinates.length > 1) {
        const suffix = String(index);
        const plannedRoute = feature.routeColor === 'planned';
        const glowColor = plannedRoute ? '#D946EF' : '#D946EF';
        const lineColor = plannedRoute ? '#C026D3' : '#D946EF';
        map.addSource(`safaritrak-page-route-${suffix}`, {
          type: 'geojson',
          data: {
            type: 'Feature',
            properties: {},
            geometry: { type: 'LineString', coordinates: validCoordinates },
          },
        });
        map.addLayer({
          id: `safaritrak-page-route-casing-${suffix}`,
          type: 'line',
          source: `safaritrak-page-route-${suffix}`,
          layout: { 'line-cap': 'round', 'line-join': 'round' },
          paint: {
            'line-color': plannedRoute ? '#fff0f3' : '#f8eaff',
            'line-width': plannedRoute ? 10 : 8,
            'line-opacity': 0.94,
          },
        });
        map.addLayer({
          id: `safaritrak-page-route-glow-${suffix}`,
          type: 'line',
          source: `safaritrak-page-route-${suffix}`,
          layout: { 'line-cap': 'round', 'line-join': 'round' },
          paint: { 'line-color': glowColor, 'line-width': 12, 'line-opacity': 0.55, 'line-blur': 5 },
        });
        map.addLayer({
          id: `safaritrak-page-route-line-${suffix}`,
          type: 'line',
          source: `safaritrak-page-route-${suffix}`,
          layout: { 'line-cap': 'round', 'line-join': 'round' },
          paint: { 'line-color': lineColor, 'line-width': plannedRoute ? 6 : 5, 'line-opacity': 1 },
        });
      }
    });

  }

  function setFeatures(nextFeatures) {
    features = Array.isArray(nextFeatures) ? nextFeatures : [];
    if (features.length) activateMap();
    renderFeatures();
  }

  function fitFeatureBounds() {
    if (!map?.isStyleLoaded() || !features.length) return;
    const coordinates = [];
    features.forEach(feature => {
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
    map.fitBounds([
      [Math.min(...longitudes), Math.min(...latitudes)],
      [Math.max(...longitudes), Math.max(...latitudes)],
    ], {
      padding: window.matchMedia('(max-width: 800px)').matches
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
    fitFeatureBounds();
    if (map?.isStyleLoaded()) pendingFeatureFit = false;
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
    map.flyTo({ center: [Number(point.lng), Number(point.lat)], zoom: 15, essential: true, duration: 650 });
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
    setUserLocation,
    showSearchLocation,
    clearSearchLocation,
    searchMarker: null,
  };

  function keepUserLocationVisible() {
    if (!userMarker || !map?.loaded()) return;
    const markerRect = userMarker.getElement().getBoundingClientRect();
    const markerPoint = { x: markerRect.left + markerRect.width / 2, y: markerRect.top + markerRect.height / 2 };
    const canvasRect = map.getCanvas().getBoundingClientRect();
    const panels = [...document.querySelectorAll(
      '.app--map-layout:not(.app--explore) .sidebar, .app--map-layout:not(.app--explore) > main > header, .app--map-layout:not(.app--explore) > main > .content > *'
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
      mapView = window.SafariTrakMapView?.read() || null;
      const cachedLocation = window.SafariTrakMapView?.readLocation() || null;
      const initialPoint = mapView || cachedLocation || { lat: -1.286389, lng: 36.817223 };
      map = new maplibregl.Map({
        container: mapElement,
        style: mapStyle(document.documentElement.dataset.theme),
        center: [initialPoint.lng, initialPoint.lat],
        zoom: mapView?.zoom ?? (cachedLocation ? 16 : 15),
        bearing: mapView?.bearing ?? 0,
        pitch: mapView?.pitch ?? 0,
        projection: { type: mapView?.projection === 'flat' ? 'mercator' : 'globe' },
        attributionControl: false,
        interactive: true,
        dragRotate: false,
        pitchWithRotate: false,
        scrollZoom: false,
        boxZoom: false,
        doubleClickZoom: false,
        touchZoomRotate: false,
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
        map.setProjection({ type: mapView?.projection === 'globe' ? 'globe' : 'mercator' });
        if (pendingFeatureRender || features.length) renderFeatures();
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
        if (cachedLocation && !pendingUserLocation) {
          setUserLocation(cachedLocation.lat, cachedLocation.lng);
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
        map.on('moveend', () => window.SafariTrakMapView?.save(map, mapView?.projection === 'flat' ? 'flat' : 'globe'));
        document.dispatchEvent(new Event('safaritrak-map-ready'));
      });
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
      if (featureData && !document.querySelector('.app--journeys')) {
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
        ? '<i class="fa-solid fa-compress" aria-hidden="true"></i><span>Show panels</span>'
        : '<i class="fa-solid fa-expand" aria-hidden="true"></i><span>Focus map</span>';
      focusButton.setAttribute('aria-label', focused ? 'Show page panels' : 'Focus on the map');
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
  mapElement.addEventListener('pointerdown', event => {
    if (event.target.closest('.maplibregl-ctrl, .app-map-marker')) return;
    setMapFocus(true);
  });
  const themeObserver = new MutationObserver(() => {
    if (!map) return;
    map.setStyle(mapStyle(document.documentElement.dataset.theme), { diff: false });
  });
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

  const initialFeatureData = document.getElementById('safariTrakMapFeatures');
  if (initialFeatureData && !document.querySelector('.app--journeys')) {
    try {
      features = JSON.parse(initialFeatureData.textContent || '[]');
      if (features.length) activateMap();
    } catch (error) {
      console.error('SafariTrak map feature data is invalid.', error);
      showMapError('Map locations could not be loaded. Other page functions remain available.');
    }
  }
})();
