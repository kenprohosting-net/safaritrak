<?php

function st_parse_coordinate(mixed $value, float $minimum, float $maximum): ?float {
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        return null;
    }

    $coordinate = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($coordinate === false || !is_finite((float) $coordinate)
        || $coordinate < $minimum || $coordinate > $maximum
    ) {
        return null;
    }

    return (float) $coordinate;
}

function st_distance_km(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float {
    if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
        return null;
    }

    $earthRadiusKm = 6371.0;

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return round($earthRadiusKm * $c, 2);
}
