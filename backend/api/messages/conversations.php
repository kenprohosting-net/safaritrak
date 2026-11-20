<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
$userId = st_require_login();

$db = safaritrak_db();

$contactsStmt = $db->prepare(
    'SELECT DISTINCT u.id, u.full_name, u.avatar_path
     FROM users u
     WHERE u.id <> ?
       AND (
         EXISTS (
           SELECT 1 FROM trusted_contacts tc
           WHERE tc.owner_id = ? AND tc.contact_user_id = u.id AND tc.status = "confirmed"
         )
         OR EXISTS (
           SELECT 1
           FROM organization_admins oa
           JOIN organizations o ON o.id = oa.organization_id AND o.is_suspended = 0
           JOIN organization_profiles p ON p.organization_id = o.id AND p.review_status = "approved"
           JOIN organization_travelers ot ON ot.organization_id = o.id AND ot.status = "active"
           WHERE oa.user_id = ? AND ot.user_id = u.id
         )
         OR EXISTS (
           SELECT 1
           FROM organization_travelers ot
           JOIN organizations o ON o.id = ot.organization_id AND o.is_suspended = 0
           JOIN organization_profiles p ON p.organization_id = o.id AND p.review_status = "approved"
           JOIN organization_admins oa ON oa.organization_id = o.id
           WHERE ot.user_id = ? AND ot.status = "active" AND oa.user_id = u.id
         )
         OR EXISTS (
           SELECT 1
           FROM rental_bookings b
           JOIN rental_vehicles v ON v.id = b.vehicle_id
           JOIN organization_admins oa ON oa.organization_id = v.organization_id
           WHERE b.customer_id = ? AND oa.user_id = u.id
             AND b.status IN ("requested", "responded", "approved", "completed")
         )
         OR EXISTS (
           SELECT 1
           FROM rental_bookings b
           JOIN rental_vehicles v ON v.id = b.vehicle_id
           JOIN organization_admins oa ON oa.organization_id = v.organization_id
           WHERE b.customer_id = u.id AND oa.user_id = ?
             AND b.status IN ("requested", "responded", "approved", "completed")
         )
       )'
);
$contactsStmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
$contacts = $contactsStmt->fetchAll();

$lastMessageStmt = $db->prepare(
    'SELECT body, attachment_path, trip_label, sender_id, created_at FROM messages
     WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
     ORDER BY created_at DESC LIMIT 1'
);

$unreadStmt = $db->prepare(
    'SELECT COUNT(*) FROM messages WHERE sender_id = ? AND receiver_id = ? AND read_at IS NULL'
);

$conversations = [];
foreach ($contacts as $contact) {
    $otherId = (int) $contact['id'];

    $lastMessageStmt->execute([$userId, $otherId, $otherId, $userId]);
    $lastMessage = $lastMessageStmt->fetch();

    $unreadStmt->execute([$otherId, $userId]);
    $unreadCount = (int) $unreadStmt->fetchColumn();
    $lastMessageBody = $lastMessage['body'] ?? null;
    if (!empty($lastMessage['attachment_path'])) {
        $lastMessageBody = 'Photo · ' . (string) ($lastMessage['trip_label'] ?: 'Trip');
    } elseif (
        is_string($lastMessageBody)
        && strncmp($lastMessageBody, '__safaritrak_location__:', strlen('__safaritrak_location__:')) === 0
    ) {
        $lastMessageBody = 'Shared a location point.';
    }

    $conversations[] = [
        'user_id' => $otherId,
        'full_name' => $contact['full_name'],
        'avatar_path' => $contact['avatar_path'],
        'last_message' => $lastMessageBody,
        'last_message_mine' => $lastMessage ? ((int) $lastMessage['sender_id'] === $userId) : null,
        'last_message_at' => $lastMessage['created_at'] ?? null,
        'unread_count' => $unreadCount,
    ];
}

usort($conversations, function ($a, $b) {
    $aTime = $a['last_message_at'] ?? '0000-00-00';
    $bTime = $b['last_message_at'] ?? '0000-00-00';
    return strcmp($bTime, $aTime);
});

st_json_ok(['conversations' => $conversations]);
