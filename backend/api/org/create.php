<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$name = trim($input['name'] ?? '');

if ($name === '' || mb_strlen($name) > 160) {
    st_json_error('Enter a name for your organization.');
}

$db = safaritrak_db();

$existing = $db->prepare('SELECT organization_id FROM organization_admins WHERE user_id = ? LIMIT 1');
$existing->execute([$userId]);
if ($existing->fetch()) {
    st_json_error('You already manage an organization.');
}

$db->beginTransaction();

try {
    $insertOrg = $db->prepare('INSERT INTO organizations (name) VALUES (?)');
    $insertOrg->execute([$name]);
    $orgId = (int) $db->lastInsertId();

    $insertAdmin = $db->prepare('INSERT INTO organization_admins (organization_id, user_id, role) VALUES (?, ?, "owner")');
    $insertAdmin->execute([$orgId, $userId]);

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    st_json_error('That organization could not be created. Please try again.', 500);
}

st_json_ok(['organization_id' => $orgId]);
