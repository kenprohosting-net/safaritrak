<?php

require_once __DIR__ . '/backend/includes/session.php';
require_once __DIR__ . '/backend/includes/response.php';
require_once __DIR__ . '/backend/config/database.php';

st_start_session();
st_require_method('POST');

if (empty($_SESSION['user_id'])) {
    st_json_error('Unauthorized access.', 401);
}

$userId = (int) $_SESSION['user_id'];
$input = st_input();
$messageId = (int) ($input['message_id'] ?? 0);

if ($messageId <= 0) {
    st_json_error('Invalid message ID.', 400);
}

$db = safaritrak_db();

// Delete the message only if the current user is the sender
$stmt = $db->prepare('DELETE FROM messages WHERE id = ? AND sender_id = ?');
$stmt->execute([$messageId, $userId]);

if ($stmt->rowCount() === 0) {
    st_json_error('Message not found or permission denied.', 404);
}

st_json_ok(['message' => 'Message deleted successfully.']);