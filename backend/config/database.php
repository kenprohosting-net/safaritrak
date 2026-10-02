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

    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}