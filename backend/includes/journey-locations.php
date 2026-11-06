<?php

function st_active_shared_journey_locations(PDO $db, int $viewerId): array {
    $locationsStmt = $db->prepare(
        'SELECT j.id AS journey_id,
                j.user_id AS owner_id,
                u.full_name AS owner_name,
                u.avatar_path AS owner_avatar_path,
                (SELECT jp.lat
                 FROM journey_positions jp
                 WHERE jp.journey_id = j.id
                 ORDER BY jp.recorded_at DESC, jp.id DESC
                 LIMIT 1) AS lat,
                (SELECT jp.lng
                 FROM journey_positions jp
                 WHERE jp.journey_id = j.id
                 ORDER BY jp.recorded_at DESC, jp.id DESC
                 LIMIT 1) AS lng,
                (SELECT jp.recorded_at
                 FROM journey_positions jp
                 WHERE jp.journey_id = j.id
                 ORDER BY jp.recorded_at DESC, jp.id DESC
                 LIMIT 1) AS recorded_at
         FROM journeys j
         JOIN users u ON u.id = j.user_id
         WHERE j.status = "active"
           AND j.user_id <> ?
           AND EXISTS (
               SELECT 1
               FROM journey_shares js
               JOIN trusted_contacts tc ON tc.id = js.trusted_contact_id
               WHERE js.journey_id = j.id
                 AND tc.owner_id = j.user_id
                 AND tc.contact_user_id = ?
                 AND tc.status = "confirmed"
                 AND tc.share_live_location = 1
           )
         ORDER BY j.started_at DESC
         LIMIT 100'
    );
    $locationsStmt->execute([$viewerId, $viewerId]);

    return $locationsStmt->fetchAll();
}
