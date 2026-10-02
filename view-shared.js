document.addEventListener('DOMContentLoaded', function () {

    const config =
        window.SafariTrakSharedJourney || {};

    const journeyId =
        parseInt(
            config.journeyId,
            10
        );

    const mapElement =
        document.getElementById(
            'sharedMap'
        );

    if (
        !journeyId ||
        !mapElement ||
        typeof L === 'undefined'
    ) {
        return;
    }

    let map = null;

    let travelerMarker = null;
    let destinationMarker = null;
    let startMarker = null;

    let destinationLine = null;
    let travelledLine = null;

    let accuracyCircle = null;

    let travelledCoordinates = [];

    let lastTravelerPosition = null;

    let totalDistance = 0;

    let updateTimer = null;

    let journeyFinished = false;

    const defaultCenter = [
        -1.286389,
        36.817223
    ];

    function validCoordinates(
        lat,
        lng
    ) {

        return (
            Number.isFinite(lat) &&
            Number.isFinite(lng) &&
            lat >= -90 &&
            lat <= 90 &&
            lng >= -180 &&
            lng <= 180
        );
    }

    function initialiseMap() {

        map =
            L.map(
                mapElement,
                {
                    zoomControl: true,
                    attributionControl: true
                }
            );

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution:
                    '&copy; OpenStreetMap contributors'
            }
        ).addTo(map);

        if (
            validCoordinates(
                config.startLat,
                config.startLng
            )
        ) {

            map.setView(
                [
                    config.startLat,
                    config.startLng
                ],
                14
            );

            createStartMarker(
                config.startLat,
                config.startLng
            );

        } else {

            map.setView(
                defaultCenter,
                13
            );
        }

        if (
            validCoordinates(
                config.destinationLat,
                config.destinationLng
            )
        ) {

            createDestinationMarker(
                config.destinationLat,
                config.destinationLng
            );
        }

        setTimeout(
            function () {
                map.invalidateSize();
            },
            300
        );
    }

    function createStartMarker(
        lat,
        lng
    ) {

        const icon =
            L.divIcon({
                className:
                    'shared-start-marker',

                html: `
                    <div style="
                        width:32px;
                        height:32px;
                        border-radius:50%;
                        background:#087f6b;
                        border:4px solid white;
                        box-shadow:0 2px 8px rgba(0,0,0,.35);
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        color:white;
                        font-size:14px;
                    ">
                        ●
                    </div>
                `,

                iconSize: [
                    32,
                    32
                ],

                iconAnchor: [
                    16,
                    16
                ]
            });

        startMarker =
            L.marker(
                [
                    lat,
                    lng
                ],
                {
                    icon:
                        icon
                }
            )
            .addTo(map)
            .bindPopup(
                '<strong>Starting Point</strong>'
            );
    }

    function createDestinationMarker(
        lat,
        lng
    ) {

        const icon =
            L.divIcon({
                className:
                    'shared-destination-marker',

                html: `
                    <div style="
                        width:38px;
                        height:38px;
                        border-radius:50%;
                        background:#c0392b;
                        border:4px solid white;
                        box-shadow:0 2px 8px rgba(0,0,0,.4);
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        color:white;
                        font-size:18px;
                    ">
                        ⚑
                    </div>
                `,

                iconSize: [
                    38,
                    38
                ],

                iconAnchor: [
                    19,
                    19
                ]
            });

        destinationMarker =
            L.marker(
                [
                    lat,
                    lng
                ],
                {
                    icon:
                        icon
                }
            )
            .addTo(map)
            .bindPopup(
                '<strong>Destination</strong>'
            );
    }

    function createTravelerMarker(
        lat,
        lng
    ) {

        const icon =
            L.divIcon({
                className:
                    'shared-traveler-marker',

                html: `
                    <div style="
                        width:25px;
                        height:25px;
                        border-radius:50%;
                        background:#148f77;
                        border:4px solid white;
                        box-shadow:
                            0 0 0 7px rgba(20,143,119,.20),
                            0 2px 8px rgba(0,0,0,.4);
                    "></div>
                `,

                iconSize: [
                    25,
                    25
                ],

                iconAnchor: [
                    12.5,
                    12.5
                ]
            });

        if (!travelerMarker) {

            travelerMarker =
                L.marker(
                    [
                        lat,
                        lng
                    ],
                    {
                        icon:
                            icon,
                        zIndexOffset:
                            1000
                    }
                )
                .addTo(map)
                .bindPopup(
                    '<strong>Traveler</strong><br>Live location'
                );

        } else {

            travelerMarker.setLatLng(
                [
                    lat,
                    lng
                ]
            );
        }
    }

    function createAccuracyCircle(
        lat,
        lng,
        accuracy
    ) {

        if (
            !Number.isFinite(
                accuracy
            ) ||
            accuracy <= 0
        ) {
            return;
        }

        if (!accuracyCircle) {

            accuracyCircle =
                L.circle(
                    [
                        lat,
                        lng
                    ],
                    {
                        radius:
                            accuracy,

                        color:
                            '#148f77',

                        fillColor:
                            '#148f77',

                        fillOpacity:
                            0.08,

                        weight:
                            1
                    }
                ).addTo(map);

        } else {

            accuracyCircle.setLatLng(
                [
                    lat,
                    lng
                ]
            );

            accuracyCircle.setRadius(
                accuracy
            );
        }
    }

    function distanceBetween(
        lat1,
        lng1,
        lat2,
        lng2
    ) {

        const earthRadius =
            6371;

        const dLat =
            (
                lat2 -
                lat1
            ) *
            Math.PI /
            180;

        const dLng =
            (
                lng2 -
                lng1
            ) *
            Math.PI /
            180;

        const a =
            Math.sin(
                dLat / 2
            ) *
            Math.sin(
                dLat / 2
            ) +
            Math.cos(
                lat1 *
                Math.PI /
                180
            ) *
            Math.cos(
                lat2 *
                Math.PI /
                180
            ) *
            Math.sin(
                dLng / 2
            ) *
            Math.sin(
                dLng / 2
            );

        const c =
            2 *
            Math.atan2(
                Math.sqrt(a),
                Math.sqrt(1 - a)
            );

        return (
            earthRadius *
            c
        );
    }

    function formatDistance(
        distance
    ) {

        if (
            distance === null ||
            distance === undefined ||
            isNaN(distance)
        ) {
            return '--';
        }

        if (distance < 1) {

            return (
                Math.round(
                    distance * 1000
                ) +
                ' m'
            );
        }

        return (
            distance.toFixed(2) +
            ' km'
        );
    }

    function updateText(
        id,
        value
    ) {

        const element =
            document.getElementById(
                id
            );

        if (element) {
            element.textContent =
                value;
        }
    }

    function updateTravelledRoute(
        lat,
        lng
    ) {

        travelledCoordinates.push(
            [
                lat,
                lng
            ]
        );

        if (
            travelledCoordinates.length >
            5000
        ) {

            travelledCoordinates.shift();
        }

        if (!travelledLine) {

            travelledLine =
                L.polyline(
                    travelledCoordinates,
                    {
                        color:
                            '#064f43',

                        weight:
                            6,

                        opacity:
                            0.95,

                        lineJoin:
                            'round',

                        lineCap:
                            'round'
                    }
                ).addTo(map);

        } else {

            travelledLine.setLatLngs(
                travelledCoordinates
            );
        }
    }

    function updateDestinationLine(
        lat,
        lng
    ) {

        if (
            !validCoordinates(
                config.destinationLat,
                config.destinationLng
            )
        ) {
            return;
        }

        const points = [
            [
                lat,
                lng
            ],
            [
                config.destinationLat,
                config.destinationLng
            ]
        ];

        if (!destinationLine) {

            destinationLine =
                L.polyline(
                    points,
                    {
                        color:
                            '#e5a82c',

                        weight:
                            4,

                        opacity:
                            0.9,

                        dashArray:
                            '8,10'
                    }
                ).addTo(map);

        } else {

            destinationLine.setLatLngs(
                points
            );
        }
    }

    function fitJourney() {

        if (!map) {
            return;
        }

        const layers = [];

        if (startMarker) {
            layers.push(
                startMarker
            );
        }

        if (destinationMarker) {
            layers.push(
                destinationMarker
            );
        }

        if (travelerMarker) {
            layers.push(
                travelerMarker
            );
        }

        if (travelledLine) {
            layers.push(
                travelledLine
            );
        }

        if (!layers.length) {
            return;
        }

        const group =
            L.featureGroup(
                layers
            );

        map.fitBounds(
            group.getBounds(),
            {
                padding: [
                    40,
                    40
                ]
            }
        );
    }

    function centerTraveler() {

        if (
            !map ||
            !travelerMarker
        ) {
            return;
        }

        const position =
            travelerMarker.getLatLng();

        map.setView(
            [
                position.lat,
                position.lng
            ],
            Math.max(
                map.getZoom(),
                15
            )
        );
    }

    async function loadSharedLocation() {

        if (
            journeyFinished
        ) {
            return;
        }

        try {

            const response =
                await fetch(
                    'backend/api/journey/watch.php?journey_id=' +
                    encodeURIComponent(
                        journeyId
                    ) +
                    '&_=' +
                    Date.now(),
                    {
                        method:
                            'GET',

                        headers: {
                            'Accept':
                                'application/json'
                        },

                        cache:
                            'no-store'
                    }
                );

            const text =
                await response.text();

            let data;

            try {

                data =
                    JSON.parse(text);

            } catch (error) {

                console.error(
                    'Invalid watch response:',
                    text
                );

                return;
            }

            if (
                !response.ok ||
                !data.success
            ) {

                updateText(
                    'trackingStatus',
                    data.message ||
                    'Unable to load live location.'
                );

                return;
            }

            if (
                data.journey
            ) {

                const status =
                    data.journey.status;

                if (
                    status === 'ended' ||
                    status === 'completed' ||
                    status === 'cancelled'
                ) {

                    journeyFinished =
                        true;

                    updateText(
                        'trackingStatus',
                        'Journey ended'
                    );
                }
            }

            if (
                !data.position
            ) {

                updateText(
                    'trackingStatus',
                    'Waiting for traveler location...'
                );

                return;
            }

            const position =
                data.position;

            const lat =
                parseFloat(
                    position.lat
                );

            const lng =
                parseFloat(
                    position.lng
                );

            if (
                !validCoordinates(
                    lat,
                    lng
                )
            ) {
                return;
            }

            const accuracy =
                parseFloat(
                    position.accuracy || 0
                );

            const speed =
                parseFloat(
                    position.speed_kmh || 0
                );

            if (
                lastTravelerPosition
            ) {

                const moved =
                    distanceBetween(
                        lastTravelerPosition.lat,
                        lastTravelerPosition.lng,
                        lat,
                        lng
                    );

                if (
                    moved > 0.003 &&
                    moved < 2
                ) {

                    totalDistance +=
                        moved;
                }

            } else if (
                validCoordinates(
                    config.startLat,
                    config.startLng
                )
            ) {

                totalDistance =
                    distanceBetween(
                        config.startLat,
                        config.startLng,
                        lat,
                        lng
                    );
            }

            lastTravelerPosition = {
                lat:
                    lat,

                lng:
                    lng
            };

            createTravelerMarker(
                lat,
                lng
            );

            createAccuracyCircle(
                lat,
                lng,
                accuracy
            );

            updateTravelledRoute(
                lat,
                lng
            );

            updateDestinationLine(
                lat,
                lng
            );

            const remaining =
                validCoordinates(
                    config.destinationLat,
                    config.destinationLng
                )
                ?
                distanceBetween(
                    lat,
                    lng,
                    config.destinationLat,
                    config.destinationLng
                )
                :
                null;

            updateText(
                'sharedSpeed',
                speed.toFixed(1) +
                ' km/h'
            );

            updateText(
                'sharedDistanceTravelled',
                formatDistance(
                    totalDistance
                )
            );

            updateText(
                'sharedDistanceRemaining',
                formatDistance(
                    remaining
                )
            );

            const lastUpdate =
                position.created_at
                    ?
                    new Date(
                        position.created_at.replace(
                            ' ',
                            'T'
                        )
                    )
                    :
                    new Date();

            updateText(
                'sharedLastUpdate',
                formatTime(
                    lastUpdate
                )
            );

            updateText(
                'lastUpdateText',
                'Updated ' +
                formatTime(
                    lastUpdate
                )
            );

            if (
                !journeyFinished
            ) {

                updateText(
                    'trackingStatus',
                    'Live location active'
                );
            }

        } catch (error) {

            console.error(
                'Live location error:',
                error
            );

            updateText(
                'trackingStatus',
                'Connection interrupted. Retrying...'
            );
        }
    }

    function formatTime(
        date
    ) {

        if (
            !date ||
            isNaN(
                date.getTime()
            )
        ) {
            return '--';
        }

        return date.toLocaleTimeString(
            [],
            {
                hour:
                    '2-digit',

                minute:
                    '2-digit',

                second:
                    '2-digit'
            }
        );
    }

    function startPolling() {

        loadSharedLocation();

        updateTimer =
            setInterval(
                loadSharedLocation,
                5000
            );
    }

    function connectControls() {

        const centerButton =
            document.getElementById(
                'centerTravelerBtn'
            );

        if (centerButton) {

            centerButton.addEventListener(
                'click',
                function () {

                    centerTraveler();
                }
            );
        }

        const fitButton =
            document.getElementById(
                'fitJourneyBtn'
            );

        if (fitButton) {

            fitButton.addEventListener(
                'click',
                function () {

                    fitJourney();
                }
            );
        }
    }

    window.SafariTrakSharedMap = {

        centerTraveler:
            centerTraveler,

        fitJourney:
            fitJourney,

        refresh:
            loadSharedLocation
    };

    initialiseMap();

    connectControls();

    startPolling();

});