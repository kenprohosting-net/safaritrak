<?php

require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$userId) {
    header('Location: login.php');
    exit;
}

$db = safaritrak_db();

$groupJourneyId = 0;

$possibleIds = [
    $_GET['group_journey_id'] ?? null,
    $_GET['groupJourneyId'] ?? null,
    $_GET['journey_id'] ?? null,
    $_GET['journeyId'] ?? null,
    $_GET['id'] ?? null,
    $_POST['group_journey_id'] ?? null,
    $_POST['groupJourneyId'] ?? null,
    $_POST['journey_id'] ?? null,
    $_POST['journeyId'] ?? null
];

foreach ($possibleIds as $possibleId) {
    if ($possibleId !== null && $possibleId !== '') {
        $possibleId = filter_var(
            $possibleId,
            FILTER_VALIDATE_INT
        );

        if ($possibleId !== false && $possibleId > 0) {
            $groupJourneyId = (int)$possibleId;
            break;
        }
    }
}

if ($groupJourneyId <= 0 && isset($_SESSION['group_journey_id'])) {
    $sessionJourneyId = filter_var(
        $_SESSION['group_journey_id'],
        FILTER_VALIDATE_INT
    );

    if ($sessionJourneyId !== false && $sessionJourneyId > 0) {
        $groupJourneyId = (int)$sessionJourneyId;
    }
}

if ($groupJourneyId <= 0) {

    $stmt = $db->prepare("
        SELECT gj.id
        FROM group_journeys gj
        WHERE gj.organizer_id = ?
        ORDER BY
            CASE
                WHEN gj.status = 'active' THEN 1
                WHEN gj.status = 'planned' THEN 2
                WHEN gj.status = 'pending' THEN 3
                ELSE 4
            END,
            gj.id DESC
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $groupJourneyId = (int)$row['id'];
    }
}

if ($groupJourneyId <= 0) {

    $stmt = $db->prepare("
        SELECT gj.id
        FROM group_journeys gj
        INNER JOIN group_members gm
            ON gm.group_journey_id = gj.id
        WHERE gm.user_id = ?
        ORDER BY
            CASE
                WHEN gj.status = 'active' THEN 1
                WHEN gj.status = 'planned' THEN 2
                WHEN gj.status = 'pending' THEN 3
                ELSE 4
            END,
            gj.id DESC
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $groupJourneyId = (int)$row['id'];
    }
}

if ($groupJourneyId <= 0) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Group Travel - SafariTrak</title>

        <style>
            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background: #f5f7f9;
            }

            .error-page {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .error-box {
                max-width: 450px;
                background: white;
                padding: 35px;
                border-radius: 16px;
                text-align: center;
                box-shadow: 0 10px 35px rgba(0,0,0,.12);
            }

            .error-box h2 {
                color: #c0392b;
                margin-bottom: 10px;
            }

            .error-box p {
                color: #667085;
                line-height: 1.6;
            }

            .error-box a {
                display: inline-block;
                margin-top: 15px;
                padding: 11px 22px;
                border-radius: 8px;
                background: #087f6b;
                color: white;
                text-decoration: none;
            }
        </style>
    </head>

    <body>

        <div class="error-page">

            <div class="error-box">

                <h2>No Group Journey Available</h2>

                <p>
                    No group journey could be found for your account.
                    Please create or join a group journey first.
                </p>

                <a href="group-travel.php">
                    Back to Group Travel
                </a>

            </div>

        </div>

    </body>
    </html>
    <?php
    exit;
}

$_SESSION['group_journey_id'] = $groupJourneyId;

$journeyStmt = $db->prepare("
    SELECT
        id,
        organizer_id,
        title,
        destination_label,
        destination_lat,
        destination_lng,
        distance_km,
        departure_at,
        status,
        route_distance_km,
        route_duration_minutes,
        distance_covered_km,
        remaining_distance_km,
        remaining_duration_minutes,
        estimated_arrival_at,
        meeting_point_label,
        meeting_point_lat,
        meeting_point_lng
    FROM group_journeys
    WHERE id = ?
    LIMIT 1
");

$journeyStmt->execute([
    $groupJourneyId
]);

$groupJourney = $journeyStmt->fetch(PDO::FETCH_ASSOC);

if (!$groupJourney) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Group Journey Not Found</title>

        <style>
            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background: #f5f7f9;
            }

            .error-page {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .error-box {
                max-width: 450px;
                background: white;
                padding: 35px;
                border-radius: 16px;
                text-align: center;
                box-shadow: 0 10px 35px rgba(0,0,0,.12);
            }

            .error-box h2 {
                color: #c0392b;
            }

            .error-box p {
                color: #667085;
                line-height: 1.6;
            }

            .error-box a {
                display: inline-block;
                margin-top: 15px;
                padding: 11px 22px;
                border-radius: 8px;
                background: #087f6b;
                color: white;
                text-decoration: none;
            }
        </style>
    </head>

    <body>

        <div class="error-page">

            <div class="error-box">

                <h2>Group Journey Not Found</h2>

                <p>
                    The selected group journey does not exist.
                </p>

                <a href="group-travel.php">
                    Back to Group Travel
                </a>

            </div>

        </div>

    </body>
    </html>
    <?php
    exit;
}

$isOrganizer =
    (int)$groupJourney['organizer_id'] === (int)$userId;

$memberStmt = $db->prepare("
    SELECT id, status
    FROM group_members
    WHERE group_journey_id = ?
      AND user_id = ?
    LIMIT 1
");

$memberStmt->execute([
    $groupJourneyId,
    $userId
]);

$member = $memberStmt->fetch(PDO::FETCH_ASSOC);

$isMember = false;

if ($member) {
    $memberStatus = strtolower(
        trim((string)$member['status'])
    );

    $allowedStatuses = [
        'confirmed',
        'accepted',
        'active',
        'joined'
    ];

    if (in_array($memberStatus, $allowedStatuses, true)) {
        $isMember = true;
    }
}

if (!$isOrganizer && !$isMember) {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Access Denied</title>

        <style>
            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background: #f5f7f9;
            }

            .error-page {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .error-box {
                max-width: 450px;
                background: white;
                padding: 35px;
                border-radius: 16px;
                text-align: center;
                box-shadow: 0 10px 35px rgba(0,0,0,.12);
            }

            .error-box h2 {
                color: #c0392b;
            }

            .error-box p {
                color: #667085;
                line-height: 1.6;
            }

            .error-box a {
                display: inline-block;
                margin-top: 15px;
                padding: 11px 22px;
                border-radius: 8px;
                background: #087f6b;
                color: white;
                text-decoration: none;
            }
        </style>
    </head>

    <body>

        <div class="error-page">

            <div class="error-box">

                <h2>Access Denied</h2>

                <p>
                    You are not a member of this group journey.
                </p>

                <a href="group-travel.php">
                    Back to Group Travel
                </a>

            </div>

        </div>

    </body>

    </html>
    <?php
    exit;
}

if (file_exists(__DIR__ . '/sidebar.php')) {
    require_once __DIR__ . '/sidebar.php';
}

$destinationLat =
    $groupJourney['destination_lat'] !== null
        ? (float)$groupJourney['destination_lat']
        : null;

$destinationLng =
    $groupJourney['destination_lng'] !== null
        ? (float)$groupJourney['destination_lng']
        : null;

$meetingLat =
    $groupJourney['meeting_point_lat'] !== null
        ? (float)$groupJourney['meeting_point_lat']
        : null;

$meetingLng =
    $groupJourney['meeting_point_lng'] !== null
        ? (float)$groupJourney['meeting_point_lng']
        : null;

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
        <?= htmlspecialchars(
            $groupJourney['title'] ?: 'Group Travel',
            ENT_QUOTES,
            'UTF-8'
        ) ?> - SafariTrak
    </title>

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7f9;
            color: #1f2933;
        }

        .group-page {
            width: 100%;
            min-height: 100vh;
            padding: 25px;
        }

        .group-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .group-header {
            background: white;
            border-radius: 16px;
            padding: 22px 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 18px rgba(0,0,0,.08);
        }

        .group-header h1 {
            margin: 0 0 7px;
            font-size: 26px;
        }

        .group-header p {
            margin: 0;
            color: #667085;
        }

        .group-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 20px;
        }

        .map-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0,0,0,.08);
        }

        #groupMap {
            width: 100%;
            height: 650px;
            min-height: 500px;
        }

        .map-status {
            padding: 13px 18px;
            border-top: 1px solid #eaecf0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .connection-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #98a2b3;
        }

        .connection-dot.connected {
            background: #12b76a;
        }

        .connection-dot.error {
            background: #d92d20;
        }

        .group-panel {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .info-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 18px rgba(0,0,0,.08);
        }

        .info-card h3 {
            margin: 0 0 15px;
            font-size: 17px;
        }

        .journey-detail {
            margin-bottom: 14px;
        }

        .detail-label {
            display: block;
            color: #667085;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .detail-value {
            font-weight: 600;
            color: #1f2933;
        }

        .meeting-point {
            padding: 12px;
            border-radius: 10px;
            background: #fff7e6;
            border: 1px solid #f5d48a;
        }

        .destination-point {
            padding: 12px;
            border-radius: 10px;
            background: #fdecec;
            border: 1px solid #f1b4b4;
        }

        .member-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .member-card {
            border: 1px solid #eaecf0;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .member-top {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .member-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            border: 3px solid white;
            box-shadow: 0 2px 6px rgba(0,0,0,.25);
            flex-shrink: 0;
        }

        .member-name {
            font-weight: 700;
            flex: 1;
        }

        .member-status {
            font-size: 11px;
            color: #667085;
        }

        .member-details {
            margin-top: 10px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px;
        }

        .member-stat {
            background: #f8fafc;
            padding: 8px;
            border-radius: 8px;
        }

        .member-stat span {
            display: block;
            font-size: 10px;
            color: #667085;
            margin-bottom: 3px;
        }

        .member-stat strong {
            font-size: 12px;
        }

        .legend {
            display: grid;
            gap: 9px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
        }

        .legend-marker {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 1px 4px rgba(0,0,0,.3);
        }

        .group-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .group-button {
            border: 0;
            border-radius: 9px;
            padding: 11px 14px;
            cursor: pointer;
            font-weight: 600;
        }

        .fit-button {
            background: #087f6b;
            color: white;
            flex: 1;
        }

        .location-button {
            background: #e8f7f2;
            color: #087f6b;
            flex: 1;
        }

        @media (max-width: 1000px) {

            .group-layout {
                grid-template-columns: 1fr;
            }

            #groupMap {
                height: 550px;
            }

        }

        @media (max-width: 600px) {

            .group-page {
                padding: 12px;
            }

            #groupMap {
                height: 450px;
                min-height: 400px;
            }

            .group-header h1 {
                font-size: 21px;
            }

        }

    </style>

</head>

<body>

<div class="group-page">

    <div class="group-container">

        <div class="group-header">

            <h1 id="groupTitle">
                <?= htmlspecialchars(
                    $groupJourney['title'] ?: 'Group Travel',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h1>

            <p id="groupStatusText">
                <?= htmlspecialchars(
                    ucfirst($groupJourney['status']),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
                group journey
            </p>

        </div>

        <div class="group-layout">

            <div class="map-card">

                <div id="groupMap"></div>

                <div class="map-status">

                    <span
                        id="connectionDot"
                        class="connection-dot">
                    </span>

                    <span id="trackingStatus">
                        Connecting to group tracking...
                    </span>

                </div>

            </div>

            <div class="group-panel">

                <div class="info-card">

                    <h3>Journey Information</h3>

                    <div class="journey-detail">

                        <span class="detail-label">
                            Destination
                        </span>

                        <div
                            id="destinationLabel"
                            class="detail-value"
                        >
                            <?= htmlspecialchars(
                                $groupJourney['destination_label']
                                ?: 'Not specified',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>

                    </div>

                    <div
                        id="destinationBox"
                        class="destination-point"
                    >

                        <span class="detail-label">
                            Group Destination Distance
                        </span>

                        <div
                            id="groupDestinationDistance"
                            class="detail-value"
                        >
                            --
                        </div>

                    </div>

                    <br>

                    <div
                        id="meetingPointBox"
                        class="meeting-point"
                    >

                        <span class="detail-label">
                            Meeting Point
                        </span>

                        <div
                            id="meetingPointLabel"
                            class="detail-value"
                        >
                            <?= htmlspecialchars(
                                $groupJourney['meeting_point_label']
                                ?: 'Not specified',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>

                        <div
                            id="meetingPointCoordinates"
                            style="font-size:11px;color:#667085;margin-top:5px;"
                        >

                            <?php if (
                                $meetingLat !== null &&
                                $meetingLng !== null
                            ): ?>

                                <?= number_format(
                                    $meetingLat,
                                    6
                                ) ?>,

                                <?= number_format(
                                    $meetingLng,
                                    6
                                ) ?>

                            <?php endif; ?>

                        </div>

                    </div>

                    <br>

                    <div
                        id="routeInfoBox"
                        class="destination-point"
                    >

                        <span class="detail-label">
                            Planned Route
                        </span>

                        <div
                            id="routeDistance"
                            class="detail-value"
                        >

                            <?php if (
                                $groupJourney['route_distance_km']
                                !== null
                            ): ?>

                                <?= number_format(
                                    (float)$groupJourney['route_distance_km'],
                                    2
                                ) ?> km

                            <?php elseif (
                                $groupJourney['distance_km']
                                !== null
                            ): ?>

                                <?= number_format(
                                    (float)$groupJourney['distance_km'],
                                    2
                                ) ?> km

                            <?php else: ?>

                                --

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

                <div class="info-card">

                    <h3>Group Members</h3>

                    <div
                        id="memberList"
                        class="member-list"
                    >

                        <div
                            style="color:#667085;font-size:13px;"
                        >
                            Loading group members...
                        </div>

                    </div>

                    <div class="group-actions">

                        <button
                            type="button"
                            id="fitGroupBtn"
                            class="group-button fit-button"
                        >
                            Show Group
                        </button>

                        <button
                            type="button"
                            id="myLocationBtn"
                            class="group-button location-button"
                        >
                            My Location
                        </button>

                    </div>

                </div>

                <div class="info-card">

                    <h3>Map Legend</h3>

                    <div class="legend">

                        <div class="legend-item">

                            <span
                                class="legend-marker"
                                style="background:#087f6b;"
                            ></span>

                            Group Member

                        </div>

                        <div class="legend-item">

                            <span
                                class="legend-marker"
                                style="background:#c0392b;"
                            ></span>

                            Destination

                        </div>

                        <div class="legend-item">

                            <span
                                class="legend-marker"
                                style="background:#f59e0b;"
                            ></span>

                            Meeting Point

                        </div>

                        <div class="legend-item">

                            <span
                                style="
                                    display:block;
                                    width:28px;
                                    height:5px;
                                    background:#2563eb;
                                    border-radius:4px;
                                "
                            ></span>

                            Route

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<input
    type="hidden"
    id="groupJourneyId"
    value="<?= (int)$groupJourneyId ?>"
>

<input
    type="hidden"
    id="group_journey_id"
    value="<?= (int)$groupJourneyId ?>"
>

<input
    type="hidden"
    id="currentUserId"
    value="<?= (int)$userId ?>"
>

<input
    type="hidden"
    id="destinationLat"
    value="<?= $destinationLat !== null ? htmlspecialchars((string)$destinationLat, ENT_QUOTES, 'UTF-8') : '' ?>"
>

<input
    type="hidden"
    id="destinationLng"
    value="<?= $destinationLng !== null ? htmlspecialchars((string)$destinationLng, ENT_QUOTES, 'UTF-8') : '' ?>"
>

<input
    type="hidden"
    id="meetingPointLat"
    value="<?= $meetingLat !== null ? htmlspecialchars((string)$meetingLat, ENT_QUOTES, 'UTF-8') : '' ?>"
>

<input
    type="hidden"
    id="meetingPointLng"
    value="<?= $meetingLng !== null ? htmlspecialchars((string)$meetingLng, ENT_QUOTES, 'UTF-8') : '' ?>"
>

<script>

window.groupJourneyId = <?= json_encode((int)$groupJourneyId) ?>;

window.currentUserId = <?= json_encode((int)$userId) ?>;

window.groupJourneyData = <?= json_encode([
    'id' => (int)$groupJourney['id'],
    'organizer_id' => (int)$groupJourney['organizer_id'],
    'title' => $groupJourney['title'],
    'status' => $groupJourney['status'],
    'destination_label' => $groupJourney['destination_label'],
    'destination_lat' => $destinationLat,
    'destination_lng' => $destinationLng,
    'meeting_point_label' => $groupJourney['meeting_point_label'],
    'meeting_point_lat' => $meetingLat,
    'meeting_point_lng' => $meetingLng,
    'route_distance_km' => $groupJourney['route_distance_km'] !== null
        ? (float)$groupJourney['route_distance_km']
        : null,
    'route_duration_minutes' => $groupJourney['route_duration_minutes'] !== null
        ? (int)$groupJourney['route_duration_minutes']
        : null
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

</script>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
></script>

<script
    src="group-travel.js"
></script>

</body>
</html>