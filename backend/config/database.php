<?php

$envFile = __DIR__ . '/env.php';
if (file_exists($envFile)) {
    require_once $envFile;
}

function safaritrak_db(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = getenv('SAFARITRAK_DB_HOST') ?: '127.0.0.1';
    $name = getenv('SAFARITRAK_DB_NAME') ?: 'safaritrak';
    $user = getenv('SAFARITRAK_DB_USER') ?: 'root';
    $pass = getenv('SAFARITRAK_DB_PASS') ?: '';
    $environment = strtolower(getenv('SAFARITRAK_ENV') ?: 'development');

    if (!in_array($environment, ['development', 'testing', 'production'], true)) {
        throw new RuntimeException('SafariTrak environment configuration is invalid.');
    }
    if ($environment === 'production') {
        ini_set('display_errors', '0');
    }

    if (
        preg_match('/[\r\n\0]/', $host) ||
        !preg_match('/^[a-zA-Z0-9_]+$/', $name) ||
        $user === '' ||
        preg_match('/[\r\n\0]/', $user)
    ) {
        throw new RuntimeException('SafariTrak database configuration is invalid. Check the server configuration.');
    }
    if ($environment === 'production' && (strtolower($user) === 'root' || $pass === '')) {
        throw new RuntimeException('SafariTrak production database credentials are not configured safely.');
    }

    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    } catch (PDOException $exception) {
        $requestId = bin2hex(random_bytes(8));
        error_log('[SafariTrak] database connection failed (' . $requestId . '): ' . $exception->getMessage());
        throw new RuntimeException(
            'SafariTrak could not connect to its database. Reference: ' . $requestId
        );
    }

    return $pdo;
}