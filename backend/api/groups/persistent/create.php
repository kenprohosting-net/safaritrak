<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$name = trim(st_input_string($input, 'name'));
$description = trim(st_input_string($input, 'description'));
$organizationId = isset($input['organization_id']) && $input['organization_id'] !== ''
    ? (int) $input['organization_id']
    : null;
$memberIds = is_array($input['member_ids'] ?? null) ? $input['member_ids'] : [];
foreach ($memberIds as $memberId) {
    if (filter_var($memberId, FILTER_VALIDATE_INT) === false || (int) $memberId < 1) {
        st_json_error('Selected organization members must be valid.', 422);
    }
}
$memberIds = array_values(array_unique(array_map('intval', $memberIds)));
if (count($memberIds) > 200) {
    st_json_error('A group can start with at most 200 selected members.', 422);
}

if ($name === '' || mb_strlen($name) > 120) {
    st_json_error('Group names must be between 1 and 120 characters.', 422);
}
if (mb_strlen($description) > 500) {
    st_json_error('Group descriptions must be 500 characters or fewer.', 422);
}
if ($organizationId !== null && $organizationId < 1) {
    st_json_error('Choose a valid organization.', 422);
}

$db = safaritrak_db();
if ($organizationId !== null) {
    $adminStmt = $db->prepare(
        'SELECT op.account_type FROM organization_admins oa
         JOIN organization_profiles op ON op.organization_id = oa.organization_id
         JOIN organizations o ON o.id = oa.organization_id
         WHERE oa.user_id = ? AND oa.organization_id = ?
           AND op.review_status = "approved" AND o.is_suspended = 0'
    );
    $adminStmt->execute([$userId, $organizationId]);
    $orgType = $adminStmt->fetchColumn();
    if (!$orgType) {
        st_json_error('Only an approved organization admin can create an organization group.', 403);
    }
    if ($orgType !== 'school') {
        st_json_error('Organization group workspaces are currently available for schools.', 403);
    }
    if ($memberIds) {
        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
        $memberStmt = $db->prepare(
            "SELECT user_id FROM organization_travelers
             WHERE organization_id = ? AND status = 'active' AND user_id IN ($placeholders)"
        );
        $memberStmt->execute(array_merge([$organizationId], $memberIds));
        $validMemberIds = array_map('intval', $memberStmt->fetchAll(PDO::FETCH_COLUMN));
        if (count($validMemberIds) !== count($memberIds)) {
            st_json_error('One or more selected users are not approved school members.', 403);
        }
    }
} elseif ($memberIds) {
    st_json_error('Only an organization admin can add organization members during group creation.', 403);
}

$db->beginTransaction();
try {
    $db->prepare(
        'INSERT INTO persistent_groups (owner_user_id, organization_id, name, description)
         VALUES (?, ?, ?, ?)'
    )->execute([$userId, $organizationId, $name, $description === '' ? null : $description]);
    $groupId = (int) $db->lastInsertId();
    $db->prepare(
        'INSERT INTO persistent_group_members (group_id, user_id, role) VALUES (?, ?, "owner")'
    )->execute([$groupId, $userId]);
    if ($memberIds) {
        $memberInsert = $db->prepare(
            'INSERT INTO persistent_group_members (group_id, user_id, role) VALUES (?, ?, "member")'
        );
        foreach ($memberIds as $memberId) {
            if ($memberId !== $userId) {
                $memberInsert->execute([$groupId, $memberId]);
            }
        }
    }
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

st_json_ok(['group_id' => $groupId]);
