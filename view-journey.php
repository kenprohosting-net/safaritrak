<?php

require_once __DIR__ . '/backend/includes/auth-guard.php';

$db = safaritrak_db();

$journeyId = isset($_GET['journey_id']) ? (int) $_GET['journey_id'] : 0;

$journey = null;

if ($journeyId > 0) {
    $journeyStmt = $db->prepare(
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
         JOIN users u
           ON u.id = j.user_id
         JOIN journey_shares js
           ON js.journey_id = j.id
         JOIN trusted_contacts tc
           ON tc.id = js.trusted_contact_id
         WHERE j.id = ?
           AND tc.contact_user_id = ?
           AND tc.status = "confirmed"
         LIMIT 1'
    );

    $journeyStmt->execute([
        $journeyId,
        $currentUser['id']
    ]);

    $journey = $journeyStmt->fetch();
}

$showAppMapBackground = $journey !== null;

if (!$journey) {
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>SafariTrak | Journey unavailable</title>

        <link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/dashboard.css') ?>">

        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        >
    </head>

    <body>
    <?php require __DIR__ . '/backend/views/app-map-background.php'; ?>

    <div class="app app--map-layout">

        <main>

            <div class="content">

                <div class="card">

                    <div class="empty" style="margin:21px">

                        <i class="fa-solid fa-lock"></i>

                        <div>
                            <b>Journey unavailable</b>

                            <p>
                                You are not authorized to view this journey,
                                or the journey no longer exists.
                            </p>
                        </div>

                        <a
                            class="empty-link"
                            href="messages.php"
                        >
                            Back to messages
                        </a>

                    </div>

                </div>

            </div>

        </main>

    </div>

    </body>
    </html>
    <?php
    exit;
}

$userName = $currentUser['full_name'] ?? 'Traveler';

?>
<!doctype html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title>SafariTrak | Live Journey</title>

    <link
        rel="stylesheet"
        href="dashboard.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <style>
        .shared-map {
            width: 100%;
            height: 500px;
            border-radius: 0 0 12px 12px;
        }

        .shared-status {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            color: var(--muted);
        }

        .status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #999;
            display: inline-block;
        }

        .status-dot.online {
            background: #18a66a;
        }

        .status-dot.offline {
            background: #c94b4b;
        }

        .shared-info {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1px;
            background: #e9eceb;
            border-top: 1px solid #e9eceb;
        }

        .shared-info-item {
            background: white;
            padding: 16px;
        }

        .shared-info-item label {
            display: block;
            font-size: 9px;
            font-weight: 700;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .shared-info-item strong {
            font-size: 14px;
        }

        @media (max-width: 700px) {
            .shared-info {
                grid-template-columns: repeat(2, 1fr);
            }

            .shared-map {
                height: 400px;
            }
        }
    </style>

</head>

<body>
<?php require __DIR__ . '/backend/views/app-map-background.php'; ?>

<div class="app app--map-layout">

    <?php require __DIR__ . '/backend/views/app-navigation.php'; ?>

    <main>

        <header>

            <button
                class="menu"
                id="menu"
                type="button"
            >
                <i class="fa-solid fa-bars"></i>
            </button>

            <div>

                <label>SHARED JOURNEY</label>

                <h1>Live Journey</h1>

            </div>

            <div class="head-actions">

                <div class="avatar">
                    <?= st_avatar_inner($currentUser) ?>
                </div>

            </div>

        </header>

        <div class="content">

            <div class="page-head">

                <div>

                    <h2>
                        <?= htmlspecialchars($journey['owner_name']) ?>
                        is travelling
                    </h2>

                    <p>

                        <?= htmlspecialchars($journey['start_label']) ?>

                        &rarr;

                        <?= htmlspecialchars($journey['end_label']) ?>

                    </p>

                </div>

                <div class="shared-status">

                    <span
                        class="status-dot"
                        id="connectionDot"
                    ></span>

                    <span id="trackingStatus">
                        Connecting...
                    </span>

                </div>

            </div>

            <div class="card shared-map-card">

                <div class="card-head">

                    <div>

                        <label>LIVE MAP</label>

                        <h3>
                            <?= htmlspecialchars($journey['owner_name']) ?>'s
                            location
                        </h3>

                    </div>

                    <button
                        type="button"
                        class="btn-ghost"
                        id="centerTraveler"
                    >
                        <i class="fa-solid fa-location-crosshairs"></i>
                        Center traveler
                    </button>

                </div>

                <div
                    id="sharedMap"
                    class="shared-map"
                ></div>

                <div class="shared-info">

                    <div class="shared-info-item">

                        <label>STATUS</label>

                        <strong id="journeyStatus">
                            <?= htmlspecialchars(
                                ucfirst($journey['status'])
                            ) ?>
                        </strong>

                    </div>

                    <div class="shared-info-item">

                        <label>SPEED</label>

                        <strong id="travelerSpeed">
                            -
                        </strong>

                    </div>

                    <div class="shared-info-item">

                        <label>DISTANCE</label>

                        <strong id="journeyDistance">

                            <?= $journey['distance_km'] !== null
                                ? number_format(
                                    (float) $journey['distance_km'],
                                    1
                                ) . ' km'
                                : '-'
                            ?>

                        </strong>

                    </div>

                    <div class="shared-info-item">

                        <label>LAST UPDATE</label>

                        <strong id="lastUpdate">
                            Waiting...
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
const SHARED_JOURNEY_ID = <?= (int) $journey['id'] ?>;

const SHARED_START_LAT =
    <?= $journey['start_lat'] !== null
        ? (float) $journey['start_lat']
        : 'null'
    ?>;

const SHARED_START_LNG =
    <?= $journey['start_lng'] !== null
        ? (float) $journey['start_lng']
        : 'null'
    ?>;

const SHARED_END_LAT =
    <?= $journey['end_lat'] !== null
        ? (float) $journey['end_lat']
        : 'null'
    ?>;

const SHARED_END_LNG =
    <?= $journey['end_lng'] !== null
        ? (float) $journey['end_lng']
        : 'null'
    ?>;
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>

<script src="view-journey.js"></script>

</body>

</html>