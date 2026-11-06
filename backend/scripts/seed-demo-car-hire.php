<?php

require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (strtolower(getenv('SAFARITRAK_ENV') ?: 'development') !== 'development') {
    fwrite(STDERR, "The demo car-hire seed is restricted to the development environment.\n");
    exit(1);
}

if (($argv[1] ?? '') !== '--apply') {
    echo "Usage: php backend/scripts/seed-demo-car-hire.php --apply\n";
    echo "Creates a synthetic, approved car-hire organization, admin, and sample vehicle.\n";
    echo "A new password is generated and displayed only on the first successful run.\n";
    exit(0);
}

$db = safaritrak_db();
$organizationName = 'SafariTrak Local Test Car Hire';
$account = [
    'name' => 'SafariTrak Demo Car Hire Admin',
    'username' => 'demo_car_hire_admin',
    'email' => 'demo-car-hire-admin@safaritrak.test',
];

$db->beginTransaction();
try {
    $existingOrganization = $db->prepare(
        'SELECT o.id, oa.user_id
         FROM organizations o
         JOIN organization_profiles p ON p.organization_id = o.id
         LEFT JOIN organization_admins oa ON oa.organization_id = o.id
         WHERE o.name = ? AND p.account_type = "car_hire"
         LIMIT 1
         FOR UPDATE'
    );
    $existingOrganization->execute([$organizationName]);
    $organization = $existingOrganization->fetch(PDO::FETCH_ASSOC);

    $existingAccount = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
    $existingAccount->execute([$account['email'], $account['username']]);
    $existingUserId = $existingAccount->fetchColumn();

    if ($organization && $existingUserId !== false
        && (int) $organization['user_id'] === (int) $existingUserId) {
        $vehicleStmt = $db->prepare(
            'SELECT id FROM rental_vehicles WHERE organization_id = ? AND registration_number = ? LIMIT 1'
        );
        $vehicleStmt->execute([(int) $organization['id'], 'ST-DEMO-001']);
        if ($vehicleStmt->fetchColumn()) {
            $db->commit();
            echo "Demo car-hire organization, admin, and vehicle already exist. Existing password is not changed or displayed.\n";
            exit(0);
        }
        throw new RuntimeException(
            'The demo car-hire account exists without its sample vehicle. No data was changed.'
        );
    }

    if ($organization || $existingUserId !== false) {
        throw new RuntimeException(
            'A demo car-hire record or account already exists in a partial/conflicting state. No data was changed.'
        );
    }

    $password = bin2hex(random_bytes(16));
    $phone = '2547' . random_int(10000000, 99999999);
    $db->prepare(
        'INSERT INTO users (full_name, username, email, email_verified_at, phone, password_hash)
         VALUES (?, ?, ?, UTC_TIMESTAMP(), ?, ?)'
    )->execute([
        $account['name'],
        $account['username'],
        $account['email'],
        $phone,
        password_hash($password, PASSWORD_DEFAULT),
    ]);
    $userId = (int) $db->lastInsertId();

    $db->prepare('INSERT INTO organizations (name) VALUES (?)')->execute([$organizationName]);
    $organizationId = (int) $db->lastInsertId();
    $db->prepare(
        'INSERT INTO organization_profiles (organization_id, account_type, review_status, reviewed_at)
         VALUES (?, "car_hire", "approved", UTC_TIMESTAMP())'
    )->execute([$organizationId]);
    $db->prepare(
        'INSERT INTO organization_admins (organization_id, user_id, role) VALUES (?, ?, "owner")'
    )->execute([$organizationId, $userId]);
    $db->prepare(
        'INSERT INTO rental_vehicles
         (organization_id, make, model, year, category, seats, daily_rate, currency, registration_number, description)
         VALUES (?, "Toyota", "Corolla", 2022, "Sedan", 5, 3500.00, "KES", "ST-DEMO-001",
                 "Synthetic sample vehicle for local fleet and booking checks.")'
    )->execute([$organizationId]);
    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

echo "Created {$organizationName} (organization ID {$organizationId}) with one sample fleet vehicle.\n";
echo "Local-only organization account:\n";
echo "  Username: {$account['username']}\n";
echo "  Email: {$account['email']}\n";
echo "  Password: {$password}\n";
