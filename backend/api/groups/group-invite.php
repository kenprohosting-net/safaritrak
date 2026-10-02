<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/session.php';

st_start_session();
st_require_method('POST');

$userId = st_require_login();
$input = st_input();

$groupJourneyId = isset($input['group_journey_id'])
    ? (int)$input['group_journey_id']
    : 0;

$inviteeId = isset($input['invitee_id'])
    ? (int)$input['invitee_id']
    : 0;

$message = isset($input['message'])
    ? trim($input['message'])
    : '';

if ($groupJourneyId <= 0) {
    st_json_error('Group journey ID is required.', 400);
}

if ($inviteeId <= 0) {
    st_json_error('Invitee ID is required.', 400);
}

if ($inviteeId === $userId) {
    st_json_error('You cannot invite yourself to a group journey.', 400);
}

$db = safaritrak_db();

$journeyStmt = $db->prepare("
    SELECT id
    FROM group_journeys
    WHERE id = ?
    LIMIT 1
");

$journeyStmt->execute([
    $groupJourneyId
]);

$groupJourney = $journeyStmt->fetch(PDO::FETCH_ASSOC);

if (!$groupJourney) {
    st_json_error('Group journey not found.', 404);
}

$memberStmt = $db->prepare("
    SELECT id, status
    FROM group_members
    WHERE group_journey_id = ?
      AND user_id = ?
    LIMIT 1
");

$memberStmt->execute([
    $groupJourneyId,
    $userId
]);

$senderMember = $memberStmt->fetch(PDO::FETCH_ASSOC);

if (!$senderMember) {
    st_json_error('You are not a member of this group journey.', 403);
}

if ($senderMember['status'] !== 'confirmed') {
    st_json_error('You are not a confirmed member of this group journey.', 403);
}

$userStmt = $db->prepare("
    SELECT id, full_name, phone
    FROM users
    WHERE id = ?
    LIMIT 1
");

$userStmt->execute([
    $inviteeId
]);

$invitee = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$invitee) {
    st_json_error('The invited user does not exist.', 404);
}

$existingMemberStmt = $db->prepare("
    SELECT id, status
    FROM group_members
    WHERE group_journey_id = ?
      AND user_id = ?
    LIMIT 1
");

$existingMemberStmt->execute([
    $groupJourneyId,
    $inviteeId
]);

$existingMember = $existingMemberStmt->fetch(PDO::FETCH_ASSOC);

if ($existingMember) {

    if ($existingMember['status'] === 'confirmed') {
        st_json_error('This user is already a member of the group journey.', 409);
    }
}

$existingInviteStmt = $db->prepare("
    SELECT id, status
    FROM group_journey_invite
    WHERE group_journey_id = ?
      AND invitee_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$existingInviteStmt->execute([
    $groupJourneyId,
    $inviteeId
]);

$existingInvite = $existingInviteStmt->fetch(PDO::FETCH_ASSOC);

if ($existingInvite && $existingInvite['status'] === 'pending') {
    st_json_error('An invitation has already been sent to this user.', 409);
}

try {

    $db->beginTransaction();

    if ($existingInvite) {

        $inviteStmt = $db->prepare("
            UPDATE group_journey_invite
            SET inviter_id = ?,
                status = 'pending',
                role = 'member',
                message = ?,
                invited_at = CURRENT_TIMESTAMP,
                responded_at = NULL,
                joined_at = NULL,
                expires_at = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $inviteStmt->execute([
            $userId,
            $message !== '' ? $message : null,
            $existingInvite['id']
        ]);

        $inviteId = (int)$existingInvite['id'];

    } else {

        $inviteStmt = $db->prepare("
            INSERT INTO group_journey_invite
            (
                group_journey_id,
                inviter_id,
                invitee_id,
                status,
                role,
                message,
                invited_at
            )
            VALUES
            (?, ?, ?, 'pending', 'member', ?, CURRENT_TIMESTAMP)
        ");

        $inviteStmt->execute([
            $groupJourneyId,
            $userId,
            $inviteeId,
            $message !== '' ? $message : null
        ]);

        $inviteId = (int)$db->lastInsertId();
    }

    $senderStmt = $db->prepare("
        SELECT full_name
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $senderStmt->execute([
        $userId
    ]);

    $sender = $senderStmt->fetch(PDO::FETCH_ASSOC);

    $senderName = $sender && !empty($sender['full_name'])
        ? $sender['full_name']
        : 'A SafariTrak user';

    $notificationBody = $senderName .
        ' invited you to join a group journey.';

    if ($message !== '') {
        $notificationBody .= ' ' . $message;
    }

    $notificationStmt = $db->prepare("
        INSERT INTO notifications
        (
            user_id,
            type,
            title,
            body,
            related_journey_id,
            related_user_id,
            is_read,
            created_at
        )
        VALUES
        (
            ?,
            'group_journey_invite',
            'Group Journey Invitation',
            ?,
            ?,
            ?,
            0,
            CURRENT_TIMESTAMP
        )
    ");

    $notificationStmt->execute([
        $inviteeId,
        $notificationBody,
        $groupJourneyId,
        $userId
    ]);

    $notificationId = (int)$db->lastInsertId();

    $db->commit();

    st_json_ok([
        'invite_id' => $inviteId,
        'notification_id' => $notificationId,
        'group_journey_id' => $groupJourneyId,
        'inviter_id' => $userId,
        'invitee_id' => $inviteeId,
        'status' => 'pending',
        'message' => 'Group journey invitation sent successfully.'
    ], 200);

} catch (PDOException $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    st_json_error(
        'Unable to send the group journey invitation.',
        500
    );

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    st_json_error(
        'An unexpected error occurred while sending the invitation.',
        500
    );

}