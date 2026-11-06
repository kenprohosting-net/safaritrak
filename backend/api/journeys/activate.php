<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/notify.php';

st_start_session();
st_require_method('POST');
$userId = st_require_login();
$input = st_input();
$journeyId = filter_var($input['journey_id'] ?? null, FILTER_VALIDATE_INT);
if (!$journeyId || $journeyId < 1) {
    st_json_error('Choose a scheduled trip to start.', 422);
}

$db = safaritrak_db();
$db->beginTransaction();
try {
    $journeyStmt = $db->prepare(
        'SELECT id, start_label, end_label, status
         FROM journeys WHERE id = ? AND user_id = ? FOR UPDATE'
    );
    $journeyStmt->execute([$journeyId, $userId]);
    $journey = $journeyStmt->fetch(PDO::FETCH_ASSOC);
    if (!$journey || $journey['status'] !== 'planned') {
        $db->rollBack();
        st_json_error('That trip is no longer scheduled and cannot be started.', 409);
    }

    $activeStmt = $db->prepare('SELECT id FROM journeys WHERE user_id = ? AND status = "active" LIMIT 1');
    $activeStmt->execute([$userId]);
    if ($activeStmt->fetchColumn()) {
        $db->rollBack();
        st_json_error('End your current journey before starting another one.', 409);
    }

    $db->prepare(
        'UPDATE journeys SET status = "active", started_at = NOW()
         WHERE id = ? AND user_id = ? AND status = "planned"'
    )->execute([$journeyId, $userId]);

    $ownerStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
    $ownerStmt->execute([$userId]);
    $ownerName = (string) $ownerStmt->fetchColumn();
    $contactsStmt = $db->prepare(
        'SELECT DISTINCT tc.contact_user_id
         FROM journey_shares js
         JOIN trusted_contacts tc ON tc.id = js.trusted_contact_id
         WHERE js.journey_id = ? AND tc.status = "confirmed"
           AND tc.share_live_location = 1 AND tc.contact_user_id IS NOT NULL'
    );
    $contactsStmt->execute([$journeyId]);
    foreach ($contactsStmt->fetchAll(PDO::FETCH_COLUMN) as $contactUserId) {
        st_notify(
            (int) $contactUserId,
            'location_share',
            $ownerName . ' started a scheduled journey they shared with you',
            $journey['start_label'] . ' to ' . $journey['end_label'],
            (int) $journeyId,
            $userId
        );
    }
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

st_json_ok(['redirect' => 'my-journeys.php?selected=' . (int) $journeyId]);
