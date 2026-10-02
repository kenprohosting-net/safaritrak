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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(
        false,
        'Only GET requests are allowed.',
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

$groupJourneyId = isset($_GET['group_journey_id'])
    ? (int)$_GET['group_journey_id']
    : 0;

if ($groupJourneyId <= 0) {
    sendResponse(
        false,
        'Invalid group journey ID.',
        [],
        400
    );
}

try {

    $db = safaritrak_db();

    $journeyStmt = $db->prepare("
        SELECT
            id,
            organizer_id,
            title,
            destination_label,
            destination_lat,
            destination_lng,
            status,
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
        sendResponse(
            false,
            'Group journey not found.',
            [],
            404
        );
    }

    $isAllowed = false;

    if (
        (int)$groupJourney['organizer_id'] ===
        (int)$userId
    ) {
        $isAllowed = true;
    }

    if (!$isAllowed) {

        $memberStmt = $db->prepare("
            SELECT id
            FROM group_members
            WHERE group_journey_id = ?
              AND user_id = ?
              AND status IN ('confirmed', 'accepted')
            LIMIT 1
        ");

        $memberStmt->execute([
            $groupJourneyId,
            $userId
        ]);

        if ($memberStmt->fetch()) {
            $isAllowed = true;
        }
    }

    if (!$isAllowed) {

        $inviteStmt = $db->prepare("
            SELECT id
            FROM group_journey_invite
            WHERE group_journey_id = ?
              AND invitee_id = ?
              AND status = 'accepted'
            LIMIT 1
        ");

        $inviteStmt->execute([
            $groupJourneyId,
            $userId
        ]);

        if ($inviteStmt->fetch()) {
            $isAllowed = true;
        }
    }

    if (!$isAllowed) {
        sendResponse(
            false,
            'You are not allowed to view this group journey.',
            [],
            403
        );
    }

    $memberStmt = $db->prepare("
        SELECT
            gm.id AS group_member_id,
            gm.user_id,
            gm.invite_name,
            gm.invite_phone,
            gm.status AS member_status,

            gp.lat,
            gp.lng,
            gp.speed_kmh,
            gp.heading,
            gp.accuracy,
            gp.updated_at

        FROM group_members gm

        LEFT JOIN group_member_positions gp
            ON gp.group_journey_id = gm.group_journey_id
            AND gp.group_member_id = gm.id
            AND gp.user_id = gm.user_id

        WHERE gm.group_journey_id = ?

        AND gm.status IN ('confirmed', 'accepted')

        ORDER BY gm.id ASC
    ");

    $memberStmt->execute([
        $groupJourneyId
    ]);

    $members = $memberStmt->fetchAll(PDO::FETCH_ASSOC);

    $destinationLat = $groupJourney['destination_lat'] !== null
        ? (float)$groupJourney['destination_lat']
        : null;

    $destinationLng = $groupJourney['destination_lng'] !== null
        ? (float)$groupJourney['destination_lng']
        : null;

    $meetingLat = $groupJourney['meeting_point_lat'] !== null
        ? (float)$groupJourney['meeting_point_lat']
        : null;

    $meetingLng = $groupJourney['meeting_point_lng'] !== null
        ? (float)$groupJourney['meeting_point_lng']
        : null;

    function calculateDistance(
        $lat1,
        $lng1,
        $lat2,
        $lng2
    ) {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);

        $a =
            sin($dLat / 2) *
            sin($dLat / 2)
            +
            cos($lat1) *
            cos($lat2) *
            sin($dLng / 2) *
            sin($dLng / 2);

        $a = min(1, max(0, $a));

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadius * $c;
    }

    $result = [];

    foreach ($members as $member) {

        $lat = $member['lat'] !== null
            ? (float)$member['lat']
            : null;

        $lng = $member['lng'] !== null
            ? (float)$member['lng']
            : null;

        $destinationDistance = null;
        $meetingDistance = null;

        if (
            $lat !== null &&
            $lng !== null &&
            $destinationLat !== null &&
            $destinationLng !== null
        ) {

            $destinationDistance = calculateDistance(
                $lat,
                $lng,
                $destinationLat,
                $destinationLng
            );
        }

        if (
            $lat !== null &&
            $lng !== null &&
            $meetingLat !== null &&
            $meetingLng !== null
        ) {

            $meetingDistance = calculateDistance(
                $lat,
                $lng,
                $meetingLat,
                $meetingLng
            );
        }

        $name = $member['invite_name'];

        if (
            $name === null ||
            trim($name) === ''
        ) {
            $name = 'Group Member ' .
                $member['group_member_id'];
        }

        $result[] = [
            'group_member_id' => (int)$member['group_member_id'],
            'user_id' => (int)$member['user_id'],
            'name' => $name,
            'invite_name' => $name,
            'status' => $member['member_status'],

            'lat' => $lat,
            'lng' => $lng,

            'latitude' => $lat,
            'longitude' => $lng,

            'speed_kmh' => $member['speed_kmh'] !== null
                ? (float)$member['speed_kmh']
                : 0,

            'heading' => $member['heading'] !== null
                ? (float)$member['heading']
                : 0,

            'accuracy' => $member['accuracy'] !== null
                ? (float)$member['accuracy']
                : 0,

            'destination_distance_km' =>
                $destinationDistance !== null
                    ? round($destinationDistance, 3)
                    : null,

            'meeting_point_distance_km' =>
                $meetingDistance !== null
                    ? round($meetingDistance, 3)
                    : null,

            'updated_at' => $member['updated_at']
        ];
    }

    sendResponse(
        true,
        'Group positions loaded successfully.',
        [
            'group_journey' => [
                'id' => (int)$groupJourney['id'],
                'title' => $groupJourney['title'],
                'status' => $groupJourney['status'],

                'destination_label' =>
                    $groupJourney['destination_label'],

                'destination_lat' =>
                    $destinationLat,

                'destination_lng' =>
                    $destinationLng,

                'meeting_point_label' =>
                    $groupJourney['meeting_point_label'],

                'meeting_point_lat' =>
                    $meetingLat,

                'meeting_point_lng' =>
                    $meetingLng
            ],

            'members' => $result,

            'positions' => $result,

            'count' => count($result)
        ]
    );

} catch (PDOException $e) {

    error_log(
        'SafariTrak positions.php error: ' .
        $e->getMessage()
    );

    sendResponse(
        false,
        'Database error while loading group positions.',
        [],
        500
    );
}