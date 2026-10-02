<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/session.php';

if (!function_exists('st_success')) {
    function st_success($data = [], $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
        exit;
    }
}

st_start_session();

$userId = st_require_login();

$db = safaritrak_db();

$stmt = $db->prepare("
    SELECT
        gji.id,
        gji.group_journey_id,
        gji.inviter_id,
        gji.invitee_id,
        gji.status,
        gji.role,
        gji.message,
        gji.invited_at,
        gji.responded_at,
        gji.joined_at,
        gji.expires_at,
        u.full_name AS inviter_name,
        u.phone AS inviter_phone
    FROM group_journey_invite gji
    LEFT JOIN users u
        ON u.id = gji.inviter_id
    WHERE gji.invitee_id = ?
      AND gji.status = 'pending'
    ORDER BY gji.invited_at DESC
");

$stmt->execute([$userId]);

$invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($invitations as &$invitation) {
    $invitation['id'] = (int)$invitation['id'];
    $invitation['group_journey_id'] = (int)$invitation['group_journey_id'];
    $invitation['inviter_id'] = (int)$invitation['inviter_id'];
    $invitation['invitee_id'] = (int)$invitation['invitee_id'];
}

unset($invitation);

st_success([
    'count' => count($invitations),
    'invitations' => $invitations
], 'Group journey invitations retrieved successfully.');