<?php

require_once __DIR__ . '/auth-guard.php';

$adminCountStmt = safaritrak_db()->query('SELECT COUNT(*) FROM platform_admins');
$platformAdminExists = ((int) $adminCountStmt->fetchColumn()) > 0;

$myAdminStmt = safaritrak_db()->prepare('SELECT id, role FROM platform_admins WHERE user_id = ?');
$myAdminStmt->execute([$currentUser['id']]);
$myPlatformRole = $myAdminStmt->fetch();
