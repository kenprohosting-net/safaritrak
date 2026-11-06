<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';
require_once __DIR__ . '/../../includes/rate-limit.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$contactUserId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
if (!$contactUserId || $contactUserId === $userId) {
    st_json_error('Choose another SafariTrak user to add.', 422);
}

$db = safaritrak_db();
$retryAfter = st_rate_limit_consume($db, 'contact-request-user', (string) $userId, 20, 3600);
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    st_json_error('Too many contact requests. Please try again later.', 429);
}

$db->beginTransaction();
try {
    $targetStmt = $db->prepare(
        'SELECT id, full_name, phone, email_verified_at FROM users WHERE id = ? FOR UPDATE'
    );
    $targetStmt->execute([$contactUserId]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
    if (!$target || empty($target['email_verified_at'])) {
        $db->rollBack();
        st_json_error('That account is not available for a contact request.', 404);
    }

    $existingStmt = $db->prepare(
        'SELECT status FROM trusted_contacts WHERE owner_id = ? AND contact_user_id = ? LIMIT 1'
    );
    $existingStmt->execute([$userId, $contactUserId]);
    $existingStatus = $existingStmt->fetchColumn();
    if ($existingStatus === 'confirmed') {
        $db->rollBack();
        st_json_error('You are already connected to this person.', 409);
    }
    if ($existingStatus === 'pending') {
        $db->rollBack();
        st_json_error('A contact request is already waiting for their response.', 409);
    }

    $incomingStmt = $db->prepare(
        'SELECT id FROM trusted_contacts
         WHERE owner_id = ? AND contact_user_id = ? AND status = "pending" LIMIT 1'
    );
    $incomingStmt->execute([$contactUserId, $userId]);
    if ($incomingStmt->fetchColumn()) {
        $db->rollBack();
        st_json_error('This person has already sent you a request. Accept it from your incoming requests.', 409);
    }

    $db->prepare(
        'INSERT INTO trusted_contacts (owner_id, contact_user_id, invite_name, invite_phone, status)
         VALUES (?, ?, ?, ?, "pending")'
    )->execute([$userId, $contactUserId, $target['full_name'], $target['phone']]);

    $senderStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
    $senderStmt->execute([$userId]);
    $senderName = (string) $senderStmt->fetchColumn();
    st_notify(
        (int) $contactUserId,
        'contact_request',
        $senderName . ' wants to connect with you',
        'Accept the request to become trusted contacts and share journeys.',
        null,
        $userId
    );
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

st_json_ok(['message' => 'Contact request sent.', 'status' => 'pending']);
