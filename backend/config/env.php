<?php

$localConfigFile = __DIR__ . '/env.local.php';
if (!is_file($localConfigFile)) {
    return;
}

$localConfig = require $localConfigFile;
if (!is_array($localConfig)) {
    throw new UnexpectedValueException('backend/config/env.local.php must return an array.');
}

foreach ($localConfig as $name => $value) {
    if (!is_string($name) || !preg_match('/^SAFARITRAK_[A-Z0-9_]+$/', $name)) {
        throw new UnexpectedValueException('Local configuration keys must use the SAFARITRAK_ prefix.');
    }
    if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
        throw new UnexpectedValueException('Local configuration values must be scalar.');
    }

    if (getenv($name) === false) {
        putenv($name . '=' . (is_bool($value) ? ($value ? '1' : '0') : (string) $value));
    }
}
