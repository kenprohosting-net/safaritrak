<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/geo.php';

st_start_session();
st_require_method('GET');

$userId = st_require_login();

$journeyId = isset($_GET['journey_id'])
    ? (int) $_GET['journey_id']
    : 0;

if ($journeyId <= 0) {
    st_json_error('Invalid journey.', 400);
}

$db = safaritrak_db();

$journeyStmt = $db->prepare(
    'SELECT
        j.id,
        j.user_id,
        j.start_label,
        j.start_lat,
        j.start_lng,
        j.end_label,
        j.end_lat,
        j.end_lng,
        j.distance_km,
        j.transport_mode,
        j.status,
        j.started_at,
        j.ended_at,
        u.full_name AS owner_name
     FROM journeys j
     JOIN users u
       ON u.id = j.user_id
     JOIN journey_shares js
       ON js.journey_id = j.id
     JOIN trusted_contacts tc
       ON tc.id = js.trusted_contact_id
     WHERE j.id = ?
       AND tc.contact_user_id = ?
       AND tc.status = "confirmed"
       AND tc.share_live_location = 1
     LIMIT 1'
);

$journeyStmt->execute([
    $journeyId,
    $userId
]);

$journey = $journeyStmt->fetch();

if (!$journey) {
    st_json_error(
        'You are not authorized to view this journey or live location sharing is disabled.',
        403
    );
}

$positionStmt = $db->prepare(
    'SELECT
        id,
        lat,
        lng,
        speed_kmh,
        created_at
     FROM journey_positions
     WHERE journey_id = ?
     ORDER BY id DESC
     LIMIT 1'
);

$positionStmt->execute([
    $journeyId
]);

$position = $positionStmt->fetch();

$location = null;

$remainingKm = null;

$effectiveSpeed = null;

$etaMinutes = null;

$expectedArrival = null;

if ($position) {

    $currentLat = (float) $position['lat'];

    $currentLng = (float) $position['lng'];

    $speedKmh = $position['speed_kmh'] !== null
        ? (float) $position['speed_kmh']
        : null;

    $endLat = $journey['end_lat'] !== null
        ? (float) $journey['end_lat']
        : null;

    $endLng = $journey['end_lng'] !== null
        ? (float) $journey['end_lng']
        : null;

    if (
        $endLat !== null &&
        $endLng !== null
    ) {

        $remainingKm = st_distance_km(
            $currentLat,
            $currentLng,
            $endLat,
            $endLng
        );

        if ($remainingKm !== null) {

            $remainingKm = max(
                0,
                (float) $remainingKm
            );

        }

    }

    $transportMode = strtolower(
        trim(
            (string) (
                $journey['transport_mode'] ?? ''
            )
        )
    );

    $defaultSpeeds = [
        'walking' => 5,
        'walk' => 5,
        'bicycle' => 15,
        'bike' => 15,
        'motorcycle' => 35,
        'motorbike' => 35,
        'car' => 50,
        'matatu' => 35,
        'bus' => 30,
        'taxi' => 45,
        'unknown' => 30
    ];

    $defaultSpeed = $defaultSpeeds['unknown'];

    if (
        isset(
            $defaultSpeeds[$transportMode]
        )
    ) {

        $defaultSpeed =
            $defaultSpeeds[$transportMode];

    }

    if (
        $speedKmh !== null &&
        $speedKmh > 0
    ) {

        $effectiveSpeed =
            $speedKmh;

    } else {

        $effectiveSpeed =
            $defaultSpeed;

    }

    if (
        $remainingKm !== null &&
        $effectiveSpeed > 0
    ) {

        $etaMinutes =
            ($remainingKm / $effectiveSpeed) * 60;

        $etaMinutes =
            max(
                0,
                (int) ceil(
                    $etaMinutes
                )
            );

        $expectedArrival =
            date(
                'Y-m-d H:i:s',
                time() +
                ($etaMinutes * 60)
            );

    }

    $location = [

        'lat' =>
            $currentLat,

        'lng' =>
            $currentLng,

        'speed_kmh' =>
            $speedKmh,

        'effective_speed_kmh' =>
            $effectiveSpeed !== null
                ? round(
                    $effectiveSpeed,
                    2
                )
                : null,

        'distance_remaining_km' =>
            $remainingKm !== null
                ? round(
                    $remainingKm,
                    2
                )
                : null,

        'updated_at' =>
            $position['created_at']

    ];

}

$coveredStmt = $db->prepare(
    'SELECT
        lat,
        lng
     FROM journey_positions
     WHERE journey_id = ?
     ORDER BY id ASC'
);

$coveredStmt->execute([
    $journeyId
]);

$points = $coveredStmt->fetchAll();

$coveredKm = 0.0;

for (
    $i = 1;
    $i < count($points);
    $i++
) {

    $leg = st_distance_km(
        (float) $points[$i - 1]['lat'],
        (float) $points[$i - 1]['lng'],
        (float) $points[$i]['lat'],
        (float) $points[$i]['lng']
    );

    $coveredKm += $leg ?? 0;

}

$totalKm = $journey['distance_km'] !== null
    ? (float) $journey['distance_km']
    : null;

$transportMode = $journey['transport_mode'] !== null
    ? strtolower(
        trim(
            (string) $journey['transport_mode']
        )
    )
    : 'unknown';

st_json_ok([

    'journey' => [

        'id' =>
            (int) $journey['id'],

        'owner_name' =>
            $journey['owner_name'],

        'start_label' =>
            $journey['start_label'],

        'start_lat' =>
            $journey['start_lat'] !== null
                ? (float) $journey['start_lat']
                : null,

        'start_lng' =>
            $journey['start_lng'] !== null
                ? (float) $journey['start_lng']
                : null,

        'end_label' =>
            $journey['end_label'],

        'end_lat' =>
            $journey['end_lat'] !== null
                ? (float) $journey['end_lat']
                : null,

        'end_lng' =>
            $journey['end_lng'] !== null
                ? (float) $journey['end_lng']
                : null,

        'distance_km' =>
            $totalKm,

        'transport_mode' =>
            $transportMode,

        'status' =>
            $journey['status'],

        'started_at' =>
            $journey['started_at'],

        'ended_at' =>
            $journey['ended_at']

    ],

    'location' =>
        $location,

    'covered_km' =>
        round(
            $coveredKm,
            2
        ),

    'total_km' =>
        $totalKm,

    'distance_remaining_km' =>
        $remainingKm !== null
            ? round(
                $remainingKm,
                2
            )
            : null,

    'speed_kmh' =>
        $position &&
        $position['speed_kmh'] !== null
            ? (float) $position['speed_kmh']
            : null,

    'effective_speed_kmh' =>
        $effectiveSpeed !== null
            ? round(
                $effectiveSpeed,
                2
            )
            : null,

    'eta_minutes' =>
        $etaMinutes,

    'expected_arrival' =>
        $expectedArrival,

    'destination' => [

        'lat' =>
            $journey['end_lat'] !== null
                ? (float) $journey['end_lat']
                : null,

        'lng' =>
            $journey['end_lng'] !== null
                ? (float) $journey['end_lng']
                : null

    ]

]);