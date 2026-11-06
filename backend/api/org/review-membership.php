<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$requestId = (int) ($input['request_id'] ?? 0);
$action = st_input_string($input, 'action');
if ($requestId < 1 || !in_array($action, ['approve', 'reject'], true)) {
    st_json_error('Choose a valid enrollment request and action.', 422);
}

$db = safaritrak_db();
$db->beginTransaction();
try {
    $requestStmt = $db->prepare(
        'SELECT r.id, r.organization_id, r.user_id, r.member_type, r.status
         FROM organization_membership_requests r
         JOIN organization_admins oa ON oa.organization_id = r.organization_id AND oa.user_id = ?
         JOIN organization_profiles p ON p.organization_id = r.organization_id
         JOIN organizations o ON o.id = r.organization_id
         WHERE r.id = ? AND r.status = "pending" AND p.account_type = "school"
           AND p.review_status = "approved" AND o.is_suspended = 0
         FOR UPDATE'
    );
    $requestStmt->execute([$userId, $requestId]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        $db->rollBack();
        st_json_error('That pending school enrollment request could not be found.', 404);
    }

    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    $db->prepare(
        'UPDATE organization_membership_requests SET status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ?'
    )->execute([$newStatus, $userId, $requestId]);
    if ($action === 'approve') {
        $db->prepare(
            'INSERT INTO organization_travelers (organization_id, user_id, status, member_type)
             VALUES (?, ?, "active", ?)
             ON DUPLICATE KEY UPDATE
               status = "active",
               member_type = VALUES(member_type),
               joined_at = UTC_TIMESTAMP()'
        )->execute([
            (int) $request['organization_id'],
            (int) $request['user_id'],
            $request['member_type'],
        ]);
    }
    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $error;
}

st_json_ok(['status' => $newStatus]);
