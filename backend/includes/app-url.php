<?php

function st_app_url(): string {
    $base = getenv('SAFARITRAK_APP_URL');
    if ($base) {
        return rtrim($base, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}
