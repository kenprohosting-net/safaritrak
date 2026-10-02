<?php

function st_start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function st_require_login(): int {
    st_start_session();

    if (empty($_SESSION['user_id'])) {
        st_json_error('You need to log in first.', 401);
        exit;
    }

    return (int) $_SESSION['user_id'];
}

function st_login_user(array $user, bool $remember = false): void {
    st_start_session();

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'] ?? null;

    if ($remember) {
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => $params['path'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}