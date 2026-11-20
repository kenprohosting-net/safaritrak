<?php

require_once __DIR__ . '/backend/includes/platform-guard.php';

if (!$myPlatformRole && $platformAdminExists) {
    header('Location: index.php');
    exit;
}

$bootstrapToken = getenv('SAFARITRAK_PLATFORM_ADMIN_BOOTSTRAP_TOKEN');
$platformBootstrapEnabled = is_string($bootstrapToken) && strlen($bootstrapToken) >= 32;

if ($myPlatformRole) {
    $totalUsersStmt = $db->query('SELECT COUNT(*) FROM users');
    $totalUsers = (int) $totalUsersStmt->fetchColumn();

    $newUsersStmt = $db->query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")');
    $newUsersThisMonth = (int) $newUsersStmt->fetchColumn();

    $totalOrgsStmt = $db->query('SELECT COUNT(*) FROM organizations');
    $totalOrgs = (int) $totalOrgsStmt->fetchColumn();

    $activeJourneysStmt = $db->query('SELECT COUNT(*) FROM journeys WHERE status = "active"');
    $activeJourneysNow = (int) $activeJourneysStmt->fetchColumn();

    $suspendedUsersStmt = $db->query('SELECT COUNT(*) FROM users WHERE is_suspended = 1');
    $suspendedUsers = (int) $suspendedUsersStmt->fetchColumn();

    $recentUsersStmt = $db->query('SELECT id, full_name, username, created_at FROM users ORDER BY created_at DESC LIMIT 5');
    $recentUsers = $recentUsersStmt->fetchAll();

    $recentOrgsStmt = $db->query(
        'SELECT o.id, o.name, o.created_at, u.full_name AS owner_name
         FROM organizations o
         JOIN organization_admins oa ON oa.organization_id = o.id AND oa.role = "owner"
         JOIN users u ON u.id = oa.user_id
         ORDER BY o.created_at DESC LIMIT 5'
    );
    $recentOrgs = $recentOrgsStmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Platform Admin</title>
<link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/dashboard.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/backend/views/app-map-background.php'; ?>
<div class="app app--map-layout">
<?php require __DIR__ . '/backend/views/app-navigation.php'; ?>

<main>
<header>
  <button class="menu" id="menu"><i class="fa-solid fa-bars"></i></button>
  <div><label>PLATFORM OVERVIEW</label><h1>SafariTrak Admin</h1></div>
  <div class="head-actions"><button><i class="fa-regular fa-bell"></i></button><div class="avatar"><?= st_avatar_inner($currentUser) ?></div></div>
</header>

<div class="content">

<?php if (!$myPlatformRole && !$platformAdminExists): ?>

<div class="card" style="max-width:480px;margin:30px auto">
  <div class="card-head"><div><label>ONE-TIME SETUP</label><h3>Claim SafariTrak ownership</h3></div></div>
  <div style="padding:0 21px 21px">
    <p style="font-size:12px;color:var(--muted);line-height:1.7;margin-bottom:16px">No platform owner exists yet. The initial owner must provide the one-time setup key configured by the site operator. Remove the key from the server configuration after setup.</p>
    <?php if ($platformBootstrapEnabled): ?>
    <div class="form-field"><label for="claimBootstrapToken">One-time setup key</label><input type="password" id="claimBootstrapToken" autocomplete="off" placeholder="Enter the platform setup key"></div>
    <p class="hint" id="claimError" style="color:#c94b4b;margin-bottom:10px;display:none"></p>
    <button type="button" class="btn-primary" id="claimOwnershipBtn" style="width:100%;justify-content:center;margin-top:12px">Claim ownership</button>
    <?php else: ?>
    <p class="hint" style="color:var(--muted);line-height:1.7">Owner setup is disabled. Configure <code>SAFARITRAK_PLATFORM_ADMIN_BOOTSTRAP_TOKEN</code> with a random value of at least 32 characters in the server environment, then reload this page.</p>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>

<div class="page-head">
  <div><h2>How SafariTrak is doing</h2><p>Everything happening across every traveler and organization.</p></div>
</div>

<div class="stat-grid">
  <div class="stat-card"><label>TOTAL USERS</label><strong><?= $totalUsers ?></strong><small><?= $newUsersThisMonth ?> new this month</small></div>
  <div class="stat-card"><label>ORGANIZATIONS</label><strong><?= $totalOrgs ?></strong><small>Registered</small></div>
  <div class="stat-card"><label>ACTIVE JOURNEYS</label><strong><?= $activeJourneysNow ?></strong><small>Right now</small></div>
  <div class="stat-card"><label>SUSPENDED ACCOUNTS</label><strong><?= $suspendedUsers ?></strong><small>Users</small></div>
</div>

<section class="lower">
  <div class="card">
    <div class="card-head"><div><label>NEWEST USERS</label><h3>Recent signups</h3></div><a href="admin-users.php">View all</a></div>
    <div class="rows">
      <?php if (empty($recentUsers)): ?>
      <p class="hint" style="padding:16px 21px;color:var(--muted);font-size:11px">No users yet.</p>
      <?php endif; ?>
      <?php foreach ($recentUsers as $ru): ?>
      <div><i class="fa-solid fa-user"></i><div><b><?= htmlspecialchars($ru['full_name']) ?></b><small>@<?= htmlspecialchars($ru['username']) ?> &middot; <?= (new DateTime($ru['created_at']))->format('j M') ?></small></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><div><label>NEWEST ORGANIZATIONS</label><h3>Recently registered</h3></div><a href="admin-organizations.php">View all</a></div>
    <div class="rows">
      <?php if (empty($recentOrgs)): ?>
      <p class="hint" style="padding:16px 21px;color:var(--muted);font-size:11px">No organizations yet.</p>
      <?php endif; ?>
      <?php foreach ($recentOrgs as $ro): ?>
      <div><i class="fa-solid fa-building"></i><div><b><?= htmlspecialchars($ro['name']) ?></b><small>Owned by <?= htmlspecialchars($ro['owner_name']) ?> &middot; <?= (new DateTime($ro['created_at']))->format('j M') ?></small></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php endif; ?>

</div>
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>
<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<?php if (!$myPlatformRole && !$platformAdminExists && $platformBootstrapEnabled): ?>
<script src="platform-onboarding.js"></script>
<?php endif; ?>
</body>
</html>