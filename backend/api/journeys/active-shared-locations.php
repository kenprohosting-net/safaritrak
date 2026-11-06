<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/journey-locations.php';

st_start_session();
st_require_method('GET');
$userId = st_require_login();

$db = safaritrak_db();
$locations = [];

$formatTimestamp = static function (?string $timestamp): ?string {
    if ($timestamp === null) {
        return null;
    }

    return (new DateTimeImmutable($timestamp, new DateTimeZone(date_default_timezone_get())))
        ->format(DATE_ATOM);
};

foreach (st_active_shared_journey_locations($db, $userId) as $location) {
    $avatarUrl = null;
    $avatarPath = $location['owner_avatar_path'] ?? '';
    if (
        is_string($avatarPath)
        && preg_match('#^uploads/[A-Za-z0-9_./-]+$#', $avatarPath)
        && !preg_match('#(^|/)\.\.(/|$)#', $avatarPath)
    ) {
        $avatarFile = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $avatarPath);
        if (is_file($avatarFile)) {
            $avatarUrl = $avatarPath . '?v=' . (string) filemtime($avatarFile);
        }
    }

    $locations[] = [
        'journey_id' => (int) $location['journey_id'],
        'name' => $location['owner_name'],
        'avatar_url' => $avatarUrl,
        'lat' => $location['lat'] !== null && (float) $location['lat'] >= -90 && (float) $location['lat'] <= 90
            ? (float) $location['lat']
            : null,
        'lng' => $location['lng'] !== null && (float) $location['lng'] >= -180 && (float) $location['lng'] <= 180
            ? (float) $location['lng']
            : null,
        'recorded_at' => $formatTimestamp($location['recorded_at']),
    ];
}

st_json_ok(['locations' => $locations]);
