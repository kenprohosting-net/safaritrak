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

L.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }
).addTo(sharedMap);

const travelerIcon = L.divIcon({
    className: 'st-marker',
    html:
        '<div style="' +
        'width:18px;' +
        'height:18px;' +
        'background:#176b5b;' +
        'border:4px solid white;' +
        'border-radius:50%;' +
        'box-shadow:0 2px 10px rgba(0,0,0,.25);' +
        '"></div>',
    iconSize: [18, 18],
    iconAnchor: [9, 9]
});

const destinationIcon = L.divIcon({
    className: 'st-marker',
    html:
        '<div style="' +
        'width:18px;' +
        'height:18px;' +
        'background:#c94b4b;' +
        'border:4px solid white;' +
        'border-radius:50%;' +
        'box-shadow:0 2px 10px rgba(0,0,0,.25);' +
        '"></div>',
    iconSize: [18, 18],
    iconAnchor: [9, 9]
});

let travelerMarker = null;
let destinationMarker = null;
let firstLocationReceived = false;

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

        sharedMap.setView(
            position,
            15
        );

        fitMap();
    }
}

function fitMap() {

    const points = [];

    if (travelerMarker) {

        points.push(
            travelerMarker.getLatLng()
        );
    }

    if (destinationMarker) {

        points.push(
            destinationMarker.getLatLng()
        );
    }

    if (points.length >= 2) {

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
    }
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

setInterval(
    getSharedJourney,
    5000
);