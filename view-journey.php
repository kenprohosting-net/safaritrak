<?php

require_once __DIR__ . '/backend/includes/auth-guard.php';

$db = safaritrak_db();

$journeyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$journey = null;

if ($journeyId > 0) {
    $stmt = $db->prepare(
        'SELECT
            j.id,
            j.user_id,
            j.start_label,
            j.start_lat,
            j.start_lng,
            j.end_label,
            j.end_lat,
            j.end_lng,
            j.distance_km,
            j.status,
            j.started_at,
            j.ended_at,
            u.full_name AS owner_name
         FROM journeys j
         JOIN users u ON u.id = j.user_id
         JOIN journey_shares js ON js.journey_id = j.id
         JOIN trusted_contacts tc ON tc.id = js.trusted_contact_id
         WHERE j.id = ?
           AND tc.contact_user_id = ?
           AND tc.status = "confirmed"
           AND tc.share_live_location = 1
         LIMIT 1'
    );

    $stmt->execute([
        $journeyId,
        $currentUser['id']
    ]);

    $journey = $stmt->fetch();
}

if (!$journey) {
    http_response_code(403);
    ?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>SafariTrak | Journey unavailable</title>
        <link rel="stylesheet" href="dashboard.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    </head>

    <body>

        <div class="app">

            <aside class="sidebar" id="sidebar">

                <div class="brand">
                    <div class="logo">
                        <i class="fa-solid fa-route"></i>
                    </div>

                    <div>
                        <b>SafariTrak</b>
                        <small>Travel smarter</small>
                    </div>
                </div>

                <nav>

                    <a href="index.php">
                        <i class="fa-solid fa-grid-2"></i>
                        Dashboard
                    </a>

                    <a href="my-journeys.php">
                        <i class="fa-solid fa-map-location-dot"></i>
                        My Journeys
                    </a>

                    <a href="live-tracking.php">
                        <i class="fa-solid fa-location-crosshairs"></i>
                        Live Tracking
                    </a>

                    <a href="places.php">
                        <i class="fa-solid fa-map-pin"></i>
                        Places
                    </a>

                    <a href="messages.php">
                        <i class="fa-regular fa-message"></i>
                        Messages
                    </a>

                    <a href="trusted-contacts.php">
                        <i class="fa-solid fa-user-group"></i>
                        Trusted Contacts
                    </a>

                    <a href="safety.php">
                        <i class="fa-solid fa-shield-halved"></i>
                        Safety
                    </a>

                </nav>

                <div class="bottom">

                    <a href="settings.php">
                        <i class="fa-solid fa-gear"></i>
                        Settings
                    </a>

                    <a href="logout.php">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        Logout
                    </a>

                    <div class="account">

                        <span>
                            <?= st_avatar_inner($currentUser) ?>
                        </span>

                        <div>
                            <b><?= htmlspecialchars($userName) ?></b>
                            <small>Traveler</small>
                        </div>

                    </div>

                </div>

            </aside>

            <main>

                <header>

                    <button class="menu" id="menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <div>
                        <label>SHARED JOURNEY</label>
                        <h1>Watch Journey</h1>
                    </div>

                    <div class="head-actions">

                        <div class="avatar">
                            <?= st_avatar_inner($currentUser) ?>
                        </div>

                    </div>

                </header>

                <div class="content">

                    <div class="card">

                        <div class="empty" style="margin:21px">

                            <i class="fa-solid fa-location-crosshairs"></i>

                            <div>

                                <b>Journey unavailable</b>

                                <p>
                                    You do not have permission to view this journey,
                                    or live location sharing has been disabled.
                                </p>

                            </div>

                            <a class="empty-link" href="index.php">
                                Return to dashboard
                            </a>

                        </div>

                    </div>

                </div>

                <footer>
                    &copy; <?= date('Y') ?> SafariTrak
                    <span>Navigate. Track. Share. Connect. Stay Safe.</span>
                </footer>

            </main>

        </div>

    </body>

    </html>
    <?php
    exit;
}

$startLat = $journey['start_lat'] !== null
    ? (float) $journey['start_lat']
    : null;

$startLng = $journey['start_lng'] !== null
    ? (float) $journey['start_lng']
    : null;

$endLat = $journey['end_lat'] !== null
    ? (float) $journey['end_lat']
    : null;

$endLng = $journey['end_lng'] !== null
    ? (float) $journey['end_lng']
    : null;

$totalDistance = $journey['distance_km'] !== null
    ? (float) $journey['distance_km']
    : null;

?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width,initial-scale=1">

    <title>SafariTrak | View Journey</title>

    <link rel="stylesheet" href="dashboard.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <style>
        #sharedMap {
            width: 100%;
            height: 520px;
            border-radius: 0 0 14px 14px;
        }

        .tracking-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .tracking-owner {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .tracking-owner-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #176b5b;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .tracking-owner b {
            display: block;
            font-size: 15px;
        }

        .tracking-owner small {
            color: var(--muted);
        }

        .connection-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
        }

        .connection-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #999;
        }

        .connection-dot.online {
            background: #22a06b;
            box-shadow: 0 0 0 4px rgba(34, 160, 107, .12);
        }

        .connection-dot.offline {
            background: #c94b4b;
            box-shadow: 0 0 0 4px rgba(201, 75, 75, .12);
        }

        .route-info {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            padding: 18px;
        }

        .route-chip {
            border: 1px solid #e8e8e8;
            border-radius: 12px;
            padding: 14px;
            background: #fff;
        }

        .route-chip label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .route-chip strong {
            font-size: 16px;
        }

        .map-card-head {
            padding: 20px 21px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .map-card-head label {
            display: block;
            font-size: 10px;
            font-weight: 800;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .map-card-head h3 {
            margin: 0;
        }

        .map-actions button {
            border: 0;
            background: #176b5b;
            color: white;
            border-radius: 8px;
            padding: 9px 14px;
            cursor: pointer;
            font-weight: 700;
        }

        .journey-route {
            padding: 0 21px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .route-point {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .route-point span {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            display: inline-block;
        }

        .route-start {
            background: #176b5b;
        }

        .route-end {
            background: #c94b4b;
        }

        .route-arrow {
            color: var(--muted);
        }

        .map-status {
            padding: 12px 21px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            font-size: 12px;
        }

        .map-status-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remaining-distance {
            color: #176b5b;
            font-weight: 800;
        }

        .st-traveler-marker {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #176b5b;
            border: 4px solid white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .30);
        }

        .st-destination-marker {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #c94b4b;
            border: 4px solid white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .30);
        }

        .remaining-line {
            stroke-dasharray: 8, 10;
            animation: moveDash 1.2s linear infinite;
        }

        @keyframes moveDash {
            to {
                stroke-dashoffset: -18;
            }
        }

        @media (max-width: 1000px) {

            .route-info {
                grid-template-columns: repeat(3, 1fr);
            }

        }

        @media (max-width: 800px) {

            .route-info {
                grid-template-columns: repeat(2, 1fr);
            }

            #sharedMap {
                height: 420px;
            }

        }

        @media (max-width: 520px) {

            .route-info {
                grid-template-columns: 1fr;
            }

            .tracking-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .journey-route {
                flex-direction: column;
                align-items: flex-start;
            }

        }
    </style>

</head>

<body>

    <div class="app">

        <aside class="sidebar" id="sidebar">

            <div class="brand">

                <div class="logo">
                    <i class="fa-solid fa-route"></i>
                </div>

                <div>
                    <b>SafariTrak</b>
                    <small>Travel smarter</small>
                </div>

            </div>

            <nav>

                <a href="index.php">
                    <i class="fa-solid fa-grid-2"></i>
                    Dashboard
                </a>

                <a href="my-journeys.php">
                    <i class="fa-solid fa-map-location-dot"></i>
                    My Journeys
                </a>

                <a class="active" href="live-tracking.php">
                    <i class="fa-solid fa-location-crosshairs"></i>
                    Live Tracking
                </a>

                <a href="places.php">
                    <i class="fa-solid fa-map-pin"></i>
                    Places
                </a>

                <a href="messages.php">
                    <i class="fa-regular fa-message"></i>
                    Messages
                </a>

                <a href="trusted-contacts.php">
                    <i class="fa-solid fa-user-group"></i>
                    Trusted Contacts
                </a>

                <a href="safety.php">
                    <i class="fa-solid fa-shield-halved"></i>
                    Safety
                </a>

            </nav>

            <div class="bottom">

                <a href="settings.php">
                    <i class="fa-solid fa-gear"></i>
                    Settings
                </a>

                <a href="logout.php">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    Logout
                </a>

                <div class="account">

                    <span>
                        <?= st_avatar_inner($currentUser) ?>
                    </span>

                    <div>
                        <b><?= htmlspecialchars($userName) ?></b>
                        <small>Traveler</small>
                    </div>

                </div>

            </div>

        </aside>

        <main>

            <header>

                <button class="menu" id="menu">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>

                    <label>SHARED JOURNEY</label>

                    <h1>Watch Journey</h1>

                </div>

                <div class="head-actions">

                    <div class="avatar">
                        <?= st_avatar_inner($currentUser) ?>
                    </div>

                </div>

            </header>

            <div class="content">

                <div class="tracking-header">

                    <div class="tracking-owner">

                        <div class="tracking-owner-icon">

                            <i class="fa-solid fa-location-dot"></i>

                        </div>

                        <div>

                            <b>
                                <?= htmlspecialchars($journey['owner_name']) ?>
                                is travelling
                            </b>

                            <small>
                                Live journey tracking
                            </small>

                        </div>

                    </div>

                    <div class="connection-status">

                        <span class="connection-dot" id="connectionDot">
                        </span>

                        <span id="trackingStatus">
                            Connecting...
                        </span>

                    </div>

                </div>

                <div class="card">

                    <div class="map-card-head">

                        <div>

                            <label>LIVE MAP</label>

                            <h3>
                                <?= htmlspecialchars($journey['start_label']) ?>
                                →
                                <?= htmlspecialchars($journey['end_label']) ?>
                            </h3>

                        </div>

                        <div class="map-actions">

                            <button type="button" id="centerTraveler">

                                <i class="fa-solid fa-location-crosshairs"></i>

                                Traveler

                            </button>

                        </div>

                    </div>

                    <div class="journey-route">

                        <div class="route-point">

                            <span class="route-start"></span>

                            <?= htmlspecialchars($journey['start_label']) ?>

                        </div>

                        <div class="route-arrow">

                            <i class="fa-solid fa-arrow-right"></i>

                        </div>

                        <div class="route-point">

                            <span class="route-end"></span>

                            <?= htmlspecialchars($journey['end_label']) ?>

                        </div>

                    </div>

                    <div id="sharedMap"></div>

                    <div class="map-status">

                        <div class="map-status-left">

                            <i class="fa-solid fa-clock"></i>

                            <span>

                                Last update:

                                <strong id="lastUpdate">-</strong>

                            </span>

                        </div>

                        <div>

                            <span id="journeyStatus">

                                <?= htmlspecialchars(ucfirst($journey['status'])) ?>

                            </span>

                        </div>

                    </div>

                    <div class="route-info">

                        <div class="route-chip">

                            <label>ROUTE DISTANCE</label>

                            <strong id="journeyDistance">

                                <?= $totalDistance !== null
                                    ? number_format($totalDistance, 1) . ' km'
                                    : 'Unknown' ?>

                            </strong>

                        </div>

                        <div class="route-chip">

                            <label>REMAINING DISTANCE</label>

                            <strong id="remainingDistance" class="remaining-distance">
                                -
                            </strong>

                        </div>

                        <div class="route-chip">

                            <label>TRAVELER SPEED</label>

                            <strong id="travelerSpeed">
                                -
                            </strong>

                        </div>

                        <div class="route-chip">

                            <label>STARTED</label>

                            <strong>

                                <?= $journey['started_at']
                                    ? (new DateTime($journey['started_at']))->format('g:i A')
                                    : '-' ?>

                            </strong>

                        </div>

                        <div class="route-chip">

                            <label>DESTINATION</label>

                            <strong>

                                <?= htmlspecialchars($journey['end_label']) ?>

                            </strong>

                        </div>

                    </div>

                </div>

            </div>

            <footer>

                &copy; <?= date('Y') ?> SafariTrak

                <span>
                    Navigate. Track. Share. Connect. Stay Safe.
                </span>

            </footer>

        </main>

    </div>

    <script>

        const SHARED_JOURNEY_ID =
            <?= (int) $journey['id'] ?>;

        const SHARED_START_LAT =
            <?= $startLat !== null ? $startLat : 'null' ?>;

        const SHARED_START_LNG =
            <?= $startLng !== null ? $startLng : 'null' ?>;

        const SHARED_END_LAT =
            <?= $endLat !== null ? $endLat : 'null' ?>;

        const SHARED_END_LNG =
            <?= $endLng !== null ? $endLng : 'null' ?>;

    </script>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>

        const defaultCenter =
            SHARED_START_LAT !== null &&
                SHARED_START_LNG !== null
                ? [
                    SHARED_START_LAT,
                    SHARED_START_LNG
                ]
                : [-1.286389, 36.817223];

        const sharedMap =
            L.map('sharedMap')
                .setView(defaultCenter, 12);

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }
        ).addTo(sharedMap);

        const travelerIcon =
            L.divIcon({
                className: 'st-marker',

                html:
                    '<div class="st-traveler-marker"></div>',

                iconSize: [22, 22],

                iconAnchor: [11, 11]
            });

        const destinationIcon =
            L.divIcon({
                className: 'st-marker',

                html:
                    '<div class="st-destination-marker"></div>',

                iconSize: [22, 22],

                iconAnchor: [11, 11]
            });

        let travelerMarker = null;

        let destinationMarker = null;

        let remainingLine = null;

        let firstLocationReceived = false;

        let lastTravelerLat = null;

        let lastTravelerLng = null;

        if (
            SHARED_END_LAT !== null &&
            SHARED_END_LNG !== null
        ) {

            destinationMarker =
                L.marker(
                    [
                        SHARED_END_LAT,
                        SHARED_END_LNG
                    ],
                    {
                        icon: destinationIcon
                    }
                )
                    .addTo(sharedMap)
                    .bindPopup(
                        '<b>Destination</b><br>' +
                        <?= json_encode($journey['end_label']) ?>
                    );

        }

        function calculateDistance(
            lat1,
            lng1,
            lat2,
            lng2
        ) {

            const earthRadius = 6371;

            const dLat =
                (lat2 - lat1) *
                Math.PI / 180;

            const dLng =
                (lng2 - lng1) *
                Math.PI / 180;

            const a =
                Math.sin(dLat / 2) *
                Math.sin(dLat / 2) +

                Math.cos(lat1 * Math.PI / 180) *
                Math.cos(lat2 * Math.PI / 180) *

                Math.sin(dLng / 2) *
                Math.sin(dLng / 2);

            const c =
                2 *
                Math.atan2(
                    Math.sqrt(a),
                    Math.sqrt(1 - a)
                );

            return earthRadius * c;
        }

        function updateRemainingDistance(
            travelerLat,
            travelerLng
        ) {

            if (
                SHARED_END_LAT === null ||
                SHARED_END_LNG === null
            ) {
                return;
            }

            const remaining =
                calculateDistance(
                    travelerLat,
                    travelerLng,
                    SHARED_END_LAT,
                    SHARED_END_LNG
                );

            const remainingElement =
                document.getElementById(
                    'remainingDistance'
                );

            if (remainingElement) {

                if (remaining < 0.05) {

                    remainingElement.textContent =
                        'Arrived';

                } else if (remaining < 1) {

                    remainingElement.textContent =
                        Math.round(
                            remaining * 1000
                        ) +
                        ' m';

                } else {

                    remainingElement.textContent =
                        remaining.toFixed(1) +
                        ' km';

                }

            }

            drawRemainingLine(
                travelerLat,
                travelerLng
            );

        }

        function drawRemainingLine(
            travelerLat,
            travelerLng
        ) {

            if (
                SHARED_END_LAT === null ||
                SHARED_END_LNG === null
            ) {
                return;
            }

            const points = [

                [
                    travelerLat,
                    travelerLng
                ],

                [
                    SHARED_END_LAT,
                    SHARED_END_LNG
                ]

            ];

            if (remainingLine) {

                remainingLine.setLatLngs(
                    points
                );

            } else {

                remainingLine =
                    L.polyline(
                        points,
                        {
                            color: '#176b5b',

                            weight: 4,

                            opacity: 0.9,

                            dashArray: '8, 10',

                            lineCap: 'round'
                        }
                    ).addTo(sharedMap);

            }

        }

        function setStatus(message) {

            const status =
                document.getElementById(
                    'trackingStatus'
                );

            if (status) {

                status.textContent =
                    message;

            }

        }

        function setConnection(
            connected
        ) {

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

        function updateMap(data) {

            const journey =
                data.journey;

            const location =
                data.location;

            const statusElement =
                document.getElementById(
                    'journeyStatus'
                );

            if (statusElement) {

                statusElement.textContent =
                    journey.status
                        ? journey.status.charAt(0).toUpperCase() +
                        journey.status.slice(1)
                        : '-';

            }

            const distanceElement =
                document.getElementById(
                    'journeyDistance'
                );

            if (
                distanceElement &&
                journey.distance_km !== null &&
                journey.distance_km !== undefined
            ) {

                distanceElement.textContent =
                    Number(
                        journey.distance_km
                    ).toFixed(1) +
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

            const lat =
                parseFloat(
                    location.lat
                );

            const lng =
                parseFloat(
                    location.lng
                );

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

                travelerMarker =
                    L.marker(
                        position,
                        {
                            icon: travelerIcon
                        }
                    )
                        .addTo(sharedMap)
                        .bindPopup(
                            '<b>' +
                            <?= json_encode($journey['owner_name']) ?> +
                            '</b><br>Current location'
                        );

            } else {

                travelerMarker.setLatLng(
                    position
                );

            }

            lastTravelerLat =
                lat;

            lastTravelerLng =
                lng;

            updateRemainingDistance(
                lat,
                lng
            );

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

                    speedElement.textContent =
                        '-';

                }

            }

            const lastUpdateElement =
                document.getElementById(
                    'lastUpdate'
                );

            if (lastUpdateElement) {

                if (location.updated_at) {

                    const date =
                        new Date(
                            location.updated_at.replace(
                                ' ',
                                'T'
                            )
                        );

                    lastUpdateElement.textContent =
                        date.toLocaleTimeString();

                } else {

                    lastUpdateElement.textContent =
                        new Date().toLocaleTimeString();

                }

            }

            setConnection(true);

            setStatus(
                'Live location connected'
            );

            if (!firstLocationReceived) {

                firstLocationReceived =
                    true;

                sharedMap.setView(
                    position,
                    15
                );

                fitMap();

            }

        }

        async function getSharedJourney() {

            try {

                const response =
                    await fetch(
                        'backend/api/journeys/view-shared.php?journey_id=' +
                        encodeURIComponent(
                            SHARED_JOURNEY_ID
                        ),
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

                const data =
                    await response.json();

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

        document
            .getElementById(
                'centerTraveler'
            )
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

    </script>

    <script src="dashboard.js"></script>

</body>

</html>