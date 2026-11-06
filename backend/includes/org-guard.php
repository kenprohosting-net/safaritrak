<?php

require_once __DIR__ . '/auth-guard.php';

$orgStmt = safaritrak_db()->prepare(
    'SELECT o.id, o.name, o.is_suspended, oa.role,
            p.account_type, p.review_status
     FROM organization_admins oa
     JOIN organizations o ON o.id = oa.organization_id
     LEFT JOIN organization_profiles p ON p.organization_id = o.id
     WHERE oa.user_id = ?
     LIMIT 1'
);
$orgStmt->execute([$currentUser['id']]);
$myOrg = $orgStmt->fetch() ?: null;
$myOrgSuspended = $myOrg && (int) $myOrg['is_suspended'] === 1;

if ($myOrg && ($myOrg['review_status'] ?? 'approved') !== 'approved') {
    header('Location: org-dashboard.php');
    exit;
}
