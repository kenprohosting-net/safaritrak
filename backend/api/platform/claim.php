<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$db = safaritrak_db();

$countStmt = $db->query('SELECT COUNT(*) FROM platform_admins');
if ((int) $countStmt->fetchColumn() > 0) {
    st_json_error('SafariTrak already has an owner. Ask an existing platform admin to add you.', 403);
}

$db->prepare('INSERT INTO platform_admins (user_id, role) VALUES (?, "owner")')->execute([$userId]);

st_json_ok();
