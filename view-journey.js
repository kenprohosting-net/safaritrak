const defaultCenter =
    (SHARED_START_LAT !== null &&
     SHARED_START_LNG !== null)
        ? [
            SHARED_START_LAT,
            SHARED_START_LNG
          ]
        : [-1.286389, 36.817223];

const sharedMap = L.map('sharedMap').setView(
    defaultCenter,
    12
);

window.SafariTrakMapStyle.attachLeafletBase(sharedMap);

function createJourneyMarker(color) {
    return L.divIcon({
        className: 'st-marker',
        html: `<div style="width:18px;height:18px;background:${color};border:4px solid white;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.28)"></div>`,
        iconSize: [18, 18],
        iconAnchor: [9, 9]
    });
}

const trackingColors = window.SafariTrakMapStyle.getTrackingColors();
const travelerIcon = createJourneyMarker(trackingColors.user);
const startIcon = createJourneyMarker(trackingColors.start);
const destinationIcon = createJourneyMarker(trackingColors.destination);
const getJourneyRouteStroke = () => {
    const colors = window.SafariTrakMapStyle.getRouteColors();
    const status = document.getElementById('journeyStatus')?.textContent?.trim().toLowerCase();
    return ['ended', 'completed'].includes(status) ? colors.completedStroke : colors.stroke;
};

let travelerMarker = null;
let deviceLocationMarker = null;
let destinationMarker = null;
let startMarker = null;
let roadRouteLine = null;
let firstLocationReceived = false;
let hasCenteredOnDeviceLocation = false;
const roadRouteStatus = document.getElementById('journeyRoadRouteStatus');

if (SHARED_START_LAT !== null && SHARED_START_LNG !== null) {
    startMarker = L.marker([SHARED_START_LAT, SHARED_START_LNG], {
        icon: startIcon
    })
    .addTo(sharedMap)
    .bindPopup('<b>Trip start</b>');
}

if (
    SHARED_END_LAT !== null &&
    SHARED_END_LNG !== null
) {
    destinationMarker = L.marker(
        [
            SHARED_END_LAT,
            SHARED_END_LNG
        ],
        {
            icon: destinationIcon
        }
    )
    .addTo(sharedMap)
    .bindPopup('<b>Destination</b>');
}

if (SHARED_START_LAT !== null && SHARED_START_LNG !== null
    && SHARED_END_LAT !== null && SHARED_END_LNG !== null) {
    window.SafariTrakRouteDirections.getDrivingRoute(
        { lat: SHARED_START_LAT, lng: SHARED_START_LNG },
        { lat: SHARED_END_LAT, lng: SHARED_END_LNG }
    ).then(route => {
        roadRouteLine = L.polyline(
            route.coordinates.map(([lng, lat]) => [lat, lng]),
            { color: getJourneyRouteStroke(), weight: 5, opacity: 0.9, dashArray: '9 7' }
        ).addTo(sharedMap);
        if (!hasCenteredOnDeviceLocation) {
            if (!window.SafariTrakRouteDirections.focusMobileLeafletRoute(
                sharedMap,
                roadRouteLine.getLatLngs(),
                [SHARED_START_LAT, SHARED_START_LNG]
            )) {
                sharedMap.fitBounds(roadRouteLine.getBounds(), { padding: [50, 50] });
            }
        }
        if (roadRouteStatus) {
            roadRouteStatus.textContent = `Road route · ${(route.distance_meters / 1000).toFixed(1)} km · about ${Math.max(1, Math.round(route.duration_seconds / 60))} min.`;
        }
    }).catch(error => {
        roadRouteLine = L.polyline(
            [[SHARED_START_LAT, SHARED_START_LNG], [SHARED_END_LAT, SHARED_END_LNG]],
            {
                color: getJourneyRouteStroke(),
                weight: 5,
                opacity: 0.75,
                dashArray: '5 8'
            }
        ).addTo(sharedMap);
        if (!hasCenteredOnDeviceLocation) {
            if (!window.SafariTrakRouteDirections.focusMobileLeafletRoute(
                sharedMap,
                roadRouteLine.getLatLngs(),
                [SHARED_START_LAT, SHARED_START_LNG]
            )) {
                sharedMap.fitBounds(roadRouteLine.getBounds(), { padding: [50, 50] });
            }
        }
        if (roadRouteStatus) {
            roadRouteStatus.textContent = `Road route unavailable. The dashed line is an approximate direction, not a road route. ${error.message || ''}`.trim();
        }
        console.error('Shared journey road route could not be loaded.', error);
    });
} else if (roadRouteStatus) {
    roadRouteStatus.textContent = 'Saved start or destination coordinates are unavailable for this journey.';
}

async function getSharedJourney() {

    try {

        const response = await fetch(
            'backend/api/journeys/view-shared.php?journey_id=' +
            encodeURIComponent(SHARED_JOURNEY_ID),
            {
                method: 'GET',
                cache: 'no-store'
            }
        );

        if (!response.ok) {
            throw new Error(
                'Unable to retrieve journey.'
            );
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(
                data.message ||
                'Journey could not be loaded.'
            );
        }

        updateMap(data);

    } catch (error) {

        console.error(error);

        setConnection(false);

        setStatus(
            'Unable to update location'
        );
    }
}

function updateMap(data) {

    const journey = data.journey;
    const location = data.location;

    const statusElement =
        document.getElementById('journeyStatus');

    if (statusElement) {

        statusElement.textContent =
            journey.status
                ? journey.status.charAt(0).toUpperCase() +
                  journey.status.slice(1)
                : '-';
    }
    if (roadRouteLine) {
        roadRouteLine.setStyle({ color: getJourneyRouteStroke() });
    }

    const distanceElement =
        document.getElementById('journeyDistance');

    if (
        distanceElement &&
        journey.distance_km !== null &&
        journey.distance_km !== undefined
    ) {

        distanceElement.textContent =
            Number(journey.distance_km).toFixed(1) +
            ' km';
    }

    if (
        !location ||
        location.lat === null ||
        location.lng === null
    ) {

        setConnection(false);

        setStatus(
            'Waiting for traveler location'
        );

        return;
    }

    const lat = parseFloat(location.lat);
    const lng = parseFloat(location.lng);

    if (
        Number.isNaN(lat) ||
        Number.isNaN(lng)
    ) {

        setConnection(false);

        setStatus(
            'Location unavailable'
        );

        return;
    }

    const position = [
        lat,
        lng
    ];

    if (!travelerMarker) {

        travelerMarker = L.marker(
            position,
            {
                icon: travelerIcon
            }
        )
        .addTo(sharedMap)
        .bindPopup(
            '<b>Traveler location</b>'
        );

    } else {

        travelerMarker.setLatLng(
            position
        );
    }

    const speedElement =
        document.getElementById(
            'travelerSpeed'
        );

    if (speedElement) {

        if (
            location.speed_kmh !== null &&
            location.speed_kmh !== undefined
        ) {

            speedElement.textContent =
                Number(
                    location.speed_kmh
                ).toFixed(0) +
                ' km/h';

        } else {

            speedElement.textContent = '-';
        }
    }

    const lastUpdateElement =
        document.getElementById(
            'lastUpdate'
        );

    if (lastUpdateElement) {

        lastUpdateElement.textContent =
            new Date().toLocaleTimeString();
    }

    setConnection(true);

    setStatus(
        'Live location connected'
    );

    if (!firstLocationReceived) {

        firstLocationReceived = true;

        if (!hasCenteredOnDeviceLocation) {
            sharedMap.setView(position, 15);
        }

        fitMap();
    }
}

function fitMap() {
    if (hasCenteredOnDeviceLocation) return;

    const points = [];

    if (travelerMarker) {

        points.push(
            travelerMarker.getLatLng()
        );
    }

    if (startMarker) {
        points.push(startMarker.getLatLng());
    }

    if (destinationMarker) {

        points.push(
            destinationMarker.getLatLng()
        );
    }

    if (points.length >= 2) {

        if (window.SafariTrakRouteDirections.focusMobileLeafletRoute(
            sharedMap,
            roadRouteLine?.getLatLngs() || points,
            travelerMarker?.getLatLng() || startMarker?.getLatLng()
        )) {
            return;
        }

        const bounds =
            L.latLngBounds(points);

        sharedMap.fitBounds(
            bounds,
            {
                padding: [
                    50,
                    50
                ]
            }
        );
    } else if (points.length === 1) {
        sharedMap.setView(points[0], 13);
    }
}

function centerOnDeviceLocation(position) {
    const point = [position.coords.latitude, position.coords.longitude];
    hasCenteredOnDeviceLocation = true;
    if (!deviceLocationMarker) {
        deviceLocationMarker = L.marker(point, { icon: travelerIcon, zIndexOffset: 1000 })
            .addTo(sharedMap)
            .bindPopup('<b>Your location on this device</b>');
    } else {
        deviceLocationMarker.setLatLng(point);
    }
    sharedMap.setView(point, 16);
}

function setStatus(message) {

    const status =
        document.getElementById(
            'trackingStatus'
        );

    if (status) {
        status.textContent = message;
    }
}

function setConnection(connected) {

    const dot =
        document.getElementById(
            'connectionDot'
        );

    if (!dot) {
        return;
    }

    dot.classList.remove(
        'online',
        'offline'
    );

    dot.classList.add(
        connected
            ? 'online'
            : 'offline'
    );
}

document
    .getElementById('centerTraveler')
    ?.addEventListener(
        'click',
        () => {

            if (!travelerMarker) {

                alert(
                    'The traveler location is not available yet.'
                );

                return;
            }

            sharedMap.setView(
                travelerMarker.getLatLng(),
                16
            );

            travelerMarker.openPopup();
        }
    );

getSharedJourney();
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
        centerOnDeviceLocation,
        () => setStatus('Device location is unavailable; the shared journey remains visible.'),
        { enableHighAccuracy: true, timeout: 12_000, maximumAge: 60_000 }
    );
}

setInterval(
    getSharedJourney,
    5000
);