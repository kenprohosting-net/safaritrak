<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/session.php';

st_start_session();
st_require_method('GET');
st_require_login();
session_write_close();

$query = trim((string) ($_GET['q'] ?? ''));
if (strlen($query) > 120) {
    st_json_error('Search text is too long.', 422);
}
if (strlen($query) < 3) {
    st_json_ok(['suggestions' => []]);
}

$providers = [
    [
        'name' => 'Open-Meteo',
        'url' => 'https://geocoding-api.open-meteo.com/v1/search?' . http_build_query([
            'name' => $query,
            'count' => 7,
            'language' => 'en',
            'countryCode' => 'KE',
        ]),
    ],
    [
        'name' => 'Photon',
        'url' => 'https://photon.komoot.io/api/?' . http_build_query([
            'q' => $query,
            'limit' => 7,
            'lang' => 'en',
        ]),
    ],
];

$hasValidResponse = false;
foreach ($providers as $provider) {
    $attempts = 1;
    for ($attempt = 0; $attempt < $attempts; $attempt++) {
        $curl = curl_init($provider['url']);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER => ['User-Agent: SafariTrak/1.0 (location autocomplete)'],
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($response === false || $status !== 200) {
            error_log('SafariTrak ' . $provider['name'] . ' location suggestions failed: ' . $curlError . ' (HTTP ' . $status . ')');
            continue;
        }

        $decoded = json_decode($response, true);
        $records = $provider['name'] === 'Open-Meteo'
            ? ($decoded['results'] ?? null)
            : ($decoded['features'] ?? null);
        if (!is_array($records)) {
            error_log('SafariTrak ' . $provider['name'] . ' location suggestions returned invalid JSON.');
            continue;
        }

        $hasValidResponse = true;
        $suggestions = [];
        foreach ($records as $record) {
            if ($provider['name'] === 'Open-Meteo') {
                $latitude = $record['latitude'] ?? null;
                $longitude = $record['longitude'] ?? null;
                $parts = [
                    $record['name'] ?? null,
                    $record['admin2'] ?? null,
                    $record['admin1'] ?? null,
                    $record['country'] ?? null,
                ];
            } else {
                $coordinates = $record['geometry']['coordinates'] ?? null;
                $properties = $record['properties'] ?? [];
                $latitude = is_array($coordinates) ? ($coordinates[1] ?? null) : null;
                $longitude = is_array($coordinates) ? ($coordinates[0] ?? null) : null;
                $parts = [
                    $properties['name'] ?? null,
                    $properties['city'] ?? $properties['locality'] ?? null,
                    $properties['state'] ?? null,
                    $properties['country'] ?? null,
                ];
            }
            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                continue;
            }
            $parts = array_values(array_unique(array_filter(
                $parts,
                static fn($part): bool => is_string($part) && trim($part) !== ''
            )));
            if (!$parts) {
                continue;
            }
            $suggestions[] = [
                'label' => implode(', ', $parts),
                'lat' => (float) $latitude,
                'lng' => (float) $longitude,
            ];
        }

        if ($suggestions) {
            st_json_ok(['suggestions' => $suggestions]);
        }
    }
}

if ($hasValidResponse) {
    st_json_ok(['suggestions' => []]);
}

st_json_error('Location suggestions are temporarily unavailable. Please try again shortly.', 502);
