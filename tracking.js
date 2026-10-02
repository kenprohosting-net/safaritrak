document.addEventListener('DOMContentLoaded', () => {
    // -------------------------------------------------------------------------
    // 1. State Configuration & Global Variables
    // -------------------------------------------------------------------------
    const journeyId = window.journeyId;
    const isWatcher = window.isWatcher || false;
    const startCoords = window.startCoordinates;
    const destCoords = window.destinationCoordinates;
    let initialTotalDistanceKm = parseFloat(window.totalDistanceKm) || 0;

    if (!journeyId || !document.getElementById('map')) return;

    let map = null;
    let userMarker = null;
    let routePolyline = null;      // Path already covered
    let directionPolyline = null;  // Recommended road directions
    let watchPositionId = null;
    let pollingIntervalId = null;
    
    // Trackers to suppress GPS jitter & OSRM snapping jumps
    let lastRecordedLatLng = null; 
    let lastOSRMQueryLatLng = null;

    const connectionDot = document.getElementById('connectionDot');
    const trackingStatus = document.getElementById('trackingStatus');
    const coveredKmEl = document.getElementById('coveredKm') || document.getElementById('distCoveredEl');
    const remainingKmEl = document.getElementById('remainingKm') || document.getElementById('distRemainingEl');
    const totalKmEl = document.getElementById('totalKm') || document.getElementById('totalDistanceEl');
    const currentSpeedEl = document.getElementById('currentSpeed');
    const locationAccuracyEl = document.getElementById('locationAccuracy');

    // Distance calculation helper (Haversine formula) in meters
    function getDistanceMeters(lat1, lon1, lat2, lon2) {
        const R = 6371e3;
        const φ1 = lat1 * Math.PI / 180;
        const φ2 = lat2 * Math.PI / 180;
        const Δφ = (lat2 - lat1) * Math.PI / 180;
        const Δλ = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
                  Math.cos(φ1) * Math.cos(φ2) *
                  Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    // -------------------------------------------------------------------------
    // 2. Map Instantiation & Initialization
    // -------------------------------------------------------------------------
    const defaultCenter = startCoords ? [startCoords.lat, startCoords.lng] : [0, 0];
    map = L.map('map').setView(defaultCenter, 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const locatorIcon = L.divIcon({
        className: 'st-locator',
        iconSize: [20, 20],
        iconAnchor: [10, 10]
    });

    const pinIcon = L.divIcon({
        className: 'st-pin',
        iconSize: [26, 34],
        iconAnchor: [13, 34]
    });

    if (startCoords) {
        userMarker = L.marker([startCoords.lat, startCoords.lng], { icon: locatorIcon })
            .addTo(map)
            .bindPopup('Traveler Location');
        lastRecordedLatLng = [startCoords.lat, startCoords.lng];
    }

    if (destCoords) {
        L.marker([destCoords.lat, destCoords.lng], { icon: pinIcon })
            .addTo(map)
            .bindPopup('Destination');
    }

    routePolyline = L.polyline([], { color: '#10b981', weight: 4 }).addTo(map);
    directionPolyline = L.polyline([], { color: '#2563eb', weight: 5, dashArray: '8, 8', opacity: 0.8 }).addTo(map);

    function updateStatus(text, active = true) {
        if (trackingStatus) trackingStatus.textContent = text;
        if (connectionDot) {
            connectionDot.style.background = active ? '#10b981' : '#ef4444';
        }
    }

    // OSRM Navigation Route & Metric Synchronization (Throttled by distance)
    async function updateDirectionRoute(currentLat, currentLng, forceUpdate = false) {
        if (!destCoords) return;

        // Skip fetch if traveler hasn't moved at least 30 meters since the last route calculation
        if (!forceUpdate && lastOSRMQueryLatLng) {
            const moveDist = getDistanceMeters(lastOSRMQueryLatLng[0], lastOSRMQueryLatLng[1], currentLat, currentLng);
            if (moveDist < 30) return; 
        }

        try {
            const url = `https://router.project-osrm.org/route/v1/driving/${currentLng},${currentLat};${destCoords.lng},${destCoords.lat}?overview=full&geometries=geojson`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.routes && data.routes.length > 0) {
                const routeCoordinates = data.routes[0].geometry.coordinates.map(coord => [coord[1], coord[0]]);
                directionPolyline.setLatLngs(routeCoordinates);

                const remainingKm = parseFloat((data.routes[0].distance / 1000).toFixed(1));

                if (!initialTotalDistanceKm || initialTotalDistanceKm < remainingKm) {
                    initialTotalDistanceKm = remainingKm;
                    if (totalKmEl) totalKmEl.textContent = `${initialTotalDistanceKm} km`;
                }

                const coveredKm = Math.max(0, (initialTotalDistanceKm - remainingKm)).toFixed(1);

                if (remainingKmEl) remainingKmEl.textContent = `${remainingKm} km`;
                if (coveredKmEl) coveredKmEl.textContent = `${coveredKm} km`;

                lastOSRMQueryLatLng = [currentLat, currentLng];
            }
        } catch (error) {
            console.error('Error fetching road directions:', error);
        }
    }

    // -------------------------------------------------------------------------
    // 3. Broadcaster Mode (Traveler Mode)
    // -------------------------------------------------------------------------
    if (!isWatcher) {
        if (startCoords) {
            updateDirectionRoute(startCoords.lat, startCoords.lng, true);
        }

        if ('geolocation' in navigator) {
            watchPositionId = navigator.geolocation.watchPosition(
                async (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const speed = position.coords.speed ? (position.coords.speed * 3.6).toFixed(1) : 0;
                    const accuracy = position.coords.accuracy ? Math.round(position.coords.accuracy) : null;

                    const latLng = [lat, lng];

                    if (currentSpeedEl) currentSpeedEl.textContent = `${speed} km/h`;
                    if (locationAccuracyEl) locationAccuracyEl.textContent = accuracy ? `${accuracy} m` : 'Unknown';
                    updateStatus('Broadcasting live', true);

                    // Skip position processing entirely if accuracy reading is worse than 50 meters
                    if (accuracy && accuracy > 50) return;

                    if (!userMarker) {
                        userMarker = L.marker(latLng, { icon: locatorIcon }).addTo(map).bindPopup('Traveler Location');
                    } else {
                        userMarker.setLatLng(latLng);
                    }

                    // Only update trail breadcrumbs if moved > 20 meters
                    if (!lastRecordedLatLng || getDistanceMeters(lastRecordedLatLng[0], lastRecordedLatLng[1], lat, lng) > 20) {
                        routePolyline.addLatLng(latLng);
                        lastRecordedLatLng = latLng;
                    }

                    map.setView(latLng);
                    updateDirectionRoute(lat, lng);

                    try {
                        await fetch('backend/api/tracking/update_location.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                journey_id: journeyId,
                                latitude: lat,
                                longitude: lng,
                                speed: speed,
                                accuracy: accuracy
                            })
                        });
                    } catch (err) {
                        updateStatus('Connection error', false);
                    }
                },
                (error) => {
                    console.error('Geolocation error:', error);
                    updateStatus('GPS Error', false);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        } else {
            updateStatus('GPS not supported', false);
        }
    }

    // -------------------------------------------------------------------------
    // 4. Watcher Mode (Shared Journey Polling Mode)
    // -------------------------------------------------------------------------
    if (isWatcher) {
        if (startCoords) {
            updateDirectionRoute(startCoords.lat, startCoords.lng, true);
        }

        async function fetchTravelerLocation() {
            try {
                const response = await fetch(`backend/api/tracking/get_location.php?journey_id=${journeyId}`);
                const data = await response.json();

                if (data.success && data.latitude != null && data.longitude != null) {
                    const lat = parseFloat(data.latitude);
                    const lng = parseFloat(data.longitude);

                    if (!isNaN(lat) && !isNaN(lng)) {
                        const latLng = [lat, lng];

                        if (!userMarker) {
                            userMarker = L.marker(latLng, { icon: locatorIcon }).addTo(map).bindPopup('Traveler Location');
                            map.setView(latLng, 15);
                        } else {
                            userMarker.setLatLng(latLng);
                        }

                        if (!lastRecordedLatLng || getDistanceMeters(lastRecordedLatLng[0], lastRecordedLatLng[1], lat, lng) > 20) {
                            routePolyline.addLatLng(latLng);
                            lastRecordedLatLng = latLng;
                        }

                        if (currentSpeedEl && data.speed !== undefined) {
                            currentSpeedEl.textContent = `${data.speed} km/h`;
                        }

                        updateStatus('Live (Watching)', true);
                        updateDirectionRoute(lat, lng);
                    }
                } else {
                    updateStatus('Waiting for traveler updates...', true);
                }
            } catch (err) {
                console.error('Error fetching traveler location:', err);
                updateStatus('Reconnecting...', false);
            }
        }

        fetchTravelerLocation();
        pollingIntervalId = setInterval(fetchTravelerLocation, 5000);
    }

    // -------------------------------------------------------------------------
    // 5. UI Bindings & Lifecycle Management
    // -------------------------------------------------------------------------
    document.getElementById('myLocationBtn')?.addEventListener('click', () => {
        if (userMarker) {
            map.setView(userMarker.getLatLng(), 15);
        } else if (startCoords) {
            map.setView([startCoords.lat, startCoords.lng], 15);
        }
    });

    window.safariTrakTracking = {
        endJourneyDirect: async () => {
            if (watchPositionId !== null) navigator.geolocation.clearWatch(watchPositionId);
            if (pollingIntervalId !== null) clearInterval(pollingIntervalId);

            try {
                const res = await fetch('backend/api/journeys/end.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ journey_id: journeyId })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = 'my-journeys.php';
                } else {
                    alert(data.message || 'Could not end journey.');
                }
            } catch (err) {
                alert('An error occurred while stopping the journey.');
            }
        }
    };
});