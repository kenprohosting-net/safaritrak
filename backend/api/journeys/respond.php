<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/session.php';

if (!function_exists('st_error')) {
    function st_error($message, $status = 400) {
        http_response_code((int)$status);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

if (!function_exists('st_success')) {
    function st_success($data = [], $message = 'Success') {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
        exit;
    }
}

st_start_session();
st_require_method('POST');

$userId = st_require_login();
$input = st_input();

$inviteId = isset($input['invite_id']) ? (int)$input['invite_id'] : 0;
$action   = isset($input['action']) ? strtolower(trim($input['action'])) : '';

if ($inviteId <= 0) {
    if (function_exists('st_error')) {
        st_error('Invitation ID is required.', 400);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invitation ID is required.']);
        exit;
    }
}

if ($action !== 'accept' && $action !== 'decline') {
    st_error('Invalid action.', 400);
}

$db = safaritrak_db();

$inviteStmt = $db->prepare("
    SELECT *
    FROM group_journey_invite
    WHERE id = ?
    LIMIT 1
");
$inviteStmt->execute([$inviteId]);
$invite = $inviteStmt->fetch(PDO::FETCH_ASSOC);

if (!$invite) {
    st_error('Invitation not found.', 404);
}

if ((int)$invite['invitee_id'] !== $userId) {
    st_error('Access denied.', 403);
}

if ($invite['status'] !== 'pending') {
    st_error('This invitation has already been processed.', 400);
}

if ($action === 'decline') {

    $stmt = $db->prepare("
        UPDATE group_journey_invite
        SET status = 'declined',
            responded_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$inviteId]);

    st_success([
        'status' => 'declined'
    ], 'Invitation declined.');
}

$userStmt = $db->prepare("
    SELECT full_name, phone
    FROM users
    WHERE id = ?
    LIMIT 1
");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

$memberCheck = $db->prepare("
    SELECT id
    FROM group_members
    WHERE group_journey_id = ?
      AND user_id = ?
    LIMIT 1
");
$memberCheck->execute([
    $invite['group_journey_id'],
    $userId
]);

$member = $memberCheck->fetch(PDO::FETCH_ASSOC);

if ($member) {

    $updateMember = $db->prepare("
        UPDATE group_members
        SET status = 'confirmed'
        WHERE id = ?
    ");
    $updateMember->execute([$member['id']]);

} else {

    $insertMember = $db->prepare("
        INSERT INTO group_members
        (
            group_journey_id,
            user_id,
            invite_name,
            invite_phone,
            status,
            created_at
        )
        VALUES
        (?, ?, ?, ?, 'confirmed', NOW())
    ");

    $insertMember->execute([
        $invite['group_journey_id'],
        $userId,
        $user['full_name'],
        $user['phone']
    ]);
}

$updateInvite = $db->prepare("
    UPDATE group_journey_invite
    SET status = 'accepted',
        responded_at = NOW(),
        joined_at = NOW()
    WHERE id = ?
");
$updateInvite->execute([$inviteId]);

$notify = $db->prepare("
    INSERT INTO notifications
    (
        user_id,
        type,
        title,
        message,
        related_id,
        is_read,
        created_at
    )
    VALUES
    (?, 'group_member_joined', 'Member Joined', ?, ?, 0, NOW())
");

$notify->execute([
    $invite['inviter_id'],
    $user['full_name'] . ' accepted your group journey invitation.',
    $invite['group_journey_id']
]);

st_success([
    'status' => 'accepted',
    'group_journey_id' => (int)$invite['group_journey_id']
], 'You have successfully joined the group journey.');