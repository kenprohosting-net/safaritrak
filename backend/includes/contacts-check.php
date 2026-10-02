<?php

function st_are_confirmed_contacts(PDO $db, int $userA, int $userB): bool {
    $stmt = $db->prepare(
        'SELECT id FROM trusted_contacts
         WHERE owner_id = ? AND contact_user_id = ? AND status = "confirmed" LIMIT 1'
    );
    $stmt->execute([$userA, $userB]);
    return (bool) $stmt->fetch();
}
