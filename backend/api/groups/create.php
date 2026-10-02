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
$inviteIds = is_array($input['invite_contact_ids'] ?? null) ? $input['invite_contact_ids'] : [];

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

st_json_ok(['group_id' => $groupId]);
