<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

try {

    $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

    if (!$userId) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'You are not logged in.'
        ]);

        exit;
    }

    $groupJourneyId = $_GET['group_journey_id'] ?? null;

    if (
        $groupJourneyId === null ||
        $groupJourneyId === ''
    ) {
        $groupJourneyId = $_GET['groupJourneyId'] ?? null;
    }

    $groupJourneyId = filter_var(
        $groupJourneyId,
        FILTER_VALIDATE_INT
    );

    if (
        $groupJourneyId === false ||
        $groupJourneyId <= 0
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid group journey ID.'
        ]);

        exit;
    }

    $db = safaritrak_db();

    $journeyStmt = $db->prepare("
        SELECT
            id,
            organizer_id,
            title,
            destination_label,
            destination_lat,
            destination_lng,
            meeting_point_label,
            meeting_point_lat,
            meeting_point_lng,
            status
        FROM group_journeys
        WHERE id = ?
        LIMIT 1
    ");

    $journeyStmt->execute([
        $groupJourneyId
    ]);

    $journey = $journeyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$journey) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Group journey not found.'
        ]);

        exit;
    }

    $isOrganizer =
        (int)$journey['organizer_id'] === (int)$userId;

    $memberCheckStmt = $db->prepare("
        SELECT
            id,
            user_id,
            status
        FROM group_members
        WHERE group_journey_id = ?
          AND user_id = ?
        LIMIT 1
    ");

    $memberCheckStmt->execute([
        $groupJourneyId,
        $userId
    ]);

    $currentMember =
        $memberCheckStmt->fetch(PDO::FETCH_ASSOC);

    $isMember = false;

    if ($currentMember) {

        $memberStatus = strtolower(
            trim((string)$currentMember['status'])
        );

        $allowedStatuses = [
            'invited',
            'confirmed',
            'accepted',
            'active',
            'joined'
        ];

        if (
            in_array(
                $memberStatus,
                $allowedStatuses,
                true
            )
        ) {
            $isMember = true;
        }
    }

    if (!$isOrganizer && !$isMember) {

        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'You do not have access to this group journey.'
        ]);

        exit;
    }

    $membersStmt = $db->prepare("
        SELECT
            gm.id,
            gm.group_journey_id,
            gm.user_id,
            gm.invite_name,
            gm.invite_phone,
            gm.status,
            gm.last_lat,
            gm.last_lng,
            gm.created_at,
            u.full_name,
            u.username,
            u.email
        FROM group_members gm
        LEFT JOIN users u
            ON u.id = gm.user_id
        WHERE gm.group_journey_id = ?
        ORDER BY
            CASE
                WHEN gm.user_id = ? THEN 0
                ELSE 1
            END,
            gm.id ASC
    ");

    $membersStmt->execute([
        $groupJourneyId,
        $userId
    ]);

    $rows = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

    $members = [];

    foreach ($rows as $row) {

        $fullName = trim(
            (string)($row['full_name'] ?? '')
        );

        $username = trim(
            (string)($row['username'] ?? '')
        );

        $inviteName = trim(
            (string)($row['invite_name'] ?? '')
        );

        if ($fullName !== '') {
            $displayName = $fullName;
        } elseif ($inviteName !== '') {
            $displayName = $inviteName;
        } elseif ($username !== '') {
            $displayName = $username;
        } else {
            $displayName = 'Group Member';
        }

        $lastLat = null;
        $lastLng = null;

        if (
            $row['last_lat'] !== null &&
            $row['last_lat'] !== ''
        ) {
            $lastLat = (float)$row['last_lat'];
        }

        if (
            $row['last_lng'] !== null &&
            $row['last_lng'] !== ''
        ) {
            $lastLng = (float)$row['last_lng'];
        }

        $members[] = [
            'id' => (int)$row['id'],

            'group_journey_id' =>
                (int)$row['group_journey_id'],

            'user_id' =>
                $row['user_id'] !== null
                    ? (int)$row['user_id']
                    : null,

            'full_name' =>
                $fullName !== ''
                    ? $fullName
                    : null,

            'username' =>
                $username !== ''
                    ? $username
                    : null,

            'email' =>
                $row['email'] !== null
                    ? $row['email']
                    : null,

            'invite_name' =>
                $inviteName !== ''
                    ? $inviteName
                    : null,

            'invite_phone' =>
                $row['invite_phone'] !== null
                    ? $row['invite_phone']
                    : null,

            'status' =>
                $row['status'],

            'last_lat' =>
                $lastLat,

            'last_lng' =>
                $lastLng,

            'display_name' =>
                $displayName,

            'is_current_user' =>
                $row['user_id'] !== null &&
                (int)$row['user_id'] === (int)$userId
        ];
    }

    echo json_encode([
        'success' => true,

        'message' =>
            'Group members loaded successfully.',

        'group_journey_id' =>
            (int)$groupJourneyId,

        'current_user_id' =>
            (int)$userId,

        'is_organizer' =>
            $isOrganizer,

        'journey' => [
            'id' =>
                (int)$journey['id'],

            'organizer_id' =>
                (int)$journey['organizer_id'],

            'title' =>
                $journey['title'],

            'destination_label' =>
                $journey['destination_label'],

            'destination_lat' =>
                $journey['destination_lat'] !== null
                    ? (float)$journey['destination_lat']
                    : null,

            'destination_lng' =>
                $journey['destination_lng'] !== null
                    ? (float)$journey['destination_lng']
                    : null,

            'meeting_point_label' =>
                $journey['meeting_point_label'],

            'meeting_point_lat' =>
                $journey['meeting_point_lat'] !== null
                    ? (float)$journey['meeting_point_lat']
                    : null,

            'meeting_point_lng' =>
                $journey['meeting_point_lng'] !== null
                    ? (float)$journey['meeting_point_lng']
                    : null,

            'status' =>
                $journey['status']
        ],

        'count' =>
            count($members),

        'members' =>
            $members

    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database error while loading group members.',
        'error' => $e->getMessage()
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Server error while loading group members.',
        'error' => $e->getMessage()
    ]);
}