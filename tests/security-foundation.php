<?php

require_once __DIR__ . '/../backend/includes/session.php';
require_once __DIR__ . '/../backend/includes/rate-limit.php';
require_once __DIR__ . '/../backend/includes/password-policy.php';
require_once __DIR__ . '/../backend/includes/migration-checksum.php';

$checks = 0;
$passed = [];
$expect = static function (bool $condition, string $message) use (&$checks, &$passed): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
    $passed[] = $message;
};

$expect(hash_equals(st_rate_limit_key('login', 'alice'), st_rate_limit_key('login', 'alice')), 'rate-limit keys are stable');
$expect(st_rate_limit_key('login', 'alice') !== st_rate_limit_key('login', 'bob'), 'rate-limit keys are scoped to their input');
$expect(st_rate_limit_key('login', 'alice') !== st_rate_limit_key('register', 'alice'), 'rate-limit buckets have separate keys');
$previousDevMode = getenv('SAFARITRAK_DEV_MODE');
$previousEnvironment = getenv('SAFARITRAK_ENV');
$previousRemoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;
putenv('SAFARITRAK_DEV_MODE=0');
putenv('SAFARITRAK_ENV=development');
$_SERVER['REMOTE_ADDR'] = '::1';
$expect(st_local_development_rate_limit_maximum(5) === 5, 'development rate-limit override is disabled when development mode is off');
putenv('SAFARITRAK_DEV_MODE=1');
$expect(st_is_local_development_request(), 'loopback requests enable local development conveniences outside production');
$expect(st_local_development_rate_limit_maximum(5) === 100, 'loopback development requests get a practical signup rate limit');
putenv('SAFARITRAK_ENV=production');
$expect(!st_is_local_development_request(), 'production never exposes local verification or reset links');
$expect(st_local_development_rate_limit_maximum(5) === 5, 'production never receives development rate-limit overrides');
putenv('SAFARITRAK_ENV=development');
$_SERVER['REMOTE_ADDR'] = '198.51.100.20';
$expect(!st_is_local_development_request(), 'non-loopback clients never receive local verification or reset links');
$expect(st_local_development_rate_limit_maximum(5) === 5, 'development rate-limit override does not apply to non-loopback clients');
if ($previousDevMode === false) {
    putenv('SAFARITRAK_DEV_MODE');
} else {
    putenv('SAFARITRAK_DEV_MODE=' . $previousDevMode);
}
if ($previousEnvironment === false) {
    putenv('SAFARITRAK_ENV');
} else {
    putenv('SAFARITRAK_ENV=' . $previousEnvironment);
}
if ($previousRemoteAddress === null) {
    unset($_SERVER['REMOTE_ADDR']);
} else {
    $_SERVER['REMOTE_ADDR'] = $previousRemoteAddress;
}
$expect(st_password_policy_error('12345678901') !== null, 'password policy rejects passwords shorter than twelve characters');
$expect(st_password_policy_error('correct horse battery') === null, 'password policy accepts a long passphrase');
$expect(st_password_policy_error(str_repeat('x', 1025)) !== null, 'password policy bounds input size');
$expect(st_password_policy_error("\xFF") !== null, 'password policy rejects malformed text');

$resetSource = file_get_contents(__DIR__ . '/../backend/api/reset-password.php');
$expect(is_string($resetSource) && str_contains($resetSource, 'FOR UPDATE'), 'password reset locks its token against concurrent reuse');
$expect(is_string($resetSource) && str_contains($resetSource, 'used_at IS NULL AND expires_at > UTC_TIMESTAMP()'), 'password reset consumes only an unused unexpired token using a consistent UTC clock');

$deleteSource = file_get_contents(__DIR__ . '/../backend/api/delete-account.php');
$expect(is_string($deleteSource) && str_contains($deleteSource, "st_require_method('POST')"), 'account deletion requires a CSRF-protected POST');
$expect(is_string($deleteSource) && str_contains($deleteSource, 'st_require_login()'), 'account deletion requires an authenticated session');
$expect(is_string($deleteSource) && str_contains($deleteSource, 'password_verify($password'), 'account deletion verifies the current password');
$expect(is_string($deleteSource) && str_contains($deleteSource, 'platform_admins'), 'account deletion protects platform administrators');
$expect(is_string($deleteSource) && str_contains($deleteSource, 'organization_admins'), 'account deletion protects organization administrators and owners');
$expect(is_string($deleteSource) && str_contains($deleteSource, 'last_lat = NULL'), 'account deletion scrubs retained group location data');
$settingsSource = file_get_contents(__DIR__ . '/../settings.js');
$expect(is_string($settingsSource) && str_contains($settingsSource, 'backend/api/delete-account.php'), 'settings uses the account deletion API route');
$consentSource = file_get_contents(__DIR__ . '/../backend/api/consent.php');
$expect(is_string($consentSource) && str_contains($consentSource, "st_require_method('POST')"), 'consent changes require a CSRF-protected POST');
$expect(is_string($consentSource) && str_contains($consentSource, 'st_record_consent'), 'consent changes are recorded through the shared versioned ledger');
$exportSource = file_get_contents(__DIR__ . '/../backend/api/export-data.php');
$expect(is_string($exportSource) && str_contains($exportSource, 'st_require_login()'), 'account data export requires an authenticated account');
$expect(is_string($exportSource) && !str_contains($exportSource, 'password_hash'), 'account data export excludes password hashes');
$platformClaimSource = file_get_contents(__DIR__ . '/../backend/api/platform/claim.php');
$expect(
    is_string($platformClaimSource)
        && str_contains($platformClaimSource, "st_require_method('POST')")
        && str_contains($platformClaimSource, 'SAFARITRAK_PLATFORM_ADMIN_BOOTSTRAP_TOKEN')
        && str_contains($platformClaimSource, 'hash_equals($bootstrapToken, $providedToken)')
        && str_contains($platformClaimSource, 'GET_LOCK'),
    'first platform-owner claim requires a CSRF-protected one-time secret and a serialized ownership check'
);

foreach ([
    'register.php',
    'resend-verification.php',
    'forgot-password.php',
    'org/signup.php',
] as $developmentLinkEndpoint) {
    $endpointSource = file_get_contents(__DIR__ . '/../backend/api/' . $developmentLinkEndpoint);
    $expect(
        is_string($endpointSource)
            && str_contains($endpointSource, 'st_is_local_development_request()')
            && !str_contains($endpointSource, "getenv('SAFARITRAK_DEV_MODE') === '1'"),
        basename($developmentLinkEndpoint) . ' gates local token links through the shared environment/loopback check'
    );
}

$migrationFiles = glob(__DIR__ . '/../backend/sql/migrations/*') ?: [];
$expect(count($migrationFiles) >= 3, 'versioned migrations are present');
foreach ($migrationFiles as $migrationFile) {
    $expect((bool) preg_match('/^\\d{4}_[a-z0-9_]+\\.(sql|php)$/', basename($migrationFile)), 'migration filename is versioned');
    $migrationSource = file_get_contents($migrationFile);
    $expect(!preg_match('/\\bDROP\\s+(TABLE|COLUMN|DATABASE)\\b/i', $migrationSource), 'migration has no destructive drop');
}
$lfMigration = tempnam(sys_get_temp_dir(), 'safaritrak-migration-lf-');
$crlfMigration = tempnam(sys_get_temp_dir(), 'safaritrak-migration-crlf-');
if ($lfMigration === false || $crlfMigration === false) {
    throw new RuntimeException('Unable to create temporary files for migration checksum tests.');
}
try {
    $migrationContents = "CREATE TABLE example (\n    id INT NOT NULL\n);\n";
    file_put_contents($lfMigration, $migrationContents);
    file_put_contents($crlfMigration, str_replace("\n", "\r\n", $migrationContents));
    $expect(
        st_migration_file_checksum($lfMigration) === st_migration_file_checksum($crlfMigration),
        'migration checksums are stable across Windows and Unix line endings'
    );
    $expect(
        st_migration_file_checksum_matches(hash('sha256', file_get_contents($crlfMigration)), $lfMigration),
        'migration validation accepts existing checksums recorded with Windows line endings'
    );
} finally {
    @unlink($lfMigration);
    @unlink($crlfMigration);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec(
    'CREATE TABLE auth_rate_limits (
        bucket TEXT NOT NULL,
        key_hash TEXT NOT NULL,
        window_started_at TEXT NOT NULL,
        attempts INTEGER NOT NULL,
        PRIMARY KEY (bucket, key_hash)
    )'
);

$expect(st_rate_limit_consume($db, 'test', 'client', 2, 60) === 0, 'rate limit allows first attempt');
$expect(st_rate_limit_consume($db, 'test', 'client', 2, 60) === 0, 'rate limit allows attempts up to configured maximum');
$expect(st_rate_limit_consume($db, 'test', 'client', 2, 60) > 0, 'rate limit blocks attempts above configured maximum');
st_rate_limit_clear($db, 'test', 'client');
$expect(st_rate_limit_consume($db, 'test', 'client', 2, 60) === 0, 'clearing an identifier resets its rate limit');

$oldWindow = gmdate('Y-m-d H:i:s', time() - 120);
$setOldWindow = $db->prepare(
    'UPDATE auth_rate_limits SET window_started_at = ?, attempts = 9 WHERE bucket = ? AND key_hash = ?'
);
$setOldWindow->execute([$oldWindow, 'test', st_rate_limit_key('test', 'client')]);
$expect(st_rate_limit_consume($db, 'test', 'client', 2, 60) === 0, 'rate limit resets an expired window');

$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost:8080';
$csrfToken = st_csrf_token();
$expect(st_validate_csrf($csrfToken), 'CSRF validation accepts the session token and matching origin');
$expect(!st_validate_csrf('invalid-token'), 'CSRF validation rejects a mismatched token');
$_SERVER['HTTP_ORIGIN'] = 'https://attacker.example';
$expect(!st_validate_csrf($csrfToken), 'CSRF validation rejects a cross-origin request');

foreach ($passed as $message) {
    echo "PASS {$message}\n";
}
echo "Completed {$checks} security foundation checks.\n";
