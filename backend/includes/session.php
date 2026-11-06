<?php

function st_start_session(?PDO $database = null): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    if (empty($_SESSION['user_id'])) {
        $GLOBALS['st_validated_session_id'] = session_id();
        return;
    }

    st_validate_session($database);
}

function st_session_database(?PDO $database = null): PDO {
    if ($database instanceof PDO) {
        return $database;
    }

    require_once __DIR__ . '/../config/database.php';
    return safaritrak_db();
}

function st_validate_session(?PDO $database = null, bool $force = false): void {
    if (session_status() === PHP_SESSION_NONE) {
        st_start_session($database);
        return;
    }

    $sessionId = session_id();
    if (!$force && ($GLOBALS['st_validated_session_id'] ?? null) === $sessionId) {
        return;
    }

    if (empty($_SESSION['user_id'])) {
        $GLOBALS['st_validated_session_id'] = $sessionId;
        return;
    }

    $db = st_session_database($database);
    $userId = (int) $_SESSION['user_id'];
    $validator = $_SESSION['_session_validator'] ?? null;

    if (!is_string($validator) || strlen($validator) !== 64) {
        st_upgrade_legacy_session($db, $userId);
        return;
    }

    $stmt = $db->prepare(
        'SELECT validator_hash, last_active_at, expires_at
         FROM sessions
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$sessionId, $userId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        st_destroy_current_session();
        return;
    }

    $now = time();
    $lastActive = strtotime((string) $record['last_active_at']);
    $expiresAt = strtotime((string) $record['expires_at']);
    $idleTimeout = !empty($_SESSION['_remember_me']) ? 7 * 24 * 60 * 60 : 30 * 60;
    $expectedValidatorHash = hash('sha256', $validator);

    if (
        !is_string($record['validator_hash']) ||
        !hash_equals($record['validator_hash'], $expectedValidatorHash) ||
        $lastActive === false ||
        $expiresAt === false ||
        $now >= $expiresAt ||
        $now - $lastActive >= $idleTimeout
    ) {
        st_destroy_current_session($db);
        return;
    }

    if ($now - $lastActive >= 300) {
        $update = $db->prepare(
            'UPDATE sessions
             SET last_active_at = ?
             WHERE id = ? AND user_id = ? AND validator_hash = ? AND expires_at > ?'
        );
        $currentTime = date('Y-m-d H:i:s', $now);
        $update->execute([$currentTime, $sessionId, $userId, $expectedValidatorHash, $currentTime]);
    }

    $GLOBALS['st_validated_session_id'] = $sessionId;
}

function st_upgrade_legacy_session(PDO $database, int $userId): void {
    $userStmt = $database->prepare(
        'SELECT id, is_suspended, email_verified_at FROM users WHERE id = ?'
    );
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || (int) $user['is_suspended'] === 1 || empty($user['email_verified_at'])) {
        st_destroy_current_session($database);
        return;
    }

    $oldSessionId = session_id();
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Could not rotate a legacy SafariTrak session.');
    }

    unset($_SESSION['_csrf_token']);
    $_SESSION['_session_validator'] = bin2hex(random_bytes(32));
    $_SESSION['_remember_me'] = false;
    st_store_session($database, $oldSessionId, $userId, false);
    $GLOBALS['st_validated_session_id'] = session_id();
}

function st_store_session(PDO $database, string $oldSessionId, int $userId, bool $remember): void {
    $sessionId = session_id();
    $validator = $_SESSION['_session_validator'] ?? null;
    if (!is_string($validator) || $sessionId === '') {
        throw new RuntimeException('Could not establish the SafariTrak session.');
    }

    $now = time();
    $currentTime = date('Y-m-d H:i:s', $now);
    $lifetime = $remember ? 30 * 24 * 60 * 60 : 12 * 60 * 60;
    $expiresAt = date('Y-m-d H:i:s', $now + $lifetime);

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    if (!is_string($ipAddress) || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
        $ipAddress = null;
    }
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $userAgent = is_string($userAgent) ? substr($userAgent, 0, 255) : null;

    $database->beginTransaction();
    try {
        if ($oldSessionId !== '' && $oldSessionId !== $sessionId) {
            $delete = $database->prepare('DELETE FROM sessions WHERE id = ?');
            $delete->execute([$oldSessionId]);
        }

        $insert = $database->prepare(
            'INSERT INTO sessions
                (id, user_id, validator_hash, user_agent, ip_address, last_active_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $sessionId,
            $userId,
            hash('sha256', $validator),
            $userAgent,
            $ipAddress,
            $currentTime,
            $expiresAt,
        ]);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        st_destroy_current_session();
        throw $exception;
    }

    $_SESSION['_remember_me'] = $remember;
    $GLOBALS['st_validated_session_id'] = $sessionId;
}

function st_destroy_current_session(?PDO $database = null): void {
    $sessionId = session_id();
    if ($database instanceof PDO && $sessionId !== '') {
        $delete = $database->prepare('DELETE FROM sessions WHERE id = ?');
        $delete->execute([$sessionId]);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    $GLOBALS['st_validated_session_id'] = null;
}

function st_logout(?PDO $database = null): void {
    st_start_session($database);
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    if (!empty($_SESSION['user_id'])) {
        $db = st_session_database($database);
        $delete = $db->prepare('DELETE FROM sessions WHERE id = ?');
        $delete->execute([session_id()]);
    }
    st_destroy_current_session();
}

function st_csrf_token(): string {
    st_start_session();

    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function st_request_is_same_origin(): bool {
    $requestScheme = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off'
        ? 'https'
        : 'http';
    $requestHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($requestHost === '') {
        return false;
    }

    $expected = parse_url($requestScheme . '://' . $requestHost);
    if (!is_array($expected) || empty($expected['host'])) {
        return false;
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '' && !empty($_SERVER['HTTP_REFERER'])) {
        $referer = parse_url((string) $_SERVER['HTTP_REFERER']);
        if (!is_array($referer) || empty($referer['scheme']) || empty($referer['host'])) {
            return false;
        }
        $origin = $referer['scheme'] . '://' . $referer['host']
            . (isset($referer['port']) ? ':' . $referer['port'] : '');
    }

    if ($origin === '') {
        return true;
    }

    $provided = parse_url($origin);
    if (!is_array($provided) || empty($provided['scheme']) || empty($provided['host'])) {
        return false;
    }

    $expectedPort = $expected['port'] ?? ($requestScheme === 'https' ? 443 : 80);
    $providedPort = $provided['port'] ?? (strtolower($provided['scheme']) === 'https' ? 443 : 80);

    return strtolower($provided['scheme']) === $requestScheme
        && strtolower($provided['host']) === strtolower($expected['host'])
        && $providedPort === $expectedPort
        && !isset($provided['user'])
        && !isset($provided['pass'])
        && !isset($provided['path'])
        && !isset($provided['query'])
        && !isset($provided['fragment']);
}

function st_validate_csrf(?string $token = null): bool {
    st_start_session();

    if (!st_request_is_same_origin()) {
        return false;
    }

    if ($token === null) {
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $formToken = $_POST['_csrf'] ?? '';
        $token = is_string($headerToken) && $headerToken !== ''
            ? $headerToken
            : (is_string($formToken) ? $formToken : null);
    }

    $expected = $_SESSION['_csrf_token'] ?? null;
    return is_string($token)
        && is_string($expected)
        && $token !== ''
        && hash_equals($expected, $token);
}

function st_require_csrf(): void {
    if (!st_validate_csrf()) {
        st_json_error('Your security token is missing or expired. Refresh the page and try again.', 403);
    }
}

function st_require_login(): int {
    st_start_session();

    if (empty($_SESSION['user_id'])) {
        st_json_error('You need to log in first.', 401);
        exit;
    }

    $userId = (int) $_SESSION['user_id'];
    session_write_close();

    return $userId;
}

function st_login_user(array $user, bool $remember = false, ?PDO $database = null): void {
    $db = st_session_database($database);
    st_start_session($db);

    $oldSessionId = session_id();
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Could not rotate the SafariTrak session after sign-in.');
    }
    unset($_SESSION['_csrf_token']);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'] ?? null;
    $_SESSION['_session_validator'] = bin2hex(random_bytes(32));
    $_SESSION['_remember_me'] = $remember;
    st_store_session($db, $oldSessionId, (int) $user['id'], $remember);

    if ($remember) {
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}