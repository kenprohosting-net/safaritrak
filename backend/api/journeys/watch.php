<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth-guard.php';

header('Content-Type: application/json; charset=utf-8');

$db = safaritrak_db();

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Authentication required.'
    ]);

    exit;
}

$journeyId =
    isset($_GET['journey_id'])
        ? (int) $_GET['journey_id']
        : 0;

if ($journeyId <= 0) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid journey ID.'
    ]);

    exit;
}

try {

    $stmt = $db->prepare("
        SELECT
            j.id,
            j.user_id,
            j.start_label,
            j.start_lat,
            j.start_lng,
            j.end_label,
            j.end_lat,
            j.end_lng,
            j.status,
            j.started_at,
            j.ended_at,
            u.name AS owner_name,
            u.username AS owner_username
        FROM journeys j
        INNER JOIN users u
            ON u.id = j.user_id
        INNER JOIN journey_shares js
            ON js.journey_id = j.id
        INNER JOIN trusted_contacts tc
            ON tc.user_id = j.user_id
            AND tc.contact_user_id = ?
        WHERE j.id = ?
        AND js.shared_with_user_id = ?
        AND tc.confirmed = 1
        AND tc.share_live_location = 1
        LIMIT 1
    ");

    $stmt->execute([
        $userId,
        $journeyId,
        $userId
    ]);

    $journey =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$journey) {

        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' =>
                'You are not authorized to view this journey.'
        ]);

        exit;
    }

    $positionStmt =
        $db->prepare("
            SELECT
                id,
                journey_id,
                lat,
                lng,
                speed_kmh,
                accuracy,
                created_at
            FROM journey_positions
            WHERE journey_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");

    $positionStmt->execute([
        $journeyId
    ]);

    $position =
        $positionStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($position) {

        $position['lat'] =
            (float)$position['lat'];

        $position['lng'] =
            (float)$position['lng'];

        $position['speed_kmh'] =
            (float)($position['speed_kmh'] ?? 0);

        $position['accuracy'] =
            (float)($position['accuracy'] ?? 0);
    }

    echo json_encode([
        'success' => true,

        'journey' => [
            'id' =>
                (int)$journey['id'],

            'owner_name' =>
                $journey['owner_name']
                ??
                $journey['owner_username']
                ??
                'Traveler',

            'start_label' =>
                $journey['start_label'],

            'start_lat' =>
                $journey['start_lat'] !== null
                    ? (float)$journey['start_lat']
                    : null,

            'start_lng' =>
                $journey['start_lng'] !== null
                    ? (float)$journey['start_lng']
                    : null,

            'end_label' =>
                $journey['end_label'],

            'end_lat' =>
                $journey['end_lat'] !== null
                    ? (float)$journey['end_lat']
                    : null,

            'end_lng' =>
                $journey['end_lng'] !== null
                    ? (float)$journey['end_lng']
                    : null,

            'status' =>
                $journey['status'],

            'started_at' =>
                $journey['started_at'],

            'ended_at' =>
                $journey['ended_at']
        ],

        'position' =>
            $position ?: null
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' =>
            'Unable to retrieve live journey location.'
    ]);
}