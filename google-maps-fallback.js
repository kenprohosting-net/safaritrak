(() => {
  let libraryRequest;
  const startedMaps = new Map();
  const fallbackVersion = '5.6.2';

  function loadMapLibre() {
    if (window.maplibregl) return Promise.resolve();
    if (libraryRequest) return libraryRequest;

    libraryRequest = new Promise((resolve, reject) => {
      if (!document.querySelector('link[data-safaritrak-maplibre]')) {
        const stylesheet = document.createElement('link');
        stylesheet.rel = 'stylesheet';
        stylesheet.href = `https://unpkg.com/maplibre-gl@${fallbackVersion}/dist/maplibre-gl.css`;
        stylesheet.dataset.safaritrakMaplibre = 'true';
        document.head.append(stylesheet);
      }

      const script = document.createElement('script');
      script.src = `https://unpkg.com/maplibre-gl@${fallbackVersion}/dist/maplibre-gl.js`;
      script.async = true;
      const timeout = window.setTimeout(() => {
        libraryRequest = null;
        reject(new Error('The backup map library took too long to load.'));
      }, 30_000);
      script.onload = () => {
        window.clearTimeout(timeout);
        if (window.maplibregl) {
          resolve();
        } else {
          libraryRequest = null;
          reject(new Error('The backup map library loaded without its map renderer.'));
        }
      };
      script.onerror = () => {
        window.clearTimeout(timeout);
        libraryRequest = null;
        reject(new Error('The backup map library could not be reached.'));
      };
      document.head.append(script);
    });
    return libraryRequest;
  }

  function loadPageMap(scriptName) {
    if (startedMaps.has(scriptName)) return startedMaps.get(scriptName);

    const request = new Promise((resolve, reject) => {
      const timeout = window.setTimeout(() => {
        document.removeEventListener('safaritrak-map-ready', handleReady);
        startedMaps.delete(scriptName);
        reject(new Error('The backup map did not finish starting. Check the map data connection.'));
      }, 25_000);
      const handleReady = () => {
        window.clearTimeout(timeout);
        document.removeEventListener('safaritrak-map-ready', handleReady);
        resolve();
      };
      document.addEventListener('safaritrak-map-ready', handleReady, { once: true });
      const script = document.createElement('script');
      script.src = new URL(scriptName, window.location.href).href;
      script.async = true;
      script.onerror = () => {
        window.clearTimeout(timeout);
        document.removeEventListener('safaritrak-map-ready', handleReady);
        startedMaps.delete(scriptName);
        reject(new Error('The backup map could not be initialized.'));
      };
      document.body.append(script);
    });
    startedMaps.set(scriptName, request);
    return request;
  }

  window.SafariTrakMapFallback = {
    async load(scriptName) {
      await loadMapLibre();
      document.documentElement.dataset.mapProvider = 'backup';
      await loadPageMap(scriptName);
    },
  };
})();
