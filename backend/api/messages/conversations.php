<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
$userId = st_require_login();

$db = safaritrak_db();

$contactsStmt = $db->prepare(
    'SELECT u.id, u.full_name, u.avatar_path
     FROM trusted_contacts tc
     JOIN users u ON u.id = tc.contact_user_id
     WHERE tc.owner_id = ? AND tc.status = "confirmed"'
);
$contactsStmt->execute([$userId]);
$contacts = $contactsStmt->fetchAll();

$lastMessageStmt = $db->prepare(
    'SELECT body, sender_id, created_at FROM messages
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

    $conversations[] = [
        'user_id' => $otherId,
        'full_name' => $contact['full_name'],
        'avatar_path' => $contact['avatar_path'],
        'last_message' => $lastMessage['body'] ?? null,
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
