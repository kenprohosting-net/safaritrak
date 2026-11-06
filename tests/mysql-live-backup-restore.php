<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../backend/config/database.php';

$host = getenv('SAFARITRAK_DB_HOST') ?: '127.0.0.1';
$databaseName = getenv('SAFARITRAK_DB_NAME') ?: 'safaritrak';
$user = getenv('SAFARITRAK_DB_USER') ?: 'root';
$password = getenv('SAFARITRAK_DB_PASS');
$password = $password === false ? '' : $password;
if (!in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true) || $databaseName !== 'safaritrak') {
    fwrite(STDERR, "This check only runs against the loopback XAMPP safaritrak database.\n");
    exit(2);
}

$source = safaritrak_db();
$sourceDatabaseName = (string) $source->query('SELECT DATABASE()')->fetchColumn();
if ($sourceDatabaseName !== $databaseName) {
    fwrite(STDERR, "The configured PDO database does not match the expected XAMPP application database.\n");
    exit(2);
}

$token = bin2hex(random_bytes(6));
$restoreName = 'safaritrak_backup_validation_' . $token;
$backupPath = tempnam(sys_get_temp_dir(), 'safaritrak-live-backup-');
$restore = null;
$created = false;

function st_live_backup_run(array $command, ?string $inputPath, array $environment): void {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, null, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start a local database backup tool.');
    }

    if ($inputPath !== null) {
        $input = fopen($inputPath, 'rb');
        if ($input === false) {
            fclose($pipes[0]);
            proc_terminate($process);
            throw new RuntimeException('Could not read the temporary database backup.');
        }
        stream_copy_to_stream($input, $pipes[0]);
        fclose($input);
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException(
            'Local database backup validation failed (' . basename($command[0]) . '): '
            . trim((string) $error . ' ' . (string) $output)
        );
    }
}

function st_live_backup_binary(string $environmentName, string $binaryName): string {
    $configured = getenv($environmentName);
    if (is_string($configured) && $configured !== '' && is_file($configured)) {
        return $configured;
    }
    if (PHP_OS_FAMILY === 'Windows') {
        $xamppBinary = 'C:\\xampp\\mysql\\bin\\' . $binaryName . '.exe';
        if (is_file($xamppBinary)) {
            return $xamppBinary;
        }
    }
    return $binaryName;
}

function st_live_backup_table_names(PDO $database): array {
    $tables = $database->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM);
    $names = array_map(static fn(array $row): string => (string) $row[0], $tables);
    sort($names, SORT_STRING);
    return $names;
}

function st_live_backup_create_table(PDO $database, string $table): string {
    $statement = $database->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_NUM);
    if (!is_array($statement) || !isset($statement[1])) {
        throw new RuntimeException('Could not verify a restored table definition.');
    }
    return preg_replace('/\sAUTO_INCREMENT=\d+/i', '', (string) $statement[1]) ?? (string) $statement[1];
}

try {
    if ($backupPath === false) {
        throw new RuntimeException('Could not allocate a temporary database backup file.');
    }

    $engineStmt = $source->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\' AND ENGINE <> \'InnoDB\''
    );
    $engineStmt->execute([$sourceDatabaseName]);
    if ((int) $engineStmt->fetchColumn() !== 0) {
        throw new RuntimeException('The live database has non-InnoDB tables; a consistent online backup cannot be verified safely.');
    }

    $databaseExists = $source->prepare(
        'SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?'
    );
    $databaseExists->execute([$restoreName]);
    if ((int) $databaseExists->fetchColumn() !== 0) {
        throw new RuntimeException('The disposable restore database already exists; refusing to overwrite it.');
    }

    $source->exec(
        'CREATE DATABASE `' . $restoreName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    $created = true;

    $environment = getenv();
    if (!is_array($environment)) {
        throw new RuntimeException('Could not read the local environment for the backup tools.');
    }
    $environment['MYSQL_PWD'] = $password;
    st_live_backup_run([
        st_live_backup_binary('SAFARITRAK_TEST_MYSQLDUMP', 'mysqldump'),
        '--host=' . $host,
        '--user=' . $user,
        '--single-transaction',
        '--routines',
        '--triggers',
        '--result-file=' . $backupPath,
        $sourceDatabaseName,
    ], null, $environment);
    if (!is_file($backupPath) || filesize($backupPath) < 1024) {
        throw new RuntimeException('The temporary live database backup is missing or unexpectedly small.');
    }
    echo "PASS loopback XAMPP database backup created in a temporary file\n";

    $restore = new PDO(
        'mysql:host=' . $host . ';dbname=' . $restoreName . ';charset=utf8mb4',
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    st_live_backup_run([
        st_live_backup_binary('SAFARITRAK_TEST_MYSQL', 'mysql'),
        '--host=' . $host,
        '--user=' . $user,
        '--database=' . $restoreName,
    ], $backupPath, $environment);
    echo "PASS temporary backup restored into an isolated disposable database\n";

    $sourceTables = st_live_backup_table_names($source);
    $restoreTables = st_live_backup_table_names($restore);
    if ($sourceTables !== $restoreTables) {
        throw new RuntimeException('The restored live backup has a different table inventory.');
    }
    foreach ($sourceTables as $table) {
        $quotedTable = '`' . str_replace('`', '``', $table) . '`';
        $sourceCount = (int) $source->query('SELECT COUNT(*) FROM ' . $quotedTable)->fetchColumn();
        $restoreCount = (int) $restore->query('SELECT COUNT(*) FROM ' . $quotedTable)->fetchColumn();
        if ($sourceCount !== $restoreCount) {
            throw new RuntimeException('The restored live backup has a row-count mismatch in a table.');
        }
        if (st_live_backup_create_table($source, $table) !== st_live_backup_create_table($restore, $table)) {
            throw new RuntimeException('The restored live backup has a table-definition mismatch.');
        }
    }
    echo 'PASS restored live backup has matching table definitions and row counts across '
        . count($sourceTables) . " tables\n";
} finally {
    $restore = null;
    if ($created) {
        $source->exec('DROP DATABASE IF EXISTS `' . $restoreName . '`');
    }
    if (is_string($backupPath) && is_file($backupPath)) {
        unlink($backupPath);
    }
}

echo "PASS temporary backup file and disposable restore database removed\n";
