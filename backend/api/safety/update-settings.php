<?php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../config/database.php';

st_start_session();
st_require_method('POST');

if (empty($_SESSION['user_id'])) {
    st_json_error('Unauthorized access.', 401);
}

$allowedFields = [
    'route_deviation_alerts',
    'arrival_notifications',
    'show_history_to_contacts',
    'allow_group_journeys',
    'discoverable_by_phone',
];

$userId = (int) $_SESSION['user_id'];
$input = st_input();

$field = (string) ($input['field'] ?? '');
$value = $input['value'] ?? null;

if (!in_array($field, $allowedFields, true)) {
    st_json_error('Unknown setting.', 422);
}

if (!is_bool($value) && $value !== 0 && $value !== 1 && $value !== '0' && $value !== '1') {
    st_json_error('Invalid value for that setting.', 422);
}

$boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

$db = safaritrak_db();
$updateStmt = $db->prepare("UPDATE users SET {$field} = ? WHERE id = ?");
$updateStmt->execute([$boolValue, $userId]);

st_json_ok(['field' => $field, 'value' => (bool) $boolValue]);