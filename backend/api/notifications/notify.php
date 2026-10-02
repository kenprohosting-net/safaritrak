<?php

function st_notify(
    int $userId,
    string $type,
    string $title,
    string $message,
    ?int $referenceId = null,
    ?int $senderUserId = null
): bool {

    $db = safaritrak_db();

    $stmt = $db->prepare(
        'INSERT INTO notifications
        (
            user_id,
            type,
            title,
            message,
            reference_id,
            sender_user_id,
            is_read,
            created_at
        )
        VALUES (?, ?, ?, ?, ?, ?, 0, NOW())'
    );

    return $stmt->execute([
        $userId,
        $type,
        $title,
        $message,
        $referenceId,
        $senderUserId
    ]);
}