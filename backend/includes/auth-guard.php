<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

st_start_session();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = safaritrak_db();

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if (!$currentUser || (int) ($currentUser['is_suspended'] ?? 0) === 1) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

$paStmt = $db->prepare('SELECT id FROM platform_admins WHERE user_id = ? LIMIT 1');
$paStmt->execute([$currentUser['id']]);
$isPlatformAdmin = (bool) $paStmt->fetch();

$oaStmt = $db->prepare('SELECT organization_id FROM organization_admins WHERE user_id = ? LIMIT 1');
$oaStmt->execute([$currentUser['id']]);
$orgAdminData = $oaStmt->fetch();

if ($isPlatformAdmin) {
    $currentUser['role'] = 'platform_admin';
    $currentUser['organization_id'] = null;
} elseif ($orgAdminData) {
    $currentUser['role'] = 'org_admin';
    $currentUser['organization_id'] = (int) $orgAdminData['organization_id'];
} else {
    $currentUser['role'] = 'traveler';
    $currentUser['organization_id'] = null;
}

$userName = $currentUser['full_name'] ?? '';

$unreadStmt = $db->prepare(
    'SELECT COUNT(DISTINCT sender_id) AS c FROM messages WHERE receiver_id = ? AND read_at IS NULL'
);
$unreadStmt->execute([$currentUser['id']]);
$unreadConversationCount = (int) ($unreadStmt->fetch()['c'] ?? 0);

function st_require_platform_admin(array $user): void {
    if (($user['role'] ?? '') !== 'platform_admin') {
        header('Location: index.php');
        exit();
    }
}

function st_require_org_admin(array $user): void {
    if (($user['role'] ?? '') !== 'org_admin') {
        header('Location: index.php');
        exit();
    }
}