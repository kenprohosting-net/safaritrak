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
$messageType = is_string($input['type'] ?? null) ? trim($input['type']) : 'text';
$locationPrefix = '__safaritrak_location__:';

if ($messageType === 'location') {
    $location = $input['location'] ?? null;
    if (
        !is_array($location)
        || !isset($location['lat'], $location['lng'])
        || !is_numeric($location['lat'])
        || !is_numeric($location['lng'])
    ) {
        st_json_error('Choose a valid location to share.');
    }

    $lat = (float) $location['lat'];
    $lng = (float) $location['lng'];
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        st_json_error('Choose a valid location to share.');
    }

    $body = $locationPrefix . json_encode(
        ['lat' => $lat, 'lng' => $lng],
        JSON_THROW_ON_ERROR
    );
    $preview = 'Shared a location point.';
} elseif ($messageType === 'text') {
    $body = is_string($input['body'] ?? null) ? trim($input['body']) : '';
    if ($body === '') {
        st_json_error('Type a message before sending.');
    }
    if (mb_strlen($body) > 2000) {
        st_json_error('That message is too long.');
    }
    $preview = mb_strlen($body) > 80 ? mb_substr($body, 0, 80) . '...' : $body;
} else {
    st_json_error('Choose a valid message type.');
}

$db = safaritrak_db();

if (!st_can_message($db, $userId, $otherId)) {
    st_json_error('You can only message confirmed trusted contacts or admins and members of your active organization.', 403);
}

$insert = $db->prepare('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)');
$insert->execute([$userId, $otherId, $body]);
$messageId = (int) $db->lastInsertId();

$senderStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
$senderStmt->execute([$userId]);
$senderName = $senderStmt->fetchColumn();

st_notify($otherId, 'new_message', 'New message from ' . $senderName, $preview, null, $userId);

$createdStmt = $db->prepare('SELECT created_at FROM messages WHERE id = ?');
$createdStmt->execute([$messageId]);

st_json_ok(['message_id' => $messageId, 'created_at' => $createdStmt->fetchColumn()]);
