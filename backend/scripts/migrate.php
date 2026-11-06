<?php

require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$commands = ['init', 'baseline', 'status', 'up'];
$command = $argv[1] ?? '--help';

if (in_array($command, ['--help', '-h', 'help'], true)) {
    echo "SafariTrak database migrations\n\n";
    echo "Usage: php backend/scripts/migrate.php <" . implode('|', $commands) . ">\n\n";
    echo "init     Create the migration ledger only.\n";
    echo "baseline Register the current schema after verifying the legacy baseline.\n";
    echo "status   Show applied and pending migrations.\n";
    echo "up       Apply checked-in forward migrations after baseline registration.\n\n";
    echo "This tool does not migrate automatically. Back up the database before any schema change.\n";
    exit(0);
}

if (!in_array($command, $commands, true)) {
    fwrite(STDERR, "Unknown command. Run with --help for usage.\n");
    exit(2);
}

$db = safaritrak_db();
$lockName = 'safaritrak_schema_migrations';
$lockStmt = $db->prepare('SELECT GET_LOCK(?, 10)');
$lockStmt->execute([$lockName]);
if ((int) $lockStmt->fetchColumn() !== 1) {
    fwrite(STDERR, "Could not acquire the migration lock; another migration may be running.\n");
    exit(1);
}

try {
    if ($command === 'init') {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(80) NOT NULL PRIMARY KEY,
                checksum CHAR(64) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB'
        );
        echo "Migration ledger is ready. No application schema or data was changed.\n";
        exit(0);
    }

    $ledgerExists = $db->query(
        "SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'"
    )->fetchColumn();
    if ((int) $ledgerExists !== 1) {
        fwrite(STDERR, "Migration ledger is missing. Run the explicit init command first.\n");
        exit(1);
    }

    if ($command === 'baseline') {
        $existing = (int) $db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
        if ($existing > 0) {
            fwrite(STDERR, "The ledger is not empty; refusing to register a second baseline.\n");
            exit(1);
        }

        st_verify_legacy_baseline($db);
        $schemaFile = dirname(__DIR__) . '/sql/schema.sql';
        $checksum = hash_file('sha256', $schemaFile);
        $insert = $db->prepare(
            'INSERT INTO schema_migrations (version, checksum) VALUES (?, ?)'
        );
        $insert->execute(['0000_legacy_baseline', $checksum]);
        echo "Verified and registered 0000_legacy_baseline. No existing data was changed.\n";
        exit(0);
    }

    $applied = $db->query('SELECT version, checksum, applied_at FROM schema_migrations ORDER BY version')
        ->fetchAll(PDO::FETCH_ASSOC);
    $appliedByVersion = [];
    foreach ($applied as $migration) {
        $appliedByVersion[$migration['version']] = $migration;
    }

    if ($command === 'status') {
        foreach ($applied as $migration) {
            echo 'APPLIED  ' . $migration['version'] . '  ' . $migration['applied_at'] . "\n";
        }
        foreach (st_migration_files() as $file) {
            $version = preg_replace('/\.(sql|php)$/', '', basename($file));
            echo (isset($appliedByVersion[$version]) ? 'APPLIED  ' : 'PENDING  ') . $version . "\n";
        }
        exit(0);
    }

    if (!isset($appliedByVersion['0000_legacy_baseline'])) {
        fwrite(STDERR, "No verified baseline exists. Run the explicit baseline command first.\n");
        exit(1);
    }

    foreach (st_migration_files() as $file) {
        $version = preg_replace('/\.(sql|php)$/', '', basename($file));
        $checksum = hash_file('sha256', $file);
        if (isset($appliedByVersion[$version])) {
            if (!hash_equals($appliedByVersion[$version]['checksum'], $checksum)) {
                fwrite(STDERR, "Checksum mismatch for applied migration {$version}; refusing to continue.\n");
                exit(1);
            }
            continue;
        }

        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'sql') {
            $sql = trim((string) file_get_contents($file));
            if (!preg_match('/^CREATE TABLE IF NOT EXISTS [a-zA-Z0-9_]+/i', $sql)) {
                fwrite(STDERR, "Migration {$version} is not an approved additive table migration; review it before use.\n");
                exit(1);
            }
            $db->exec($sql);
            if ($version === '0001_auth_rate_limits') {
                st_verify_rate_limit_table($db);
            }
        } else {
            $migration = require $file;
            if (!is_callable($migration)) {
                fwrite(STDERR, "Migration {$version} must return a callable PDO migration.\n");
                exit(1);
            }
            $migration($db);
        }

        $insert = $db->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (?, ?)');
        $insert->execute([$version, $checksum]);
        echo "APPLIED  {$version}\n";
    }

    echo "Migration check complete. No down/destructive migration is provided.\n";
} finally {
    $releaseStmt = $db->prepare('SELECT RELEASE_LOCK(?)');
    $releaseStmt->execute([$lockName]);
}

function st_verify_legacy_baseline(PDO $db): void {
    $requiredTables = [
        'users',
        'email_verifications',
        'password_resets',
        'sessions',
        'trusted_contacts',
        'journeys',
        'journey_shares',
        'journey_positions',
        'messages',
        'notifications',
        'sos_alerts',
        'group_journeys',
        'group_members',
        'organizations',
        'organization_admins',
        'organization_travelers',
        'platform_admins',
    ];

    $checkTable = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    foreach ($requiredTables as $table) {
        $checkTable->execute([$table]);
        if ((int) $checkTable->fetchColumn() !== 1) {
            throw new RuntimeException("Cannot baseline: required legacy table {$table} is missing.");
        }
    }

    $requiredColumns = [
        'users' => ['email_verified_at', 'is_suspended'],
        'organizations' => ['is_suspended'],
        'sessions' => ['validator_hash', 'last_active_at', 'expires_at'],
    ];
    $checkColumn = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column) {
            $checkColumn->execute([$table, $column]);
            if ((int) $checkColumn->fetchColumn() !== 1) {
                throw new RuntimeException(
                    "Cannot baseline: required legacy column {$table}.{$column} is missing."
                );
            }
        }
    }
}

function st_verify_rate_limit_table(PDO $db): void {
    $requiredColumns = ['bucket', 'key_hash', 'window_started_at', 'attempts'];
    $check = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    foreach ($requiredColumns as $column) {
        $check->execute(['auth_rate_limits', $column]);
        if ((int) $check->fetchColumn() !== 1) {
            throw new RuntimeException(
                "The existing auth_rate_limits table does not match the expected migration structure."
            );
        }
    }
}

function st_migration_files(): array {
    $directory = dirname(__DIR__) . '/sql/migrations';
    $files = array_merge(
        glob($directory . '/*.sql') ?: [],
        glob($directory . '/*.php') ?: []
    );
    sort($files, SORT_STRING);
    return $files;
}
