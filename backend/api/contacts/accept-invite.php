<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$token = st_input_string($input, 'token');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    st_json_error('That invitation link is invalid or expired.', 404);
}

$db = safaritrak_db();
$db->beginTransaction();
try {
    $inviteStmt = $db->prepare(
        'SELECT id, owner_id, invite_name, relationship, recipient_email
         FROM trusted_contact_invites
         WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP()
           AND (claimed_user_id IS NULL OR claimed_user_id = ?) AND accepted_at IS NULL
         FOR UPDATE'
    );
    $inviteStmt->execute([hash('sha256', $token), $userId]);
    $invite = $inviteStmt->fetch(PDO::FETCH_ASSOC);
    if (!$invite || (int) $invite['owner_id'] === $userId) {
        $db->rollBack();
        st_json_error('That invitation link is invalid, expired, or already used.', 404);
    }

    $userStmt = $db->prepare('SELECT email, phone FROM users WHERE id = ?');
    $userStmt->execute([$userId]);
    $recipient = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$recipient || ($invite['recipient_email'] !== null
        && strcasecmp((string) $invite['recipient_email'], (string) $recipient['email']) !== 0)) {
        $db->rollBack();
        st_json_error('Sign in with the email address this invitation was sent to.', 403);
    }

    $ownerId = (int) $invite['owner_id'];
    $upsertContact = static function (PDO $db, int $ownerId, int $contactId, string $name, string $phone, ?string $relationship): void {
        $find = $db->prepare('SELECT id, status FROM trusted_contacts WHERE owner_id = ? AND contact_user_id = ? LIMIT 1');
        $find->execute([$ownerId, $contactId]);
        $row = $find->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if ($row['status'] !== 'confirmed') {
                $db->prepare(
                    'UPDATE trusted_contacts SET status = "confirmed", invite_name = ?, invite_phone = ?, relationship = ?
                     WHERE id = ?'
                )->execute([$name, $phone, $relationship, $row['id']]);
            }
            return;
        }
        $db->prepare(
            'INSERT INTO trusted_contacts (owner_id, contact_user_id, invite_name, invite_phone, relationship, status)
             VALUES (?, ?, ?, ?, ?, "confirmed")'
        )->execute([$ownerId, $contactId, $name, $phone, $relationship]);
    };

    $ownerNameStmt = $db->prepare('SELECT full_name, phone FROM users WHERE id = ?');
    $ownerNameStmt->execute([$ownerId]);
    $owner = $ownerNameStmt->fetch(PDO::FETCH_ASSOC);
    if (!$owner) {
        $db->rollBack();
        st_json_error('The person who sent this invitation is no longer available.', 404);
    }
    $upsertContact($db, $ownerId, $userId, (string) $invite['invite_name'], (string) $recipient['phone'], $invite['relationship']);
    $upsertContact($db, $userId, $ownerId, (string) $owner['full_name'], (string) $owner['phone'], null);
    $db->prepare('UPDATE trusted_contact_invites SET claimed_user_id = ?, accepted_at = UTC_TIMESTAMP() WHERE id = ?')
        ->execute([$userId, $invite['id']]);
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

st_json_ok(['message' => 'Invitation accepted. You are now connected as trusted contacts.', 'redirect' => 'trusted-contacts.php']);
