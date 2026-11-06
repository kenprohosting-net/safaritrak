(() => {
  let nextId = 0;

  document.querySelectorAll('[data-location-autocomplete]').forEach(input => {
    const wrapper = input.parentElement;
    if (!wrapper) return;
    wrapper.classList.add('location-autocomplete');

    const list = document.createElement('div');
    list.className = 'location-suggestions';
    list.id = `location-suggestions-${++nextId}`;
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    wrapper.append(list);
    const status = document.createElement('div');
    status.className = 'location-suggestion-status';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    status.hidden = true;
    wrapper.append(status);
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', list.id);
    input.setAttribute('aria-expanded', 'false');

    let timer;
    let requestId = 0;
    let activeController = null;
    let options = [];
    let activeIndex = -1;

    const setStatus = message => {
      status.textContent = message;
      status.hidden = !message;
    };

    const close = () => {
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activeIndex = -1;
    };

    const coordinateFields = () => {
      const form = input.closest('form');
      return input.id === 'startPoint'
        ? [document.getElementById('startLat'), document.getElementById('startLng')]
        : input.id === 'endPoint'
          ? [document.getElementById('endLat'), document.getElementById('endLng')]
          : [form?.querySelector('[name="end_lat"]'), form?.querySelector('[name="end_lng"]')];
    };

    const clearCoordinates = () => coordinateFields().filter(Boolean).forEach(field => { field.value = ''; });

    const applySelection = suggestion => {
      input.value = suggestion.label;
      const fields = coordinateFields();
      if (fields[0]) fields[0].value = String(suggestion.lat);
      if (fields[1]) fields[1].value = String(suggestion.lng);
      close();
      input.dispatchEvent(new CustomEvent('safaritrak-location-selected', {
        bubbles: true,
        detail: suggestion,
      }));
    };

    const select = async option => {
      const currentRequest = ++requestId;
      const button = list.querySelector(`[data-option-index="${option.index}"]`);
      if (button) button.disabled = true;
      try {
        let suggestion = option;
        if (option.placePrediction) {
          const place = option.placePrediction.toPlace();
          let timeoutId;
          await Promise.race([
            place.fetchFields({ fields: ['displayName', 'formattedAddress', 'location'] }),
            new Promise((_, reject) => {
              timeoutId = setTimeout(() => reject(new Error('That place took too long to load. Choose it again or select another result.')), 6000);
            }),
          ]).finally(() => clearTimeout(timeoutId));
          const location = place.location;
          if (!location || !Number.isFinite(location.lat()) || !Number.isFinite(location.lng())) {
            throw new Error('Google Places did not return a location for that suggestion.');
          }
          suggestion = {
            label: place.formattedAddress || place.displayName || option.label,
            lat: location.lat(),
            lng: location.lng(),
          };
        }
        if (currentRequest !== requestId) return;
        if (!Number.isFinite(Number(suggestion.lat)) || !Number.isFinite(Number(suggestion.lng))) {
          throw new Error('That place does not have usable map coordinates.');
        }
        applySelection(suggestion);
      } catch (error) {
        console.error('The selected location could not be resolved.', error);
        setStatus('Could not load that place. Please choose another suggestion.');
        input.dispatchEvent(new CustomEvent('safaritrak-location-suggestion-error', {
          bubbles: true,
          detail: { message: 'Could not load that place. Please choose another suggestion.' },
        }));
      }
    };

    const render = suggestions => {
      options = suggestions.map((suggestion, index) => ({ ...suggestion, index }));
      list.replaceChildren();
      if (!options.length) {
        close();
        setStatus('No matching places. Try a longer or different search.');
        return;
      }
      setStatus('');
      options.forEach(option => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'location-suggestion';
        button.id = `${list.id}-option-${option.index}`;
        button.dataset.optionIndex = String(option.index);
        button.setAttribute('role', 'option');
        button.setAttribute('aria-selected', 'false');
        button.textContent = option.label;
        button.addEventListener('mousedown', event => event.preventDefault());
        button.addEventListener('click', () => select(option));
        list.append(button);
      });
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    };

    const fetchJson = async (url, signal, timeoutMilliseconds = 12000) => {
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), timeoutMilliseconds);
      const abortRequest = () => controller.abort();
      if (signal?.aborted) abortRequest();
      signal?.addEventListener('abort', abortRequest, { once: true });
      try {
        const response = await fetch(url, {
          headers: { Accept: 'application/json' },
          signal: controller.signal,
        });
        const payload = await response.json();
        if (!response.ok) {
          throw new Error(`Location search failed (HTTP ${response.status}).`);
        }
        return payload;
      } finally {
        clearTimeout(timeout);
        signal?.removeEventListener('abort', abortRequest);
      }
    };

    const fetchOpenMeteoSuggestions = async (query, signal) => {
      const url = new URL('https://geocoding-api.open-meteo.com/v1/search');
      url.search = new URLSearchParams({
        name: query,
        count: '7',
        language: 'en',
        countryCode: 'KE',
      }).toString();
      const payload = await fetchJson(url, signal);
      const suggestions = (payload.results || []).filter(item => item
        && typeof item.name === 'string'
        && Number.isFinite(Number(item.latitude))
        && Number.isFinite(Number(item.longitude)))
        .map(item => ({
          label: [item.name, item.admin2, item.admin1, item.country]
            .filter((part, index, parts) => typeof part === 'string' && part.trim()
              && parts.indexOf(part) === index)
            .join(', '),
          lat: Number(item.latitude),
          lng: Number(item.longitude),
        }));
      if (!suggestions.length) throw new Error('No matching places from Open-Meteo.');
      return suggestions;
    };

    const fetchPhotonSuggestions = async (query, signal) => {
      const url = new URL('https://photon.komoot.io/api/');
      url.search = new URLSearchParams({ q: `${query} Kenya`, limit: '7', lang: 'en' }).toString();
      const payload = await fetchJson(url, signal);
      const suggestions = (payload.features || []).flatMap(feature => {
        const coordinates = feature?.geometry?.coordinates;
        const properties = feature?.properties || {};
        const lat = Number(coordinates?.[1]);
        const lng = Number(coordinates?.[0]);
        const label = [
          properties.name,
          properties.city || properties.locality,
          properties.state,
          properties.country,
        ].filter((part, index, parts) => typeof part === 'string' && part.trim()
          && parts.indexOf(part) === index).join(', ');
        return label && Number.isFinite(lat) && Number.isFinite(lng)
          ? [{ label, lat, lng }]
          : [];
      });
      if (!suggestions.length) throw new Error('No matching places from Photon.');
      return suggestions;
    };

    const fetchBrowserSuggestions = (query, signal) => Promise.any([
      fetchOpenMeteoSuggestions(query, signal),
      fetchPhotonSuggestions(query, signal),
    ]);

    const withTimeout = (promise, milliseconds, message) => {
      let timeoutId;
      return Promise.race([
        promise,
        new Promise((_, reject) => {
          timeoutId = setTimeout(() => reject(new Error(message)), milliseconds);
        }),
      ]).finally(() => clearTimeout(timeoutId));
    };

    const fetchGoogleSuggestions = async (query, signal) => {
      let readyTimeout;
      const maps = await Promise.race([
        window.SafariTrakGoogleMaps?.ready,
        new Promise((_, reject) => {
          readyTimeout = setTimeout(() => reject(new Error('Google Maps is still loading.')), 8000);
        }),
      ]).finally(() => clearTimeout(readyTimeout));
      if (signal.aborted) throw new DOMException('Search was cancelled.', 'AbortError');
      const places = await maps?.importLibrary('places');
      if (!places?.AutocompleteSuggestion) throw new Error('Google Places Autocomplete is unavailable.');
      const result = await places.AutocompleteSuggestion.fetchAutocompleteSuggestions({
        input: query,
        includedRegionCodes: ['ke'],
      });
      const suggestions = (result.suggestions || [])
        .map(item => item.placePrediction)
        .filter(Boolean)
        .map(placePrediction => ({
          label: placePrediction.text.toString(),
          placePrediction,
        }));
      if (!suggestions.length) throw new Error('No matching Google Places suggestions.');
      return suggestions;
    };

    const fetchServerSuggestions = async (query, signal) => {
      const url = new URL('backend/api/places/suggest.php', window.location.href);
      url.searchParams.set('q', query);
      const payload = await fetchJson(url, signal, 8000);
      if (!payload.success || !Array.isArray(payload.suggestions)) {
        throw new Error(payload.message || 'The server could not find matching places.');
      }
      return payload.suggestions
        .filter(item => item && typeof item.label === 'string'
          && Number.isFinite(Number(item.lat)) && Number.isFinite(Number(item.lng)))
        .map(item => ({
          label: item.label,
          lat: Number(item.lat),
          lng: Number(item.lng),
        }));
    };

    const fetchSuggestions = async query => {
      const currentRequest = ++requestId;
      activeController?.abort();
      activeController = new AbortController();
      const { signal } = activeController;
      try {
        const suggestions = await Promise.any([
          withTimeout(fetchGoogleSuggestions(query, signal), 12000, 'Google Places search timed out.'),
          fetchBrowserSuggestions(query, signal),
        ]);
        if (currentRequest !== requestId) return;
        activeController.abort();
        render(suggestions);
      } catch (error) {
        if (signal.aborted || currentRequest !== requestId) return;
        try {
          const suggestions = await fetchServerSuggestions(query, signal);
          if (currentRequest !== requestId) return;
          render(suggestions);
        } catch (fallbackError) {
          if (signal.aborted || currentRequest !== requestId) return;
          console.error('Google, browser, and local location suggestions failed.', { error, fallbackError });
          render([]);
          setStatus(fallbackError.message || 'Location suggestions are unavailable. Please try again shortly.');
          input.dispatchEvent(new CustomEvent('safaritrak-location-suggestion-error', {
            bubbles: true,
            detail: { message: fallbackError.message || 'Location suggestions are unavailable. Please try again shortly.' },
          }));
        }
      }
    };

    input.addEventListener('input', () => {
      requestId += 1;
      activeController?.abort();
      activeController = null;
      clearCoordinates();
      options = [];
      close();
      setStatus('');
      clearTimeout(timer);
      const query = input.value.trim();
      if (query.length < 3) {
        setStatus(query ? 'Type at least 3 characters to search.' : '');
        return;
      }
      setStatus('Searching for places…');
      timer = setTimeout(() => fetchSuggestions(query), 250);
    });

    input.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        close();
        return;
      }
      if (event.key === 'Enter') {
        if (list.hidden || !options.length) {
          if (input.closest('#dashboardPlanner')) {
            event.preventDefault();
            setStatus('Choose a place from the suggestions before finding a route.');
          }
          return;
        }
        event.preventDefault();
        select(options[activeIndex >= 0 ? activeIndex : 0]);
        return;
      }
      if (!options.length || list.hidden) return;
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex = (activeIndex + (event.key === 'ArrowDown' ? 1 : options.length - 1)) % options.length;
        list.querySelectorAll('[role="option"]').forEach((option, index) => {
          const selected = index === activeIndex;
          option.setAttribute('aria-selected', String(selected));
          if (selected) {
            input.setAttribute('aria-activedescendant', option.id);
            option.scrollIntoView({ block: 'nearest' });
          }
        });
      }
    });
    input.addEventListener('blur', () => window.setTimeout(close, 120));
  });

  document.addEventListener('safaritrak-location-selected', event => {
    const point = event.detail;
    if (window.SafariTrakPageMap?.showSearchLocation) {
      window.SafariTrakPageMap.showSearchLocation(point);
    } else {
      window.SafariTrakDashboardMap?.showSearchLocation(point);
    }
  });
})();
