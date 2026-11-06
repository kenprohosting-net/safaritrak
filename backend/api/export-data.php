<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/session.php';

st_start_session();
st_require_method('GET');
$userId = st_require_login();
$db = safaritrak_db();

$userStmt = $db->prepare(
    'SELECT id, full_name, username, email, email_verified_at, phone, home_address, avatar_path,
            show_history_to_contacts, allow_group_journeys, discoverable_by_phone,
            route_deviation_alerts, arrival_notifications, auto_sos_on_silence,
            sos_alert_notifications, product_update_emails, created_at, updated_at
     FROM users WHERE id = ?'
);
$userStmt->execute([$userId]);
$user = $userStmt->fetch();
if (!$user) {
    st_json_error('Your account could not be found.', 404);
}

$queries = [
    'journeys' => [
        'SELECT * FROM journeys WHERE user_id = ? ORDER BY started_at',
        [$userId],
    ],
    'journey_positions' => [
        'SELECT jp.* FROM journey_positions jp
         INNER JOIN journeys j ON j.id = jp.journey_id
         WHERE j.user_id = ? ORDER BY jp.recorded_at',
        [$userId],
    ],
    'journey_locations' => [
        'SELECT jl.* FROM journey_locations jl
         INNER JOIN journeys j ON j.id = jl.journey_id
         WHERE j.user_id = ? ORDER BY jl.recorded_at',
        [$userId],
    ],
    'trusted_contacts' => [
        'SELECT id, owner_id, contact_user_id, invite_name, invite_phone, relationship,
                status, share_live_location, journey_alerts, sos_alerts, created_at, updated_at
         FROM trusted_contacts
         WHERE owner_id = ? OR contact_user_id = ?
         ORDER BY created_at',
        [$userId, $userId],
    ],
    'journey_shares' => [
        'SELECT js.* FROM journey_shares js
         INNER JOIN journeys j ON j.id = js.journey_id
         WHERE j.user_id = ? ORDER BY js.created_at',
        [$userId],
    ],
    'messages' => [
        'SELECT id, sender_id, receiver_id, body, read_at, created_at
         FROM messages WHERE sender_id = ? OR receiver_id = ? ORDER BY created_at',
        [$userId, $userId],
    ],
    'notifications' => [
        'SELECT id, type, title, body, related_journey_id, related_user_id, is_read, created_at
         FROM notifications WHERE user_id = ? ORDER BY created_at',
        [$userId],
    ],
    'sos_alerts' => [
        'SELECT id, journey_id, lat, lng, status, resolved_by, created_at, resolved_at
         FROM sos_alerts WHERE user_id = ? ORDER BY created_at',
        [$userId],
    ],
    'organized_group_journeys' => [
        'SELECT id, title, destination_label, destination_lat, destination_lng,
                distance_km, departure_at, status, created_at
         FROM group_journeys WHERE organizer_id = ? ORDER BY created_at',
        [$userId],
    ],
    'group_memberships' => [
        'SELECT gm.id, gm.group_journey_id, gm.status, gm.last_lat, gm.last_lng, gm.created_at,
                gj.title AS group_title, gj.destination_label
         FROM group_members gm
         INNER JOIN group_journeys gj ON gj.id = gm.group_journey_id
         WHERE gm.user_id = ? ORDER BY gm.created_at',
        [$userId],
    ],
    'organization_administrator_roles' => [
        'SELECT oa.organization_id, o.name AS organization_name, oa.role, oa.created_at
         FROM organization_admins oa
         INNER JOIN organizations o ON o.id = oa.organization_id
         WHERE oa.user_id = ? ORDER BY oa.created_at',
        [$userId],
    ],
    'organization_memberships' => [
        'SELECT ot.organization_id, o.name AS organization_name, ot.status, ot.joined_at
         FROM organization_travelers ot
         INNER JOIN organizations o ON o.id = ot.organization_id
         WHERE ot.user_id = ? ORDER BY ot.joined_at',
        [$userId],
    ],
    'platform_administrator_roles' => [
        'SELECT role, created_at FROM platform_admins WHERE user_id = ? ORDER BY created_at',
        [$userId],
    ],
    'privacy_consents' => [
        'SELECT consent_type, document_version, accepted_at
         FROM privacy_consents WHERE user_id = ? ORDER BY accepted_at, consent_type',
        [$userId],
    ],
];

$export = [
    'format' => 'safaritrak-account-export',
    'format_version' => 1,
    'exported_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
    'account' => $user,
];

$avatarPattern = '/\Auploads\/avatars\/user-' . preg_quote((string) $userId, '/') . '-[a-f0-9]{12}\.(?:jpg|png|webp)\z/';
$avatarDirectory = realpath(__DIR__ . '/../../uploads/avatars');
$avatarPath = $user['avatar_path'] ?? null;
$avatarFile = is_string($avatarPath) && preg_match($avatarPattern, $avatarPath) && $avatarDirectory !== false
    ? realpath($avatarDirectory . DIRECTORY_SEPARATOR . basename($avatarPath))
    : false;
$export['account']['avatar'] = null;
if (
    $avatarFile !== false &&
    dirname($avatarFile) === $avatarDirectory &&
    is_file($avatarFile) &&
    filesize($avatarFile) <= 4 * 1024 * 1024
) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($avatarFile);
    if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $contents = file_get_contents($avatarFile);
        if (!is_string($contents)) {
            throw new RuntimeException('The profile image could not be read for export.');
        }
        $export['account']['avatar'] = [
            'filename' => basename($avatarFile),
            'mime_type' => $mimeType,
            'encoding' => 'base64',
            'data' => base64_encode($contents),
        ];
    }
}
unset($export['account']['avatar_path']);

foreach ($queries as $name => [$sql, $parameters]) {
    $statement = $db->prepare($sql);
    $statement->execute($parameters);
    $export[$name] = $statement->fetchAll();
}

$json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="safaritrak-account-data-' . gmdate('Y-m-d') . '.json"');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
echo $json;
