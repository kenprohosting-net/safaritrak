<?php

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

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

$groupJourneyId = filter_var(
    $groupJourneyId,
    FILTER_VALIDATE_INT
);

if (!$groupJourneyId || $groupJourneyId <= 0) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid group journey ID.'
    ]);

    exit;
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

    $memberStmt = $db->prepare("
        SELECT
            id,
            user_id,
            status
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

        $status = strtolower(
            trim((string)$member['status'])
        );

        if (
            $status === 'confirmed' ||
            $status === 'accepted' ||
            $status === 'active' ||
            $status === 'joined' ||
            $status === 'invited'
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
            u.email,
            u.phone
        FROM group_members gm
        LEFT JOIN users u
            ON u.id = gm.user_id
        WHERE gm.group_journey_id = ?
        ORDER BY gm.id ASC
    ");

    $membersStmt->execute([
        $groupJourneyId
    ]);

    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'group_journey_id' => (int)$groupJourneyId,
        'organizer_id' => (int)$journey['organizer_id'],
        'members' => $members
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database error.',
        'error' => $e->getMessage()
    ]);
}