<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';
require_once __DIR__ . '/../../includes/geo.php';
require_once __DIR__ . '/../../includes/geo.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();

$input = st_input();
$title = trim($input['title'] ?? '');
$destinationLabel = trim($input['destination_label'] ?? '');
$startLabel = trim($input['start_label'] ?? '');
$startLat = st_parse_coordinate($input['start_lat'] ?? null, -90, 90);
$startLng = st_parse_coordinate($input['start_lng'] ?? null, -180, 180);
$destinationLat = st_parse_coordinate($input['destination_lat'] ?? null, -90, 90);
$destinationLng = st_parse_coordinate($input['destination_lng'] ?? null, -180, 180);
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
if ($startLabel === '' || $startLat === null || $startLng === null) {
    $errors['start_location'] = 'Choose a starting point from the location suggestions.';
}
if ($destinationLat === null || $destinationLng === null) {
    $errors['destination_location'] = 'Choose a destination from the location suggestions.';
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
    'INSERT INTO group_journeys
        (organizer_id, title, destination_label, destination_lat, destination_lng,
         meeting_point_label, meeting_point_lat, meeting_point_lng, departure_at, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "upcoming")'
);
$insert->execute([
    $userId,
    $title,
    $destinationLabel,
    $destinationLat,
    $destinationLng,
    $startLabel,
    $startLat,
    $startLng,
    $departureAt,
]);
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
