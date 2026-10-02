<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth-guard.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit;
}

$journeyId = isset($_GET['journey_id'])
    ? (int)$_GET['journey_id']
    : 0;

if ($journeyId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid journey ID'
    ]);
    exit;
}

try {

    $db = safaritrak_db();

    $journeyStmt = $db->prepare("
        SELECT
            j.id,
            j.user_id,
            j.start_label,
            j.start_lat,
            j.start_lng,
            j.end_label,
            j.end_lat,
            j.end_lng,
            j.transport_mode,
            j.status,
            j.started_at,
            j.ended_at
        FROM journeys j
        WHERE j.id = ?
        LIMIT 1
    ");

    $journeyStmt->execute([
        $journeyId
    ]);

    $journey = $journeyStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$journey) {
        echo json_encode([
            'success' => false,
            'message' => 'Journey not found'
        ]);
        exit;
    }

    $isOwner =
        ((int)$journey['user_id'] === (int)$userId);

    $isAuthorized = $isOwner;

    if (!$isAuthorized) {

        try {

            $shareStmt = $db->prepare("
                SELECT id
                FROM journey_shares
                WHERE journey_id = ?
                AND shared_with_user_id = ?
                AND status = 'active'
                LIMIT 1
            ");

            $shareStmt->execute([
                $journeyId,
                $userId
            ]);

            if ($shareStmt->fetch(
                PDO::FETCH_ASSOC
            )) {
                $isAuthorized = true;
            }

        } catch (PDOException $e) {

            $isAuthorized = false;
        }
    }

    if (!$isAuthorized) {

        try {

            $groupStmt = $db->prepare("
                SELECT gm.id
                FROM group_members gm
                INNER JOIN group_journeys gj
                    ON gj.group_id = gm.group_id
                WHERE gj.journey_id = ?
                AND gm.user_id = ?
                AND gm.status = 'active'
                LIMIT 1
            ");

            $groupStmt->execute([
                $journeyId,
                $userId
            ]);

            if ($groupStmt->fetch(
                PDO::FETCH_ASSOC
            )) {
                $isAuthorized = true;
            }

        } catch (PDOException $e) {

            $isAuthorized = false;
        }
    }

    if (!$isAuthorized) {

        echo json_encode([
            'success' => false,
            'message' => 'You are not authorized to view this journey'
        ]);

        exit;
    }

    $positionStmt = $db->prepare("
        SELECT
            id,
            lat,
            lng,
            speed_kmh,
            created_at
        FROM journey_positions
        WHERE journey_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");

    $positionStmt->execute([
        $journeyId
    ]);

    $position = $positionStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$position) {

        $currentLat =
            (float)$journey['start_lat'];

        $currentLng =
            (float)$journey['start_lng'];

        $speed = 0;

        $updatedAt =
            $journey['started_at'];

    } else {

        $currentLat =
            (float)$position['lat'];

        $currentLng =
            (float)$position['lng'];

        $speed =
            (float)$position['speed_kmh'];

        $updatedAt =
            $position['created_at'];
    }

    $destinationLat =
        (float)$journey['end_lat'];

    $destinationLng =
        (float)$journey['end_lng'];

    $earthRadius = 6371;

    $lat1 = deg2rad($currentLat);
    $lng1 = deg2rad($currentLng);

    $lat2 = deg2rad($destinationLat);
    $lng2 = deg2rad($destinationLng);

    $differenceLat = $lat2 - $lat1;
    $differenceLng = $lng2 - $lng1;

    $a =
        sin($differenceLat / 2) *
        sin($differenceLat / 2)
        +
        cos($lat1) *
        cos($lat2) *
        sin($differenceLng / 2) *
        sin($differenceLng / 2);

    $a = min(1, max(0, $a));

    $c = 2 * atan2(
        sqrt($a),
        sqrt(1 - $a)
    );

    $distanceRemaining =
        $earthRadius * $c;

    if ($distanceRemaining < 0.05) {
        $distanceRemaining = 0;
    }

    $etaMinutes = null;

    if (
        $speed > 0 &&
        $distanceRemaining > 0
    ) {
        $etaMinutes =
            ($distanceRemaining / $speed) * 60;

        $etaMinutes =
            (int)ceil($etaMinutes);
    }

    echo json_encode([
        'success' => true,
        'journey_id' => (int)$journey['id'],
        'owner' => $isOwner,
        'latitude' => $currentLat,
        'longitude' => $currentLng,
        'destination_latitude' => $destinationLat,
        'destination_longitude' => $destinationLng,
        'destination_name' => $journey['end_label'],
        'start_name' => $journey['start_label'],
        'speed_kmh' => round($speed, 1),
        'distance_remaining_km' =>
            round($distanceRemaining, 2),
        'eta_minutes' => $etaMinutes,
        'transport_mode' =>
            $journey['transport_mode'],
        'status' => $journey['status'],
        'started_at' => $journey['started_at'],
        'ended_at' => $journey['ended_at'],
        'updated_at' => $updatedAt
    ]);

} catch (PDOException $e) {

    error_log(
        'SafariTrak shared journey error: ' .
        $e->getMessage()
    );

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load shared journey'
    ]);
}