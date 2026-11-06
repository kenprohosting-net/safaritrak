<?php

require_once __DIR__ . '/../backend/includes/journey-locations.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec(
    'CREATE TABLE users (id INTEGER PRIMARY KEY, full_name TEXT, avatar_path TEXT);
     CREATE TABLE journeys (
         id INTEGER PRIMARY KEY,
         user_id INTEGER NOT NULL,
         status TEXT NOT NULL,
         started_at TEXT NOT NULL
     );
     CREATE TABLE trusted_contacts (
         id INTEGER PRIMARY KEY,
         owner_id INTEGER NOT NULL,
         contact_user_id INTEGER,
         status TEXT NOT NULL,
         share_live_location INTEGER NOT NULL
     );
     CREATE TABLE journey_shares (
         journey_id INTEGER NOT NULL,
         trusted_contact_id INTEGER NOT NULL
     );
     CREATE TABLE journey_positions (
         id INTEGER PRIMARY KEY,
         journey_id INTEGER NOT NULL,
         lat REAL NOT NULL,
         lng REAL NOT NULL,
         speed_kmh REAL,
         recorded_at TEXT NOT NULL
     )'
);
$db->exec(
    "INSERT INTO users (id, full_name) VALUES (1, 'Owner'), (2, 'Viewer'), (3, 'Unrelated');
     INSERT INTO journeys (id, user_id, status, started_at) VALUES
         (10, 1, 'active', '2025-01-01 10:00:00'),
         (11, 1, 'active', '2025-01-01 11:00:00'),
         (12, 1, 'active', '2025-01-01 12:00:00'),
         (13, 1, 'completed', '2025-01-01 13:00:00'),
         (14, 2, 'active', '2025-01-01 14:00:00'),
         (15, 1, 'active', '2025-01-01 15:00:00'),
         (16, 1, 'active', '2025-01-01 16:00:00');
     INSERT INTO trusted_contacts (id, owner_id, contact_user_id, status, share_live_location) VALUES
         (20, 1, 2, 'confirmed', 1),
         (21, 1, 2, 'confirmed', 0),
         (22, 1, 2, 'pending', 1),
         (23, 3, 2, 'confirmed', 1);
     INSERT INTO journey_shares (journey_id, trusted_contact_id) VALUES
         (10, 20), (11, 21), (12, 22), (13, 20), (14, 20), (15, 23);
     INSERT INTO journey_positions (id, journey_id, lat, lng, speed_kmh, recorded_at) VALUES
         (1, 10, -1.2, 36.8, 4, '2025-01-01 10:01:00'),
         (2, 10, -1.3, 36.9, 5, '2025-01-01 10:02:00'),
         (3, 11, -1.4, 37.0, 6, '2025-01-01 11:02:00'),
         (4, 12, -1.5, 37.1, 7, '2025-01-01 12:02:00'),
         (5, 13, -1.6, 37.2, 8, '2025-01-01 13:02:00'),
         (6, 14, -1.7, 37.3, 9, '2025-01-01 14:02:00'),
         (7, 15, -1.8, 37.4, 10, '2025-01-01 15:02:00'),
         (8, 16, -1.9, 37.5, 11, '2025-01-01 16:02:00')"
);

$visible = st_active_shared_journey_locations($db, 2);
if (count($visible) !== 1 || (int) $visible[0]['journey_id'] !== 10) {
    throw new RuntimeException('Only the owner-confirmed, active, explicitly shared journey should be visible.');
}
if ((float) $visible[0]['lat'] !== -1.3 || (float) $visible[0]['lng'] !== 36.9) {
    throw new RuntimeException('The API query should return only the latest recorded location.');
}

if (st_active_shared_journey_locations($db, 3) !== []) {
    throw new RuntimeException('Unrelated viewers must not receive shared journey locations.');
}

echo "PASS active shared journey privacy boundaries\n";
echo "PASS latest location selection\n";
echo "PASS unrelated viewer isolation\n";
echo "Completed 3 map privacy checks.\n";
