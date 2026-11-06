<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <title>SafariTrak | Shared live location</title>
  <link rel="stylesheet" href="https://unpkg.com/maplibre-gl@5.6.2/dist/maplibre-gl.css">
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; color: #f3edff; background: #080a14; font: 15px system-ui, sans-serif; }
    header { position: absolute; z-index: 2; top: 14px; left: 50%; width: min(560px, calc(100% - 28px)); padding: 16px 20px; border: 1px solid #ca63ff70; border-radius: 16px; background: #0b0c19e8; box-shadow: 0 12px 32px #0008; transform: translateX(-50%); backdrop-filter: blur(18px); }
    header h1 { margin: 0 0 6px; font-size: 18px; }
    header p { margin: 4px 0; color: #c8bed8; font-size: 13px; }
    #shareMap { position: fixed; inset: 0; }
    #shareStatus { position: fixed; z-index: 3; right: 14px; bottom: 14px; max-width: min(400px, calc(100% - 28px)); padding: 10px 13px; border: 1px solid #ca63ff70; border-radius: 12px; background: #0b0c19e8; box-shadow: 0 8px 24px #0008; }
  </style>
</head>
<body>
  <header>
    <h1 id="shareTitle">Trip share</h1>
    <p id="shareRoute">Loading shared journey…</p>
    <p id="shareIntro">This temporary link only shows the journey shared with you.</p>
  </header>
  <div id="shareMap" role="region" aria-label="Live location map"></div>
  <p id="shareStatus" role="status" aria-live="polite">Connecting…</p>
  <script src="https://unpkg.com/maplibre-gl@5.6.2/dist/maplibre-gl.js"></script>
  <script>
    (() => {
      const token = new URLSearchParams(location.search).get('token') || '';
      const status = document.getElementById('shareStatus');
      const route = document.getElementById('shareRoute');
      const title = document.getElementById('shareTitle');
      const intro = document.getElementById('shareIntro');
      let map;
      let marker;
      let currentPoint;
      let refreshTimer;
      let stopped = false;

      async function showPlannedRoute(journey) {
        const start = [Number(journey.start_lng), Number(journey.start_lat)];
        const end = [Number(journey.end_lng), Number(journey.end_lat)];
        if (![...start, ...end].every(Number.isFinite)) {
          status.textContent = 'This scheduled trip does not have enough location data to show its route.';
          return;
        }
        if (map) return;
        let coordinates = [start, end];
        let routeAvailable = false;
        try {
          const response = await fetch(
            `https://router.project-osrm.org/route/v1/driving/${start.join(',')};${end.join(',')}?overview=full&geometries=geojson&steps=false`
          );
          const payload = await response.json();
          const routeCoordinates = payload?.routes?.[0]?.geometry?.coordinates;
          if (response.ok && payload.code === 'Ok' && Array.isArray(routeCoordinates) && routeCoordinates.length > 1) {
            coordinates = routeCoordinates;
            routeAvailable = true;
          }
        } catch (error) {
          console.error('Public planned route geometry could not be loaded.', error);
        }
        if (!window.maplibregl) {
          status.textContent = 'The trip was received, but map rendering is unavailable in this browser.';
          return;
        }
        map = new maplibregl.Map({
          container: 'shareMap',
          style: 'https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json',
          center: start,
          zoom: 7,
          attributionControl: true,
          dragRotate: false,
          scrollZoom: false,
          doubleClickZoom: false,
          touchZoomRotate: false,
        });
        map.once('load', () => {
          map.addSource('planned-trip-route', {
            type: 'geojson',
            data: {
              type: 'Feature',
              properties: {},
              geometry: { type: 'LineString', coordinates },
            },
          });
          map.addLayer({
            id: 'planned-trip-route-line',
            type: 'line',
            source: 'planned-trip-route',
            paint: { 'line-color': '#ff2d75', 'line-width': 5, 'line-opacity': 0.92 },
          });
          marker = new maplibregl.Marker({ color: '#47f5cf' }).setLngLat(start).addTo(map);
          new maplibregl.Marker({ color: '#ff4db8' }).setLngLat(end).addTo(map);
          const bounds = coordinates.reduce(
            (routeBounds, point) => routeBounds.extend(point),
            new maplibregl.LngLatBounds(coordinates[0], coordinates[0])
          );
          map.fitBounds(bounds, { padding: { top: 130, right: 70, bottom: 70, left: 70 }, maxZoom: 14 });
        });
        const departure = journey.planned_departure_at
          ? new Date(journey.planned_departure_at.replace(' ', 'T')).toLocaleString()
          : 'Departure time not set';
        status.textContent = `Scheduled route · ${departure}. ${routeAvailable ? 'Road route shown; live location is not shared.' : 'Showing start and destination; road routing is unavailable.'}`;
      }

      async function refreshLocation() {
        if (!refreshTimer) refreshTimer = setInterval(() => { if (!stopped) refreshLocation(); }, 30000);
        if (!/^[a-f0-9]{64}$/.test(token)) {
          status.textContent = 'This live-location link is invalid or expired.';
          stopped = true;
          clearInterval(refreshTimer);
          return;
        }
        try {
          const response = await fetch(`backend/api/journeys/public-share.php?token=${encodeURIComponent(token)}`, {
            cache: 'no-store',
            headers: { Accept: 'application/json' },
          });
          const data = await response.json();
          if (!response.ok || !data.success) {
            status.textContent = data.message || 'This live-location link is no longer available.';
            stopped = true;
            clearInterval(refreshTimer);
            return;
          }
          const journey = data.journey;
          route.textContent = `${journey.start_label} → ${journey.end_label}`;
          if (journey.status === 'planned') {
            title.textContent = 'Scheduled trip route';
            intro.textContent = 'This link shows the planned route only. No live location is shared until the trip starts.';
            stopped = true;
            clearInterval(refreshTimer);
            await showPlannedRoute(journey);
            return;
          }
          const point = journey.location;
          if (!point) {
            title.textContent = 'Trip route';
            intro.textContent = 'Live location updates will appear after the traveler shares their first GPS position.';
            await showPlannedRoute(journey);
            status.textContent = 'Journey is active. Waiting for the first location update.';
            return;
          }
          currentPoint = point;
          title.textContent = 'Live journey location';
          intro.textContent = 'Location access is limited to this active journey. This temporary link expires after one hour.';
          if (!map) {
            if (!window.maplibregl) {
              status.textContent = 'The location was received, but map rendering is unavailable in this browser.';
              return;
            }
            map = new maplibregl.Map({
              container: 'shareMap',
              style: 'https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json',
              center: [point.lng, point.lat],
              zoom: 15,
              attributionControl: true,
              dragRotate: false,
              scrollZoom: false,
              doubleClickZoom: false,
              touchZoomRotate: false,
            });
            map.once('load', () => {
              marker = new maplibregl.Marker({ color: '#ff365d' })
                .setLngLat([currentPoint.lng, currentPoint.lat])
                .addTo(map);
            });
          } else if (map.loaded() && marker) {
            marker.setLngLat([point.lng, point.lat]);
            map.easeTo({ center: [point.lng, point.lat], duration: 450 });
          }
          status.textContent = `Last location update: ${new Date(point.recorded_at).toLocaleString()}. Refreshes every 30 seconds.`;
        } catch (error) {
          status.textContent = 'Could not refresh this location. Check your connection.';
        }
      }

      refreshLocation();
    })();
  </script>
</body>
</html>
