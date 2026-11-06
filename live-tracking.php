<?php

require __DIR__ . '/backend/includes/auth-guard.php';

$db = safaritrak_db();
$requestedJourneyId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

$ownedActiveStmt = $db->prepare(
    'SELECT id FROM journeys WHERE user_id = ? AND status = "active" LIMIT 1'
);
$ownedActiveStmt->execute([(int) $currentUser['id']]);
$ownedActiveJourneyId = (int) ($ownedActiveStmt->fetchColumn() ?: 0);

if ($requestedJourneyId > 0 && $requestedJourneyId === $ownedActiveJourneyId) {
    header('Location: my-journeys.php?selected=' . $ownedActiveJourneyId);
    exit;
}

if ($requestedJourneyId > 0) {
    $sharedJourneyStmt = $db->prepare(
        'SELECT j.id
         FROM journeys j
         WHERE j.id = ? AND j.status = "active"
           AND EXISTS (
               SELECT 1
               FROM journey_shares js
               JOIN trusted_contacts tc ON tc.id = js.trusted_contact_id
               WHERE js.journey_id = j.id
                 AND tc.contact_user_id = ?
                 AND tc.status = "confirmed"
                 AND tc.share_live_location = 1
           )
         LIMIT 1'
    );
    $sharedJourneyStmt->execute([$requestedJourneyId, (int) $currentUser['id']]);
    $sharedJourneyId = (int) ($sharedJourneyStmt->fetchColumn() ?: 0);
    if ($sharedJourneyId > 0) {
        header('Location: watch-journey.php?id=' . $sharedJourneyId);
        exit;
    }
}

if ($ownedActiveJourneyId > 0) {
    header('Location: my-journeys.php?selected=' . $ownedActiveJourneyId);
    exit;
}

header('Location: my-journeys.php');
exit;
