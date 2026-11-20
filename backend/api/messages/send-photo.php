<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/contacts-check.php';
require_once __DIR__ . '/../../includes/notify.php';
require_once __DIR__ . '/../../includes/message-attachments.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$otherId = (int) ($_POST['to'] ?? 0);
$tripLabel = trim(is_string($_POST['trip_label'] ?? null) ? $_POST['trip_label'] : '');
$caption = trim(is_string($_POST['caption'] ?? null) ? $_POST['caption'] : '');
if ($tripLabel === '' || mb_strlen($tripLabel) > 120) {
    st_json_error('Enter a trip label of up to 120 characters.', 422);
}
if (mb_strlen($caption) > 2000) {
    st_json_error('Photo captions must be 2,000 characters or fewer.', 422);
}

$db = safaritrak_db();
if (!st_can_message($db, $userId, $otherId)) {
    st_json_error('You can only share photos with confirmed trusted contacts or admins and members of your active organization.', 403);
}

$photo = st_store_message_photo($_FILES['photo'] ?? []);
try {
    $insert = $db->prepare(
        'INSERT INTO messages (sender_id, receiver_id, body, attachment_path, trip_label)
         VALUES (?, ?, ?, ?, ?)'
    );
    $insert->execute([$userId, $otherId, $caption, $photo['path'], $tripLabel]);
} catch (Throwable $exception) {
    $storedPhoto = st_message_photo_file($photo['path']);
    if ($storedPhoto !== null && is_file($storedPhoto['path'])) {
        unlink($storedPhoto['path']);
    }
    throw $exception;
}

$messageId = (int) $db->lastInsertId();
$senderStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
$senderStmt->execute([$userId]);
$senderName = (string) $senderStmt->fetchColumn();
st_notify($otherId, 'new_message', 'New photo from ' . $senderName, 'Shared a photo from ' . $tripLabel, null, $userId);

$createdStmt = $db->prepare('SELECT created_at FROM messages WHERE id = ?');
$createdStmt->execute([$messageId]);
st_json_ok(['message_id' => $messageId, 'created_at' => $createdStmt->fetchColumn()]);
