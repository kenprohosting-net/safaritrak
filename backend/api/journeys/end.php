<?php

require_once __DIR__ . '/../../includes/auth-guard.php';

header(
    'Content-Type: application/json; charset=utf-8'
);

try {

    $db = safaritrak_db();

    $input =
        json_decode(
            file_get_contents('php://input'),
            true
        );

    if (!is_array($input)) {
        $input = $_POST;
    }


    $journeyId =
        isset($input['journey_id'])
            ? (int)$input['journey_id']
            : 0;


    if ($journeyId <= 0) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' =>
                'Invalid journey ID.'
        ]);

        exit;
    }


    $userId =
        (int)$currentUser['id'];


    $checkStmt =
        $db->prepare(
            'SELECT
                id,
                user_id,
                status,
                started_at,
                ended_at
             FROM journeys
             WHERE id = ?
             AND user_id = ?
             LIMIT 1'
        );


    $checkStmt->execute([
        $journeyId,
        $userId
    ]);


    $journey =
        $checkStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$journey) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' =>
                'Journey not found.'
        ]);

        exit;
    }


    $currentStatus =
        strtolower(
            trim(
                (string)(
                    $journey['status']
                    ?? ''
                )
            )
        );


    if ($currentStatus === 'completed') {

        echo json_encode([
            'success' => true,
            'message' =>
                'Journey is already completed.',
            'journey_id' =>
                $journeyId,
            'status' =>
                'completed',
            'ended_at' =>
                $journey['ended_at']
        ]);

        exit;
    }


    if ($currentStatus === 'cancelled') {

        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' =>
                'This journey has already been cancelled.',
            'journey_id' =>
                $journeyId,
            'status' =>
                'cancelled'
        ]);

        exit;
    }


    if (
        $currentStatus !== 'active' &&
        $currentStatus !== 'in_progress'
    ) {

        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' =>
                'This journey is not currently active.',
            'journey_id' =>
                $journeyId,
            'status' =>
                $currentStatus
        ]);

        exit;
    }


    $updateStmt =
        $db->prepare(
            'UPDATE journeys
             SET
                status = "completed",
                ended_at = NOW()
             WHERE id = ?
             AND user_id = ?
             AND (
                status = "active"
                OR status = "in_progress"
             )'
        );


    $updateStmt->execute([
        $journeyId,
        $userId
    ]);


    $verifyStmt =
        $db->prepare(
            'SELECT
                id,
                status,
                started_at,
                ended_at,
                distance_km
             FROM journeys
             WHERE id = ?
             AND user_id = ?
             LIMIT 1'
        );


    $verifyStmt->execute([
        $journeyId,
        $userId
    ]);


    $updatedJourney =
        $verifyStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$updatedJourney) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' =>
                'The journey could not be verified.'
        ]);

        exit;
    }


    $updatedStatus =
        strtolower(
            trim(
                (string)(
                    $updatedJourney['status']
                    ?? ''
                )
            )
        );


    if ($updatedStatus !== 'completed') {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' =>
                'The journey could not be marked as completed.'
        ]);

        exit;
    }


    echo json_encode([
        'success' => true,
        'message' =>
            'Journey ended successfully.',
        'journey_id' =>
            (int)$updatedJourney['id'],
        'status' =>
            'completed',
        'started_at' =>
            $updatedJourney['started_at'],
        'ended_at' =>
            $updatedJourney['ended_at'],
        'distance_km' =>
            $updatedJourney['distance_km']
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' =>
            'Server error while ending the journey.',
        'error' =>
            $e->getMessage()
    ]);
}