<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$groupId = (int) ($input['group_id'] ?? 0);
$action = st_input_string($input, 'action');
$targetUserId = (int) ($input['user_id'] ?? 0);

if ($groupId < 1 || !in_array($action, ['leave', 'remove'], true)) {
    st_json_error('Choose a valid group action.', 422);
}
if ($action === 'remove' && $targetUserId < 1) {
    st_json_error('Choose a valid group member.', 422);
}
if ($action === 'remove' && $targetUserId === $userId) {
    st_json_error('Use the leave-group action to remove yourself.', 422);
}

$db = safaritrak_db();
$db->beginTransaction();
try {
    $actorStmt = $db->prepare(
        'SELECT g.owner_user_id, g.organization_id, gm.role
         FROM persistent_groups g
         JOIN persistent_group_members gm ON gm.group_id = g.id AND gm.user_id = ?
         WHERE g.id = ? AND g.status = "active"
           AND (
             g.organization_id IS NULL
             OR (
               EXISTS (
                 SELECT 1 FROM organizations o
                 JOIN organization_profiles p ON p.organization_id = o.id
                 WHERE o.id = g.organization_id AND o.is_suspended = 0 AND p.review_status = "approved"
               )
               AND (
                 EXISTS (
                   SELECT 1 FROM organization_travelers ot
                   WHERE ot.organization_id = g.organization_id AND ot.user_id = gm.user_id AND ot.status = "active"
                 )
                 OR EXISTS (
                   SELECT 1 FROM organization_admins oa
                   WHERE oa.organization_id = g.organization_id AND oa.user_id = gm.user_id
                 )
               )
             )
           )
         FOR UPDATE'
    );
    $actorStmt->execute([$userId, $groupId]);
    $actor = $actorStmt->fetch(PDO::FETCH_ASSOC);
    if (!$actor) {
        $db->rollBack();
        st_json_error('You must be an active group member to change group membership.', 403);
    }

    if ($action === 'remove') {
        if (!in_array($actor['role'], ['owner', 'admin'], true)) {
            $db->rollBack();
            st_json_error('Only group owners and admins can remove members.', 403);
        }
        $targetStmt = $db->prepare(
            'SELECT role FROM persistent_group_members
             WHERE group_id = ? AND user_id = ? FOR UPDATE'
        );
        $targetStmt->execute([$groupId, $targetUserId]);
        $targetRole = $targetStmt->fetchColumn();
        if (!$targetRole) {
            $db->rollBack();
            st_json_error('That user is not a member of this group.', 404);
        }
        if ($targetRole === 'owner') {
            $db->rollBack();
            st_json_error('The group owner cannot be removed. They can leave the group instead.', 403);
        }
        if ($actor['role'] === 'admin' && $targetRole !== 'member') {
            $db->rollBack();
            st_json_error('Admins can remove members, but not other group admins.', 403);
        }
        $db->prepare(
            'DELETE FROM persistent_group_members WHERE group_id = ? AND user_id = ?'
        )->execute([$groupId, $targetUserId]);
    } else {
        if ($actor['role'] === 'owner') {
            $successorStmt = $db->prepare(
                'SELECT user_id FROM persistent_group_members
                 WHERE group_id = ? AND user_id <> ?
                 ORDER BY FIELD(role, "admin", "member"), joined_at, id
                 LIMIT 1 FOR UPDATE'
            );
            $successorStmt->execute([$groupId, $userId]);
            $successorId = (int) ($successorStmt->fetchColumn() ?: 0);
            if ($successorId > 0) {
                $db->prepare(
                    'UPDATE persistent_group_members SET role = "member"
                     WHERE group_id = ? AND role = "owner"'
                )->execute([$groupId]);
                $db->prepare(
                    'UPDATE persistent_group_members SET role = "owner"
                     WHERE group_id = ? AND user_id = ?'
                )->execute([$groupId, $successorId]);
                $db->prepare(
                    'UPDATE persistent_groups SET owner_user_id = ? WHERE id = ?'
                )->execute([$successorId, $groupId]);
            } else {
                $db->prepare(
                    'UPDATE persistent_groups SET status = "archived" WHERE id = ?'
                )->execute([$groupId]);
            }
        }
        $db->prepare(
            'DELETE FROM persistent_group_members WHERE group_id = ? AND user_id = ?'
        )->execute([$groupId, $userId]);
    }

    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

st_json_ok([
    'message' => $action === 'leave' ? 'You left the group.' : 'The member was removed from the group.',
]);
