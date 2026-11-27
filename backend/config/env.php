<?php

$localConfigFile = __DIR__ . '/env.local.php';
if (!is_file($localConfigFile)) {
    return;
}

$localConfig = require $localConfigFile;
if (!is_array($localConfig)) {
    throw new UnexpectedValueException('backend/config/env.local.php must return an array.');
}

$allowedGoogleKeys = [
    'GOOGLE_CLIENT_ID',
    'GOOGLE_CLIENT_SECRET',
    'GOOGLE_REDIRECT_URI',
    'GOOGLE_REFRESH_TOKEN',
    'GMAIL_SENDER_EMAIL',
    'GOOGLE_MAPS_API_KEY',
];
foreach ($localConfig as $name => $value) {
    if (!is_string($name)
        || (!preg_match('/^SAFARITRAK_[A-Z0-9_]+$/', $name) && !in_array($name, $allowedGoogleKeys, true))) {
        throw new UnexpectedValueException('Local configuration keys must use the SAFARITRAK_ prefix or an approved Google Maps/mail key.');
    }
    if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
        throw new UnexpectedValueException('Local configuration values must be scalar.');
    }

    $environmentName = $name === 'GOOGLE_MAPS_API_KEY'
        ? 'SAFARITRAK_GOOGLE_MAPS_API_KEY'
        : $name;
    if (getenv($environmentName) === false) {
        putenv($environmentName . '=' . (is_bool($value) ? ($value ? '1' : '0') : (string) $value));
    }
}
