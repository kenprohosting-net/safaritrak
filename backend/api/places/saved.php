<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST', 'DELETE'], true)) {
    st_json_error('Method not allowed.', 405);
}
if ($method !== 'GET') {
    st_require_method($method);
}
$userId = st_require_login();
$db = safaritrak_db();

if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT id, name, category, address, note, lat, lng, created_at
         FROM saved_locations WHERE user_id = ? ORDER BY name, id'
    );
    $stmt->execute([$userId]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($locations as &$location) {
        $location['id'] = (int) $location['id'];
        $location['lat'] = (float) $location['lat'];
        $location['lng'] = (float) $location['lng'];
    }
    unset($location);
    st_json_ok(['locations' => $locations]);
}

$input = st_input();
if ($method === 'DELETE') {
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        st_json_error('Choose a valid saved location.', 422);
    }
    $delete = $db->prepare('DELETE FROM saved_locations WHERE id = ? AND user_id = ?');
    $delete->execute([$id, $userId]);
    if ($delete->rowCount() !== 1) {
        st_json_error('That saved location could not be found.', 404);
    }
    st_json_ok();
}

$name = trim(st_input_string($input, 'name'));
$category = st_input_string($input, 'category') ?: 'other';
$address = trim(st_input_string($input, 'address'));
$note = trim(st_input_string($input, 'note'));
$lat = filter_var($input['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($input['lng'] ?? null, FILTER_VALIDATE_FLOAT);
$categories = ['home', 'work', 'hospital', 'police', 'fuel', 'hotel', 'restaurant', 'car_wash', 'pharmacy', 'school', 'other'];
if ($name === '' || mb_strlen($name) > 160 || !in_array($category, $categories, true)
    || mb_strlen($address) > 500 || mb_strlen($note) > 500 || $lat === false || $lng === false
    || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    st_json_error('Enter a name, category, and valid map location.', 422);
}

$insert = $db->prepare(
    'INSERT INTO saved_locations (user_id, name, category, address, note, lat, lng) VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$insert->execute([
    $userId,
    $name,
    $category,
    $address === '' ? null : $address,
    $note === '' ? null : $note,
    $lat,
    $lng,
]);
st_json_ok(['id' => (int) $db->lastInsertId()]);
