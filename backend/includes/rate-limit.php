<?php

function st_rate_limit_key(string $bucket, string $value): string {
    $secret = getenv('SAFARITRAK_RATE_LIMIT_SECRET') ?: '';
    $production = strtolower(getenv('SAFARITRAK_ENV') ?: 'development') === 'production';
    if ($production && strlen($secret) < 32) {
        throw new RuntimeException('Authentication rate limiting requires a configured production secret.');
    }
    if ($secret === '') {
        $secret = 'safaritrak-local-rate-limit';
    }
    return hash_hmac('sha256', $bucket . "\0" . $value, $secret);
}

function st_local_development_rate_limit_maximum(int $maximumAttempts): int {
    if (getenv('SAFARITRAK_DEV_MODE') !== '1') {
        return $maximumAttempts;
    }

    $address = st_client_address();
    if (in_array($address, ['127.0.0.1', '::1'], true)) {
        return max($maximumAttempts, 100);
    }

    return $maximumAttempts;
}

function st_rate_limit_consume(
    PDO $db,
    string $bucket,
    string $value,
    int $maximumAttempts,
    int $windowSeconds
): int {
    if ($maximumAttempts < 1 || $windowSeconds < 1) {
        throw new InvalidArgumentException('Rate-limit attempts and window must be positive.');
    }

    $now = time();
    $nowUtc = gmdate('Y-m-d H:i:s', $now);
    $cutoffUtc = gmdate('Y-m-d H:i:s', $now - $windowSeconds);
    $keyHash = st_rate_limit_key($bucket, $value);

    $cleanup = $db->prepare('DELETE FROM auth_rate_limits WHERE window_started_at < ?');
    $cleanup->execute([gmdate('Y-m-d H:i:s', $now - 86400)]);

    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $stmt = $db->prepare(
            'INSERT INTO auth_rate_limits (bucket, key_hash, window_started_at, attempts)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
                attempts = IF(window_started_at <= ?, 1, attempts + 1),
                window_started_at = IF(window_started_at <= ?, VALUES(window_started_at), window_started_at)'
        );
    } elseif ($driver === 'sqlite') {
        $stmt = $db->prepare(
            'INSERT INTO auth_rate_limits (bucket, key_hash, window_started_at, attempts)
             VALUES (?, ?, ?, 1)
             ON CONFLICT (bucket, key_hash) DO UPDATE SET
                attempts = CASE WHEN window_started_at <= ? THEN 1 ELSE attempts + 1 END,
                window_started_at = CASE WHEN window_started_at <= ? THEN excluded.window_started_at ELSE window_started_at END'
        );
    } else {
        throw new RuntimeException('The configured database driver does not support authentication rate limits.');
    }
    $stmt->execute([$bucket, $keyHash, $nowUtc, $cutoffUtc, $cutoffUtc]);

    $read = $db->prepare(
        'SELECT attempts, window_started_at
         FROM auth_rate_limits
         WHERE bucket = ? AND key_hash = ?'
    );
    $read->execute([$bucket, $keyHash]);
    $state = $read->fetch(PDO::FETCH_ASSOC);
    if (!$state) {
        throw new RuntimeException('The authentication rate-limit state could not be read.');
    }

    if ((int) $state['attempts'] > $maximumAttempts) {
        $windowStart = strtotime($state['window_started_at'] . ' UTC');
        return max(1, $windowStart + $windowSeconds - $now);
    }

    return 0;
}

function st_rate_limit_clear(PDO $db, string $bucket, string $value): void {
    $stmt = $db->prepare('DELETE FROM auth_rate_limits WHERE bucket = ? AND key_hash = ?');
    $stmt->execute([$bucket, st_rate_limit_key($bucket, $value)]);
}

function st_client_address(): string {
    $address = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($address, FILTER_VALIDATE_IP) ? $address : 'unknown';
}
