let map = null;

let currentMarker = null;

let destinationMarker = null;

let distanceLine = null;

let distanceLabel = null;

let updateTimer = null;

let firstLoad = true;

const normalLocationIcon = L.divIcon({
    className: 'normal-location-marker',
    html: '<div class="location-dot"></div>',
    iconSize: [30, 30],
    iconAnchor: [15, 15]
});

const destinationIcon = L.divIcon({
    className: 'destination-marker',
    html: '<div class="destination-pin">🏁</div>',
    iconSize: [36, 36],
    iconAnchor: [18, 32]
});

function initializeMap() {

    map = L.map('map').setView(
        [-1.286389, 36.817223],
        13
    );

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            attribution:
                '&copy; OpenStreetMap contributors'
        }
    ).addTo(map);

    loadJourney();

    updateTimer = setInterval(
        loadJourney,
        5000
    );
}

function loadJourney() {

    if (!journeyId) {
        showError('Journey ID is missing.');
        return;
    }

    fetch(
        `../backend/api/journeys/get-shared-journey.php?journey_id=${encodeURIComponent(journeyId)}`
    )
    .then(response => {

        if (!response.ok) {
            throw new Error(
                `HTTP error ${response.status}`
            );
        }

        return response.json();
    })
    .then(data => {

        if (!data.success) {
            showError(
                data.message ||
                'Unable to load journey.'
            );

            return;
        }

        hideError();

        updateJourney(data);
    })
    .catch(error => {

        console.error(
            'Journey loading error:',
            error
        );

        showError(
            'Unable to connect to the journey server.'
        );
    });
}

function updateJourney(data) {

    const latitude =
        parseFloat(data.latitude);

    const longitude =
        parseFloat(data.longitude);

    const destinationLatitude =
        parseFloat(
            data.destination_latitude
        );

    const destinationLongitude =
        parseFloat(
            data.destination_longitude
        );

    if (
        Number.isNaN(latitude) ||
        Number.isNaN(longitude)
    ) {
        return;
    }

    if (
        Number.isNaN(destinationLatitude) ||
        Number.isNaN(destinationLongitude)
    ) {
        return;
    }

    updateCurrentLocation(
        latitude,
        longitude
    );

    updateDestination(
        destinationLatitude,
        destinationLongitude
    );

    updateDistanceLine(
        latitude,
        longitude,
        destinationLatitude,
        destinationLongitude,
        data.distance_remaining_km
    );

    updateInformation(data);

    if (firstLoad) {

        const bounds = L.latLngBounds([
            [
                latitude,
                longitude
            ],
            [
                destinationLatitude,
                destinationLongitude
            ]
        ]);

        map.fitBounds(
            bounds,
            {
                padding: [60, 60],
                maxZoom: 16
            }
        );

        firstLoad = false;
    }
}

function updateCurrentLocation(
    latitude,
    longitude
) {

    const position = [
        latitude,
        longitude
    ];

    if (!currentMarker) {

        currentMarker = L.marker(
            position,
            {
                icon: normalLocationIcon
            }
        ).addTo(map);

        currentMarker.bindPopup(
            'Current location'
        );

    } else {

        currentMarker.setLatLng(
            position
        );
    }
}

function updateDestination(
    latitude,
    longitude
) {

    const position = [
        latitude,
        longitude
    ];

    if (!destinationMarker) {

        destinationMarker = L.marker(
            position,
            {
                icon: destinationIcon
            }
        ).addTo(map);

        destinationMarker.bindPopup(
            'Destination'
        );

    } else {

        destinationMarker.setLatLng(
            position
        );
    }
}

function updateDistanceLine(
    currentLatitude,
    currentLongitude,
    destinationLatitude,
    destinationLongitude,
    distance
) {

    const currentPosition = [
        currentLatitude,
        currentLongitude
    ];

    const destinationPosition = [
        destinationLatitude,
        destinationLongitude
    ];

    if (distanceLine) {
        map.removeLayer(distanceLine);
    }

    distanceLine = L.polyline(
        [
            currentPosition,
            destinationPosition
        ],
        {
            dashArray: '6, 10',
            weight: 3,
            opacity: 0.8
        }
    ).addTo(map);

    const midpointLatitude =
        (
            currentLatitude +
            destinationLatitude
        ) / 2;

    const midpointLongitude =
        (
            currentLongitude +
            destinationLongitude
        ) / 2;

    if (distanceLabel) {
        map.removeLayer(distanceLabel);
    }

    distanceLabel = L.marker(
        [
            midpointLatitude,
            midpointLongitude
        ],
        {
            icon: L.divIcon({
                className:
                    'distance-label',
                html:
                    `<span>
                        📏 ${distance} km
                    </span>`,
                iconSize: [
                    120,
                    30
                ],
                iconAnchor: [
                    60,
                    15
                ]
            }),
            interactive: false
        }
    ).addTo(map);
}

function updateInformation(data) {

    const distanceElement =
        document.getElementById(
            'distanceRemaining'
        );

    const speedElement =
        document.getElementById(
            'currentSpeed'
        );

    const etaElement =
        document.getElementById(
            'eta'
        );

    const statusElement =
        document.getElementById(
            'trackingStatus'
        );

    const destinationElement =
        document.getElementById(
            'destinationName'
        );

    const updatedElement =
        document.getElementById(
            'lastUpdated'
        );

    const liveDot =
        document.getElementById(
            'liveDot'
        );

    if (distanceElement) {

        distanceElement.textContent =
            `${data.distance_remaining_km} km`;
    }

    if (speedElement) {

        speedElement.textContent =
            `${data.speed_kmh} km/h`;
    }

    if (etaElement) {

        if (
            data.eta_minutes !== null &&
            data.eta_minutes !== undefined
        ) {

            etaElement.textContent =
                `${data.eta_minutes} min`;

        } else {

            etaElement.textContent =
                'Calculating...';
        }
    }

    if (statusElement) {

        if (data.status === 'active') {

            statusElement.textContent =
                'Live';

        } else {

            statusElement.textContent =
                'Journey ended';
        }
    }

    if (liveDot) {

        liveDot.style.opacity =
            data.status === 'active'
                ? '1'
                : '0.4';
    }

    if (destinationElement) {

        destinationElement.textContent =
            data.destination_name ||
            'Destination';
    }

    if (updatedElement) {

        updatedElement.textContent =
            formatUpdatedTime(
                data.updated_at
            );
    }
}

function formatUpdatedTime(
    timestamp
) {

    if (!timestamp) {
        return '--';
    }

    const date =
        new Date(
            timestamp.replace(' ', 'T')
        );

    if (Number.isNaN(date.getTime())) {
        return timestamp;
    }

    return date.toLocaleTimeString();
}

function showError(message) {

    const errorElement =
        document.getElementById(
            'mapError'
        );

    if (!errorElement) {
        return;
    }

    errorElement.textContent =
        message;

    errorElement.style.display =
        'block';
}

function hideError() {

    const errorElement =
        document.getElementById(
            'mapError'
        );

    if (!errorElement) {
        return;
    }

    errorElement.style.display =
        'none';
}

window.addEventListener(
    'load',
    initializeMap
);

window.addEventListener(
    'beforeunload',
    function() {

        if (updateTimer) {
            clearInterval(updateTimer);
        }
    }
);