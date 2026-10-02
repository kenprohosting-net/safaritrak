<?php
ob_start();
register_shutdown_function(function () {
    $buffer = ob_get_clean();
    if (preg_match('/\{.*\}\s*$/s', $buffer, $m)) {
        if (trim($buffer) !== trim($m[0])) {
            error_log('[SafariTrak mark-read] stripped extra output: ' . trim(str_replace($m[0], '', $buffer)));
        }
        header('Content-Type: application/json');
        echo $m[0];
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        error_log('[SafariTrak mark-read] fatal with no JSON produced: ' . trim($buffer));
        echo json_encode(['success' => false, 'message' => 'Unexpected server error. Check php-error.log.']);
    }
});

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$db = safaritrak_db();

if (!empty($input['all'])) {
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$userId]);
    st_json_ok();
    exit;
}

$notificationId = (int) ($input['id'] ?? 0);
$stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
$stmt->execute([$notificationId, $userId]);

st_json_ok();
exit;