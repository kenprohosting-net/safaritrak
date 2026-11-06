<?php

require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (strtolower(getenv('SAFARITRAK_ENV') ?: 'development') !== 'development') {
    fwrite(STDERR, "The demo school seed is restricted to the development environment.\n");
    exit(1);
}

if (($argv[1] ?? '') !== '--apply') {
    echo "Usage: php backend/scripts/seed-demo-school.php --apply\n";
    echo "Creates synthetic local accounts and an approved demo school. Passwords are generated once.\n";
    exit(0);
}

$db = safaritrak_db();
$schoolName = 'SafariTrak Local Test School';
$accounts = [
    'admin' => [
        'name' => 'SafariTrak Demo School Admin',
        'username' => 'demo_school_admin',
        'email' => 'demo-school-admin@safaritrak.test',
    ],
    'student' => [
        'name' => 'SafariTrak Demo Student',
        'username' => 'demo_school_student',
        'email' => 'demo-school-student@safaritrak.test',
    ],
    'guardian' => [
        'name' => 'SafariTrak Demo Guardian',
        'username' => 'demo_school_guardian',
        'email' => 'demo-school-guardian@safaritrak.test',
    ],
];

$db->beginTransaction();
try {
    $existingSchool = $db->prepare(
        'SELECT o.id, oa.user_id
         FROM organizations o
         JOIN organization_profiles p ON p.organization_id = o.id
         LEFT JOIN organization_admins oa ON oa.organization_id = o.id
         WHERE o.name = ? AND p.account_type = "school"
         LIMIT 1
         FOR UPDATE'
    );
    $existingSchool->execute([$schoolName]);
    $school = $existingSchool->fetch(PDO::FETCH_ASSOC);

    $existingAccounts = [];
    $findAccount = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
    foreach ($accounts as $role => $account) {
        $findAccount->execute([$account['email'], $account['username']]);
        $existingAccounts[$role] = $findAccount->fetchColumn();
    }

    $allAccountsExist = !in_array(false, $existingAccounts, true);
    if ($school && $allAccountsExist) {
        $adminId = (int) $existingAccounts['admin'];
        if ((int) $school['user_id'] === $adminId) {
            $db->commit();
            echo "Demo school and accounts already exist. Existing passwords are not changed or displayed.\n";
            exit(0);
        }
    }

    if ($school || count(array_filter($existingAccounts, static fn ($id) => $id !== false)) > 0) {
        throw new RuntimeException(
            'A demo-school record or account already exists in a partial/conflicting state. No data was changed.'
        );
    }

    $userInsert = $db->prepare(
        'INSERT INTO users (full_name, username, email, email_verified_at, phone, password_hash)
         VALUES (?, ?, ?, UTC_TIMESTAMP(), ?, ?)'
    );
    $generatedPasswords = [];
    $userIds = [];
    foreach ($accounts as $role => $account) {
        $password = bin2hex(random_bytes(16));
        $generatedPasswords[$role] = $password;
        $phone = '2547' . random_int(10000000, 99999999);
        $userInsert->execute([
            $account['name'],
            $account['username'],
            $account['email'],
            $phone,
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        $userIds[$role] = (int) $db->lastInsertId();
    }

    $db->prepare('INSERT INTO organizations (name) VALUES (?)')->execute([$schoolName]);
    $organizationId = (int) $db->lastInsertId();
    $db->prepare(
        'INSERT INTO organization_profiles (organization_id, account_type, review_status, reviewed_at)
         VALUES (?, "school", "approved", UTC_TIMESTAMP())'
    )->execute([$organizationId]);
    $db->prepare(
        'INSERT INTO organization_admins (organization_id, user_id, role) VALUES (?, ?, "owner")'
    )->execute([$organizationId, $userIds['admin']]);
    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

echo "Created {$schoolName} (organization ID {$organizationId}).\n";
echo "Use these local-only accounts to test enrollment invitations and approval:\n";
foreach ($accounts as $role => $account) {
    echo strtoupper($role) . "\n";
    echo "  Email: {$account['email']}\n";
    echo "  Username: {$account['username']}\n";
    echo "  Password: {$generatedPasswords[$role]}\n";
}
