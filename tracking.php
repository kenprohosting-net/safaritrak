<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/session.php';

st_start_session();

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    header('Location: login.php');
    exit;
}

$journeyId =
    isset($_GET['journey_id'])
        ? (int)$_GET['journey_id']
        : 0;

$journey = null;
$pageError = null;

if ($journeyId <= 0) {

    $pageError =
        'The journey could not be identified.';

} else {

    try {

        $db = safaritrak_db();

        $stmt = $db->prepare(
            'SELECT
                id,
                user_id,
                start_label,
                start_lat,
                start_lng,
                end_label,
                end_lat,
                end_lng,
                transport_mode,
                distance_km,
                status,
                started_at,
                ended_at
             FROM journeys
             WHERE id = ?
             AND user_id = ?
             LIMIT 1'
        );

        $stmt->execute([
            $journeyId,
            $userId
        ]);

        $journey =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$journey) {

            $pageError =
                'That journey could not be found.';
        }

    } catch (Throwable $e) {

        $pageError =
            'Unable to load this journey.';
    }
}

$startLabel =
    $journey['start_label']
    ?? 'Starting point';

$endLabel =
    $journey['end_label']
    ?? 'Destination';

$startLat =
    isset($journey['start_lat'])
        ? (float)$journey['start_lat']
        : null;

$startLng =
    isset($journey['start_lng'])
        ? (float)$journey['start_lng']
        : null;

$endLat =
    isset($journey['end_lat'])
        ? (float)$journey['end_lat']
        : null;

$endLng =
    isset($journey['end_lng'])
        ? (float)$journey['end_lng']
        : null;

$status =
    strtolower(
        trim(
            (string)($journey['status'] ?? '')
        )
    );

$isActive =
    $status === 'active' ||
    $status === 'in_progress';

$isCompleted =
    $status === 'completed';

$isCancelled =
    $status === 'cancelled';

$userName =
    $_SESSION['full_name']
    ?? $_SESSION['name']
    ?? $_SESSION['username']
    ?? 'Traveler';

$userInitial =
    strtoupper(
        substr(
            trim($userName),
            0,
            1
        )
    );

if ($userInitial === '') {
    $userInitial = 'T';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
SafariTrak - Journey Tracking
</title>

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7f6;
    color: #263633;
}

.topbar {
    height: 68px;
    background: white;
    border-bottom: 1px solid #e2e8e6;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 25px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.brand-icon {
    width: 38px;
    height: 38px;
    background: #10a77e;
    color: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.brand strong {
    font-size: 16px;
}

.brand small {
    display: block;
    color: #899391;
    font-size: 9px;
    margin-top: 2px;
}

.profile {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #e4f1ed;
    color: #087d67;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.content {
    max-width: 1250px;
    margin: auto;
    padding: 22px;
}

.heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.heading h1 {
    margin: 0;
    font-size: 21px;
}

.heading p {
    margin: 5px 0 0;
    color: #788683;
    font-size: 11px;
}

.back-button {
    text-decoration: none;
    background: #edf3f1;
    color: #38524c;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: bold;
}

.tracking-layout {
    display: grid;
    grid-template-columns: 310px 1fr;
    gap: 18px;
}

.panel {
    background: white;
    border: 1px solid #dfe7e4;
    border-radius: 15px;
    overflow: hidden;
}

.panel-header {
    padding: 17px;
    border-bottom: 1px solid #edf1f0;
}

.panel-header h2 {
    margin: 0;
    font-size: 14px;
}

.panel-header p {
    margin: 5px 0 0;
    color: #7e8987;
    font-size: 10px;
}

.route-section {
    padding: 18px;
}

.route-row {
    display: grid;
    grid-template-columns: 25px 1fr;
    column-gap: 11px;
}

.route-visual {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.route-point {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    flex-shrink: 0;
}

.route-point.start {
    background: #12846e;
}

.route-point.destination {
    background: #d94e4e;
}

.route-line {
    width: 3px;
    min-height: 55px;
    background: #b8d5cc;
}

.route-information strong {
    display: block;
    font-size: 10px;
    color: #87918f;
    text-transform: uppercase;
}

.route-information span {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    font-weight: bold;
    line-height: 1.4;
}

.status-box {
    margin: 0 18px 18px;
    padding: 12px;
    border-radius: 10px;
    background: #e9f7f1;
    color: #14785f;
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 10px;
    font-weight: bold;
}

.status-box.completed {
    background: #e9f7ef;
    color: #198754;
}

.status-box.cancelled {
    background: #fdecec;
    color: #c0392b;
}

.status-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #13a577;
}

.status-box.completed .status-dot {
    background: #198754;
}

.status-box.cancelled .status-dot {
    background: #c0392b;
}

.stats {
    padding: 0 18px 18px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.stat {
    background: #f7faf9;
    padding: 11px;
    border-radius: 9px;
}

.stat small {
    display: block;
    color: #8a9693;
    font-size: 8px;
}

.stat strong {
    display: block;
    margin-top: 4px;
    font-size: 11px;
}

.actions {
    padding: 18px;
    border-top: 1px solid #edf1f0;
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.action-button {
    width: 100%;
    border: none;
    border-radius: 9px;
    padding: 12px 15px;
    font-size: 11px;
    font-weight: bold;
    cursor: pointer;
}

.share-button {
    background: #147968;
    color: white;
}

.end-journey-button {
    background: #c94c4c;
    color: white;
}

.end-journey-button:hover {
    background: #aa3d3d;
}

.end-journey-button:disabled {
    opacity: .6;
    cursor: not-allowed;
}

.map-panel {
    min-height: 620px;
    position: relative;
}

#map {
    width: 100%;
    height: 620px;
}

.map-status {
    position: absolute;
    top: 14px;
    left: 14px;
    z-index: 500;
    background: white;
    border-radius: 9px;
    padding: 9px 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.12);
    font-size: 10px;
    font-weight: bold;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(17,30,27,.58);
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-overlay.show {
    display: flex;
}

.modal {
    width: 100%;
    max-width: 420px;
    background: white;
    border-radius: 17px;
    padding: 28px;
    text-align: center;
    box-shadow: 0 25px 70px rgba(0,0,0,.3);
}

.modal-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 16px;
    border-radius: 50%;
    background: #fff0f0;
    color: #c94c4c;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 23px;
}

.modal.success .modal-icon {
    background: #e8f7f1;
    color: #148369;
}

.modal h3 {
    margin: 0;
    font-size: 18px;
}

.modal p {
    margin: 10px 0 23px;
    color: #75817f;
    font-size: 12px;
    line-height: 1.6;
}

.modal-buttons {
    display: flex;
    justify-content: center;
    gap: 9px;
}

.modal-button {
    border: none;
    border-radius: 9px;
    padding: 11px 19px;
    cursor: pointer;
    font-size: 11px;
    font-weight: bold;
}

.modal-cancel {
    background: #edf2f0;
    color: #40514d;
}

.modal-confirm {
    background: #c94c4c;
    color: white;
}

.modal-success {
    background: #147968;
    color: white;
}

.modal-button:disabled {
    opacity: .6;
}

@media(max-width:850px) {

    .tracking-layout {
        grid-template-columns: 1fr;
    }

    #map {
        height: 450px;
    }

}

</style>

</head>

<body>

<header class="topbar">

<div class="brand">

<div class="brand-icon">
<i class="fas fa-route"></i>
</div>

<div>

<strong>SafariTrak</strong>

<small>
Live journey tracking
</small>

</div>

</div>

<div class="profile">
<?= htmlspecialchars($userInitial) ?>
</div>

</header>

<main class="content">

<?php if ($pageError): ?>

<div style="
    background:white;
    padding:40px;
    border-radius:15px;
    text-align:center;
">

<h2>
Journey unavailable
</h2>

<p>
<?= htmlspecialchars($pageError) ?>
</p>

<a href="my-journeys.php">
Back to My Journeys
</a>

</div>

<?php else: ?>

<div class="heading">

<div>

<h1>

<?php if ($isCompleted): ?>

Journey Completed

<?php elseif ($isCancelled): ?>

Journey Cancelled

<?php else: ?>

Journey in Progress

<?php endif; ?>

</h1>

<p>

<?php if ($isCompleted): ?>

This journey has already been completed.

<?php elseif ($isCancelled): ?>

This journey has been cancelled.

<?php else: ?>

Follow your route and monitor your current location.

<?php endif; ?>

</p>

</div>

<a
    href="my-journeys.php"
    class="back-button"
>

<i class="fas fa-arrow-left"></i>

My Journeys

</a>

</div>

<div class="tracking-layout">

<section class="panel">

<div class="panel-header">

<h2>
Current Journey
</h2>

<p>
Your route and journey information
</p>

</div>

<div class="route-section">

<div class="route-row">

<div class="route-visual">

<div class="route-point start"></div>

<div class="route-line"></div>

<div class="route-point destination"></div>

</div>

<div class="route-information">

<div style="padding-bottom:45px;">

<strong>
Starting point
</strong>

<span>
<?= htmlspecialchars($startLabel) ?>
</span>

</div>

<div>

<strong>
Destination
</strong>

<span>
<?= htmlspecialchars($endLabel) ?>
</span>

</div>

</div>

</div>

</div>

<div
    class="status-box
    <?= $isCompleted ? 'completed' : '' ?>
    <?= $isCancelled ? 'cancelled' : '' ?>"
>

<div class="status-dot"></div>

<span id="trackingStatus">

<?php if ($isCompleted): ?>

Journey completed

<?php elseif ($isCancelled): ?>

Journey cancelled

<?php else: ?>

Journey in progress

<?php endif; ?>

</span>

</div>

<div class="stats">

<div class="stat">

<small>
Current speed
</small>

<strong id="speedValue">
0.0 km/h
</strong>

</div>

<div class="stat">

<small>
Distance travelled
</small>

<strong id="distanceValue">
<?= $journey['distance_km'] !== null
    ? number_format(
        (float)$journey['distance_km'],
        2
    ) . ' km'
    : '-- km'
?>
</strong>

</div>

<div class="stat">

<small>
Distance remaining
</small>

<strong id="remainingValue">
-- km
</strong>

</div>

<div class="stat">

<small>
GPS accuracy
</small>

<strong id="accuracyValue">
-- m
</strong>

</div>

</div>

<div class="actions">

<?php if ($isActive): ?>

<button
    type="button"
    class="action-button share-button"
    id="shareJourneyBtn"
>

<i class="fas fa-share-nodes"></i>

Share Journey

</button>

<button
    type="button"
    class="action-button end-journey-button"
    id="endJourneyBtn"
>

<i class="fas fa-flag-checkered"></i>

End Journey

</button>

<?php elseif ($isCompleted): ?>

<div style="
    text-align:center;
    padding:8px;
    color:#198754;
    font-size:11px;
    font-weight:bold;
">

<i class="fas fa-circle-check"></i>

This journey is completed.

</div>

<?php elseif ($isCancelled): ?>

<div style="
    text-align:center;
    padding:8px;
    color:#c0392b;
    font-size:11px;
    font-weight:bold;
">

<i class="fas fa-circle-xmark"></i>

This journey was cancelled.

</div>

<?php endif; ?>

</div>

</section>

<section class="panel map-panel">

<div class="map-status">

<span id="mapStatus">

<?php if ($isActive): ?>

Waiting for GPS...

<?php elseif ($isCompleted): ?>

Journey completed

<?php else: ?>

Journey cancelled

<?php endif; ?>

</span>

</div>

<div id="map"></div>

</section>

</div>

<?php endif; ?>

</main>


<div
    class="modal-overlay"
    id="endJourneyModal"
>

<div class="modal">

<div class="modal-icon">

<i class="fas fa-flag-checkered"></i>

</div>

<h3>
End Journey?
</h3>

<p>
Are you sure you want to end this journey?
Your GPS tracking and live location sharing will stop.
</p>

<div class="modal-buttons">

<button
    type="button"
    class="modal-button modal-cancel"
    id="cancelEndJourney"
>
Cancel
</button>

<button
    type="button"
    class="modal-button modal-confirm"
    id="confirmEndJourney"
>
End Journey
</button>

</div>

</div>

</div>


<div
    class="modal-overlay"
    id="resultModal"
>

<div class="modal success">

<div class="modal-icon">

<i class="fas fa-check"></i>

</div>

<h3 id="resultTitle">
Journey Ended
</h3>

<p id="resultMessage">
Your journey has ended successfully.
</p>

<div class="modal-buttons">

<button
    type="button"
    class="modal-button modal-success"
    id="resultButton"
>
Go to My Journeys
</button>

</div>

</div>

</div>


<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>

const JOURNEY_ID =
    <?= json_encode($journeyId) ?>;

const START_LAT =
    <?= json_encode($startLat) ?>;

const START_LNG =
    <?= json_encode($startLng) ?>;

const END_LAT =
    <?= json_encode($endLat) ?>;

const END_LNG =
    <?= json_encode($endLng) ?>;

const JOURNEY_STATUS =
    <?= json_encode($status) ?>;

const JOURNEY_ACTIVE =
    <?= $isActive ? 'true' : 'false' ?>;


let map = null;

let currentMarker = null;

let startMarker = null;

let destinationMarker = null;

let routeLine = null;

let accuracyCircle = null;

let watchId = null;

let journeyEnded =
    !JOURNEY_ACTIVE;


const endJourneyBtn =
    document.getElementById(
        'endJourneyBtn'
    );

const endJourneyModal =
    document.getElementById(
        'endJourneyModal'
    );

const cancelEndJourney =
    document.getElementById(
        'cancelEndJourney'
    );

const confirmEndJourney =
    document.getElementById(
        'confirmEndJourney'
    );

const resultModal =
    document.getElementById(
        'resultModal'
    );

const resultTitle =
    document.getElementById(
        'resultTitle'
    );

const resultMessage =
    document.getElementById(
        'resultMessage'
    );

const resultButton =
    document.getElementById(
        'resultButton'
    );


function showResult(
    title,
    message
) {

    if (!resultModal) {
        return;
    }

    resultTitle.textContent =
        title;

    resultMessage.textContent =
        message;

    resultModal.classList.add(
        'show'
    );
}


function stopGPS() {

    if (watchId !== null) {

        navigator.geolocation.clearWatch(
            watchId
        );

        watchId = null;
    }
}


function startGPS() {

    if (!JOURNEY_ACTIVE) {
        return;
    }

    if (!navigator.geolocation) {

        document.getElementById(
            'mapStatus'
        ).textContent =
            'GPS is not supported';

        return;
    }

    watchId =
        navigator.geolocation.watchPosition(

            function(position) {

                if (journeyEnded) {
                    return;
                }

                const lat =
                    position.coords.latitude;

                const lng =
                    position.coords.longitude;

                const accuracy =
                    position.coords.accuracy || 0;

                const speed =
                    position.coords.speed || 0;

                updateCurrentMarker(
                    [lat, lng],
                    accuracy
                );

                document.getElementById(
                    'speedValue'
                ).textContent =
                    (
                        speed * 3.6
                    ).toFixed(1) +
                    ' km/h';

                document.getElementById(
                    'accuracyValue'
                ).textContent =
                    Math.round(
                        accuracy
                    ) +
                    ' m';

                document.getElementById(
                    'mapStatus'
                ).textContent =
                    'GPS connected';

                sendPosition(
                    lat,
                    lng,
                    speed,
                    accuracy
                );

            },

            function() {

                document.getElementById(
                    'mapStatus'
                ).textContent =
                    'GPS unavailable';

            },

            {
                enableHighAccuracy: true,
                maximumAge: 3000,
                timeout: 15000
            }
        );
}


function updateCurrentMarker(
    latLng,
    accuracy
) {

    const icon =
        L.divIcon({

            className: '',

            html:
                '<div style="' +
                'width:18px;' +
                'height:18px;' +
                'border-radius:50%;' +
                'background:#147968;' +
                'border:4px solid white;' +
                'box-shadow:0 2px 10px rgba(0,0,0,.4);' +
                '"></div>',

            iconSize: [
                18,
                18
            ],

            iconAnchor: [
                9,
                9
            ]

        });


    if (!currentMarker) {

        currentMarker =
            L.marker(
                latLng,
                {
                    icon: icon
                }
            )
            .addTo(map)
            .bindPopup(
                'Your current location'
            );

    } else {

        currentMarker.setLatLng(
            latLng
        );
    }


    if (accuracy > 0) {

        if (!accuracyCircle) {

            accuracyCircle =
                L.circle(
                    latLng,
                    {
                        radius: accuracy,
                        color: '#147968',
                        fillOpacity: .08
                    }
                ).addTo(map);

        } else {

            accuracyCircle.setLatLng(
                latLng
            );

            accuracyCircle.setRadius(
                accuracy
            );
        }
    }
}


async function sendPosition(
    latitude,
    longitude,
    speed,
    accuracy
) {

    if (!JOURNEY_ACTIVE || journeyEnded) {
        return;
    }

    try {

        const response =
            await fetch(
                './backend/api/journey/update-position.php',
                {
                    method: 'POST',

                    credentials:
                        'same-origin',

                    headers: {
                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json'
                    },

                    body:
                        JSON.stringify({

                            journey_id:
                                JOURNEY_ID,

                            latitude:
                                latitude,

                            longitude:
                                longitude,

                            speed_kmh:
                                speed * 3.6,

                            accuracy:
                                accuracy
                        })
                }
            );


        if (!response.ok) {
            return;
        }


        const data =
            await response.json();


        if (
            data.success &&
            data.distance_remaining !==
            undefined
        ) {

            document.getElementById(
                'remainingValue'
            ).textContent =
                Number(
                    data.distance_remaining
                ).toFixed(2) +
                ' km';
        }


        if (
            data.success &&
            data.distance_travelled !==
            undefined
        ) {

            document.getElementById(
                'distanceValue'
            ).textContent =
                Number(
                    data.distance_travelled
                ).toFixed(2) +
                ' km';
        }

    } catch (error) {

        console.log(
            'Unable to update location.'
        );
    }
}


function createMap() {

    map =
        L.map(
            'map'
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
        START_LAT !== null &&
        START_LNG !== null
    ) {

        const startIcon =
            L.divIcon({

                className: '',

                html:
                    '<div style="' +
                    'width:28px;' +
                    'height:28px;' +
                    'border-radius:50%;' +
                    'background:#147968;' +
                    'border:3px solid white;' +
                    'display:flex;' +
                    'align-items:center;' +
                    'justify-content:center;' +
                    'color:white;' +
                    '"><i class="fas fa-play"></i></div>',

                iconSize: [
                    28,
                    28
                ],

                iconAnchor: [
                    14,
                    14
                ]

            });


        startMarker =
            L.marker(
                [
                    START_LAT,
                    START_LNG
                ],
                {
                    icon: startIcon
                }
            )
            .addTo(map)
            .bindPopup(
                'Starting Point'
            );
    }


    if (
        END_LAT !== null &&
        END_LNG !== null
    ) {

        const destinationIcon =
            L.divIcon({

                className: '',

                html:
                    '<div style="' +
                    'width:30px;' +
                    'height:30px;' +
                    'border-radius:50%;' +
                    'background:#d94e4e;' +
                    'border:3px solid white;' +
                    'display:flex;' +
                    'align-items:center;' +
                    'justify-content:center;' +
                    'color:white;' +
                    '"><i class="fas fa-flag-checkered"></i></div>',

                iconSize: [
                    30,
                    30
                ],

                iconAnchor: [
                    15,
                    15
                ]

            });


        destinationMarker =
            L.marker(
                [
                    END_LAT,
                    END_LNG
                ],
                {
                    icon:
                        destinationIcon
                }
            )
            .addTo(map)
            .bindPopup(
                'Destination'
            );
    }


    if (
        START_LAT !== null &&
        START_LNG !== null &&
        END_LAT !== null &&
        END_LNG !== null
    ) {

        routeLine =
            L.polyline(
                [
                    [
                        START_LAT,
                        START_LNG
                    ],
                    [
                        END_LAT,
                        END_LNG
                    ]
                ],
                {
                    color: '#147968',
                    weight: 7,
                    opacity: .85
                }
            ).addTo(map);


        map.fitBounds(
            routeLine.getBounds(),
            {
                padding: [
                    40,
                    40
                ]
            }
        );

    } else {

        map.setView(
            [
                -1.286389,
                36.817223
            ],
            13
        );
    }


    setTimeout(
        function() {

            map.invalidateSize();

        },
        300
    );
}


async function endJourney() {

    if (!JOURNEY_ACTIVE) {
        return;
    }

    if (!JOURNEY_ID) {

        showResult(
            'Unable to End Journey',
            'The journey ID could not be found.'
        );

        return;
    }


    confirmEndJourney.disabled =
        true;

    cancelEndJourney.disabled =
        true;

    confirmEndJourney.textContent =
        'Ending Journey...';


    try {

        const response =
            await fetch(
                './backend/api/journeys/end.php',
                {
                    method: 'POST',

                    credentials:
                        'same-origin',

                    headers: {

                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json'
                    },

                    body:
                        JSON.stringify({

                            journey_id:
                                Number(
                                    JOURNEY_ID
                                )
                        })
                }
            );


        const responseText =
            await response.text();


        let data;

        try {

            data =
                JSON.parse(
                    responseText
                );

        } catch (error) {

            console.error(
                'End Journey response:',
                responseText
            );

            throw new Error(
                'The server returned an invalid response.'
            );
        }


        if (
            !response.ok ||
            !data.success
        ) {

            throw new Error(
                data.message ||
                'Unable to end the journey.'
            );
        }


        journeyEnded =
            true;


        stopGPS();


        endJourneyModal.classList.remove(
            'show'
        );


        document.getElementById(
            'trackingStatus'
        ).textContent =
            'Journey completed';


        document.getElementById(
            'mapStatus'
        ).textContent =
            'Journey ended';


        if (endJourneyBtn) {

            endJourneyBtn.style.display =
                'none';
        }


        showResult(
            'Journey Ended',
            'Your journey has been completed successfully.'
        );

    } catch (error) {

        confirmEndJourney.disabled =
            false;

        cancelEndJourney.disabled =
            false;

        confirmEndJourney.textContent =
            'End Journey';


        endJourneyModal.classList.remove(
            'show'
        );


        showResult(
            'Unable to End Journey',
            error.message
        );
    }
}


if (endJourneyBtn) {

    endJourneyBtn.addEventListener(
        'click',
        function() {

            endJourneyModal.classList.add(
                'show'
            );

        }
    );
}


if (cancelEndJourney) {

    cancelEndJourney.addEventListener(
        'click',
        function() {

            endJourneyModal.classList.remove(
                'show'
            );

        }
    );
}


if (confirmEndJourney) {

    confirmEndJourney.addEventListener(
        'click',
        endJourney
    );
}


if (resultButton) {

    resultButton.addEventListener(
        'click',
        function() {

            window.location.href =
                'my-journeys.php';

        }
    );
}


if (endJourneyModal) {

    endJourneyModal.addEventListener(
        'click',
        function(event) {

            if (
                event.target ===
                endJourneyModal
            ) {

                endJourneyModal.classList.remove(
                    'show'
                );
            }

        }
    );
}


if (!<?= $pageError ? 'true' : 'false' ?>) {

    createMap();

    <?php if ($isActive): ?>

    startGPS();

    <?php endif; ?>
}

</script>

</body>

</html>