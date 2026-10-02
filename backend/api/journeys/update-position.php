<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth-guard.php';

function sendResponse($success, $message = '', $data = [], $status = 200)
{
    http_response_code($status);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(
        false,
        'Only POST requests are allowed.',
        [],
        405
    );
}

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    sendResponse(
        false,
        'You must be logged in.',
        [],
        401
    );
}

$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    $input = $_POST;
}

$journeyId = isset($input['journey_id'])
    ? (int)$input['journey_id']
    : 0;

$groupJourneyId = isset($input['group_journey_id'])
    ? (int)$input['group_journey_id']
    : 0;

$latitude = isset($input['latitude'])
    ? (float)$input['latitude']
    : null;

$longitude = isset($input['longitude'])
    ? (float)$input['longitude']
    : null;

$accuracy = isset($input['accuracy'])
    ? (float)$input['accuracy']
    : null;

$speed = isset($input['speed_kmh'])
    ? (float)$input['speed_kmh']
    : null;

$heading = isset($input['heading'])
    ? (float)$input['heading']
    : null;

if ($latitude === null || $longitude === null) {
    sendResponse(
        false,
        'Latitude and longitude are required.',
        [],
        400
    );
}

if (
    !is_finite($latitude) ||
    !is_finite($longitude)
) {
    sendResponse(
        false,
        'Invalid GPS coordinates.',
        [],
        400
    );
}

if (
    $latitude < -90 ||
    $latitude > 90 ||
    $longitude < -180 ||
    $longitude > 180
) {
    sendResponse(
        false,
        'GPS coordinates are outside the valid range.',
        [],
        400
    );
}

if ($accuracy !== null) {
    if (
        !is_finite($accuracy) ||
        $accuracy < 0
    ) {
        $accuracy = null;
    }
}

if ($speed !== null) {
    if (
        !is_finite($speed) ||
        $speed < 0
    ) {
        $speed = null;
    }
}

if ($heading !== null) {
    if (
        !is_finite($heading) ||
        $heading < 0 ||
        $heading > 360
    ) {
        $heading = null;
    }
}

try {

    $db = safaritrak_db();

    $normalJourneyUpdated = false;
    $groupJourneyUpdated = false;

    $normalJourneyData = [];
    $groupJourneyData = [];

    if ($journeyId > 0) {

        $stmt = $db->prepare("
            SELECT
                id,
                user_id,
                status,
                destination_lat,
                destination_lng
            FROM journeys
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $journeyId,
            $userId
        ]);

        $journey = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($journey) {

            if ($journey['status'] === 'active') {

                $destinationLat = $journey['destination_lat'] !== null
                    ? (float)$journey['destination_lat']
                    : null;

                $destinationLng = $journey['destination_lng'] !== null
                    ? (float)$journey['destination_lng']
                    : null;

                $distanceKm = null;

                if (
                    $destinationLat !== null &&
                    $destinationLng !== null
                ) {

                    $earthRadius = 6371;

                    $lat1 = deg2rad($latitude);
                    $lat2 = deg2rad($destinationLat);

                    $deltaLat = deg2rad(
                        $destinationLat - $latitude
                    );

                    $deltaLng = deg2rad(
                        $destinationLng - $longitude
                    );

                    $a =
                        sin($deltaLat / 2) *
                        sin($deltaLat / 2)
                        +
                        cos($lat1) *
                        cos($lat2) *
                        sin($deltaLng / 2) *
                        sin($deltaLng / 2);

                    $a = min(1, max(0, $a));

                    $c = 2 * atan2(
                        sqrt($a),
                        sqrt(1 - $a)
                    );

                    $distanceKm =
                        $earthRadius * $c;
                }

                $update = $db->prepare("
                    UPDATE journeys
                    SET
                        current_lat = ?,
                        current_lng = ?,
                        current_accuracy = ?,
                        speed_kmh = ?,
                        heading = ?,
                        distance_remaining_km = ?,
                        last_location_update = NOW()
                    WHERE id = ?
                      AND user_id = ?
                      AND status = 'active'
                ");

                $update->execute([
                    $latitude,
                    $longitude,
                    $accuracy,
                    $speed,
                    $heading,
                    $distanceKm,
                    $journeyId,
                    $userId
                ]);

                $normalJourneyUpdated = true;

                $normalJourneyData = [
                    'journey_id' => $journeyId,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'accuracy' => $accuracy,
                    'speed_kmh' => $speed,
                    'heading' => $heading,
                    'distance_remaining_km' => $distanceKm
                ];
            }
        }
    }

    if ($groupJourneyId > 0) {

        $groupStmt = $db->prepare("
            SELECT
                id,
                group_journey_id,
                user_id,
                status
            FROM group_members
            WHERE group_journey_id = ?
              AND user_id = ?
              AND status IN ('confirmed', 'accepted')
            LIMIT 1
        ");

        $groupStmt->execute([
            $groupJourneyId,
            $userId
        ]);

        $groupMember = $groupStmt->fetch(PDO::FETCH_ASSOC);

        if (!$groupMember) {

            $groupInviteStmt = $db->prepare("
                SELECT
                    id,
                    group_journey_id,
                    invitee_id,
                    status
                FROM group_journey_invite
                WHERE group_journey_id = ?
                  AND invitee_id = ?
                  AND status = 'accepted'
                LIMIT 1
            ");

            $groupInviteStmt->execute([
                $groupJourneyId,
                $userId
            ]);

            $groupInvite = $groupInviteStmt->fetch(PDO::FETCH_ASSOC);

            if ($groupInvite) {

                $memberCheck = $db->prepare("
                    SELECT id
                    FROM group_members
                    WHERE group_journey_id = ?
                      AND user_id = ?
                    LIMIT 1
                ");

                $memberCheck->execute([
                    $groupJourneyId,
                    $userId
                ]);

                $groupMember = $memberCheck->fetch(PDO::FETCH_ASSOC);
            }
        }

        if ($groupMember) {

            $groupMemberId = (int)$groupMember['id'];

            $groupUpdate = $db->prepare("
                UPDATE group_member_positions
                SET
                    lat = ?,
                    lng = ?,
                    speed_kmh = ?,
                    heading = ?,
                    accuracy = ?,
                    updated_at = NOW()
                WHERE group_journey_id = ?
                  AND group_member_id = ?
                  AND user_id = ?
            ");

            $groupUpdate->execute([
                $latitude,
                $longitude,
                $speed,
                $heading,
                $accuracy,
                $groupJourneyId,
                $groupMemberId,
                $userId
            ]);

            if ($groupUpdate->rowCount() === 0) {

                $positionCheck = $db->prepare("
                    SELECT id
                    FROM group_member_positions
                    WHERE group_journey_id = ?
                      AND group_member_id = ?
                      AND user_id = ?
                    LIMIT 1
                ");

                $positionCheck->execute([
                    $groupJourneyId,
                    $groupMemberId,
                    $userId
                ]);

                $existingPosition = $positionCheck->fetch(PDO::FETCH_ASSOC);

                if (!$existingPosition) {

                    $insertPosition = $db->prepare("
                        INSERT INTO group_member_positions
                        (
                            group_journey_id,
                            group_member_id,
                            user_id,
                            lat,
                            lng,
                            speed_kmh,
                            heading,
                            accuracy,
                            updated_at,
                            created_at
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");

                    $insertPosition->execute([
                        $groupJourneyId,
                        $groupMemberId,
                        $userId,
                        $latitude,
                        $longitude,
                        $speed,
                        $heading,
                        $accuracy
                    ]);
                }
            }

            $groupJourneyUpdated = true;

            $groupJourneyData = [
                'group_journey_id' => $groupJourneyId,
                'group_member_id' => $groupMemberId,
                'user_id' => $userId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $accuracy,
                'speed_kmh' => $speed,
                'heading' => $heading
            ];
        }
    }

    if (
        !$normalJourneyUpdated &&
        !$groupJourneyUpdated
    ) {

        sendResponse(
            false,
            'No valid active journey or group journey was found for your account.',
            [],
            404
        );
    }

    sendResponse(
        true,
        'Location updated successfully.',
        [
            'normal_journey' => $normalJourneyUpdated,
            'group_journey' => $groupJourneyUpdated,
            'journey' => $normalJourneyData,
            'group' => $groupJourneyData,
            'updated_at' => date('Y-m-d H:i:s')
        ]
    );

} catch (PDOException $e) {

    error_log(
        'SafariTrak update-position.php error: ' .
        $e->getMessage()
    );

    sendResponse(
        false,
        'Database error while updating the location.',
        [],
        500
    );
}