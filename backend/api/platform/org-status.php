<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$db = safaritrak_db();

$adminStmt = $db->prepare('SELECT id FROM platform_admins WHERE user_id = ?');
$adminStmt->execute([$userId]);
if (!$adminStmt->fetch()) {
    st_json_error('You do not have platform admin access.', 403);
}

$input = st_input();
$orgId = (int) ($input['organization_id'] ?? 0);
$action = $input['action'] ?? '';

if (!in_array($action, ['approve', 'reject', 'suspend', 'reactivate'], true)) {
    st_json_error('That action is not recognized.');
}

$orgStmt = $db->prepare('SELECT id FROM organizations WHERE id = ?');
$orgStmt->execute([$orgId]);
if (!$orgStmt->fetch()) {
    st_json_error('That organization could not be found.', 404);
}

if (in_array($action, ['approve', 'reject'], true)) {
    $reviewStatus = $action === 'approve' ? 'approved' : 'rejected';
    $reviewStmt = $db->prepare(
        'UPDATE organization_profiles SET review_status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP()
         WHERE organization_id = ?'
    );
    $reviewStmt->execute([$reviewStatus, $userId, $orgId]);
    if ($reviewStmt->rowCount() === 0) {
        st_json_error('This organization has no reviewable account profile.', 409);
    }
    if ($action === 'approve') {
        $db->prepare(
            'INSERT IGNORE INTO school_verifications
                (user_id, school_id, role, verification_status, verified_at)
             SELECT oa.user_id, oa.organization_id, "school_admin", "verified", UTC_TIMESTAMP()
             FROM organization_admins oa
             JOIN organization_profiles p ON p.organization_id = oa.organization_id
             WHERE oa.organization_id = ? AND p.account_type = "school"
               AND p.review_status = "approved"'
        )->execute([$orgId]);
        $db->prepare(
            'INSERT IGNORE INTO school_verifications
                (user_id, school_id, role, verification_status, verified_at)
             SELECT ot.user_id, ot.organization_id, "school_admin", "verified", UTC_TIMESTAMP()
             FROM organization_travelers ot
             JOIN organization_profiles p ON p.organization_id = ot.organization_id
             WHERE ot.organization_id = ? AND p.account_type = "school"
               AND p.review_status = "approved" AND ot.status = "active"
               AND ot.member_type = "staff"
               AND NOT EXISTS (
                 SELECT 1 FROM organization_admins oa
                 WHERE oa.organization_id = ot.organization_id AND oa.user_id = ot.user_id
               )'
        )->execute([$orgId]);
    } elseif ($reviewStatus === 'rejected') {
        $db->prepare(
            'UPDATE school_verifications SET verification_status = "revoked"
             WHERE school_id = ?'
        )->execute([$orgId]);
    }
    st_json_ok(['status' => $reviewStatus]);
}

$newStatus = $action === 'suspend' ? 1 : 0;
$db->prepare('UPDATE organizations SET is_suspended = ? WHERE id = ?')->execute([$newStatus, $orgId]);

st_json_ok(['status' => $newStatus ? 'suspended' : 'active']);
