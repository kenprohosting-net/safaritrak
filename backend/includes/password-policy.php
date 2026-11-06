<?php

function st_password_policy_error(string $password): ?string {
    if (strlen($password) > 1024) {
        return 'Password must be no more than 1024 bytes.';
    }

    if (preg_match('//u', $password) !== 1) {
        return 'Password must contain valid text.';
    }

    $length = function_exists('mb_strlen')
        ? mb_strlen($password, 'UTF-8')
        : strlen($password);
    if ($length < 12) {
        return 'Password must be at least 12 characters.';
    }

    return null;
}
