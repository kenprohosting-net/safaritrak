<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/rate-limit.php';

st_start_session();
st_require_method('GET');
$userId = st_require_login();
$query = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($query) < 2 || mb_strlen($query) > 60) {
    st_json_ok(['users' => []]);
}

$db = safaritrak_db();
$retryAfter = st_rate_limit_consume($db, 'contact-search-user', (string) $userId, 40, 60);
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    st_json_error('Please wait a moment before searching for more people.', 429);
}

$stmt = $db->prepare(
    'SELECT u.id, u.full_name, u.username
     FROM users u
     WHERE u.id <> ?
       AND u.email_verified_at IS NOT NULL
       AND (
         LEFT(LOWER(u.username), CHAR_LENGTH(?)) = LOWER(?)
         OR LEFT(LOWER(u.full_name), CHAR_LENGTH(?)) = LOWER(?)
       )
       AND NOT EXISTS (
         SELECT 1 FROM trusted_contacts tc
         WHERE (tc.owner_id = ? AND tc.contact_user_id = u.id)
            OR (tc.owner_id = u.id AND tc.contact_user_id = ? AND tc.status = "confirmed")
       )
     ORDER BY u.username ASC
     LIMIT 10'
);
$stmt->execute([$userId, $query, $query, $query, $query, $userId, $userId]);
$users = array_map(
    static fn(array $user): array => [
        'id' => (int) $user['id'],
        'full_name' => (string) $user['full_name'],
        'username' => (string) $user['username'],
    ],
    $stmt->fetchAll(PDO::FETCH_ASSOC)
);

st_json_ok(['users' => $users]);
