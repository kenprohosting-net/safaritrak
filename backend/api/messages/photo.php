<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/message-attachments.php';

st_start_session();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    st_json_error('Only GET requests are allowed.', 405);
}
$userId = st_require_login();
$messageId = (int) ($_GET['id'] ?? 0);
$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
if ($messageId < 1 || !in_array($type, ['direct', 'group'], true)) {
    st_json_error('Choose a valid photo.', 422);
}

$db = safaritrak_db();
if ($type === 'direct') {
    $photoStmt = $db->prepare(
        'SELECT attachment_path FROM messages
         WHERE id = ? AND (sender_id = ? OR receiver_id = ?)'
    );
    $photoStmt->execute([$messageId, $userId, $userId]);
} else {
    $photoStmt = $db->prepare(
        'SELECT m.attachment_path
         FROM persistent_group_messages m
         JOIN persistent_groups g ON g.id = m.group_id AND g.status = "active"
         JOIN persistent_group_members gm ON gm.group_id = g.id AND gm.user_id = ?
         WHERE m.id = ?
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
    $photoStmt->execute([$userId, $messageId]);
}
$relativePath = $photoStmt->fetchColumn();
$photo = is_string($relativePath) && $relativePath !== ''
    ? st_message_photo_file($relativePath)
    : null;
if ($photo === null) {
    st_json_error('That photo is unavailable.', 404);
}

session_write_close();
header('Content-Type: ' . $photo['mime']);
header('Content-Disposition: inline; filename="safaritrak-photo.' . pathinfo($photo['path'], PATHINFO_EXTENSION) . '"');
header('Content-Length: ' . filesize($photo['path']));
header('Cache-Control: private, max-age=60');
header('X-Content-Type-Options: nosniff');
readfile($photo['path']);
exit;
