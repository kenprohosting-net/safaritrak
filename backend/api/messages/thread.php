<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/contacts-check.php';

st_start_session();
$userId = st_require_login();

$otherId = (int) ($_GET['with'] ?? 0);

$db = safaritrak_db();

if (!st_are_confirmed_contacts($db, $userId, $otherId)) {
    st_json_error('You can only message confirmed trusted contacts.', 403);
}

$otherStmt = $db->prepare('SELECT id, full_name, avatar_path FROM users WHERE id = ?');
$otherStmt->execute([$otherId]);
$other = $otherStmt->fetch();

if (!$other) {
    st_json_error('That person could not be found.', 404);
}

$messagesStmt = $db->prepare(
    'SELECT id, sender_id, receiver_id, body, created_at, read_at
     FROM messages
     WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
     ORDER BY created_at ASC'
);
$messagesStmt->execute([$userId, $otherId, $otherId, $userId]);
$messages = $messagesStmt->fetchAll();

$db->prepare('UPDATE messages SET read_at = NOW() WHERE sender_id = ? AND receiver_id = ? AND read_at IS NULL')
    ->execute([$otherId, $userId]);

foreach ($messages as &$m) {
    $m['is_mine'] = (int) $m['sender_id'] === $userId;
}
unset($m);

st_json_ok(['other' => $other, 'messages' => $messages]);
