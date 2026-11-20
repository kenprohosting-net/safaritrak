<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/contacts-check.php';

st_start_session();
$userId = st_require_login();

$otherId = (int) ($_GET['with'] ?? 0);

$db = safaritrak_db();

if (!st_can_message($db, $userId, $otherId)) {
    st_json_error('You can only message confirmed trusted contacts or admins and members of your active organization.', 403);
}

$otherStmt = $db->prepare('SELECT id, full_name, avatar_path FROM users WHERE id = ?');
$otherStmt->execute([$otherId]);
$other = $otherStmt->fetch();

if (!$other) {
    st_json_error('That person could not be found.', 404);
}

$messagesStmt = $db->prepare(
    'SELECT id, sender_id, receiver_id, body, attachment_path, trip_label, created_at, read_at
     FROM messages
     WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
     ORDER BY created_at ASC'
);
$messagesStmt->execute([$userId, $otherId, $otherId, $userId]);
$messages = $messagesStmt->fetchAll();

$db->prepare('UPDATE messages SET read_at = NOW() WHERE sender_id = ? AND receiver_id = ? AND read_at IS NULL')
    ->execute([$otherId, $userId]);

$locationPrefix = '__safaritrak_location__:';
foreach ($messages as &$m) {
    $m['is_mine'] = (int) $m['sender_id'] === $userId;
    if (!empty($m['attachment_path'])) {
        $m['message_type'] = 'photo';
        $m['trip_label'] = (string) ($m['trip_label'] ?? '');
        $m['attachment_url'] = 'backend/api/messages/photo.php?type=direct&id=' . (int) $m['id'];
        $m['body'] = (string) $m['body'];
    } elseif (strncmp((string) $m['body'], $locationPrefix, strlen($locationPrefix)) === 0) {
        $m['message_type'] = 'location';
        $coordinates = json_decode(substr((string) $m['body'], strlen($locationPrefix)), true);
        $m['location'] = is_array($coordinates)
            && isset($coordinates['lat'], $coordinates['lng'])
            && is_numeric($coordinates['lat'])
            && is_numeric($coordinates['lng'])
            && (float) $coordinates['lat'] >= -90
            && (float) $coordinates['lat'] <= 90
            && (float) $coordinates['lng'] >= -180
            && (float) $coordinates['lng'] <= 180
            ? ['lat' => (float) $coordinates['lat'], 'lng' => (float) $coordinates['lng']]
            : null;
        $m['body'] = $m['location'] === null
            ? 'Location data is unavailable.'
            : 'Shared a location point.';
    } else {
        $m['message_type'] = 'text';
    }
    unset($m['attachment_path']);
}
unset($m);

st_json_ok(['other' => $other, 'messages' => $messages]);
