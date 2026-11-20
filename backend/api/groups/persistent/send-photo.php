<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/message-attachments.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$groupId = (int) ($_POST['group_id'] ?? 0);
$tripLabel = trim(is_string($_POST['trip_label'] ?? null) ? $_POST['trip_label'] : '');
$caption = trim(is_string($_POST['caption'] ?? null) ? $_POST['caption'] : '');
if ($groupId < 1) {
    st_json_error('Choose a group.', 422);
}
if ($tripLabel === '' || mb_strlen($tripLabel) > 120) {
    st_json_error('Enter a trip label of up to 120 characters.', 422);
}
if (mb_strlen($caption) > 2000) {
    st_json_error('Photo captions must be 2,000 characters or fewer.', 422);
}

$db = safaritrak_db();
$memberStmt = $db->prepare(
    'SELECT 1 FROM persistent_group_members gm
     JOIN persistent_groups g ON g.id = gm.group_id
     WHERE gm.group_id = ? AND gm.user_id = ? AND g.status = "active"
       AND (
         g.organization_id IS NULL
         OR (
           EXISTS (
             SELECT 1 FROM organizations o
             JOIN organization_profiles p ON p.organization_id = o.id
             WHERE o.id = g.organization_id AND o.is_suspended = 0 AND p.review_status = "approved"
           )
           AND (
             EXISTS (
               SELECT 1 FROM organization_travelers ot
               WHERE ot.organization_id = g.organization_id AND ot.user_id = gm.user_id AND ot.status = "active"
             )
             OR EXISTS (
               SELECT 1 FROM organization_admins oa
               WHERE oa.organization_id = g.organization_id AND oa.user_id = gm.user_id
             )
           )
         )
       )'
);
$memberStmt->execute([$groupId, $userId]);
if (!$memberStmt->fetchColumn()) {
    st_json_error('You must be an active member of this group to share photos.', 403);
}

$photo = st_store_message_photo($_FILES['photo'] ?? []);
try {
    $db->prepare(
        'INSERT INTO persistent_group_messages (group_id, sender_id, body, attachment_path, trip_label)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$groupId, $userId, $caption, $photo['path'], $tripLabel]);
    $messageId = (int) $db->lastInsertId();
    $db->prepare('UPDATE persistent_groups SET updated_at = NOW() WHERE id = ?')->execute([$groupId]);
} catch (Throwable $exception) {
    $storedPhoto = st_message_photo_file($photo['path']);
    if ($storedPhoto !== null && is_file($storedPhoto['path'])) {
        unlink($storedPhoto['path']);
    }
    throw $exception;
}

st_json_ok(['message_id' => $messageId]);
