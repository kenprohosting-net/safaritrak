(() => {
  const validPoint = point => Number.isFinite(Number(point?.lat))
    && Number.isFinite(Number(point?.lng))
    && Number(point.lat) >= -90 && Number(point.lat) <= 90
    && Number(point.lng) >= -180 && Number(point.lng) <= 180;

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

  window.SafariTrakRouteDirections = { getDrivingRoute };
})();
