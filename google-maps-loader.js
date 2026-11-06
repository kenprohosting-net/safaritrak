(() => {
  const key = document.querySelector('meta[name="google-maps-api-key"]')?.content.trim();
  if (!key) {
    window.SafariTrakGoogleMaps = {
      ready: Promise.reject(new Error('Google Maps is not configured for this installation.')),
    };
    window.SafariTrakGoogleMaps.ready.catch(() => {});
    return;
  }

  let resolveReady;
  let rejectReady;
  let settled = false;
  let timeoutId;
  const ready = new Promise((resolve, reject) => {
    resolveReady = () => {
      if (settled) return;
      if (typeof window.google?.maps?.Map !== 'function') {
        rejectReady(new Error('Google Maps loaded without its Maps library. Check the API key and enabled APIs.'));
        return;
      }
      settled = true;
      window.clearTimeout(timeoutId);
      resolve(window.google.maps);
    };
    rejectReady = error => {
      if (settled) return;
      settled = true;
      window.clearTimeout(timeoutId);
      reject(error);
    };
  });
  window.SafariTrakGoogleMaps = { ready };
  window.__safaritrakGoogleMapsLoaded = async () => {
    try {
      if (typeof window.google?.maps?.importLibrary === 'function') {
        await window.google.maps.importLibrary('maps');
      }
      if (typeof window.google?.maps?.Map === 'function') {
        resolveReady();
      } else {
        rejectReady(new Error('Google Maps finished loading without exposing its Maps API.'));
      }
    } catch (error) {
      rejectReady(error instanceof Error ? error : new Error('Google Maps library initialization failed.'));
    }
  };
  window.gm_authFailure = () => {
    const error = new Error('Google rejected this Maps API key. Check its API restrictions, enabled APIs, and billing.');
    rejectReady(error);
    document.dispatchEvent(new CustomEvent('safaritrak-google-maps-error', {
      detail: { message: error.message },
    }));
  };

  const script = document.createElement('script');
  script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&v=weekly&libraries=places&loading=async&callback=__safaritrakGoogleMapsLoaded`;
  script.async = true;
  script.onerror = () => rejectReady(new Error('Google Maps could not be loaded. Check the API key, API restrictions, and connection.'));
  timeoutId = window.setTimeout(() => {
    if (typeof window.google?.maps?.Map === 'function') {
      resolveReady();
    } else {
      rejectReady(new Error('Google Maps did not finish loading. Check the API key, enabled APIs, and billing status.'));
    }
  }, 30_000);
  document.head.append(script);
})();
