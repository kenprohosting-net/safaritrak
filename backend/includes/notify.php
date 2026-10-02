<?php
function st_notify(
    int $userId,
    string $type,
    string $title,
    string $body,
    ?int $relatedJourneyId = null,
    ?int $relatedUserId = null
): bool {

    $db = safaritrak_db();

    $stmt = $db->prepare(
        'INSERT INTO notifications
        (
            user_id,
            type,
            title,
            body,
            related_journey_id,
            related_user_id,
            is_read,
            created_at
        )
        VALUES (?, ?, ?, ?, ?, ?, 0, NOW())'
    );

    return $stmt->execute([
        $userId,
        $type,
        $title,
        $body,
        $relatedJourneyId,
        $relatedUserId,
    ]);
}