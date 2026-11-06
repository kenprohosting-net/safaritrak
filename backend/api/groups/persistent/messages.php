<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/session.php';

st_start_session();
$userId = st_require_login();
$db = safaritrak_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $groupId = (int) ($_GET['group_id'] ?? 0);
    if ($groupId < 1) {
        st_json_error('Choose a group.', 422);
    }
    $memberStmt = $db->prepare(
        'SELECT 1 FROM persistent_group_members gm
         JOIN persistent_groups g ON g.id = gm.group_id
         WHERE gm.group_id = ? AND gm.user_id = ? AND g.status = "active"
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
           )'
    );
    $memberStmt->execute([$groupId, $userId]);
    if (!$memberStmt->fetchColumn()) {
        st_json_error('You must be an active member of this group to view its chat.', 403);
    }
    $messagesStmt = $db->prepare(
        'SELECT m.id, m.sender_id, u.full_name AS sender_name, m.body, m.created_at
         FROM persistent_group_messages m
         JOIN users u ON u.id = m.sender_id
         WHERE m.group_id = ?
         ORDER BY m.created_at DESC, m.id DESC
         LIMIT 100'
    );
    $messagesStmt->execute([$groupId]);
    $messages = array_reverse($messagesStmt->fetchAll(PDO::FETCH_ASSOC));
    foreach ($messages as &$message) {
        $message['is_mine'] = (int) $message['sender_id'] === $userId;
    }
    unset($message);
    st_json_ok(['messages' => $messages]);
}

st_require_method('POST');
$input = st_input();
$groupId = (int) ($input['group_id'] ?? 0);
$body = trim(st_input_string($input, 'body'));
if ($groupId < 1 || $body === '' || mb_strlen($body) > 2000) {
    st_json_error('Choose a group and enter a message up to 2,000 characters.', 422);
}
$memberStmt = $db->prepare(
    'SELECT 1 FROM persistent_group_members gm
     JOIN persistent_groups g ON g.id = gm.group_id
     WHERE gm.group_id = ? AND gm.user_id = ? AND g.status = "active"
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
       )'
);
$memberStmt->execute([$groupId, $userId]);
if (!$memberStmt->fetchColumn()) {
    st_json_error('You must be an active member of this group to send messages.', 403);
}
$db->prepare(
    'INSERT INTO persistent_group_messages (group_id, sender_id, body) VALUES (?, ?, ?)'
)->execute([$groupId, $userId, $body]);
st_json_ok(['message_id' => (int) $db->lastInsertId()]);
