<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';
require_once __DIR__ . '/../../includes/contacts-check.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$otherId = (int) ($input['to'] ?? 0);
$body = trim($input['body'] ?? '');

if ($body === '') {
    st_json_error('Type a message before sending.');
}

if (mb_strlen($body) > 2000) {
    st_json_error('That message is too long.');
}

$db = safaritrak_db();

if (!st_are_confirmed_contacts($db, $userId, $otherId)) {
    st_json_error('You can only message confirmed trusted contacts.', 403);
}

$insert = $db->prepare('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)');
$insert->execute([$userId, $otherId, $body]);
$messageId = (int) $db->lastInsertId();

$senderStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
$senderStmt->execute([$userId]);
$senderName = $senderStmt->fetchColumn();

$preview = mb_strlen($body) > 80 ? mb_substr($body, 0, 80) . '...' : $body;
st_notify($otherId, 'new_message', 'New message from ' . $senderName, $preview, null, $userId);

$createdStmt = $db->prepare('SELECT created_at FROM messages WHERE id = ?');
$createdStmt->execute([$messageId]);

st_json_ok(['message_id' => $messageId, 'created_at' => $createdStmt->fetchColumn()]);
