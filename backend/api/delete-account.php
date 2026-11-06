<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$password = st_input_string($input, 'password');

if ($password === '') {
    st_json_error('Enter your password to confirm account deletion.', 422);
}

$db = safaritrak_db();
$db->beginTransaction();

try {
    $userStmt = $db->prepare('SELECT password_hash, avatar_path FROM users WHERE id = ? FOR UPDATE');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $db->rollBack();
        st_json_error('Your password is incorrect.', 422);
    }

    $platformAdminStmt = $db->prepare(
        'SELECT id FROM platform_admins WHERE user_id = ? LIMIT 1 FOR UPDATE'
    );
    $platformAdminStmt->execute([$userId]);
    $isPlatformAdmin = (bool) $platformAdminStmt->fetchColumn();

    $organizationAdminStmt = $db->prepare(
        'SELECT id FROM organization_admins WHERE user_id = ? LIMIT 1 FOR UPDATE'
    );
    $organizationAdminStmt->execute([$userId]);
    $hasOrganizationDuties = (bool) $organizationAdminStmt->fetchColumn();

    if ($isPlatformAdmin || $hasOrganizationDuties) {
        $db->rollBack();
        st_json_error(
            'Transfer or remove your platform or organization administrator duties before deleting your account.',
            409
        );
    }

    $removeContactReferences = $db->prepare(
        'UPDATE trusted_contacts
         SET contact_user_id = NULL,
             invite_name = \'Deleted user\',
             invite_phone = \'\',
             relationship = NULL,
             status = \'declined\',
             share_live_location = 0,
             journey_alerts = 0,
             sos_alerts = 0
         WHERE contact_user_id = ?'
    );
    $removeContactReferences->execute([$userId]);

    $removeGroupMemberData = $db->prepare(
        'UPDATE group_members
         SET user_id = NULL,
             invite_name = NULL,
             invite_phone = NULL,
             status = \'declined\',
             last_lat = NULL,
             last_lng = NULL
         WHERE user_id = ?'
    );
    $removeGroupMemberData->execute([$userId]);

    $deleteUser = $db->prepare('DELETE FROM users WHERE id = ?');
    $deleteUser->execute([$userId]);
    if ($deleteUser->rowCount() !== 1) {
        throw new RuntimeException('Account deletion did not remove exactly one user.');
    }

    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

$avatarPath = $user['avatar_path'] ?? null;
if (
    is_string($avatarPath) &&
    preg_match(
        '/\Auploads\/avatars\/user-' . preg_quote((string) $userId, '/') . '-[a-f0-9]{12}\.(?:jpg|png|webp)\z/',
        $avatarPath
    )
) {
    $avatarDirectory = realpath(__DIR__ . '/../../uploads/avatars');
    $avatarFile = $avatarDirectory === false
        ? false
        : realpath($avatarDirectory . DIRECTORY_SEPARATOR . basename($avatarPath));
    if (
        $avatarFile !== false &&
        dirname($avatarFile) === $avatarDirectory &&
        is_file($avatarFile) &&
        !unlink($avatarFile)
    ) {
        error_log('[SafariTrak] Could not remove avatar for deleted account ' . $userId . '.');
    }
}

st_logout($db);
st_json_ok(['redirect' => 'login.php']);
