<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$host = getenv('SAFARITRAK_TEST_DB_HOST') ?: '';
$user = getenv('SAFARITRAK_TEST_DB_USER') ?: '';
$password = getenv('SAFARITRAK_TEST_DB_PASS');
if (!in_array(strtolower($host), ['127.0.0.1', 'localhost'], true) || $user === '') {
    fwrite(STDERR, "Set SAFARITRAK_TEST_DB_HOST=127.0.0.1 and SAFARITRAK_TEST_DB_USER for a local disposable MySQL/MariaDB server. SAFARITRAK_TEST_DB_PASS defaults to an empty password.\n");
    exit(2);
}
$password = $password === false ? '' : $password;

$token = bin2hex(random_bytes(6));
$databases = [
    'source' => 'safaritrak_validation_' . $token . '_source',
    'restore' => 'safaritrak_validation_' . $token . '_restore',
];
$createdDatabases = [];
$backupPath = tempnam(sys_get_temp_dir(), 'st-mysql-validation-');
$admin = null;
$dsn = 'mysql:host=' . $host . ';charset=utf8mb4';
$credentials = [$user, $password];

function st_mysql_validation_run(
    array $command,
    ?string $input = null,
    ?array $environment = null
): string {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, null, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the database validation process.');
    }

    if ($input !== null) {
        fwrite($pipes[0], $input);
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException(
            'Database validation command failed (' . basename($command[0]) . '): '
            . trim((string) $error . ' ' . (string) $output)
        );
    }

    return (string) $output;
}

function st_mysql_validation_binary(string $environmentName, string $binaryName): string {
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

function st_mysql_validation_create_database(PDO $admin, string $name, string $host, array $credentials): PDO {
    if (!preg_match('/^safaritrak_validation_[a-f0-9]{12}_(source|restore)$/', $name)) {
        throw new RuntimeException('Refusing to create a database outside the disposable validation namespace.');
    }
    $exists = $admin->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
    $exists->execute([$name]);
    if ((int) $exists->fetchColumn() !== 0) {
        throw new RuntimeException('Disposable database already exists; refusing to overwrite it.');
    }

    $admin->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        return new PDO(
            'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
            $credentials[0],
            $credentials[1],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $exception) {
        $admin->exec('DROP DATABASE IF EXISTS `' . $name . '`');
        throw $exception;
    }
}

function st_mysql_validation_add_user(PDO $database, string $username, string $password): int {
    $insert = $database->prepare(
        'INSERT INTO users (full_name, username, email, phone, password_hash)
         VALUES (?, ?, ?, ?, ?)'
    );
    $insert->execute([
        $username,
        $username,
        $username . '@example.invalid',
        '2547' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        password_hash($password, PASSWORD_DEFAULT),
    ]);
    return (int) $database->lastInsertId();
}

function st_mysql_validation_delete_account_request(
    int $userId,
    string $password,
    string $fixture,
    array $environment
): array {
    $environment['SAFARITRAK_TEST_DELETE_USER_ID'] = (string) $userId;
    $environment['SAFARITRAK_TEST_DELETE_PASSWORD'] = $password;
    $output = st_mysql_validation_run([PHP_BINARY, $fixture], null, $environment);
    $response = json_decode(trim($output), true);
    if (!is_array($response)) {
        throw new RuntimeException('Account deletion returned invalid JSON.');
    }
    return $response;
}

function st_mysql_validation_export_account(int $userId, string $fixture, array $environment): array {
    $environment['SAFARITRAK_TEST_EXPORT_USER_ID'] = (string) $userId;
    $output = st_mysql_validation_run([PHP_BINARY, $fixture], null, $environment);
    $response = json_decode($output, true);
    if (!is_array($response)) {
        throw new RuntimeException('Account export returned invalid JSON.');
    }
    return $response;
}

try {
    if ($backupPath === false) {
        throw new RuntimeException('Could not allocate a temporary database backup file.');
    }

    $admin = new PDO($dsn, $credentials[0], $credentials[1], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $source = st_mysql_validation_create_database($admin, $databases['source'], $host, $credentials);
    $createdDatabases[] = $databases['source'];

    $schemaPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'schema.sql';
    $schema = file_get_contents($schemaPath);
    if (!is_string($schema)) {
        throw new RuntimeException('Could not read the checked-in schema snapshot.');
    }

    $schema = preg_replace(
        '/^\s*(?:covered_km DECIMAL\(7, 2\) NOT NULL DEFAULT 0|sos_alert_notifications TINYINT\(1\) NOT NULL DEFAULT 1|product_update_emails TINYINT\(1\) NOT NULL DEFAULT 0),\s*\R/m',
        '',
        $schema,
        -1,
        $removedColumns
    );
    $schema = preg_replace(
        '/CREATE TABLE (?:journey_locations|trusted_contact_invites|journey_share_links) \(.*?\) ENGINE=InnoDB;\s*/s',
        '',
        (string) $schema,
        -1,
        $removedTables
    );
    if ($removedColumns !== 3 || $removedTables !== 3) {
        throw new RuntimeException('Could not construct the expected pre-migration schema fixture.');
    }

    foreach (preg_split('/;\s*(?:\r?\n|$)/', $schema) as $statement) {
        if (trim($statement) !== '') {
            $source->exec($statement);
        }
    }
    $insertUser = $source->prepare(
        'INSERT INTO users (full_name, username, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)'
    );
    $insertUser->execute([
        'Synthetic Migration Test',
        'migration_test',
        'migration-test@example.invalid',
        '254700000001',
        password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
    ]);
    $legacyOrganization = $source->prepare('INSERT INTO organizations (name) VALUES (?)');
    $legacyOrganization->execute(['Legacy school migration test']);
    $legacyOrganizationId = (int) $source->lastInsertId();
    $source->prepare(
        'INSERT INTO organization_admins (organization_id, user_id, role) VALUES (?, ?, "owner")'
    )->execute([$legacyOrganizationId, (int) $source->query("SELECT id FROM users WHERE username = 'migration_test'")->fetchColumn()]);
    echo "PASS disposable legacy schema loaded with synthetic sentinel data\n";

    $dumpBinary = st_mysql_validation_binary('SAFARITRAK_TEST_MYSQLDUMP', 'mysqldump');
    $clientEnvironment = getenv();
    if (!is_array($clientEnvironment)) {
        throw new RuntimeException('Could not read the current process environment for the backup tools.');
    }
    $clientEnvironment['MYSQL_PWD'] = $credentials[1];
    st_mysql_validation_run([
        $dumpBinary,
        '--host=' . $host,
        '--user=' . $credentials[0],
        '--single-transaction',
        '--routines',
        '--triggers',
        '--result-file=' . $backupPath,
        $databases['source'],
    ], null, $clientEnvironment);
    if (!is_file($backupPath) || filesize($backupPath) < 1000) {
        throw new RuntimeException('The disposable database backup is missing or unexpectedly small.');
    }
    echo "PASS disposable database backup created\n";

    $restore = st_mysql_validation_create_database($admin, $databases['restore'], $host, $credentials);
    $createdDatabases[] = $databases['restore'];
    $mysqlBinary = st_mysql_validation_binary('SAFARITRAK_TEST_MYSQL', 'mysql');
    st_mysql_validation_run([
        $mysqlBinary,
        '--host=' . $host,
        '--user=' . $credentials[0],
        '--database=' . $databases['restore'],
    ], file_get_contents($backupPath), $clientEnvironment);
    echo "PASS backup restored into a separate disposable database\n";

    $sourceTables = $source->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $restoredTables = $restore->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($sourceTables);
    sort($restoredTables);
    if ($sourceTables !== $restoredTables) {
        throw new RuntimeException('Restored schema table inventory does not match the backup source.');
    }
    foreach ($sourceTables as $table) {
        $sourceCount = (int) $source->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        $restoreCount = (int) $restore->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        if ($sourceCount !== $restoreCount) {
            throw new RuntimeException('Restored row count mismatch in table ' . $table . '.');
        }
    }
    echo 'PASS restored schema and row counts match across ' . count($sourceTables) . " tables\n";

    $runner = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'migrate.php';
    $runnerEnvironment = getenv();
    if (!is_array($runnerEnvironment)) {
        throw new RuntimeException('Could not read the current process environment for the migration test.');
    }
    $runnerEnvironment['SAFARITRAK_DB_HOST'] = $host;
    $runnerEnvironment['SAFARITRAK_DB_NAME'] = $databases['restore'];
    $runnerEnvironment['SAFARITRAK_DB_USER'] = $credentials[0];
    $runnerEnvironment['SAFARITRAK_DB_PASS'] = $credentials[1];
    $runnerEnvironment['SAFARITRAK_ENV'] = 'testing';

    $sawPendingMigrations = false;
    $finalStatus = '';
    foreach (['init', 'baseline', 'status', 'up', 'status'] as $command) {
        $output = st_mysql_validation_run([PHP_BINARY, $runner, $command], null, $runnerEnvironment);
        echo 'PASS migration runner ' . $command . " completed\n";
        if ($command === 'status' && !$sawPendingMigrations) {
            if (!str_contains($output, 'PENDING  0001_auth_rate_limits')) {
                throw new RuntimeException('Migration status did not report pending work before application.');
            }
            $sawPendingMigrations = true;
            echo "PASS pending migration state was reported before apply\n";
        } elseif ($command === 'status') {
            $finalStatus = $output;
        }
    }
    if ($finalStatus === '' || str_contains($finalStatus, 'PENDING')) {
        throw new RuntimeException('Migration status still reports pending work after application.');
    }

    $sentinel = $restore->query("SELECT username FROM users WHERE username = 'migration_test'")->fetchColumn();
    $checks = [
        'auth_rate_limits' => "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_rate_limits'",
        'covered_km' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journeys' AND COLUMN_NAME = 'covered_km'",
        'journey_locations' => "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journey_locations'",
        'trusted_contact_invites' => "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trusted_contact_invites'",
        'journey_share_links' => "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journey_share_links'",
        'organization_profiles' => "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organization_profiles'",
        'persistent_groups' => "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persistent_groups'",
        'persistent_group_messages' => "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persistent_group_messages'",
        'rental_vehicles' => "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rental_vehicles'",
        'rental_bookings' => "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rental_bookings'",
        'migration_ledger' => 'SELECT COUNT(*) FROM schema_migrations',
    ];
    $expected = [
        'auth_rate_limits' => 1,
        'covered_km' => 1,
        'journey_locations' => 1,
        'trusted_contact_invites' => 1,
        'journey_share_links' => 1,
        'organization_profiles' => 1,
        'persistent_groups' => 1,
        'persistent_group_messages' => 1,
        'rental_vehicles' => 1,
        'rental_bookings' => 1,
        'privacy_consents' => 1,
        'migration_ledger' => 10,
    ];
    if ($sentinel !== 'migration_test') {
        throw new RuntimeException('Migration did not preserve the restored sentinel user.');
    }
    $checks['privacy_consents'] = "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_consents'";
    foreach ($checks as $name => $query) {
        if ((int) $restore->query($query)->fetchColumn() !== $expected[$name]) {
            throw new RuntimeException('Post-migration verification failed for ' . $name . '.');
        }
    }
    echo "PASS restored user row preserved after migrations\n";
    echo "PASS auth, journey, invitation, group, rental, privacy, and migration tables verified\n";
    $legacyProfile = $restore->prepare(
        'SELECT account_type, review_status FROM organization_profiles WHERE organization_id = ?'
    );
    $legacyProfile->execute([$legacyOrganizationId]);
    $legacyProfileRecord = $legacyProfile->fetch(PDO::FETCH_ASSOC);
    if (!$legacyProfileRecord || $legacyProfileRecord['account_type'] !== 'school' || $legacyProfileRecord['review_status'] !== 'approved') {
        throw new RuntimeException('Migration did not preserve existing organizations as approved school accounts.');
    }
    echo "PASS existing organization remains approved during account-type migration\n";

    $inviteOwnerId = st_mysql_validation_add_user($restore, 'invite_owner_' . $token, 'Invite owner test password 2026!');
    $inviteToken = bin2hex(random_bytes(32));
    $inviteHash = hash('sha256', $inviteToken);
    $restore->prepare(
        'INSERT INTO trusted_contact_invites (owner_id, token_hash, invite_name, expires_at)
         VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY))'
    )->execute([$inviteOwnerId, $inviteHash, 'Invitee']);
    $storedInviteHash = $restore->prepare('SELECT token_hash FROM trusted_contact_invites WHERE owner_id = ?');
    $storedInviteHash->execute([$inviteOwnerId]);
    $contactCount = $restore->prepare('SELECT COUNT(*) FROM trusted_contacts WHERE owner_id = ?');
    $contactCount->execute([$inviteOwnerId]);
    if ($storedInviteHash->fetchColumn() !== $inviteHash || $inviteHash === $inviteToken || (int) $contactCount->fetchColumn() !== 0) {
        throw new RuntimeException('An invitation must store only a token hash and must not create a contact before sign-up.');
    }
    echo "PASS invitation tokens are stored as hashes without creating a contact before sign-up\n";

    $shareJourney = $restore->prepare('INSERT INTO journeys (user_id, start_label, end_label) VALUES (?, ?, ?)');
    $shareJourney->execute([$inviteOwnerId, 'Share start', 'Share finish']);
    $shareJourneyId = (int) $restore->lastInsertId();
    $shareToken = bin2hex(random_bytes(32));
    $shareHash = hash('sha256', $shareToken);
    $restore->prepare(
        'INSERT INTO journey_share_links (journey_id, owner_id, token_hash, expires_at)
         VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR))'
    )->execute([$shareJourneyId, $inviteOwnerId, $shareHash]);
    $shareCheck = $restore->prepare(
        'SELECT COUNT(*) FROM journey_share_links l JOIN journeys j ON j.id = l.journey_id
         WHERE l.token_hash = ? AND l.owner_id = j.user_id AND l.expires_at > UTC_TIMESTAMP() AND j.status = "active"'
    );
    $shareCheck->execute([$shareHash]);
    if ((int) $shareCheck->fetchColumn() !== 1 || $shareHash === $shareToken) {
        throw new RuntimeException('The live-share link must be hashed and constrained to a current active journey.');
    }
    echo "PASS optional live-share links are hashed and bound to active journeys with one-hour expiry\n";

    require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'consent.php';
    $consentUserId = st_mysql_validation_add_user($restore, 'consent_test_' . $token, 'Consent test password 2026!');
    st_record_consent($restore, $consentUserId);
    st_record_consent($restore, $consentUserId);
    $consentCount = $restore->prepare('SELECT COUNT(*) FROM privacy_consents WHERE user_id = ?');
    $consentCount->execute([$consentUserId]);
    if ((int) $consentCount->fetchColumn() !== 2) {
        throw new RuntimeException('Consent records were not inserted idempotently for both current documents.');
    }
    echo "PASS current Terms and Privacy Policy acceptance is versioned and stored exactly once\n";

    $exportUserId = st_mysql_validation_add_user($restore, 'export_test_' . $token, 'Export test password 2026!');
    st_record_consent($restore, $exportUserId);
    $exportJourney = $restore->prepare(
        'INSERT INTO journeys (user_id, start_label, end_label) VALUES (?, ?, ?)'
    );
    $exportJourney->execute([$exportUserId, 'Export start', 'Export finish']);
    $exportJourneyId = (int) $restore->lastInsertId();
    $restore->prepare(
        'INSERT INTO journey_positions (journey_id, lat, lng) VALUES (?, ?, ?)'
    )->execute([$exportJourneyId, 1.1111111, 36.1111111]);
    $exportPeerId = st_mysql_validation_add_user($restore, 'export_peer_' . $token, 'Export peer password 2026!');
    $restore->prepare(
        'INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)'
    )->execute([$exportUserId, $exportPeerId, 'Synthetic export message']);
    $otherJourneyOwnerId = st_mysql_validation_add_user($restore, 'export_private_' . $token, 'Private owner password 2026!');
    $otherJourney = $restore->prepare(
        'INSERT INTO journeys (user_id, start_label, end_label) VALUES (?, ?, ?)'
    );
    $otherJourney->execute([$otherJourneyOwnerId, 'Other private start', 'Other private finish']);

    $exportFixture = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'export-data-request.php';
    $exportData = st_mysql_validation_export_account($exportUserId, $exportFixture, $runnerEnvironment);
    $exportContainsPrivateData = ($exportData['format'] ?? null) === 'safaritrak-account-export'
        && (int) ($exportData['account']['id'] ?? 0) === $exportUserId
        && !array_key_exists('password_hash', $exportData['account'] ?? [])
        && count($exportData['journeys'] ?? []) === 1
        && count($exportData['journey_positions'] ?? []) === 1
        && count($exportData['messages'] ?? []) === 1
        && count($exportData['privacy_consents'] ?? []) === 2
        && !array_key_exists('sessions', $exportData)
        && !array_key_exists('auth_rate_limits', $exportData);
    if (!$exportContainsPrivateData) {
        throw new RuntimeException('Account export omitted owned data or included unrelated/security-only data.');
    }
    echo "PASS authenticated account export includes owned trip/location/message/consent data and excludes credentials\n";

    $deletePassword = 'Correct horse deletion 2026!';
    $deleteTargetId = st_mysql_validation_add_user($restore, 'delete_target_' . $token, $deletePassword);
    $organizationOwnerId = st_mysql_validation_add_user($restore, 'delete_owner_' . $token, $deletePassword);
    $organizationAdminId = st_mysql_validation_add_user($restore, 'delete_org_admin_' . $token, $deletePassword);
    $platformAdminId = st_mysql_validation_add_user($restore, 'delete_admin_' . $token, $deletePassword);

    $organization = $restore->prepare('INSERT INTO organizations (name) VALUES (?)');
    $organization->execute(['Deletion policy test ' . $token]);
    $organizationId = (int) $restore->lastInsertId();
    $restore->prepare(
        'INSERT INTO organization_admins (organization_id, user_id, role) VALUES (?, ?, \'owner\')'
    )->execute([$organizationId, $organizationOwnerId]);
    $restore->prepare(
        'INSERT INTO organization_admins (organization_id, user_id, role) VALUES (?, ?, \'admin\')'
    )->execute([$organizationId, $organizationAdminId]);
    $restore->prepare(
        'INSERT INTO platform_admins (user_id, role) VALUES (?, \'staff\')'
    )->execute([$platformAdminId]);

    $externalContact = $restore->prepare(
        'INSERT INTO trusted_contacts
         (owner_id, contact_user_id, invite_name, invite_phone, relationship, status)
         VALUES (?, ?, ?, ?, ?, \'confirmed\')'
    );
    $externalContact->execute([
        $platformAdminId,
        $deleteTargetId,
        'Deletion target',
        '254700000123',
        'friend',
    ]);
    $externalContactId = (int) $restore->lastInsertId();
    $restore->prepare(
        'INSERT INTO trusted_contacts (owner_id, contact_user_id, invite_name, invite_phone)
         VALUES (?, ?, ?, ?)'
    )->execute([$deleteTargetId, $platformAdminId, 'Platform admin', '254700000124']);

    $journey = $restore->prepare(
        'INSERT INTO journeys (user_id, start_label, end_label) VALUES (?, ?, ?)'
    );
    $journey->execute([$deleteTargetId, 'Private start', 'Private finish']);
    $journeyId = (int) $restore->lastInsertId();
    $restore->prepare(
        'INSERT INTO journey_positions (journey_id, lat, lng) VALUES (?, ?, ?)'
    )->execute([$journeyId, 1.2345678, 36.1234567]);

    $groupJourney = $restore->prepare(
        'INSERT INTO group_journeys (organizer_id, title, destination_label) VALUES (?, ?, ?)'
    );
    $groupJourney->execute([$platformAdminId, 'Shared group test', 'Shared destination']);
    $groupJourneyId = (int) $restore->lastInsertId();
    $restore->prepare(
        'INSERT INTO group_members
         (group_journey_id, user_id, invite_name, invite_phone, status, last_lat, last_lng)
         VALUES (?, ?, ?, ?, \'confirmed\', ?, ?)'
    )->execute([$groupJourneyId, $deleteTargetId, 'Deletion target', '254700000123', 1.2345678, 36.1234567]);

    $fixture = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'delete-account-request.php';
    $deleteEnvironment = $runnerEnvironment;
    $invalidPasswordResult = st_mysql_validation_delete_account_request(
        $deleteTargetId,
        'wrong password',
        $fixture,
        $deleteEnvironment
    );
    if (($invalidPasswordResult['success'] ?? true) !== false) {
        throw new RuntimeException('Account deletion accepted an incorrect password.');
    }
    echo "PASS account deletion requires the current password\n";

    foreach ([$organizationOwnerId, $organizationAdminId, $platformAdminId] as $protectedUserId) {
        $blockedResult = st_mysql_validation_delete_account_request(
            $protectedUserId,
            $deletePassword,
            $fixture,
            $deleteEnvironment
        );
        if (($blockedResult['success'] ?? true) !== false ||
            !str_contains($blockedResult['message'] ?? '', 'duties before deleting')) {
            throw new RuntimeException('Account deletion did not block a protected administrator account.');
        }
    }
    if ((int) $restore->query('SELECT COUNT(*) FROM organizations WHERE id = ' . $organizationId)->fetchColumn() !== 1) {
        throw new RuntimeException('A blocked owner deletion removed its organization.');
    }
    echo "PASS account deletion preserves organizations and blocks platform and organization administrators\n";

    $deleteResult = st_mysql_validation_delete_account_request(
        $deleteTargetId,
        $deletePassword,
        $fixture,
        $deleteEnvironment
    );
    if (($deleteResult['success'] ?? false) !== true || ($deleteResult['redirect'] ?? '') !== 'login.php') {
        throw new RuntimeException('Account deletion did not complete successfully.');
    }

    $scrubbedContact = $restore->prepare(
        'SELECT contact_user_id, invite_name, invite_phone, relationship, status,
                share_live_location, journey_alerts, sos_alerts
         FROM trusted_contacts WHERE id = ?'
    );
    $scrubbedContact->execute([$externalContactId]);
    $contactRecord = $scrubbedContact->fetch();
    $scrubbedGroupMember = $restore->prepare(
        'SELECT user_id, invite_name, invite_phone, status, last_lat, last_lng
         FROM group_members WHERE group_journey_id = ?'
    );
    $scrubbedGroupMember->execute([$groupJourneyId]);
    $groupMemberRecord = $scrubbedGroupMember->fetch();
    $accountRemains = $restore->prepare('SELECT COUNT(*) FROM users WHERE id = ?');
    $accountRemains->execute([$deleteTargetId]);
    $contactIsScrubbed = $contactRecord &&
        $contactRecord['contact_user_id'] === null &&
        $contactRecord['invite_name'] === 'Deleted user' &&
        $contactRecord['invite_phone'] === '' &&
        $contactRecord['relationship'] === null &&
        $contactRecord['status'] === 'declined' &&
        (int) $contactRecord['share_live_location'] === 0 &&
        (int) $contactRecord['journey_alerts'] === 0 &&
        (int) $contactRecord['sos_alerts'] === 0;
    $groupMemberIsScrubbed = $groupMemberRecord &&
        $groupMemberRecord['user_id'] === null &&
        $groupMemberRecord['invite_name'] === null &&
        $groupMemberRecord['invite_phone'] === null &&
        $groupMemberRecord['status'] === 'declined' &&
        $groupMemberRecord['last_lat'] === null &&
        $groupMemberRecord['last_lng'] === null;
    if (
        (int) $accountRemains->fetchColumn() !== 0 ||
        !$contactIsScrubbed ||
        !$groupMemberIsScrubbed ||
        (int) $restore->query('SELECT COUNT(*) FROM journeys WHERE id = ' . $journeyId)->fetchColumn() !== 0 ||
        (int) $restore->query('SELECT COUNT(*) FROM journey_positions WHERE journey_id = ' . $journeyId)->fetchColumn() !== 0 ||
        (int) $restore->query('SELECT COUNT(*) FROM trusted_contacts WHERE owner_id = ' . $deleteTargetId)->fetchColumn() !== 0
    ) {
        throw new RuntimeException('Account deletion did not remove or scrub its linked records safely.');
    }
    echo "PASS account deletion removes private records and scrubs retained contact and group-location data\n";
} finally {
    if ($admin instanceof PDO) {
        foreach ($createdDatabases as $name) {
            if (preg_match('/^safaritrak_validation_[a-f0-9]{12}_(source|restore)$/', $name)) {
                $admin->exec('DROP DATABASE IF EXISTS `' . $name . '`');
            }
        }
    }
    if (is_string($backupPath) && is_file($backupPath)) {
        unlink($backupPath);
    }
}
