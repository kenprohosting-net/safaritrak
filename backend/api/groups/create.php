<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';
require_once __DIR__ . '/../../includes/geo.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$title = trim($input['title'] ?? '');
$destinationLabel = trim($input['destination_label'] ?? '');
$destinationLat = isset($input['destination_lat']) && $input['destination_lat'] !== '' ? (float) $input['destination_lat'] : null;
$destinationLng = isset($input['destination_lng']) && $input['destination_lng'] !== '' ? (float) $input['destination_lng'] : null;
$departureAt = !empty($input['departure_at']) ? $input['departure_at'] : null;
if (isset($input['invite_contact_ids']) && !is_array($input['invite_contact_ids'])) {
    st_json_error('Selected trusted contacts must be sent as a list.', 422);
}
if (isset($input['persistent_group_ids']) && !is_array($input['persistent_group_ids'])) {
    st_json_error('Selected persistent groups must be sent as a list.', 422);
}
$inviteIds = is_array($input['invite_contact_ids'] ?? null) ? $input['invite_contact_ids'] : [];
$persistentGroupIds = is_array($input['persistent_group_ids'] ?? null) ? $input['persistent_group_ids'] : [];
foreach ([$inviteIds, $persistentGroupIds] as $selectedIds) {
    foreach ($selectedIds as $selectedId) {
        if (filter_var($selectedId, FILTER_VALIDATE_INT) === false || (int) $selectedId < 1) {
            st_json_error('Selected contacts or groups must be valid.', 422);
        }
    }
}
$inviteIds = array_values(array_unique(array_map('intval', $inviteIds)));
$persistentGroupIds = array_values(array_unique(array_map('intval', $persistentGroupIds)));
if (count($inviteIds) > 100 || count($persistentGroupIds) > 100) {
    st_json_error('You can invite up to 100 contacts and 100 groups at a time.', 422);
}

$errors = [];
if ($title === '') {
    $errors['title'] = 'Give this trip a name.';
}
if ($destinationLabel === '') {
    $errors['destination_label'] = 'Enter a destination.';
}

if (!empty($errors)) {
    st_json_error('Please fix the highlighted fields.', 422, ['errors' => $errors]);
}

$db = safaritrak_db();
$persistentGroupMemberIds = [];
if (!empty($persistentGroupIds)) {
    $placeholders = implode(',', array_fill(0, count($persistentGroupIds), '?'));
    $groupsStmt = $db->prepare(
        "SELECT pg.id
         FROM persistent_groups pg
         JOIN persistent_group_members owner_member
           ON owner_member.group_id = pg.id AND owner_member.user_id = ?
         WHERE pg.id IN ($placeholders) AND pg.status = 'active'"
    );
    $groupsStmt->execute(array_merge([$userId], $persistentGroupIds));
    $validGroupIds = array_map('intval', $groupsStmt->fetchAll(PDO::FETCH_COLUMN));
    if (count($validGroupIds) !== count($persistentGroupIds)) {
        st_json_error('One or more selected groups are unavailable or you are no longer a member.', 403);
    }
    $membersStmt = $db->prepare(
        "SELECT DISTINCT gm.user_id
         FROM persistent_group_members gm
         JOIN persistent_groups pg ON pg.id = gm.group_id AND pg.status = 'active'
         WHERE pg.id IN ($placeholders) AND gm.user_id <> ?
           AND (
             pg.organization_id IS NULL
             OR EXISTS (
               SELECT 1 FROM organization_travelers ot
               WHERE ot.organization_id = pg.organization_id AND ot.user_id = gm.user_id AND ot.status = 'active'
             )
             OR EXISTS (
               SELECT 1 FROM organization_admins oa
               WHERE oa.organization_id = pg.organization_id AND oa.user_id = gm.user_id
             )
           )"
    );
    $membersStmt->execute(array_merge($persistentGroupIds, [$userId]));
    $persistentGroupMemberIds = array_map('intval', $membersStmt->fetchAll(PDO::FETCH_COLUMN));
}

$insert = $db->prepare(
    'INSERT INTO group_journeys (organizer_id, title, destination_label, destination_lat, destination_lng, departure_at, status)
     VALUES (?, ?, ?, ?, ?, ?, "upcoming")'
);
$insert->execute([$userId, $title, $destinationLabel, $destinationLat, $destinationLng, $departureAt]);
$groupId = (int) $db->lastInsertId();

$organizerStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
$organizerStmt->execute([$userId]);
$organizerName = $organizerStmt->fetchColumn();

$memberInsert = $db->prepare(
    'INSERT INTO group_members (group_journey_id, user_id, status) VALUES (?, ?, "confirmed")'
);
$memberInsert->execute([$groupId, $userId]);

if (!empty($inviteIds)) {
    $placeholders = implode(',', array_fill(0, count($inviteIds), '?'));
    $contactsStmt = $db->prepare(
        "SELECT contact_user_id FROM trusted_contacts
         WHERE owner_id = ? AND status = 'confirmed' AND contact_user_id IS NOT NULL AND id IN ($placeholders)"
    );
    $contactsStmt->execute(array_merge([$userId], $inviteIds));
    $contacts = $contactsStmt->fetchAll();

    $inviteInsert = $db->prepare(
        'INSERT INTO group_members (group_journey_id, user_id, status) VALUES (?, ?, "invited")'
    );

    foreach ($contacts as $contact) {
        $inviteInsert->execute([$groupId, $contact['contact_user_id']]);

        st_notify(
            (int) $contact['contact_user_id'],
            'group_invite',
            $organizerName . ' invited you to a group journey',
            $title . ' to ' . $destinationLabel,
            null,
            $userId
        );
    }
}

if (!empty($persistentGroupIds)) {
    $existingMemberStmt = $db->prepare(
        'SELECT id FROM group_members WHERE group_journey_id = ? AND user_id = ? LIMIT 1'
    );
    $inviteInsert = $db->prepare(
        'INSERT INTO group_members (group_journey_id, user_id, status) VALUES (?, ?, "invited")'
    );
    foreach ($persistentGroupMemberIds as $memberId) {
        $existingMemberStmt->execute([$groupId, (int) $memberId]);
        if ($existingMemberStmt->fetchColumn()) {
            continue;
        }
        $inviteInsert->execute([$groupId, (int) $memberId]);
        st_notify(
            (int) $memberId,
            'group_invite',
            $organizerName . ' invited you to a group journey',
            $title . ' to ' . $destinationLabel,
            null,
            $userId
        );
    }
}

st_json_ok(['group_id' => $groupId]);
