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

$stmt = $db->prepare(
    'SELECT
        u.id,
        u.full_name,
        u.username,
        u.email,
        u.phone,
        u.is_suspended,
        u.home_address,
        u.avatar_path,
        u.show_history_to_contacts,
        u.allow_group_journeys,
        u.discoverable_by_phone,
        u.route_deviation_alerts,
        u.arrival_notifications,
        u.auto_sos_on_silence,
        u.sos_alert_notifications,
        u.product_update_emails,
        EXISTS (SELECT 1 FROM platform_admins pa WHERE pa.user_id = u.id) AS is_platform_admin,
        (SELECT oa.organization_id FROM organization_admins oa WHERE oa.user_id = u.id LIMIT 1) AS organization_id
     FROM users u
     WHERE u.id = ?'
);
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if (!$currentUser || (int) ($currentUser['is_suspended'] ?? 0) === 1) {
    st_destroy_current_session($db);
    header('Location: login.php');
    exit();
}

$isPlatformAdmin = (bool) $currentUser['is_platform_admin'];
$organizationId = $currentUser['organization_id'];
unset($currentUser['is_platform_admin'], $currentUser['organization_id']);

if ($isPlatformAdmin) {
    $currentUser['role'] = 'platform_admin';
    $currentUser['organization_id'] = null;
} elseif ($organizationId !== null) {
    $currentUser['role'] = 'org_admin';
    $currentUser['organization_id'] = (int) $organizationId;
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

session_write_close();

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