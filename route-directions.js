(() => {
  const validPoint = point => Number.isFinite(Number(point?.lat))
    && Number.isFinite(Number(point?.lng))
    && Number(point.lat) >= -90 && Number(point.lat) <= 90
    && Number(point.lng) >= -180 && Number(point.lng) <= 180;

  function focusMobileRoute(map, coordinates, start) {
    if (!map || !window.matchMedia('(max-width: 800px)').matches || !Array.isArray(coordinates)) {
      return false;
    }

    const route = coordinates.filter(point => Array.isArray(point) && point.length >= 2
      && Number.isFinite(Number(point[0])) && Number.isFinite(Number(point[1]))
      && Number(point[0]) >= -180 && Number(point[0]) <= 180
      && Number(point[1]) >= -90 && Number(point[1]) <= 90
    );
    if (route.length < 2) return false;

    const origin = Array.isArray(start) && start.length >= 2
      && Number.isFinite(Number(start[0])) && Number.isFinite(Number(start[1]))
      ? [Number(start[0]), Number(start[1])]
      : route[0].map(Number);
    const nextPoint = route.find(point => Math.abs(Number(point[0]) - origin[0]) > 1e-6
      || Math.abs(Number(point[1]) - origin[1]) > 1e-6);
    const bearing = nextPoint
      ? (() => {
        const toRadians = value => value * Math.PI / 180;
        const fromLat = toRadians(origin[1]);
        const toLat = toRadians(Number(nextPoint[1]));
        const deltaLng = toRadians(Number(nextPoint[0]) - origin[0]);
        const y = Math.sin(deltaLng) * Math.cos(toLat);
        const x = Math.cos(fromLat) * Math.sin(toLat)
          - Math.sin(fromLat) * Math.cos(toLat) * Math.cos(deltaLng);
        return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
      })()
      : 0;
    const canvas = map.getCanvas();
    map.easeTo({
      center: origin,
      zoom: 16,
      bearing,
      offset: [-canvas.clientWidth * 0.22, canvas.clientHeight * 0.25],
      duration: 500,
      essential: true,
    });
    return true;
  }

  function focusMobileLeafletRoute(map, coordinates, start) {
    if (!map || !window.matchMedia('(max-width: 800px)').matches
      || typeof map.setView !== 'function' || typeof map.panBy !== 'function'
      || typeof map.getSize !== 'function' || !Array.isArray(coordinates)) {
      return false;
    }

    const route = coordinates.map(point => Array.isArray(point)
      ? [Number(point[0]), Number(point[1])]
      : [Number(point?.lat), Number(point?.lng)]
    ).filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng)
      && Math.abs(lat) <= 90 && Math.abs(lng) <= 180);
    if (route.length < 2) return false;

    const origin = Array.isArray(start)
      ? [Number(start[0]), Number(start[1])]
      : [Number(start?.lat), Number(start?.lng)];
    const center = origin.every(Number.isFinite)
      ? origin
      : route[0];
    const size = map.getSize();
    map.setView(center, 16, { animate: false });
    map.panBy([-size.x * 0.22, size.y * 0.25], { animate: false });
    return true;
  }

  function focusMobileGoogleRoute(map, coordinates, start) {
    if (!map || !window.matchMedia('(max-width: 800px)').matches
      || typeof map.setCenter !== 'function' || typeof map.setZoom !== 'function'
      || typeof map.panBy !== 'function' || !Array.isArray(coordinates)) {
      return false;
    }

    const route = coordinates.map(point => Array.isArray(point)
      ? [Number(point[0]), Number(point[1])]
      : [Number(point?.lng), Number(point?.lat)]
    ).filter(([lng, lat]) => Number.isFinite(lng) && Number.isFinite(lat)
      && Math.abs(lng) <= 180 && Math.abs(lat) <= 90);
    if (route.length < 2) return false;

    const origin = Array.isArray(start)
      ? [Number(start[0]), Number(start[1])]
      : [Number(start?.lng), Number(start?.lat)];
    const center = origin.length >= 2 && origin.every(Number.isFinite)
      && Math.abs(origin[0]) <= 180 && Math.abs(origin[1]) <= 90
      ? origin
      : route[0];
    const nextPoint = route.find(point => Math.abs(point[0] - center[0]) > 1e-6
      || Math.abs(point[1] - center[1]) > 1e-6);
    if (nextPoint && typeof map.setHeading === 'function') {
      const toRadians = value => value * Math.PI / 180;
      const fromLat = toRadians(center[1]);
      const toLat = toRadians(nextPoint[1]);
      const deltaLng = toRadians(nextPoint[0] - center[0]);
      const y = Math.sin(deltaLng) * Math.cos(toLat);
      const x = Math.cos(fromLat) * Math.sin(toLat)
        - Math.sin(fromLat) * Math.cos(toLat) * Math.cos(deltaLng);
      map.setHeading((Math.atan2(y, x) * 180 / Math.PI + 360) % 360);
    }

    map.setCenter({ lat: center[1], lng: center[0] });
    map.setZoom(16);
    const canvas = map.getDiv?.();
    if (canvas) map.panBy(Math.round(canvas.clientWidth * 0.22), -Math.round(canvas.clientHeight * 0.25));
    return true;
  }

  function validateRoute(payload, providerName) {
    const route = payload?.routes?.[0];
    const coordinates = route?.geometry?.coordinates;
    if (payload?.code !== 'Ok' || !Array.isArray(coordinates) || coordinates.length < 2
      || !Number.isFinite(Number(route.distance))
      || !Number.isFinite(Number(route.duration))
      || coordinates.some(point => !Array.isArray(point) || point.length < 2
        || !Number.isFinite(Number(point[0])) || !Number.isFinite(Number(point[1]))
        || Number(point[0]) < -180 || Number(point[0]) > 180
        || Number(point[1]) < -90 || Number(point[1]) > 90)
    ) {
      throw new Error(`${providerName} did not return a usable driving route.`);
    }

    return {
      distance_meters: Number(route.distance),
      duration_seconds: Number(route.duration),
      coordinates,
    };
  }

  async function getBrowserRoute(start, destination, signal) {
    const coordinates = `${start.lng},${start.lat};${destination.lng},${destination.lat}`;
    const url = new URL(`https://router.project-osrm.org/route/v1/driving/${coordinates}`);
    url.search = new URLSearchParams({
      overview: 'full',
      geometries: 'geojson',
      steps: 'false',
      alternatives: 'true',
    }).toString();
    const response = await fetch(url, {
      cache: 'no-store',
      headers: { Accept: 'application/json' },
      signal,
    });
    if (!response.ok) throw new Error(`The direct route service returned HTTP ${response.status}.`);
    return validateRoute(await response.json(), 'The direct route service');
  }

  async function getServerRoute(start, destination, signal) {
    const url = new URL('backend/api/routes/directions.php', window.location.href);
    url.search = new URLSearchParams({
      start_lat: String(start.lat),
      start_lng: String(start.lng),
      end_lat: String(destination.lat),
      end_lng: String(destination.lng),
    }).toString();
    const response = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
      signal,
    });
    let payload;
    try {
      payload = await response.json();
    } catch (error) {
      throw new Error(`The authenticated route service returned an invalid response (HTTP ${response.status}).`);
    }
    if (!response.ok || !payload.success) {
      throw new Error(payload.message || `The authenticated route service returned HTTP ${response.status}.`);
    }
    return validateRoute({ code: 'Ok', routes: [{
      distance: payload.route?.distance_meters,
      duration: payload.route?.duration_seconds,
      geometry: { coordinates: payload.route?.coordinates },
    }] }, 'The authenticated route service');
  }

  async function getDrivingRoute(start, destination, { signal } = {}) {
    if (!validPoint(start) || !validPoint(destination)) {
      throw new TypeError('Choose valid starting and destination locations.');
    }

    const controller = new AbortController();
    let timedOut = false;
    const timeout = setTimeout(() => {
      timedOut = true;
      controller.abort();
    }, 15_000);
    const abortRequest = () => controller.abort();
    if (signal?.aborted) abortRequest();
    signal?.addEventListener('abort', abortRequest, { once: true });

    try {
      if (signal?.aborted) throw new DOMException('Route search was cancelled.', 'AbortError');
      return await Promise.any([
        getBrowserRoute(start, destination, controller.signal),
        getServerRoute(start, destination, controller.signal),
      ]);
    } catch (error) {
      if (signal?.aborted) {
        throw new DOMException('Route search was cancelled.', 'AbortError');
      }
      if (timedOut) throw new Error('Route search timed out. Please retry.');
      if (error.name === 'AbortError') {
        throw new Error('Route search timed out. Please retry.');
      }
      if (error instanceof AggregateError) {
        const messages = error.errors
          .filter(item => item && item.name !== 'AbortError')
          .map(item => item.message);
        throw new Error(messages[0] || 'Neither route service could find a drivable road route.');
      }
      throw error;
    } finally {
      clearTimeout(timeout);
      signal?.removeEventListener('abort', abortRequest);
      controller.abort();
    }
  }

  window.SafariTrakRouteDirections = {
    focusMobileGoogleRoute,
    focusMobileLeafletRoute,
    focusMobileRoute,
    getDrivingRoute,
  };
})();
