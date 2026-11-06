(() => {
  const viewKey = 'safaritrak-map-view-v3';
  const locationKey = 'safaritrak-device-location-v1';
  const userId = document.querySelector('[data-map-user-id]')?.dataset.mapUserId;
  const scopedViewKey = userId ? `${viewKey}:${userId}` : viewKey;
  const scopedLocationKey = userId ? `${locationKey}:${userId}` : locationKey;

  function readJson(key) {
    try {
      const value = sessionStorage.getItem(key);
      return value ? JSON.parse(value) : null;
    } catch (error) {
      console.warn('SafariTrak could not read temporary map state.', error);
      return null;
    }
  }

  function writeJson(key, value) {
    try {
      sessionStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
      console.warn('SafariTrak could not save temporary map state.', error);
    }
  }

  function validPoint(point) {
    return point && Number.isFinite(point.lat) && Number.isFinite(point.lng)
      && point.lat >= -90 && point.lat <= 90
      && point.lng >= -180 && point.lng <= 180;
  }

  function coordinate(value, key) {
    const result = value?.[key];
    return typeof result === 'function' ? result.call(value) : result;
  }

  window.SafariTrakMapView = {
    read() {
      const state = readJson(scopedViewKey);
      if (
        !state ||
        !validPoint({ lat: state.lat, lng: state.lng }) ||
        !Number.isFinite(state.zoom) ||
        state.zoom < 0 ||
        state.zoom > 22
      ) return null;
      return {
        lat: state.lat,
        lng: state.lng,
        zoom: state.zoom,
        bearing: Number.isFinite(state.bearing) ? state.bearing : 0,
        pitch: Number.isFinite(state.pitch) ? state.pitch : 0,
        projection: state.projection === 'satellite' ? 'satellite' : 'flat',
      };
    },
    save(map, projection) {
      if (!map) return;
      const center = map.getCenter();
      const mapProjection = projection || (map.getMapTypeId?.() === 'satellite' ? 'satellite' : 'flat');
      writeJson(scopedViewKey, {
        lat: coordinate(center, 'lat'),
        lng: coordinate(center, 'lng'),
        zoom: map.getZoom(),
        bearing: map.getBearing?.() || 0,
        pitch: map.getPitch?.() || 0,
        projection: mapProjection === 'satellite' ? 'satellite' : 'flat',
      });
    },
    readLocation() {
      const location = readJson(scopedLocationKey);
      if (!location || !validPoint(location) || !Number.isFinite(location.timestamp)) return null;
      return location;
    },
    saveLocation(position) {
      if (!position || !Number.isFinite(position.coords?.latitude) || !Number.isFinite(position.coords?.longitude)) {
        throw new TypeError('A valid device location is required.');
      }
      writeJson(scopedLocationKey, {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
        accuracy: Number.isFinite(position.coords.accuracy) ? position.coords.accuracy : null,
        timestamp: Date.now(),
      });
    },
  };
})();
