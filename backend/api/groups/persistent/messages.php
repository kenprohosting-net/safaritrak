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
        'SELECT m.id, m.sender_id, u.full_name AS sender_name, u.avatar_path AS sender_avatar_path,
                m.body, m.attachment_path, m.trip_label, m.created_at
         FROM persistent_group_messages m
         JOIN users u ON u.id = m.sender_id
         WHERE m.group_id = ?
         ORDER BY m.created_at DESC, m.id DESC
         LIMIT 100'
    );
    $messagesStmt->execute([$groupId]);
    $messages = array_reverse($messagesStmt->fetchAll(PDO::FETCH_ASSOC));
    $lastReadMessageId = null;
    foreach ($messages as &$message) {
        $message['is_mine'] = (int) $message['sender_id'] === $userId;
        if (!empty($message['attachment_path'])) {
            $message['message_type'] = 'photo';
            $message['trip_label'] = (string) ($message['trip_label'] ?? '');
            $message['attachment_url'] = 'backend/api/messages/photo.php?type=group&id=' . (int) $message['id'];
            $message['body'] = (string) $message['body'];
            unset($message['attachment_path']);
        } else {
            $message['message_type'] = 'text';
            $message['location'] = null;
        }
        $locationPrefix = '__safaritrak_location__:';
        if ($message['message_type'] !== 'photo' && str_starts_with((string) $message['body'], $locationPrefix)) {
            $location = json_decode(substr((string) $message['body'], strlen($locationPrefix)), true);
            if (
                is_array($location)
                && isset($location['lat'], $location['lng'])
                && is_numeric($location['lat'])
                && is_numeric($location['lng'])
                && is_finite((float) $location['lat'])
                && is_finite((float) $location['lng'])
                && (float) $location['lat'] >= -90 && (float) $location['lat'] <= 90
                && (float) $location['lng'] >= -180 && (float) $location['lng'] <= 180
            ) {
                $message['message_type'] = 'location';
                $message['location'] = [
                    'lat' => (float) $location['lat'],
                    'lng' => (float) $location['lng'],
                ];
                $message['body'] = 'Shared a location point.';
            }
        }
        $lastReadMessageId = $message['id'];
    }
    unset($message);
    if ($lastReadMessageId !== null) {
        $db->prepare(
            'UPDATE persistent_group_members
             SET last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), ?)
             WHERE group_id = ? AND user_id = ?'
        )->execute([$lastReadMessageId, $groupId, $userId]);
    }
    st_json_ok(['messages' => $messages]);
}

st_require_method('POST');
$input = st_input();
$groupId = (int) ($input['group_id'] ?? 0);
$body = trim(st_input_string($input, 'body'));
$messageType = st_input_string($input, 'type');
if ($groupId < 1) {
    st_json_error('Choose a group.', 422);
}
if ($messageType === 'location') {
    $lat = $input['lat'] ?? null;
    $lng = $input['lng'] ?? null;
    if (
        !is_numeric($lat) || !is_numeric($lng)
        || !is_finite((float) $lat) || !is_finite((float) $lng)
        || (float) $lat < -90 || (float) $lat > 90
        || (float) $lng < -180 || (float) $lng > 180
    ) {
        st_json_error('A valid location is required to share a map point.', 422);
    }
    $body = '__safaritrak_location__:' . json_encode(
        ['lat' => (float) $lat, 'lng' => (float) $lng],
        JSON_THROW_ON_ERROR
    );
} elseif ($messageType !== '' && $messageType !== 'text') {
    st_json_error('Choose a supported message type.', 422);
} elseif ($body === '' || mb_strlen($body) > 2000) {
    st_json_error('Enter a message up to 2,000 characters.', 422);
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
