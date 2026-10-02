<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
$userId = st_require_login();

$limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 8;

$db = safaritrak_db();
$stmt = $db->prepare(
    'SELECT id, type, title, body, related_journey_id, related_user_id, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT ' . $limit
);
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$unreadStmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$unreadStmt->execute([$userId]);
$unreadCount = (int) $unreadStmt->fetchColumn();

st_json_ok(['notifications' => $notifications, 'unread_count' => $unreadCount]);
